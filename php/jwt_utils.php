<?php
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Erreur : Le dossier vendor n\'est pas trouvé. Exécutez "composer install" dans ' . __DIR__);
}
require_once __DIR__ . '/vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$secretKey = 'ton_cle_secrete_super_longue_et_securisee'; // La même partout

function generateJWT($userId, $role) {
    global $secretKey;
    $payload = [
        'iss' => 'localhost',
        'iat' => time(),
        'exp' => time() + 3600, // 1 heure
        'sub' => $userId,
        'role' => strtolower($role),
    ];
    return JWT::encode($payload, $secretKey, 'HS256');
}

function validateJWT($token) {
    global $secretKey;
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        error_log("Erreur validation JWT : " . $e->getMessage());
        return false;
    }
}
?>
