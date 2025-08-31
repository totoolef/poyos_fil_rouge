<?php
include '../config/config_principal.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  out(['success'=>false,'message'=>'Méthode non autorisée'], 405);
}

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);
$decoded = validateJWT($token);
if (!$decoded) out(['success'=>false,'message'=>'Token invalide'], 401);

$role = $decoded->role ?? '';
$userId = (int)($decoded->sub ?? 0);
if ($userId <= 0) out(['success'=>false,'message'=>'Utilisateur non identifié'], 401);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$candidatureId = (int)($body['candidature_id'] ?? 0);
$commentaire = trim($body['commentaire'] ?? '');
if ($candidatureId <= 0) out(['success'=>false,'message'=>'candidature_id requis'], 400);

/* Vérif droits */
$res = pg_query_params($db->dbLink, "
  SELECT c.annonce_id, c.conducteur_id, a.annonceur_id
  FROM candidatures c
  JOIN annonces a ON a.id = c.annonce_id
  WHERE c.id = $1
  LIMIT 1
", [$candidatureId]);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);
if (!$row) out(['success'=>false,'message'=>'Candidature introuvable'], 404);

$conducteurId = (int)$row['conducteur_id'];
$annonceurId  = (int)$row['annonceur_id'];

if ($role === 'annonceur' && $userId !== $annonceurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
if ($role === 'conducteur' && $userId !== $conducteurId) out(['success'=>false,'message'=>'Accès non autorisé'], 403);
if ($role !== 'annonceur' && $role !== 'conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'], 403);

/* Upsert statut -> bat_modifs + reset validations */
$resS = pg_query_params($db->dbLink, "SELECT id FROM design_status WHERE candidature_id=$1", [$candidatureId]);
if ($resS === false) dberr($db->dbLink);
$exists = (bool) pg_fetch_row($resS);

if ($exists) {
  // Update avec sqlCmd
  $cmdUpdate = new sqlCmd();
  $cmdUpdate->Add('statut', 'bat_modifs', 's');
  $cmdUpdate->Add('valide_annonceur', false, 'b');
  $cmdUpdate->Add('valide_conducteur', false, 'b');
  $cmdUpdate->Add('updated_at', date('Y-m-d H:i:s'), 'd');

  $queryUpdate = $cmdUpdate->MakeUpdateQuery('design_status', "candidature_id=$candidatureId") . " RETURNING statut::text AS statut, valide_annonceur, valide_conducteur, updated_at";
  $resU = $db->sql_query($queryUpdate);
  if ($resU === false) dberr($db->dbLink);
  $stat = pg_fetch_assoc($resU);
} else {
  // Insert avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('candidature_id', $candidatureId, 'n');
  $cmd->Add('statut', 'bat_modifs', 's');
  $cmd->Add('valide_annonceur', false, 'b');
  $cmd->Add('valide_conducteur', false, 'b');

  $query = $cmd->MakeInsertQuery('design_status') . " RETURNING statut::text AS statut, valide_annonceur, valide_conducteur, updated_at";
  $resI = $db->sql_query($query);
  if ($resI === false) dberr($db->dbLink);
  $stat = pg_fetch_assoc($resI);
}

/* Message système avec sqlCmd */
$contenu = "Demande de modifications sur le BAT" . ($commentaire !== '' ? " : ".$commentaire : ".");

$cmdMessage = new sqlCmd();
$cmdMessage->Add('candidature_id', $candidatureId, 'n');
$cmdMessage->Add('expediteur_role', $role, 's');
$cmdMessage->Add('expediteur_id', $userId, 'n');
$cmdMessage->Add('contenu', $contenu, 's');

$queryMessage = $cmdMessage->MakeInsertQuery('candidature_messages');
$db->sql_query($queryMessage);

out([
  'success'=>true,
  'message'=>'Demande de modifications enregistrée',
  'data'=>[
    'candidature_id'=>$candidatureId,
    'statut'=>$stat['statut'],
    'valide_annonceur'=>($stat['valide_annonceur'] === 't'),
    'valide_conducteur'=>($stat['valide_conducteur'] === 't'),
    'updated_at'=>$stat['updated_at'] ?? null
  ]
], 200);
