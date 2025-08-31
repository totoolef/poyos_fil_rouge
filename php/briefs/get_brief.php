<?php
include '../config/config_principal.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  out(['success'=>false,'message'=>'Méthode non autorisée'], 405);
}

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);

$decoded = validateJWT($token);
if (!$decoded) out(['success'=>false,'message'=>'Token invalide'], 401);

$role = $decoded->role ?? '';
$userId = (int)($decoded->sub ?? 0);
if ($userId <= 0) out(['success'=>false,'message'=>'Utilisateur non identifié'], 401);

$candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
if ($candidatureId <= 0) out(['success'=>false,'message'=>'candidature_id requis'], 400);

/* Vérifier la relation et récupérer annonce_id + conducteur_id + annonceur_id */
$sql = "
  SELECT c.annonce_id, c.conducteur_id, a.annonceur_id
  FROM candidatures c
  JOIN annonces a ON a.id = c.annonce_id
  WHERE c.id = $1
  LIMIT 1
";
$res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);
if (!$row) out(['success'=>false,'message'=>'Candidature introuvable'], 404);

$annonceId    = (int)$row['annonce_id'];
$conducteurId = (int)$row['conducteur_id'];
$annonceurId  = (int)$row['annonceur_id'];

if ($role === 'annonceur' && $userId !== $annonceurId) {
  out(['success'=>false,'message'=>'Accès non autorisé'], 403);
}
if ($role === 'conducteur' && $userId !== $conducteurId) {
  out(['success'=>false,'message'=>'Accès non autorisé'], 403);
}
if ($role !== 'annonceur' && $role !== 'conducteur') {
  out(['success'=>false,'message'=>'Rôle non autorisé'], 403);
}

/* Récupérer le brief le plus récent (si plusieurs) */
$sqlBrief = "
  SELECT id, annonce_id, conducteur_id, champs_json, created_at
  FROM design_briefs
  WHERE annonce_id = $1 AND conducteur_id = $2
  ORDER BY id DESC
  LIMIT 1
";
$resB = pg_query_params($db->dbLink, $sqlBrief, [$annonceId, $conducteurId]);
if ($resB === false) dberr($db->dbLink);
$brief = pg_fetch_assoc($resB);

if (!$brief) {
  out([
    'success'=>true,
    'message'=>'Aucun brief pour cette candidature',
    'data'=>['brief'=>null,'assets'=>[]]
  ], 200);
}

/* Assets du brief */
$sqlAssets = "
  SELECT id, type::text AS type, url, version, created_at
  FROM design_assets
  WHERE brief_id = $1
  ORDER BY created_at ASC, id ASC
";
$resA = pg_query_params($db->dbLink, $sqlAssets, [(int)$brief['id']]);
if ($resA === false) dberr($db->dbLink);
$assets = [];
while ($a = pg_fetch_assoc($resA)) $assets[] = $a;

out([
  'success'=>true,
  'data'=>[
    'brief'=>[
      'id'            => (int)$brief['id'],
      'annonce_id'    => (int)$brief['annonce_id'],
      'conducteur_id' => (int)$brief['conducteur_id'],
      'candidature_id'=> $candidatureId,
      'champs_json'   => json_decode($brief['champs_json'] ?? '{}', true),
      'created_at'    => $brief['created_at'] ?? null,
    ],
    'assets'=>$assets
  ]
], 200);
