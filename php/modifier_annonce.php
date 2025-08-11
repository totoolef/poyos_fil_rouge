<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['id']) || !isset($postData['type_pub']) || !isset($postData['titre']) || !isset($postData['description']) || !isset($postData['localisation']) || !isset($postData['nombre_vehicules']) || !isset($postData['duree_mois']) || !isset($postData['paiement_mensuel']) || !isset($postData['statut'])) {
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
    $escapedAnnonceId = pg_escape_string($db->dbLink, $postData['id']);
    $escapedAnnonceurId = pg_escape_string($db->dbLink, $annonceurId);
    $result = $db->sql_result("SELECT * FROM annonces WHERE id = '$escapedAnnonceId' AND annonceur_id = '$escapedAnnonceurId' LIMIT 1");
    if (!$result->sql_fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Annonce non trouvée ou non autorisée']);
        exit;
    }

    $cmd = new sqlCmd();
    $cmd->Add('type_pub', $postData['type_pub'], 's');
    $cmd->Add('titre', $postData['titre'], 's');
    $cmd->Add('description', $postData['description'], 's');
    $cmd->Add('localisation', $postData['localisation'], 's');
    $cmd->Add('nombre_vehicules', $postData['nombre_vehicules'], 'n');
    $cmd->Add('duree_mois', $postData['duree_mois'], 'n');
    $cmd->Add('paiement_mensuel', $postData['paiement_mensuel'], 'n');
    $cmd->Add('statut', $postData['statut'], 's');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $sqlwhere = "id = '$escapedAnnonceId' AND annonceur_id = '$escapedAnnonceurId'";
    $query = $cmd->MakeUpdateQuery('annonces', $sqlwhere);
    if ($cmd->execute($db)) {
        echo json_encode(['success' => true, 'message' => 'Annonce modifiée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la modification : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>