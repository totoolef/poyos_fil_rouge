<?php
include '../config/config_principal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$dec=validateJWT($tok); if(!$dec) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$dec->role??''; $uid=(int)($dec->sub??0); if($uid<=0) out(['success'=>false,'message'=>'Utilisateur non identifié'],401);

$body=json_decode(file_get_contents('php://input'),true);
$cid=(int)($body['candidature_id']??0); if($cid<=0) out(['success'=>false,'message'=>'candidature_id requis'],400);

$q="SELECT c.id, c.annonce_id, c.conducteur_id, a.annonceur_id
    FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1 LIMIT 1";
$r=pg_query_params($db->dbLink,$q,[$cid]); if($r===false) dberr($db->dbLink);
$rw=pg_fetch_assoc($r); if(!$rw) out(['success'=>false,'message'=>'Candidature introuvable'],404);
if(($role==='annonceur' && (int)$rw['annonceur_id']!==$uid) ||
   ($role==='conducteur' && (int)$rw['conducteur_id']!==$uid)) out(['success'=>false,'message'=>'Accès non autorisé'],403);
if($role!=='annonceur' && $role!=='conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'],403);

$annId=(int)$rw['annonce_id']; $condId=(int)$rw['conducteur_id'];

$c=pg_query_params($db->dbLink,"SELECT * FROM contrats WHERE annonce_id=$1 AND conducteur_id=$2 ORDER BY id DESC LIMIT 1",[$annId,$condId]);
if($c===false) dberr($db->dbLink);
$ct=pg_fetch_assoc($c); if(!$ct) out(['success'=>false,'message'=>'Contrat inexistant (créez-le d\'abord)'],404);

$setCol = ($role==='annonceur') ? 'signature_annonceur_at' : 'signature_conducteur_at';

// Update signature avec sqlCmd
$cmd = new sqlCmd();
$cmd->Add($setCol, date('Y-m-d H:i:s'), 'd');
$cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeUpdateQuery('contrats', "id=".(int)$ct['id']) . " RETURNING *";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$after = pg_fetch_assoc($res);

/* Si les deux ont signé -> statut_contrat='signe' */
if(!empty($after['signature_annonceur_at']) && !empty($after['signature_conducteur_at']) && ($after['statut_contrat']!=='signe')){
  $cmdFinal = new sqlCmd();
  $cmdFinal->Add('statut_contrat', 'signe', 's');
  $cmdFinal->Add('updated_at', date('Y-m-d H:i:s'), 'd');

  $queryFinal = $cmdFinal->MakeUpdateQuery('contrats', "id=".(int)$ct['id']) . " RETURNING *";
  $resFinal = $db->sql_query($queryFinal);
  if ($resFinal === false) dberr($db->dbLink);
  $after = pg_fetch_assoc($resFinal);
}

/* Message système avec sqlCmd */
$contenu = ($role==='annonceur') ? "Contrat signé par l'annonceur." : "Contrat signé par le conducteur.";
$cmdMessage = new sqlCmd();
$cmdMessage->Add('candidature_id', $cid, 'n');
$cmdMessage->Add('expediteur_role', $role, 's');
$cmdMessage->Add('expediteur_id', $uid, 'n');
$cmdMessage->Add('contenu', $contenu, 's');

$queryMessage = $cmdMessage->MakeInsertQuery('candidature_messages');
$db->sql_query($queryMessage);

out(['success'=>true,'data'=>$after],200);
