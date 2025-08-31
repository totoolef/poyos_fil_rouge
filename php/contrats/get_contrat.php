<?php
include '../config/config_principal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$dec=validateJWT($tok); if(!$dec) out(['success'=>false,'message'=>'Token invalide'],401);

$role=$dec->role??''; $uid=(int)($dec->sub??0);
$cid=(int)($_GET['candidature_id']??0); if($cid<=0) out(['success'=>false,'message'=>'candidature_id requis'],400);

$q="SELECT c.id, c.annonce_id, c.conducteur_id, a.annonceur_id
    FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1 LIMIT 1";
$r=pg_query_params($db->dbLink,$q,[$cid]); if($r===false) dberr($db->dbLink);
$rw=pg_fetch_assoc($r); if(!$rw) out(['success'=>false,'message'=>'Candidature introuvable'],404);

if(($role==='annonceur' && (int)$rw['annonceur_id']!==$uid) ||
   ($role==='conducteur' && (int)$rw['conducteur_id']!==$uid)) out(['success'=>false,'message'=>'Accès non autorisé'],403);
if($role!=='annonceur' && $role!=='conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'],403);

$annId=(int)$rw['annonce_id']; $condId=(int)$rw['conducteur_id'];

$c=pg_query_params($db->dbLink,"
  SELECT * FROM contrats WHERE annonce_id=$1 AND conducteur_id=$2 ORDER BY id DESC LIMIT 1
",[$annId,$condId]); if($c===false) dberr($db->dbLink);
$ct=pg_fetch_assoc($c);
out(['success'=>true,'data'=>$ct?:null],200);
