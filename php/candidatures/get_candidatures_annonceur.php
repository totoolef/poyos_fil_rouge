<?php
include '../config/config_principal.php';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        $decoded = validateJWT($token);
        if (!$decoded || $decoded->role !== 'annonceur') {
            echo json_encode(['success' => false, 'message' => 'Token invalide ou rôle non autorisé']);
            exit;
        }

        $annonceurId = $decoded->sub;
        $escapedId = pg_escape_string($db->dbLink, $annonceurId);
        $result = $db->sql_result("SELECT c.*, a.type_pub, a.titre AS titre_annonce, a.localisation, a.duree_mois, a.paiement_mensuel, u.nom AS nom_conducteur, u.prenom AS prenom_conducteur FROM candidatures c INNER JOIN annonces a ON c.annonce_id = a.id INNER JOIN utilisateurs u ON c.conducteur_id = u.id WHERE a.annonceur_id = '$escapedId'");
        $candidatures = $result->sql_fetch_all();

        echo json_encode(['success' => true, 'candidatures' => $candidatures]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>