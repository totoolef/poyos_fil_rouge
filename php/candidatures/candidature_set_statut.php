<?php
include '../config/config_principal.php';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Utilisez POST']); exit; }

  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded || ($decoded->role ?? '') !== 'annonceur') { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Non autorisé']); exit; }
  $annonceurId = (int)$decoded->sub;

  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];
  $candidatureId = (int)($body['candidature_id'] ?? 0);
  $nouveau = $body['statut'] ?? '';
  $motif = trim((string)($body['motif'] ?? ''));

  if ($candidatureId <= 0 || !in_array($nouveau, ['acceptee','refusee'], true)) {
    http_response_code(400); echo json_encode(['success'=>false,'message'=>'Paramètres invalides']); exit;
  }

  // Vérifier propriété
  $sql = "SELECT c.id, c.statut::text AS statut, a.annonceur_id FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1 LIMIT 1";
  $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
  if ($res === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)]); exit; }
  $row = pg_fetch_assoc($res);
  if (!$row || (int)$row['annonceur_id'] !== $annonceurId) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }
  if ($row['statut'] !== 'en_attente') { http_response_code(409); echo json_encode(['success'=>false,'message'=>'Transition de statut non autorisée']); exit; }

  // Update avec sqlCmd
  $cmdUpdate = new sqlCmd();
  $cmdUpdate->Add('statut', $nouveau, 's');
  $cmdUpdate->Add('updated_at', date('Y-m-d H:i:s'), 'd');

  $queryUpdate = $cmdUpdate->MakeUpdateQuery('candidatures', "id=$candidatureId");
  $res = $db->sql_query($queryUpdate);
  if ($res === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur SQL update: '.$db->sql_error()]); exit; }

  // Event avec sqlCmd
  $type = ($nouveau === 'acceptee') ? 'acceptee' : 'refusee';
  $meta = json_encode(['motif'=>$motif], JSON_UNESCAPED_UNICODE);
  
  $cmdEvent = new sqlCmd();
  $cmdEvent->Add('candidature_id', $candidatureId, 'n');
  $cmdEvent->Add('type', $type, 's');
  $cmdEvent->Add('acteur_role', 'annonceur', 's');
  $cmdEvent->Add('acteur_id', $annonceurId, 'n');
  $cmdEvent->Add('meta', $meta, 's');

  $queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
  $db->sql_query($queryEvent);

  echo json_encode(['success'=>true,'nouveau_statut'=>$nouveau]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('candidature_set_statut.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne']);
}
