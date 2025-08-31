<?php
include '../config/config_principal.php';

/* util: clamp au dernier jour du mois */
function next_due_from($anchorDay, $fromTs){
  $d = new DateTime($fromTs);
  $d->modify('first day of next month');
  $last = (int)$d->format('t');
  $day = max(1, min((int)$anchorDay, $last));
  $d->setDate((int)$d->format('Y'), (int)$d->format('m'), $day)->setTime(10,0,0);
  return $d->format('Y-m-d H:i:s');
}

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);

$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$jwt->role??''; $uid=(int)($jwt->sub??0);

/* Pour l'instant on autorise l'annonceur du dossier (admin plus tard) */
if($role!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);

$body=json_decode(file_get_contents('php://input'),true);
$candidatureId=(int)($body['candidature_id']??0);
$poseVideoId  =(int)($body['pose_video_id']??0);
if($candidatureId<=0 || $poseVideoId<=0) out(['success'=>false,'message'=>'candidature_id et pose_video_id requis'],400);

/* Vérif droits annonceur sur la candidature */
$q="SELECT c.id AS cand_id, c.conducteur_id, c.annonce_id, a.annonceur_id
    FROM candidatures c JOIN annonces a ON a.id=c.annonce_id
    WHERE c.id=$1";
$r=pg_query_params($db->dbLink,$q,[$candidatureId]); if(!$r) dberr($db->dbLink);
$row=pg_fetch_assoc($r); if(!$row) out(['success'=>false,'message'=>'Candidature introuvable'],404);
if((int)$row['annonceur_id']!==$uid) out(['success'=>false,'message'=>'Accès refusé'],403);

/* Marquer la vidéo de pose validée avec sqlCmd */
$cmdVideo = new sqlCmd();
$cmdVideo->Add('statut', 'valide', 's');

$queryVideo = $cmdVideo->MakeUpdateQuery('pose_videos', "id=$poseVideoId AND candidature_id=$candidatureId") . " RETURNING id,url,created_at";
$res = $db->sql_query($queryVideo);
if ($res === false) dberr($db->dbLink);
$pv = pg_fetch_assoc($res);
if (!$pv) out(['success'=>false,'message'=>'Vidéo de pose introuvable'],404);

/* Récup contrat lié */
$c=pg_query_params($db->dbLink,"
  SELECT con.id, con.suivi_active FROM contrats con
  JOIN candidatures ca ON ca.annonce_id=con.annonce_id AND ca.conducteur_id=con.conducteur_id
  WHERE ca.id=$1 ORDER BY con.id DESC LIMIT 1
",[$candidatureId]); if(!$c) dberr($db->dbLink);
$contrat=pg_fetch_assoc($c); if(!$contrat) out(['success'=>false,'message'=>'Contrat non trouvé pour cette candidature'],404);

$anchorDay = (int)date('j');                       // jour d'ancrage = aujourd'hui
$now = (new DateTime())->format('Y-m-d H:i:s');
$nextDue = next_due_from($anchorDay, $now);

/* Activer la campagne + init suivi avec sqlCmd */
$cmdContrat = new sqlCmd();
$cmdContrat->Add('suivi_active', true, 'b');
$cmdContrat->Add('suivi_anchor_day', $anchorDay, 'n');
$cmdContrat->Add('suivi_next_due_at', $nextDue, 'd');
$cmdContrat->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$queryContrat = $cmdContrat->MakeUpdateQuery('contrats', "id=".(int)$contrat['id']) . " RETURNING id, suivi_active, suivi_anchor_day, suivi_next_due_at";
$resContrat = $db->sql_query($queryContrat);
if ($resContrat === false) dberr($db->dbLink);
$contratUpd = pg_fetch_assoc($resContrat);

/* (optionnel) passer le statut design -> campagne_active si table existe */
@pg_query($db->dbLink, "UPDATE design_status SET statut='campagne_active', updated_at=CURRENT_TIMESTAMP WHERE candidature_id=".((int)$candidatureId));

/* Event/log simple avec sqlCmd */
$cmdEvent = new sqlCmd();
$cmdEvent->Add('candidature_id', $candidatureId, 'n');
$cmdEvent->Add('type', 'pose_effectuee', 's');
$cmdEvent->Add('acteur_role', 'annonceur', 's');
$cmdEvent->Add('acteur_id', $uid, 'n');
$cmdEvent->Add('meta', json_encode(['video_pose_id'=>$poseVideoId]), 's');

$queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
@$db->sql_query($queryEvent);

out(['success'=>true,'message'=>'Campagne activée','data'=>[
  'pose_video'=>$pv,
  'suivi'=>$contratUpd
]]);
