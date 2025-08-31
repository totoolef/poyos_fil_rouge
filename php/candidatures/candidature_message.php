<?php
include '../config/config_principal.php';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Utilisez POST']); exit; }

  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Non autorisé']); exit; }

  $role = $decoded->role ?? '';
  if (!in_array($role, ['annonceur','conducteur'], true)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Rôle refusé']); exit; }
  $userId = (int)$decoded->sub;

  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $candidatureId = (int)($body['candidature_id'] ?? 0);
  $message = trim((string)($body['message'] ?? ''));

  if ($candidatureId <= 0 || $message === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Paramètres invalides']); exit; }

  // ACL selon rôle
  if ($role === 'annonceur') {
    $sql = "SELECT a.annonceur_id FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
    $row = $res ? pg_fetch_assoc($res) : null;
    if (!$row || (int)$row['annonceur_id'] !== $userId) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }
  } else { // conducteur
    $sql = "SELECT conducteur_id FROM candidatures WHERE id=$1";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
    $row = $res ? pg_fetch_assoc($res) : null;
    if (!$row || (int)$row['conducteur_id'] !== $userId) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }
  }

  // Insert message avec sqlCmd
  $cmd = new sqlCmd();
  $cmd->Add('candidature_id', $candidatureId, 'n');
  $cmd->Add('expediteur_role', $role, 's');
  $cmd->Add('expediteur_id', $userId, 'n');
  $cmd->Add('contenu', $message, 's');

  $query = $cmd->MakeInsertQuery('candidature_messages') . " RETURNING id";
  $res = $db->sql_query($query);
  if ($res === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur SQL message: '.$db->sql_error()]); exit; }
  $mid = (int)(pg_fetch_assoc($res)['id'] ?? 0);

  // Insert event avec sqlCmd
  $meta = json_encode(['message_preview'=>mb_substr($message,0,80)], JSON_UNESCAPED_UNICODE);
  $cmdEvent = new sqlCmd();
  $cmdEvent->Add('candidature_id', $candidatureId, 'n');
  $cmdEvent->Add('type', 'message', 's');
  $cmdEvent->Add('acteur_role', $role, 's');
  $cmdEvent->Add('acteur_id', $userId, 'n');
  $cmdEvent->Add('meta', $meta, 's');

  $queryEvent = $cmdEvent->MakeInsertQuery('candidature_events');
  $db->sql_query($queryEvent);

  echo json_encode(['success'=>true,'message_id'=>$mid]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('candidature_message.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne']);
}
