<?php
include('cors.php');
include('sqlcmd.php');
include('config.php');
include('jwt_utils.php');

// Remplace par ton API Key Dropbox Sign (trouvé dans ton tableau de bord)
$apiKey = '1f00ce2a8fd1795409c7761e3e7078388480e12213096b43c44c39b629aa18bb'; // Remplace par ta clé réelle
$baseUrl = 'https://api.hellosign.com/v3';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilisez POST.']);
        exit;
    }

    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'annonceur') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    $annonceurId = $decoded->sub;

    // Récupère les données du formulaire
    if (empty($_FILES['file']) || !isset($_POST['contract_id'])) {
        echo json_encode(['success' => false, 'message' => 'Fichier ou ID de contrat manquant']);
        exit;
    }

    $contractId = pg_escape_string($db->dbLink, $_POST['contract_id']);
    $file = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];

    // Récupère les emails et noms des signataires depuis la BDD
    $escapedContractId = pg_escape_string($db->dbLink, $contractId);
    $query = "SELECT 
                a.annonceur_id, 
                c.conducteur_id, 
                au.email AS email_annonceur, 
                au.nom AS nom_annonceur, 
                au.prenom AS prenom_annonceur, 
                cu.email AS email_conducteur, 
                cu.nom AS nom_conducteur, 
                cu.prenom AS prenom_conducteur 
              FROM contrats c 
              INNER JOIN annonces a ON c.annonce_id = a.id 
              INNER JOIN utilisateurs au ON a.annonceur_id = au.id 
              INNER JOIN utilisateurs cu ON c.conducteur_id = cu.id 
              WHERE c.id = '$escapedContractId' AND a.annonceur_id = '$annonceurId' LIMIT 1";
    $result = $db->sql_result($query);
    $signerInfo = $result->sql_fetch_assoc();

    if (!$signerInfo) {
        echo json_encode(['success' => false, 'message' => 'Informations des signataires non trouvées']);
        exit;
    }

    // Prépare les données pour l'API Dropbox Sign
    $data = [
        'title' => 'Contrat Poyos - Signature requise',
        'subject' => 'Veuillez signer votre contrat Poyos',
        'message' => 'Merci de signer ce contrat pour finaliser votre accord.',
        'signers' => json_encode([
            [
                'email_address' => $signerInfo['email_conducteur'] ?? 'conducteur@example.com',
                'name' => ($signerInfo['nom_conducteur'] ?? 'Inconnu') . ' ' . ($signerInfo['prenom_conducteur'] ?? ''),
                'role' => 'signer1'
            ],
            [
                'email_address' => $signerInfo['email_annonceur'] ?? 'annonceur@example.com',
                'name' => ($signerInfo['nom_annonceur'] ?? '[Ton nom]') . ' ' . ($signerInfo['prenom_annonceur'] ?? ''),
                'role' => 'signer2'
            ]
        ]),
        'files' => curl_file_create($file, 'application/pdf', $fileName),
        'metadata' => json_encode(['contract_id' => $contractId]),
    ];

    // Envoie la requête à Dropbox Sign avec débogage
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/signature_request/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($apiKey . ':'),
    ]);
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Active le mode verbose pour débogage

    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        throw new Exception("Erreur cURL : " . curl_error($ch) . " - Détails : " . $verboseLog);
    }

    curl_close($ch);
    fclose($verbose);

    $result = json_decode($response, true);
    error_log("Réponse Dropbox Sign : " . print_r($result, true)); // Log la réponse pour débogage
    if (isset($result['signature_request_id'])) {
        // Mise à jour du contrat avec le statut
        $cmd = new sqlCmd();
        $cmd->Add('statut', 'en_attente_signature', 's');
        $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');
        $sqlwhere = "id = '$contractId' AND annonce_id IN (SELECT id FROM annonces WHERE annonceur_id = '$annonceurId')";
        $query = $cmd->MakeUpdateQuery('contrats', $sqlwhere);
        if ($cmd->execute($db)) {
            echo json_encode(['success' => true, 'message' => 'Contrat envoyé pour signature', 'signature_request_id' => $result['signature_request_id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour du contrat : ' . $db->sql_error()]);
        }
    } else {
        $errorMessage = $result['error']['error_msg'] ?? ($result['error_message'] ?? 'Erreur inconnue');
        echo json_encode(['success' => false, 'message' => 'Échec de l\'envoi via Dropbox Sign : ' . $errorMessage]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception dans envoyer_signature.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne : ' . $e->getMessage()]);
}
?>