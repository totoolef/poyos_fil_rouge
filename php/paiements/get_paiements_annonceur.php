<?php
include '../config/config_principal.php';

if(($_SERVER['REQUEST_METHOD']??'')!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$jwt->role??''; $uid=(int)($jwt->sub??0);

if($role!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);

/* Récupérer tous les paiements de l'annonceur */
$query = "
  SELECT 
    p.id,
    p.type,
    p.montant_total,
    p.commission_poyos,
    p.montant_particulier,
    p.date_paiement,
    p.statut,
    p.created_at,
    CONCAT(cu.prenom, ' ', cu.nom) as conducteur_nom,
    cu.email as conducteur_email
  FROM paiements p
  JOIN contrats co ON co.id = p.contrat_id
  JOIN annonces a ON a.id = co.annonce_id
  JOIN utilisateurs cu ON cu.id = co.conducteur_id
  WHERE a.annonceur_id = $1
  ORDER BY p.created_at DESC
";

$result = pg_query_params($db->dbLink, $query, [$uid]);

if(!$result) dberr($db->dbLink);

$paiements = [];
while($row = pg_fetch_assoc($result)) {
  $paiements[] = [
    'id' => (int)$row['id'],
    'type' => $row['type'],
    'montant_total' => (float)$row['montant_total'],
    'commission_poyos' => (float)$row['commission_poyos'],
    'montant_particulier' => (float)$row['montant_particulier'],
    'date_paiement' => $row['date_paiement'],
    'statut' => $row['statut'],
    'created_at' => $row['created_at'],
    'conducteur_nom' => $row['conducteur_nom'],
    'conducteur_email' => $row['conducteur_email']
  ];
}

out(['success'=>true,'data'=>['paiements'=>$paiements]]);
