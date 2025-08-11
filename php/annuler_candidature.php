<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['candidature_id'])) {
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
    $escapedCandidatureId = pg_escape_string($db->dbLink, $postData['candidature_id']);
    $escapedConducteurId = pg_escape_string($db->dbLink, $conducteurId);

    $cmd = new sqlCmd();
    $cmd->Add('statut', 'annulee', 's');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $sqlwhere = "id = '$escapedCandidatureId' AND conducteur_id = '$escapedConducteurId' AND statut = 'en_attente'";
    $query = $cmd->MakeUpdateQuery('candidatures', $sqlwhere);
    if ($cmd->execute($db)) {
        echo json_encode(['success' => true, 'message' => 'Candidature annulée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de l\'annulation : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>