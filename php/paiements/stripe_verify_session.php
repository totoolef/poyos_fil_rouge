<?php
include '../config/config_principal.php';
use Stripe\Stripe; use Stripe\Checkout\Session;

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt || ($jwt->role??'')!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);
$uid=(int)($jwt->sub??0);

$body=json_decode(file_get_contents('php://input'),true);
$contratId=(int)($body['contrat_id']??0);
if($contratId<=0) out(['success'=>false,'message'=>'contrat_id requis'],400);

$q=pg_query_params($db->dbLink,"
  SELECT p.id, p.stripe_session_id, p.statut, a.annonceur_id
  FROM paiements p JOIN contrats c ON c.id=p.contrat_id
  JOIN annonces a ON a.id=c.annonce_id
  WHERE p.contrat_id=$1 ORDER BY p.id DESC LIMIT 1
",[$contratId]); if(!$q) dberr($db->dbLink);
$pay=pg_fetch_assoc($q); if(!$pay) out(['success'=>false,'message'=>'Paiement introuvable'],404);
if((int)$pay['annonceur_id']!==$uid) out(['success'=>false,'message'=>'Accès refusé'],403);

if($pay['statut']==='paye') out(['success'=>true,'data'=>['statut'=>'paye']],200);
if(empty($pay['stripe_session_id'])) out(['success'=>false,'message'=>'Session Stripe inconnue'],400);

Stripe::setApiKey($stripeSecret);
$s = Session::retrieve($pay['stripe_session_id']);
if(($s->status ?? '')==='complete' || ($s->payment_status ?? '')==='paid'){
  // Mise à jour avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('statut', 'paye', 's');
  $cmd->Add('stripe_payment_intent_id', (string)($s->payment_intent ?? ''), 's');
  $cmd->Add('date_paiement', date('Y-m-d'), 'd');
  $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

  $query = $cmd->MakeUpdateQuery('paiements', "id=".(int)$pay['id']);
  $db->sql_query($query);
  out(['success'=>true,'data'=>['statut'=>'paye']],200);
}
out(['success'=>true,'data'=>['statut'=>'en_attente']],200);
