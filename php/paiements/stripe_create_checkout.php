<?php
include '../config/config_principal.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);

$tok = bearer();
if (!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt = validateJWT($tok);
if (!$jwt || (($jwt->role ?? '') !== 'annonceur')) out(['success'=>false,'message'=>'Rôle annonceur requis'],403);
$uid = (int)($jwt->sub ?? 0);

$body = json_decode(file_get_contents('php://input'), true);
$contratId = (int)($body['contrat_id'] ?? 0);
if ($contratId <= 0) out(['success'=>false,'message'=>'contrat_id requis'],400);

/* Vérif ownership */
$q = "SELECT c.id, a.annonceur_id FROM contrats c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
$r = pg_query_params($db->dbLink, $q, [$contratId]);
if (!$r) dberr($db->dbLink);
$row = pg_fetch_assoc($r);
if (!$row) out(['success'=>false,'message'=>'Contrat introuvable'],404);
if ((int)$row['annonceur_id'] !== $uid) out(['success'=>false,'message'=>'Accès refusé'],403);

/* Récup paiement en_attente */
$p = pg_query_params($db->dbLink, "SELECT * FROM paiements WHERE contrat_id=$1 AND statut='en_attente' ORDER BY id DESC LIMIT 1", [$contratId]);
if (!$p) dberr($db->dbLink);
$pay = pg_fetch_assoc($p);
if (!$pay) out(['success'=>false,'message'=>"Aucun paiement 'en_attente' pour ce contrat"],400);

$amount = (float)$pay['montant_total'];
if ($amount <= 0) out(['success'=>false,'message'=>'Montant invalide'],400);
$amountCents = (int)round($amount * 100);

/* URLs retour */
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173';
$candidatureIdParam = isset($_GET['candidature_id']) ? $_GET['candidature_id'] : '';
$successUrl = $origin . '/annonceur/contrats?candidatureId=' . urlencode($candidatureIdParam) . '&paid=1';
$cancelUrl  = $origin . '/annonceur/contrats?candidatureId=' . urlencode($candidatureIdParam) . '&canceled=1';

/* Stripe */
Stripe::setApiKey($stripeSecret);

$session = Session::create([
  'mode' => 'payment',
  'payment_method_types' => ['card'],
  'line_items' => [[
    'price_data' => [
      'currency' => 'eur',
      'product_data' => ['name' => 'Commande impression + créa'],
      'unit_amount' => $amountCents,
    ],
    'quantity' => 1,
  ]],
  'metadata' => [
    'paiement_id' => (string)$pay['id'],
    'contrat_id'  => (string)$contratId,
  ],
  'success_url' => $successUrl,
  'cancel_url'  => $cancelUrl,
]);

/* Sauvegarde session_id avec sqlCmd */
$cmd = new sqlCmd();
$cmd->Add('stripe_session_id', $session->id, 's');
$cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeUpdateQuery('paiements', "id=".(int)$pay['id']);
$db->sql_query($query);

out(['success'=>true,'data'=>['checkout_url'=>$session->url,'session_id'=>$session->id]]);
