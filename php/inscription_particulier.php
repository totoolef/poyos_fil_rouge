<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');

try {
    $postData = json_decode(file_get_contents('php://input'), true);

    // Échappement manuel de l'email
    $email = str_replace("'", "''", $postData['email']);

    // Vérifie si l'email existe déjà
    $result = $db->sql_result("SELECT COUNT(*) FROM utilisateurs WHERE email = '$email'");
    if ($result && $result->sql_fetch_result(0, 0) > 0) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }

    // Insertion dans la table utilisateurs
    $cmd = new sqlCmd();
    $cmd->Add('email', $postData['email'], 's');
    $cmd->Add('mot_de_passe_hash', password_hash($postData['mot_de_passe'], PASSWORD_BCRYPT), 's');
    $cmd->Add('role', 'conducteur', 's');
    $cmd->Add('nom', $postData['nom'], 's');
    $cmd->Add('prenom', $postData['prenom'], 's');
    $cmd->Add('plaque_immatriculation', $postData['plaque_immatriculation'], 's');
    $cmd->Add('adresse', $postData['ville'] . ', ' . $postData['code_postal'], 's');
    $cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $query = $cmd->MakeInsertQuery('utilisateurs');

    if ($db->sql_query($query)) {
        // Récupérer l'ID de l'utilisateur (si une séquence auto-incrémentée est utilisée)
        $idRes = $db->sql_result("SELECT id FROM utilisateurs WHERE email = '$email'");
        $lastId = $idRes ? $idRes->sql_fetch_result(0, 'id') : null;

        echo json_encode(['success' => true, 'id' => $lastId, 'message' => 'Inscription réussie']);
    } else {
        echo json_encode(['success' => false, 'message' => "Échec de l'inscription : " . $db->sql_error()]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
