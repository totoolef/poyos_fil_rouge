<?php
include '../config/config_principal.php';

use Stripe\Webhook;

$payload = @file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
try {
  $event = Webhook::constructEvent($payload, $sig, $stripeWebhookSecret);
} catch(Exception $e){
  http_response_code(400); echo json_encode(['error'=>'Bad signature']); exit;
}

$type = $event['type'] ?? '';
$obj  = $event['data']['object'] ?? [];

if ($type === 'checkout.session.completed') {
  $sessionId = $obj['id'] ?? null;
  $paymentIntent = $obj['payment_intent'] ?? null;

  if ($sessionId) {
    $q=pg_query_params($db->dbLink,"SELECT id FROM paiements WHERE stripe_session_id=$1 LIMIT 1",[$sessionId]);
    if ($q && ($row=pg_fetch_assoc($q))) {
      // Mise à jour avec sqlCmd
      $cmd = new sqlCmd();
      $cmd->Add('statut', 'paye', 's');
      $cmd->Add('stripe_payment_intent_id', $paymentIntent, 's');
      $cmd->Add('date_paiement', date('Y-m-d'), 'd');
      $cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

      $query = $cmd->MakeUpdateQuery('paiements', "id=".(int)$row['id']);
      $db->sql_query($query);
    }
  }
}

http_response_code(200);
echo json_encode(['received'=>true]);
