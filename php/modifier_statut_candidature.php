<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['candidature_id']) || !isset($postData['statut'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
        exit;
    }

    if ($postData['statut'] !== 'acceptee' && $postData['statut'] !== 'refusee') {
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
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
    $escapedCandidatureId = pg_escape_string($db->dbLink, $postData['candidature_id']);
    $escapedAnnonceurId = pg_escape_string($db->dbLink, $annonceurId);

    // Vérifie que la candidature appartient à une annonce de l'annonceur
    $result = $db->sql_result("SELECT c.id FROM candidatures c INNER JOIN annonces a ON c.annonce_id = a.id WHERE c.id = '$escapedCandidatureId' AND a.annonceur_id = '$escapedAnnonceurId' LIMIT 1");
    if (!$result->sql_fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Candidature non trouvée ou non autorisée']);
        exit;
    }

    $cmd = new sqlCmd();
    $cmd->Add('statut', $postData['statut'], 's');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $sqlwhere = "id = '$escapedCandidatureId'";
    $query = $cmd->MakeUpdateQuery('candidatures', $sqlwhere);
    if ($cmd->execute($db)) {
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>