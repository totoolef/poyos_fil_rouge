<?php
include '../config/config_principal.php';

// Les fonctions bearer(), out() et dberr() sont maintenant dans config_principal.php

if ($_SERVER['REQUEST_METHOD']!=='POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);

$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$dec=validateJWT($tok); if(!$dec || ($dec->role??'')!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);
$userId=(int)($dec->sub??0);

$body=json_decode(file_get_contents('php://input'),true);
$contratId=(int)($body['contrat_id']??0);
$montant=(float)($body['montant_total']??0);
$type=(string)($body['type']??'autre');
if($contratId<=0 || $montant<=0) out(['success'=>false,'message'=>'contrat_id et montant requis'],400);

// Vérif que l'annonceur est bien lié à ce contrat
$q="SELECT c.id, a.annonceur_id FROM contrats c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
$r=pg_query_params($db->dbLink,$q,[$contratId]); if(!$r) dberr($db->dbLink);
$row=pg_fetch_assoc($r); if(!$row) out(['success'=>false,'message'=>'Contrat introuvable'],404);
if((int)$row['annonceur_id']!==$userId) out(['success'=>false,'message'=>'Accès refusé'],403);

// Commission uniquement pour les paiements mensuels
if ($type === 'mensuel') {
    $commission = round($montant * 0.15, 2); // 15% commission
    $particulier = $montant - $commission;
} else {
    $commission = 0; // Pas de commission pour les autres types
    $particulier = $montant;
}

// Créer le paiement avec sqlCmd
$cmd = new sqlCmd();
$cmd->Add('contrat_id', $contratId, 'n');
$cmd->Add('montant_total', $montant, 'n');
$cmd->Add('commission_poyos', $commission, 'n');
$cmd->Add('montant_particulier', $particulier, 'n');
$cmd->Add('date_paiement', date('Y-m-d'), 'd');
$cmd->Add('statut', 'en_attente', 's');
$cmd->Add('type', $type, 's');
$cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
$cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeInsertQuery('paiements') . " RETURNING *";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$p = pg_fetch_assoc($res);

out(['success'=>true,'message'=>'Paiement créé','data'=>$p],200);
