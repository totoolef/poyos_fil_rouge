<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php'); // Ajout pour la génération de JWT

try {
    error_log("Requête reçue - Méthode: " . $_SERVER['REQUEST_METHOD']);
    $rawInput = file_get_contents('php://input');
    error_log("Données brutes: " . $rawInput);
    $postData = json_decode($rawInput, true);
    error_log("Données décodées: " . print_r($postData, true));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$postData || !isset($postData['email']) || !isset($postData['mot_de_passe']) || !isset($postData['role'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
            exit;
        }

        // Utiliser la connexion $db pour pg_escape_string
        $escapedEmail = pg_escape_string($db->dbLink, $postData['email']); // $db->dbLink est la connexion dans pgsqlInterface
        $result = $db->sql_result("SELECT * FROM utilisateurs WHERE email = '$escapedEmail' LIMIT 1");
        $user = $result->sql_fetch_assoc();

        if ($user && password_verify($postData['mot_de_passe'], $user['mot_de_passe_hash']) && $user['role'] === $postData['role']) {
            $token = generateJWT($user['id'], $user['role']); // Génération du token JWT
            echo json_encode(['success' => true, 'role' => $user['role'], 'token' => $token, 'message' => 'Connexion réussie']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email, mot de passe ou rôle incorrect']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>