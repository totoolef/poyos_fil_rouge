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

$candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
if ($candidatureId <= 0) out(['success'=>false,'message'=>'candidature_id requis'], 400);

/* Vérifier droits et récupérer trio annonce/conducteur/annonceur */
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

/* Dernier brief (si plusieurs) */
$sqlB = "
  SELECT id
  FROM design_briefs
  WHERE annonce_id = $1 AND conducteur_id = $2
  ORDER BY id DESC
  LIMIT 1
";
$resB = pg_query_params($db->dbLink, $sqlB, [$annonceId, $conducteurId]);
if ($resB === false) dberr($db->dbLink);
$brief = pg_fetch_assoc($resB);
$briefId = $brief ? (int)$brief['id'] : 0;

/* Dernier asset (tous types) + existence bat / maquette */
$lastAsset = null;
$hasBat = false; $hasMaquette = false;
if ($briefId > 0) {
  $resA = pg_query_params($db->dbLink, "
    SELECT id, type::text AS type, url, version, created_at
    FROM design_assets
    WHERE brief_id = $1
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ", [$briefId]);
  if ($resA === false) dberr($db->dbLink);
  $lastAsset = pg_fetch_assoc($resA) ?: null;

  $resBat = pg_query_params($db->dbLink, "SELECT 1 FROM design_assets WHERE brief_id = $1 AND type='bat' LIMIT 1", [$briefId]);
  if ($resBat === false) dberr($db->dbLink);
  $hasBat = (bool) pg_fetch_row($resBat);

  $resMak = pg_query_params($db->dbLink, "SELECT 1 FROM design_assets WHERE brief_id = $1 AND type='maquette' LIMIT 1", [$briefId]);
  if ($resMak === false) dberr($db->dbLink);
  $hasMaquette = (bool) pg_fetch_row($resMak);
}

/* Status existant ? */
$resS = pg_query_params($db->dbLink, "
  SELECT id, statut::text AS statut, valide_annonceur, valide_conducteur, updated_at
  FROM design_status
  WHERE candidature_id = $1
  LIMIT 1
", [$candidatureId]);
if ($resS === false) dberr($db->dbLink);
$stat = pg_fetch_assoc($resS);

/* Si absent → initialiser selon assets présents */
if (!$stat) {
  $initial = 'en_brief';
  if ($hasBat) $initial = 'bat_disponible';
  else if ($hasMaquette) $initial = 'maquette_en_cours';

  // Insert avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('candidature_id', $candidatureId, 'n');
  $cmd->Add('statut', $initial, 's');
  $cmd->Add('valide_annonceur', false, 'b');
  $cmd->Add('valide_conducteur', false, 'b');

  $query = $cmd->MakeInsertQuery('design_status') . " RETURNING id, statut::text AS statut, valide_annonceur, valide_conducteur, updated_at";
  $resI = $db->sql_query($query);
  if ($resI === false) dberr($db->dbLink);
  $stat = pg_fetch_assoc($resI);
}

/* Réponse */
// PostgreSQL retourne 't'/'f', pas true/false
$valide_annonceur = ($stat['valide_annonceur'] === 't' || $stat['valide_annonceur'] === true);
$valide_conducteur = ($stat['valide_conducteur'] === 't' || $stat['valide_conducteur'] === true);

out([
  'success' => true,
  'data' => [
    'candidature_id'   => $candidatureId,
    'brief_id'         => $briefId ?: null,
    'statut'           => $stat['statut'],
    'valide_annonceur' => $valide_annonceur,
    'valide_conducteur'=> $valide_conducteur,
    'updated_at'       => $stat['updated_at'] ?? null,
    'last_asset'       => $lastAsset ? [
      'id'=>(int)$lastAsset['id'], 'type'=>$lastAsset['type'], 'version'=>(int)$lastAsset['version'], 'created_at'=>$lastAsset['created_at'] ?? null
    ] : null
  ]
], 200);
