<?php
include '../config/config_principal.php';

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
if($role!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);

$validationId=(int)(json_decode(file_get_contents('php://input'),true)['validation_id']??0);
if($validationId<=0) out(['success'=>false,'message'=>'validation_id requis'],400);

/* Récup validation + contrat + annonceur */
$q=pg_query_params($db->dbLink,"
  SELECT v.id, v.contrat_id, c.conducteur_id, a.annonceur_id, c.suivi_anchor_day, c.suivi_next_due_at, a.montant_mensuel
  FROM validations_mensuelles v
  JOIN contrats c ON c.id=v.contrat_id
  JOIN annonces a ON a.id=c.annonce_id
  WHERE v.id=$1
",[$validationId]); if(!$q) dberr($db->dbLink);
$rw=pg_fetch_assoc($q); if(!$rw) out(['success'=>false,'message'=>'Validation introuvable'],404);
if((int)$rw['annonceur_id']!==$uid) out(['success'=>false,'message'=>'Accès refusé'],403);

/* Marquer validée avec sqlCmd */
$cmdValidation = new sqlCmd();
$cmdValidation->Add('statut', 'valide', 's');
$cmdValidation->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$queryValidation = $cmdValidation->MakeUpdateQuery('validations_mensuelles', "id=$validationId") . " RETURNING id, contrat_id";
$res = $db->sql_query($queryValidation);
if ($res === false) dberr($db->dbLink);
$v = pg_fetch_assoc($res);

/* Calculer prochaine due (depuis now) */
$anchor=(int)$rw['suivi_anchor_day']; if($anchor<=0) $anchor=(int)date('j');
$next = next_due_from($anchor, (new DateTime())->format('Y-m-d H:i:s'));

/* Maj contrat avec sqlCmd */
$cmdContrat = new sqlCmd();
$cmdContrat->Add('suivi_next_due_at', $next, 'd');
$cmdContrat->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$queryContrat = $cmdContrat->MakeUpdateQuery('contrats', "id=".(int)$rw['contrat_id']) . " RETURNING id, suivi_next_due_at";
$resContrat = $db->sql_query($queryContrat);
if ($resContrat === false) dberr($db->dbLink);
$contr = pg_fetch_assoc($resContrat);

/* Créer le paiement automatiquement avec sqlCmd */
$montantMensuel = (float)($rw['montant_mensuel'] ?? 0);
if ($montantMensuel > 0) {
    $commission = round($montantMensuel * 0.15, 2); // 15% commission
    $particulier = $montantMensuel - $commission;
    
    $cmdPaiement = new sqlCmd();
    $cmdPaiement->Add('contrat_id', (int)$rw['contrat_id'], 'n');
    $cmdPaiement->Add('montant_total', $montantMensuel, 'n');
    $cmdPaiement->Add('commission_poyos', $commission, 'n');
    $cmdPaiement->Add('montant_particulier', $particulier, 'n');
    $cmdPaiement->Add('date_paiement', date('Y-m-d'), 'd');
    $cmdPaiement->Add('statut', 'en_attente', 's');
    $cmdPaiement->Add('type', 'mensuel', 's');
    $cmdPaiement->Add('created_at', date('Y-m-d H:i:s'), 'd');
    $cmdPaiement->Add('updated_at', date('Y-m-d H:i:s'), 'd');

    $queryPaiement = $cmdPaiement->MakeInsertQuery('paiements') . " RETURNING id, montant_total, statut";
    $resPaiement = $db->sql_query($queryPaiement);
    
    if ($resPaiement) {
        $paiementData = pg_fetch_assoc($resPaiement);
        // Log du paiement créé avec sqlCmd
        $cmdEventPaiement = new sqlCmd();
        $cmdEventPaiement->Add('candidature_id', "(SELECT ca.id FROM candidatures ca JOIN contrats co ON co.annonce_id=ca.annonce_id AND co.conducteur_id=ca.conducteur_id WHERE co.id=".(int)$rw['contrat_id']." LIMIT 1)", 'l');
        $cmdEventPaiement->Add('type', 'paiement_auto_creé', 's');
        $cmdEventPaiement->Add('acteur_role', 'système', 's');
        $cmdEventPaiement->Add('acteur_id', 0, 'n');
        $cmdEventPaiement->Add('meta', json_encode(['paiement_id'=>$paiementData['id'], 'montant'=>$montantMensuel]), 's');

        $queryEventPaiement = $cmdEventPaiement->MakeInsertQuery('candidature_events');
        @$db->sql_query($queryEventPaiement);
    }
}

/* Event avec sqlCmd */
$cmdEvent = new sqlCmd();
$cmdEvent->Add('candidature_id', "(SELECT ca.id FROM candidatures ca JOIN contrats co ON co.annonce_id=ca.annonce_id AND co.conducteur_id=ca.conducteur_id WHERE co.id=".(int)$rw['contrat_id']." LIMIT 1)", 'l');
$cmdEvent->Add('type', 'validation_mensuelle', 's');
$cmdEvent->Add('acteur_role', 'annonceur', 's');
$cmdEvent->Add('acteur_id', $uid, 'n');
$cmdEvent->Add('meta', json_encode(['validation_id'=>$validationId,'action'=>'valide']), 's');

$queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
@$db->sql_query($queryEvent);

out(['success'=>true,'data'=>['validation_id'=>$validationId,'next_due'=>$contr['suivi_next_due_at']]]);
