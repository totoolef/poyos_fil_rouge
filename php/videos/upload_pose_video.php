<?php
include '../config/config_principal.php';

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt || ($jwt->role??'')!=='conducteur') out(['success'=>false,'message'=>'Rôle conducteur requis'],403);
$uid=(int)($jwt->sub??0);

$candidatureId=(int)($_POST['candidature_id']??0); if($candidatureId<=0) out(['success'=>false,'message'=>'candidature_id requis'],400);
if(!isset($_FILES['video'])) out(['success'=>false,'message'=>'Aucun fichier vidéo'],400);

/* Vérifier que la candidature appartient au conducteur et est acceptée */
$q=pg_query_params($db->dbLink,"SELECT c.id, c.statut, c.conducteur_id FROM candidatures c WHERE c.id=$1 AND c.conducteur_id=$2",[$candidatureId,$uid]);
if(!$q) dberr($db->dbLink);
$c=pg_fetch_assoc($q); if(!$c) out(['success'=>false,'message'=>'Candidature introuvable ou non autorisée'],403);
if($c['statut']!=='acceptee') out(['success'=>false,'message'=>'Candidature non acceptée'],400);

$f=$_FILES['video']; if($f['error']!==UPLOAD_ERR_OK) out(['success'=>false,'message'=>'Erreur upload'],400);
$allowed=['video/mp4','video/quicktime','video/x-matroska','video/avi','video/wmv'];
if(!in_array($f['type'],$allowed)) out(['success'=>false,'message'=>'Format non supporté'],400);
/* sécurité taille (ex: 200 Mo max) */
if(($f['size']??0) > 200*1024*1024) out(['success'=>false,'message'=>'Vidéo trop volumineuse (max 200 Mo)'],400);

/* Save file */
$dir=__DIR__.'/uploads/pose_videos'; @mkdir($dir,0777,true);
$ext=pathinfo($f['name'],PATHINFO_EXTENSION) ?: 'mp4';
$name='pose_'.$uid.'_'.time().'.'.$ext;
$dest=$dir.'/'.$name;
if(!move_uploaded_file($f['tmp_name'],$dest)) out(['success'=>false,'message'=>'Échec sauvegarde'],500);
$url='uploads/pose_videos/'.$name;

/* Insérer dans la table pose_videos avec sqlCmd */
$cmd = new sqlCmd();
$cmd->Add('candidature_id', $candidatureId, 'n');
$cmd->Add('user_id', $uid, 'n');
$cmd->Add('url', $url, 's');
$cmd->Add('statut', 'en_attente', 's');
$cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeInsertQuery('pose_videos') . " RETURNING id, url, statut, created_at";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);

/* Event/log simple avec sqlCmd */
$cmdEvent = new sqlCmd();
$cmdEvent->Add('candidature_id', $candidatureId, 'n');
$cmdEvent->Add('type', 'pose_video_uploaded', 's');
$cmdEvent->Add('acteur_role', 'conducteur', 's');
$cmdEvent->Add('acteur_id', $uid, 'n');
$cmdEvent->Add('meta', json_encode(['pose_video_id'=>$row['id'],'action'=>'upload']), 's');

$queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
@$db->sql_query($queryEvent);

out(['success'=>true,'data'=>$row]);
