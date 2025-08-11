<?php
require_once 'cors.php';
require_once 'sqlcmd.php';
require_once 'config.php';
require_once 'jwt_utils.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); // Méthode non autorisée
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilisez GET.']);
        exit;
    }

    // Vérifie le token JWT
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $decoded = validateJWT($token);
    if (!$decoded || $decoded->role !== 'annonceur') {
        http_response_code(401); // Non autorisé
        echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
        exit;
    }

    $annonceurId = $decoded->sub;

    // Vérifie la connexion à la base de données
    if (!$db->dbLink) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Connexion à la base de données perdue']);
        exit;
    }

    $escapedId = pg_escape_string($db->dbLink, $annonceurId);

    // Requête pour les contrats de l'annonceur
    $query = "SELECT c.*, a.titre AS titre_annonce, u.nom AS nom_conducteur, u.prenom AS prenom_conducteur 
              FROM contrats c 
              INNER JOIN annonces a ON c.annonce_id = a.id 
              INNER JOIN utilisateurs u ON c.conducteur_id = u.id 
              WHERE a.annonceur_id = '$escapedId'";
    
    $result = $db->sql_result($query);

    if ($result === false) {
        $error = pg_last_error($db->dbLink);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Échec de la requête SQL : ' . $error]);
        exit;
    }

    $contrats = $result->sql_fetch_all();
    echo json_encode(['success' => true, 'contrats' => $contrats]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception dans get_contrats.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne : ' . $e->getMessage()]);
}
?>