<?php
include '../config/config_principal.php';

try {
    $postData = json_decode(file_get_contents('php://input'), true);

    // Vérifie le token JWT
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'conducteur') {
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    // Utilise l'ID de l'utilisateur extrait du token
    $userId = $decoded->sub;
    $idToken = pg_escape_string($db->dbLink, (string)$userId);

    // Construction de la requête
    $cmd = new sqlCmd();
    $cmd->Add('email', $postData['email'], 's');
    $cmd->Add('nom', $postData['nom'], 's');
    $cmd->Add('prenom', $postData['prenom'], 's');
    $cmd->Add('adresse', $postData['adresse'], 's');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    if (!empty($postData['mot_de_passe'])) {
        $cmd->Add('mot_de_passe_hash', password_hash($postData['mot_de_passe'], PASSWORD_BCRYPT), 's');
    }

    $sqlwhere = "id = '$idToken' AND role = 'conducteur'";
    $query = $cmd->MakeUpdateQuery('utilisateurs', $sqlwhere);

    if ($cmd->execute($db)) {
        echo json_encode(['success' => true, 'message' => 'Paramètres mis à jour']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour : ' . $db->sql_error()]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
