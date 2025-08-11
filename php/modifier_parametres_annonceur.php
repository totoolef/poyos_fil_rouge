<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('auth_utilisateur.php'); // ← contient authorizeRequest()

try {
    $postData = json_decode(file_get_contents('php://input'), true);

    // Vérifie uniquement le rôle (l’ID viendra du token)
    $auth = authorizeRequest('annonceur');

    if (!$auth['success']) {
        echo json_encode(['success' => false, 'message' => $auth['message']]);
        exit;
    }

    // Utilise l’ID de l’utilisateur extrait du token
    $userId = $auth['userId'];
    $idToken = pg_escape_string($db->dbLink, (string)$userId);

    // Construction de la requête
    $cmd = new sqlCmd();
    $cmd->Add('email', $postData['email'], 's');
    $cmd->Add('nom', $postData['nom'], 's');
    $cmd->Add('prenom', $postData['prenom'], 's');
    $cmd->Add('nom_entreprise', $postData['nom_entreprise'], 's');
    $cmd->Add('adresse', $postData['adresse'], 's');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    if (!empty($postData['mot_de_passe'])) {
        $cmd->Add('mot_de_passe_hash', password_hash($postData['mot_de_passe'], PASSWORD_BCRYPT), 's');
    }

    $sqlwhere = "id = '$idToken' AND role = 'annonceur'";
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
