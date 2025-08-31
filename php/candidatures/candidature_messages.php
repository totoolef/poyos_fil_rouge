<?php
include '../config/config_principal.php';
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Utilisez GET']); exit; }

  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Non autorisé']); exit; }

  $role = $decoded->role ?? '';
  if (!in_array($role, ['annonceur','conducteur'], true)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Rôle refusé']); exit; }

  $candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
  $after = $_GET['after'] ?? null;
  if ($candidatureId <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'candidature_id manquant']); exit; }

  // ACL
  if ($role === 'annonceur') {
    $sql = "SELECT a.annonceur_id FROM candidatures c JOIN annonces a ON a.id=c.annonce_id WHERE c.id=$1";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
    $row = $res ? pg_fetch_assoc($res) : null;
    if (!$row || (int)$row['annonceur_id'] !== (int)$decoded->sub) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }
  } else {
    $sql = "SELECT conducteur_id FROM candidatures WHERE id=$1";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
    $row = $res ? pg_fetch_assoc($res) : null;
    if (!$row || (int)$row['conducteur_id'] !== (int)$decoded->sub) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }
  }

  // Lecture
  if ($after) {
    $sql = "SELECT id, expediteur_role::text AS role, expediteur_id AS user_id, contenu, pieces_jointes, created_at
              FROM candidature_messages
             WHERE candidature_id=$1 AND created_at > $2
          ORDER BY created_at ASC";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId, $after]);
  } else {
    $sql = "SELECT id, expediteur_role::text AS role, expediteur_id AS user_id, contenu, pieces_jointes, created_at
              FROM candidature_messages
             WHERE candidature_id=$1
          ORDER BY created_at ASC";
    $res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
  }
  if ($res === false) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)]); exit; }

  $msgs = [];
  while ($r = pg_fetch_assoc($res)) {
    $msgs[] = [
      'id' => (int)$r['id'],
      'role' => $r['role'],
      'user_id' => (int)$r['user_id'],
      'contenu' => $r['contenu'],
      'pieces_jointes' => $r['pieces_jointes'] ? json_decode($r['pieces_jointes'], true) : null,
      'created_at' => $r['created_at'],
    ];
  }

  echo json_encode(['success'=>true,'messages'=>$msgs]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('candidature_messages.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne']);
}
