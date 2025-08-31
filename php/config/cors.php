<?php
// cors.php

// 1) Whitelist d’origines (évite d’ouvrir à tout le monde)
$allowedOrigins = [
  'http://localhost:5173',
  // ajoute ici tes autres domaines front plus tard (https://app.tondomaine.com, etc.)
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  // en dev, tu peux forcer localhost
  header("Access-Control-Allow-Origin: http://localhost:5173");
}

header('Vary: Origin');

// Si tu utilises des cookies/credentials côté front (pas nécessaire ici)
// header('Access-Control-Allow-Credentials: true');

// 2) Autorise toutes les méthodes utiles (inclut DELETE)
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

// 3) Autorise les en-têtes dont tu as besoin (Authorization pour le Bearer)
$reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization';
header("Access-Control-Allow-Headers: $reqHeaders");

header('Access-Control-Max-Age: 86400');

// 4) Préflight OPTIONS : on répond et on sort
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ⚠️ NE PAS forcer 'Content-Type: application/json' ici.
// Laisse chaque endpoint définir son propre Content-Type.
// Sinon, les uploads multipart/form-data seront cassés.
