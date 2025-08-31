<?php
include '../config/config_principal.php';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        error_log("Token reçu : " . $token); // Débogage
        $decoded = validateJWT($token);
        if (!$decoded || !isset($decoded->sub) || $decoded->role !== 'conducteur') {
            error_log("Token décodé : " . print_r($decoded, true)); // Débogage
            echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
            exit;
        }

        $userId = $decoded->sub;
        $escapedId = pg_escape_string($db->dbLink, $userId);
        $result = $db->sql_result("SELECT id, email, nom, prenom, nom_entreprise, adresse, role FROM utilisateurs WHERE id = '$escapedId' LIMIT 1");
        $user = $result->sql_fetch_assoc();

        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>