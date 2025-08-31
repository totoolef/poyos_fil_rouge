<?php
include '../config/config_principal.php';

try {
    $postData = json_decode(file_get_contents('php://input'), true);
    if (!$postData || !isset($postData['email']) || !isset($postData['mot_de_passe']) || !isset($postData['nom']) || !isset($postData['prenom']) || !isset($postData['nom_entreprise']) || !isset($postData['code_postal']) || !isset($postData['ville'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes ou invalides']);
        exit;
    }

    // Vérifie si l'email existe déjà
    $escapedEmail = pg_escape_string($db->dbLink, $postData['email']);
    $result = $db->sql_result("SELECT COUNT(*) FROM utilisateurs WHERE email = '$escapedEmail'");
    if ($result->sql_fetch_result(0, 0) > 0) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }

    // Insertion dans la table utilisateurs
    $cmd = new sqlCmd();
    $cmd->Add('email', $postData['email'], 's');
    $cmd->Add('mot_de_passe_hash', password_hash($postData['mot_de_passe'], PASSWORD_BCRYPT), 's');
    $cmd->Add('role', 'annonceur', 's');
    $cmd->Add('nom', $postData['nom'], 's');
    $cmd->Add('prenom', $postData['prenom'], 's');
    $cmd->Add('nom_entreprise', $postData['nom_entreprise'], 's');
    $cmd->Add('adresse', $postData['ville'] . ', ' . $postData['code_postal'], 's');
    $cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
    $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $query = $cmd->MakeInsertQuery('utilisateurs');
    if ($cmd->execute($db)) {
        // Récupère l'ID avec une requête séparée
        $lastIdResult = $db->sql_result("SELECT currval('utilisateurs_id_seq') AS last_id");
        $lastId = $lastIdResult->sql_fetch_assoc()['last_id'];
        echo json_encode(['success' => true, 'id' => $lastId, 'message' => 'Inscription réussie']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Échec de l\'inscription : ' . $db->sql_error()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>