<?php
include '../config/config_principal.php';



if($_SERVER['REQUEST_METHOD']!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$dec=validateJWT($tok); if(!$dec) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$dec->role??''; $uid=(int)($dec->sub??0);

$contratId=(int)($_GET['contrat_id']??0); if($contratId<=0) out(['success'=>false,'message'=>'contrat_id requis'],400);

$q="SELECT p.*, a.annonceur_id, c.conducteur_id
    FROM paiements p JOIN contrats c ON c.id=p.contrat_id
    JOIN annonces a ON a.id=c.annonce_id
    WHERE p.contrat_id=$1 ORDER BY p.id DESC LIMIT 1";
$r=pg_query_params($db->dbLink,$q,[$contratId]); if(!$r) dberr($db->dbLink);
$row=pg_fetch_assoc($r); if(!$row) out(['success'=>true,'data'=>null],200);

// Check droits : annonceur du contrat ou conducteur lié
if(($role==='annonceur' && (int)$row['annonceur_id']!==$uid) ||
   ($role==='conducteur' && (int)$row['conducteur_id']!==$uid)) out(['success'=>false,'message'=>'Accès interdit'],403);

out(['success'=>true,'data'=>$row],200);
