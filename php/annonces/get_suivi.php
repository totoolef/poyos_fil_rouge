<?php
include "../config/config_principal.php";

if(($_SERVER['REQUEST_METHOD']??'')!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$jwt->role??''; $uid=(int)($jwt->sub??0);

$contratId=(int)($_GET['contrat_id']??0); if($contratId<=0) out(['success'=>false,'message'=>'contrat_id requis'],400);

/* Vérif droits : annonceur du contrat ou conducteur lié */
$q="SELECT c.id, a.annonceur_id, c.conducteur_id FROM contrats c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
$r=pg_query_params($db->dbLink,$q,[$contratId]); if(!$r) dberr($db->dbLink);
$rw=pg_fetch_assoc($r); if(!$rw) out(['success'=>false,'message'=>'Contrat introuvable'],404);
if( ($role==='annonceur' && (int)$rw['annonceur_id']!==$uid) ||
    ($role==='conducteur' && (int)$rw['conducteur_id']!==$uid) ){
  out(['success'=>false,'message'=>'Accès refusé'],403);
}

/* Suivi + historique */
$s=pg_query_params($db->dbLink,"SELECT suivi_active, suivi_anchor_day, suivi_next_due_at FROM contrats WHERE id=$1",[$contratId]);
if(!$s) dberr($db->dbLink);
$suivi=pg_fetch_assoc($s);

$hist=[];
$h=pg_query_params($db->dbLink,"
  SELECT id, mois, due_at, statut, video_url, kilometrage, created_at, updated_at
  FROM validations_mensuelles
  WHERE contrat_id=$1
  ORDER BY COALESCE(due_at, created_at) DESC
",[$contratId]); if(!$h) dberr($db->dbLink);
while($x=pg_fetch_assoc($h)) $hist[]=$x;

// Ajouter les paiements mensuels liés aux validations
$paiements=[];
$p=pg_query_params($db->dbLink,"
  SELECT id, montant_total, montant_particulier, commission_poyos, date_paiement, statut, type, created_at
  FROM paiements
  WHERE contrat_id=$1 AND type='mensuel'
  ORDER BY created_at DESC
",[$contratId]); if(!$p) dberr($db->dbLink);
while($x=pg_fetch_assoc($p)) $paiements[]=$x;

out(['success'=>true,'data'=>['suivi'=>$suivi,'historique'=>$hist,'paiements'=>$paiements]]);
