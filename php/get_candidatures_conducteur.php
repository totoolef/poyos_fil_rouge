<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        $decoded = validateJWT($token);
        if (!$decoded || $decoded->role !== 'conducteur') {
            echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
            exit;
        }

        $conducteurId = $decoded->sub;
        $escapedId = pg_escape_string($db->dbLink, $conducteurId);
        $result = $db->sql_result("SELECT c.*, a.titre AS titre_annonce, a.localisation FROM candidatures c INNER JOIN annonces a ON c.annonce_id = a.id WHERE c.conducteur_id = '$escapedId'");
        $candidatures = $result->sql_fetch_all();

        echo json_encode(['success' => true, 'candidatures' => $candidatures]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>