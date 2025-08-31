<?php
include '../config/config_principal.php';

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt || ($jwt->role??'')!=='conducteur') out(['success'=>false,'message'=>'Rôle conducteur requis'],403);
$uid=(int)($jwt->sub??0);

$contratId=(int)($_POST['contrat_id']??0); if($contratId<=0) out(['success'=>false,'message'=>'contrat_id requis'],400);
if(!isset($_FILES['video'])) out(['success'=>false,'message'=>'Aucun fichier vidéo'],400);

/* Vérifier que le contrat appartient au conducteur et que le suivi est actif */
$q=pg_query_params($db->dbLink,"SELECT c.id, c.suivi_active, c.suivi_next_due_at, c.suivi_anchor_day FROM contrats c WHERE c.id=$1 AND c.conducteur_id=$2",[$contratId,$uid]);
if(!$q) dberr($db->dbLink);
$c=pg_fetch_assoc($q); if(!$c) out(['success'=>false,'message'=>'Contrat introuvable ou non autorisé'],403);
if(!(bool)$c['suivi_active']) out(['success'=>false,'message'=>'Suivi non actif'],400);

/* Vérifier si on est dans la fenêtre d'upload (2 jours avant à 5 jours après) */
$now = new DateTime();
$dueDate = new DateTime($c['suivi_next_due_at']);
$anchorDay = (int)$c['suivi_anchor_day'];

// Calculer la fenêtre d'upload
$windowStart = clone $dueDate;
$windowStart->modify('-2 days');
$windowEnd = clone $dueDate;
$windowEnd->modify('+5 days');

if ($now < $windowStart || $now > $windowEnd) {
    out(['success'=>false,'message'=>'Hors de la fenêtre d\'upload. Fenêtre : ' . $windowStart->format('d/m/Y') . ' à ' . $windowEnd->format('d/m/Y')],400);
}

/* Vérifier qu'il n'y a pas déjà une vidéo pour ce mois */
$mois = (int)$now->format('n'); // 1-12
$annee = (int)$now->format('Y');
$existing = pg_query_params($db->dbLink,"
  SELECT id FROM validations_mensuelles 
  WHERE contrat_id=$1 AND mois=$2 AND EXTRACT(YEAR FROM created_at)=$3
",[$contratId,$mois,$annee]);
if(!$existing) dberr($db->dbLink);
if(pg_num_rows($existing) > 0) out(['success'=>false,'message'=>'Vidéo mensuelle déjà envoyée pour ce mois'],400);

$f=$_FILES['video']; if($f['error']!==UPLOAD_ERR_OK) out(['success'=>false,'message'=>'Erreur upload'],400);
$allowed=['video/mp4','video/quicktime','video/x-matroska'];
if(!in_array($f['type'],$allowed)) out(['success'=>false,'message'=>'Format non supporté'],400);
/* sécurité taille (ex: 200 Mo max) */
if(($f['size']??0) > 200*1024*1024) out(['success'=>false,'message'=>'Vidéo trop volumineuse (max 200 Mo)'],400);

/* Save file */
$dir=__DIR__.'/uploads/validations_videos'; @mkdir($dir,0777,true);
$ext=pathinfo($f['name'],PATHINFO_EXTENSION) ?: 'mp4';
$name='mensuelle_'.$uid.'_'.time().'.'.$ext;
$dest=$dir.'/'.$name;
if(!move_uploaded_file($f['tmp_name'],$dest)) out(['success'=>false,'message'=>'Échec sauvegarde'],500);
$url='uploads/validations_videos/'.$name;

/* Insérer dans validations_mensuelles avec sqlCmd */
$cmd = new sqlCmd();
$cmd->Add('contrat_id', $contratId, 'n');
$cmd->Add('mois', $mois, 'n');
$cmd->Add('kilometrage', 0, 'n');
$cmd->Add('video_url', $url, 's');
$cmd->Add('statut', 'en_attente', 's');
$cmd->Add('due_at', $c['suivi_next_due_at'], 'd');
$cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
$cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeInsertQuery('validations_mensuelles') . " RETURNING id, statut, video_url, due_at";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);

/* Event/log simple avec sqlCmd */
$cmdEvent = new sqlCmd();
$cmdEvent->Add('candidature_id', "(SELECT ca.id FROM candidatures ca JOIN contrats co ON co.annonce_id=ca.annonce_id AND co.conducteur_id=ca.conducteur_id WHERE co.id=$contratId LIMIT 1)", 'l');
$cmdEvent->Add('type', 'validation_mensuelle', 's');
$cmdEvent->Add('acteur_role', 'conducteur', 's');
$cmdEvent->Add('acteur_id', $uid, 'n');
$cmdEvent->Add('meta', json_encode(['validation_id'=>$row['id'],'action'=>'upload']), 's');

$queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
@$db->sql_query($queryEvent);

out(['success'=>true,'data'=>$row]);
