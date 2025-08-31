<?php
include "../config/config_principal.php";


try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        $decoded = validateJWT($token);
        if (!$decoded || $decoded->role !== 'conducteur') {
            echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
            exit;
        }

        $result = $db->sql_result("SELECT * FROM annonces WHERE statut = 'ouverte'");
        $annonces = $result->sql_fetch_all();

        echo json_encode(['success' => true, 'annonces' => $annonces]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>