<?php
include "../config/config_principal.php";

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['type_pub']) || !isset($postData['titre']) || !isset($postData['description']) || !isset($postData['localisation_type']) || !isset($postData['localisation']) || !isset($postData['nombre_vehicules']) || !isset($postData['duree_mois']) || !isset($postData['paiement_mensuel'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
        exit;
    }

    // Vérifie le token JWT
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'annonceur') {
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    $annonceurId = $decoded->sub;

    // Prépare la commande SQL
    $cmd = new sqlCmd();
    $cmd->Add('annonceur_id', $annonceurId, 'n');
    $cmd->Add('type_pub', $postData['type_pub'], 's');
    $cmd->Add('titre', $postData['titre'], 's');
    $cmd->Add('description', $postData['description'], 's');
    $cmd->Add('localisation_type', $postData['localisation_type'], 's');
    $cmd->Add('localisation_personnalisee', $postData['localisation_personnalisee'] ?? '', 's');
    $cmd->Add('code_postal', $postData['code_postal'] ?? '', 's');
    $cmd->Add('ville', $postData['ville'] ?? '', 's');
    $cmd->Add('localisation', $postData['localisation'], 's');
    $cmd->Add('nombre_vehicules', $postData['nombre_vehicules'], 'n');
    $cmd->Add('duree_mois', $postData['duree_mois'], 'n');
    $cmd->Add('paiement_mensuel', $postData['paiement_mensuel'], 'n');
    $cmd->Add('statut', 'ouverte', 's');
    $cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $query = $cmd->MakeInsertQuery('annonces');
    if ($cmd->execute($db)) {
        $lastIdResult = $db->sql_result("SELECT currval('annonces_id_seq') AS last_id");
        $lastId = $lastIdResult->sql_fetch_assoc()['last_id'];
        echo json_encode(['success' => true, 'id' => $lastId, 'message' => 'Annonce créée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la création : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    error_log("Exception : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>