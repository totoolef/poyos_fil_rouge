<?php
include '../config/config_principal.php';

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

$briefId = isset($_GET['brief_id']) ? (int)$_GET['brief_id'] : 0;
$candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$allowedTypes = ['brief','maquette','bat','source'];
if ($type && !in_array($type, $allowedTypes, true)) {
  out(['success'=>false,'message'=>'type invalide'], 400);
}

/* Si pas de brief_id, on résout via candidature_id → dernier brief */
if ($briefId <= 0) {
  if ($candidatureId <= 0) out(['success'=>false,'message'=>'brief_id ou candidature_id requis'], 400);

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

  if ($role === 'annonceur' && $userId !== $annonceurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
  if ($role === 'conducteur' && $userId !== $conducteurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
  if ($role !== 'annonceur' && $role !== 'conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'], 403);

  $sqlB = "
    SELECT id
    FROM design_briefs
    WHERE annonce_id = $1 AND conducteur_id = $2
    ORDER BY id DESC
    LIMIT 1
  ";
  $resB = pg_query_params($db->dbLink, $sqlB, [$annonceId, $conducteurId]);
  if ($resB === false) dberr($db->dbLink);
  $b = pg_fetch_assoc($resB);
  if (!$b) {
    out(['success'=>true,'data'=>['brief'=>null,'assets'=>[]],'message'=>'Aucun brief'], 200);
  }
  $briefId = (int)$b['id'];
} else {
  // Vérifier accès au brief fourni
  $sqlCheck = "
    SELECT b.id, b.annonce_id, b.conducteur_id, a.annonceur_id
    FROM design_briefs b
    JOIN annonces a ON a.id = b.annonce_id
    WHERE b.id = $1
    LIMIT 1
  ";
  $resC = pg_query_params($db->dbLink, $sqlCheck, [$briefId]);
  if ($resC === false) dberr($db->dbLink);
  $rowC = pg_fetch_assoc($resC);
  if (!$rowC) out(['success'=>false,'message'=>'Brief introuvable'], 404);

  $annonceurId = (int)$rowC['annonceur_id'];
  $conducteurId = (int)$rowC['conducteur_id'];
  if ($role === 'annonceur' && $userId !== $annonceurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
  if ($role === 'conducteur' && $userId !== $conducteurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
  if ($role !== 'annonceur' && $role !== 'conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'], 403);
}

/* Récup assets */
if ($type) {
  $sqlA = "
    SELECT id, type::text AS type, url, version, created_at
    FROM design_assets
    WHERE brief_id = $1 AND type = $2::design_asset_type
    ORDER BY created_at ASC, id ASC
  ";
  $resA = pg_query_params($db->dbLink, $sqlA, [$briefId, $type]);
} else {
  $sqlA = "
    SELECT id, type::text AS type, url, version, created_at
    FROM design_assets
    WHERE brief_id = $1
    ORDER BY created_at ASC, id ASC
  ";
  $resA = pg_query_params($db->dbLink, $sqlA, [$briefId]);
}

if ($resA === false) dberr($db->dbLink);
$assets = [];
while ($a = pg_fetch_assoc($resA)) $assets[] = $a;

out(['success'=>true, 'data'=>['brief_id'=>$briefId, 'assets'=>$assets]], 200);
