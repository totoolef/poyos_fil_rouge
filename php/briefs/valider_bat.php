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

/* Upsert design_status si absent */
$resS = pg_query_params($db->dbLink, "SELECT id, statut FROM design_status WHERE candidature_id=$1", [$candidatureId]);
if ($resS === false) dberr($db->dbLink);
$stat = pg_fetch_assoc($resS);
if (!$stat) {
  // Insert avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('candidature_id', $candidatureId, 'n');
  $cmd->Add('statut', 'bat_disponible', 's');
  $cmd->Add('valide_annonceur', false, 'b');
  $cmd->Add('valide_conducteur', false, 'b');

  $query = $cmd->MakeInsertQuery('design_status') . " RETURNING id, statut";
  $resI = $db->sql_query($query);
  if ($resI === false) dberr($db->dbLink);
}

/* Coche validation selon rôle */
$setCol = ($role === 'annonceur') ? 'valide_annonceur' : 'valide_conducteur';
$cmdUpdate = new sqlCmd();
$cmdUpdate->Add($setCol, true, 'b');
$cmdUpdate->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$queryUpdate = $cmdUpdate->MakeUpdateQuery('design_status', "candidature_id=$candidatureId") . " RETURNING valide_annonceur, valide_conducteur, statut::text AS statut";
$resU = $db->sql_query($queryUpdate);
if ($resU === false) dberr($db->dbLink);
$after = pg_fetch_assoc($resU);

/* Double validation -> prêt à commander */
if ($after && $after['valide_annonceur'] === 't' && $after['valide_conducteur'] === 't' && $after['statut'] !== 'pret_a_commander') {
  $cmdFinal = new sqlCmd();
  $cmdFinal->Add('statut', 'pret_a_commander', 's');
  $cmdFinal->Add('updated_at', date('Y-m-d H:i:s'), 'd');

  $queryFinal = $cmdFinal->MakeUpdateQuery('design_status', "candidature_id=$candidatureId") . " RETURNING statut::text AS statut, valide_annonceur, valide_conducteur, updated_at";
  $resP = $db->sql_query($queryFinal);
  if ($resP === false) dberr($db->dbLink);
  $final = pg_fetch_assoc($resP);
} else {
  $final = [
    'statut' => $after['statut'],
    'valide_annonceur' => $after['valide_annonceur'],
    'valide_conducteur' => $after['valide_conducteur'],
    'updated_at' => null
  ];
}

/* Journal : message système simple avec sqlCmd */
$contenu = ($role === 'annonceur')
  ? "Validation du BAT par l'annonceur."
  : "Validation du BAT par le conducteur.";

$cmdMessage = new sqlCmd();
$cmdMessage->Add('candidature_id', $candidatureId, 'n');
$cmdMessage->Add('expediteur_role', $role, 's');
$cmdMessage->Add('expediteur_id', $userId, 'n');
$cmdMessage->Add('contenu', $contenu, 's');

$queryMessage = $cmdMessage->MakeInsertQuery('candidature_messages');
$db->sql_query($queryMessage);

out([
  'success'=>true,
  'message'=>'Validation enregistrée',
  'data'=>[
    'candidature_id'=>$candidatureId,
    'statut'=>$final['statut'],
    'valide_annonceur'=>($final['valide_annonceur'] === 't'),
    'valide_conducteur'=>($final['valide_conducteur'] === 't'),
    'updated_at'=>$final['updated_at'] ?? null
  ]
], 200);
