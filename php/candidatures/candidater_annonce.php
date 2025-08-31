<?php
include '../config/config_principal.php';

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['annonce_id']) || !isset($postData['message']) || !isset($postData['marque_voiture']) || !isset($postData['modele_voiture']) || !isset($postData['couleur'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
        exit;
    }

    // Vérifie le token JWT
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'conducteur') {
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    $conducteurId = $decoded->sub;

    // Insertion dans candidatures
    $cmd = new sqlCmd();
    $cmd->Add('annonce_id', $postData['annonce_id'], 'n');
    $cmd->Add('conducteur_id', $conducteurId, 'n');
    $cmd->Add('message', $postData['message'], 's');
    $cmd->Add('marque_voiture', $postData['marque_voiture'], 's');
    $cmd->Add('modele_voiture', $postData['modele_voiture'], 's');
    $cmd->Add('couleur', $postData['couleur'], 's');
    $cmd->Add('statut', 'en_attente', 's');
    $cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $query = $cmd->MakeInsertQuery('candidatures');
    if ($cmd->execute($db)) {
        $lastIdResult = $db->sql_result("SELECT currval('candidatures_id_seq') AS last_id");
        $lastId = $lastIdResult->sql_fetch_assoc()['last_id'];
        echo json_encode(['success' => true, 'id' => $lastId, 'message' => 'Candidature envoyée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la candidature : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>