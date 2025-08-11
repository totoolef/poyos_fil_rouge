<?php
// api/cors.php

// Définir l'origine avec un fallback pour dev
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173';
header('Access-Control-Allow-Origin: ' . $origin); // Autorise l'origine détectée
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Gestion de la requête pré-vol
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    exit; // Arrêter immédiatement
}

// Pour POST et autres méthodes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Assure que le content-type est défini
    $postData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur JSON decode: " . json_last_error_msg());
    }
}
?>