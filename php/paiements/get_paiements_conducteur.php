<?php
include '../config/config_principal.php';

if(($_SERVER['REQUEST_METHOD']??'')!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt=validateJWT($tok); if(!$jwt) out(['success'=>false,'message'=>'Token invalide'],401);
$role=$jwt->role??''; $uid=(int)($jwt->sub??0);

if($role!=='conducteur') out(['success'=>false,'message'=>'Rôle conducteur requis'],403);

/* Récupérer tous les paiements mensuels du conducteur */
$query = "
  SELECT 
    p.id,
    p.montant_total,
    p.commission_poyos,
    p.montant_particulier,
    p.date_paiement,
    p.statut,
    p.created_at,
    a.titre as campagne_titre,
    CONCAT(au.prenom, ' ', au.nom) as annonceur_nom,
    au.nom_entreprise as annonceur_entreprise,
    au.email as annonceur_email
  FROM paiements p
  JOIN contrats co ON co.id = p.contrat_id
  JOIN annonces a ON a.id = co.annonce_id
  JOIN utilisateurs au ON au.id = a.annonceur_id
  WHERE co.conducteur_id = $1 AND p.type = 'mensuel'
  ORDER BY p.created_at DESC
";

$result = pg_query_params($db->dbLink, $query, [$uid]);

if(!$result) dberr($db->dbLink);

$paiements = [];
while($row = pg_fetch_assoc($result)) {
  $paiements[] = [
    'id' => (int)$row['id'],
    'montant_total' => (float)$row['montant_total'],
    'commission_poyos' => (float)$row['commission_poyos'],
    'montant_particulier' => (float)$row['montant_particulier'],
    'date_paiement' => $row['date_paiement'],
    'statut' => $row['statut'],
    'created_at' => $row['created_at'],
    'campagne_titre' => $row['campagne_titre'],
    'annonceur_nom' => $row['annonceur_entreprise'] ?: $row['annonceur_nom'],
    'annonceur_email' => $row['annonceur_email']
  ];
}

out(['success'=>true,'data'=>['paiements'=>$paiements]]);
