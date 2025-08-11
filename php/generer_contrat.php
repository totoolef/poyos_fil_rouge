<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilisez POST.']);
        exit;
    }

    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['candidature_id'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
        exit;
    }

    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'annonceur') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    $annonceurId = $decoded->sub;
    $escapedCandidatureId = pg_escape_string($db->dbLink, $postData['candidature_id']);

    // Vérifie la connexion à la base de données
    if (!$db->dbLink) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Connexion à la base de données perdue']);
        exit;
    }

    // Vérifie si un contrat existe déjà pour cette candidature
    $checkQuery = "SELECT id, version FROM contrats WHERE annonce_id IN (SELECT annonce_id FROM candidatures WHERE id = '$escapedCandidatureId') AND conducteur_id IN (SELECT conducteur_id FROM candidatures WHERE id = '$escapedCandidatureId') LIMIT 1";
    $checkResult = $db->sql_result($checkQuery);
    $existingContract = $checkResult->sql_fetch_assoc();

    // Récupère les détails complets
    $result = $db->sql_result("SELECT c.*, a.type_pub, a.titre, a.localisation, a.localisation_type, a.localisation_personnalisee, a.code_postal, a.ville, a.duree_mois, a.paiement_mensuel, a.annonceur_id, u.nom AS nom_conducteur, u.prenom AS prenom_conducteur FROM candidatures c INNER JOIN annonces a ON c.annonce_id = a.id INNER JOIN utilisateurs u ON c.conducteur_id = u.id WHERE c.id = '$escapedCandidatureId' AND a.annonceur_id = '$annonceurId' AND c.statut = 'acceptee' LIMIT 1");
    $candidature = $result->sql_fetch_assoc();

    if (!$candidature) {
        echo json_encode(['success' => false, 'message' => 'Candidature non trouvée, non acceptée, ou non autorisée']);
        exit;
    }

    // Détermine la localisation détaillée
    $localisationDetaillee = $candidature['localisation_type'] === 'personnalise'
        ? $candidature['localisation_personnalisee'] ?? 'Non spécifiée'
        : ($candidature['ville'] ?? 'Non spécifiée') . ', ' . ($candidature['code_postal'] ?? 'Non spécifié');

    // Génère le contenu du contrat (HTML pour preview)
    $contratHtml = "<h3>Contrat de Publicité Véhicule - Poyos</h3>
        <p><strong>Date :</strong> " . date('d/m/Y') . "</p>
        <p><strong>Annonceur :</strong> " . $decoded->sub . " (ID: " . $candidature['annonceur_id'] . ")</p>
        <p><strong>Conducteur :</strong> " . ($candidature['nom_conducteur'] ?? 'Inconnu') . " " . ($candidature['prenom_conducteur'] ?? 'Inconnu') . " (ID: " . $candidature['conducteur_id'] . ")</p>
        <p><strong>Annonce :</strong> " . ($candidature['titre'] ?? 'Inconnue') . " (Type : " . ($candidature['type_pub'] ?? 'Non spécifié') . ")</p>
        <p><strong>Localisation :</strong> " . $localisationDetaillee . "</p>
        <p><strong>Durée :</strong> " . ($candidature['duree_mois'] ?? 0) . " mois</p>
        <p><strong>Paiement mensuel :</strong> " . ($candidature['paiement_mensuel'] ?? 0) . " €</p>
        <p><strong>Commission Poyos (12%) :</strong> " . (($candidature['paiement_mensuel'] ?? 0) * 0.12) . " €</p>
        <p><strong>Clauses :</strong> L'annonceur s'engage à fournir les visuels et organiser la pose (estimé 1500-3000€). Le conducteur s'engage à soumettre des validations mensuelles.</p>
        <p><strong>Voiture :</strong> " . ($candidature['marque_voiture'] ?? 'Non spécifié') . " " . ($candidature['modele_voiture'] ?? 'Non spécifié') . " (" . ($candidature['couleur'] ?? 'Non spécifié') . ")</p>";

    // Insertion ou mise à jour
    if ($existingContract) {
        $cmd = new sqlCmd();
        $cmd->Add('contenu_contrat', $contratHtml, 's');
        $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');
        $sqlwhere = "id = '" . $existingContract['id'] . "'";
        $query = $cmd->MakeUpdateQuery('contrats', $sqlwhere);
        if ($cmd->execute($db)) {
            echo json_encode(['success' => true, 'contrat_html' => $contratHtml, 'message' => 'Contrat mis à jour avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour : ' . $db->sql_error()]);
        }
    } else {
        $cmd = new sqlCmd();
        $cmd->Add('annonce_id', $candidature['annonce_id'], 'n');
        $cmd->Add('conducteur_id', $candidature['conducteur_id'], 'n');
        $cmd->Add('contenu_contrat', $contratHtml, 's');
        $cmd->Add('date_debut', date('Y-m-d'), 'd');
        $cmd->Add('statut', 'en_attente_signature', 's');
        $cmd->Add('version', 1, 'n');
        $cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
        $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

        $query = $cmd->MakeInsertQuery('contrats');
        if ($cmd->execute($db)) {
            $lastIdResult = $db->sql_result("SELECT currval('contrats_id_seq') AS last_id");
            $lastId = $lastIdResult->sql_fetch_assoc()['last_id'];
            echo json_encode(['success' => true, 'contrat_html' => $contratHtml, 'message' => 'Contrat généré avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la création du contrat : ' . $db->sql_error()]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception dans generer_contrat.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne : ' . $e->getMessage()]);
}
?>