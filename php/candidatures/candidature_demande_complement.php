<?php
include '../config/config_principal.php';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Utilisez POST']); exit; }

  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded || ($decoded->role ?? '') !== 'annonceur') { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Non autorisé']); exit; }
  $annonceurId = (int)$decoded->sub;

  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $candidatureId = (int)($body['candidature_id'] ?? 0);
  $message = trim((string)($body['message'] ?? ''));

  if ($candidatureId <= 0 || $message === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Paramètres invalides']); exit; }

  // ACL
  $sql = "SELECT a.annonceur_id FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
  $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
  $row = $res ? pg_fetch_assoc($res) : null;
  if (!$row || (int)$row['annonceur_id'] !== $annonceurId) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }

  // Message avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('candidature_id', $candidatureId, 'n');
  $cmd->Add('expediteur_role', 'annonceur', 's');
  $cmd->Add('expediteur_id', $annonceurId, 'n');
  $cmd->Add('contenu', $message, 's');

  $query = $cmd->MakeInsertQuery('candidature_messages') . " RETURNING id";
  $res = $db->sql_query($query);
  if ($res === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur SQL message: '.$db->sql_error()]); exit; }
  $mid = (int)(pg_fetch_assoc($res)['id'] ?? 0);

  // Event avec sqlCmd
  $meta = json_encode(['message_preview'=>mb_substr($message,0,80)], JSON_UNESCAPED_UNICODE);
  $cmdEvent = new sqlCmd();
  $cmdEvent->Add('candidature_id', $candidatureId, 'n');
  $cmdEvent->Add('type', 'demande_complement', 's');
  $cmdEvent->Add('acteur_role', 'annonceur', 's');
  $cmdEvent->Add('acteur_id', $annonceurId, 'n');
  $cmdEvent->Add('meta', $meta, 's');

  $queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
  $db->sql_query($queryEvent);

  echo json_encode(['success'=>true,'message_id'=>$mid]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('candidature_demande_complement.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne']);
}
