<?php
include '../config/config_principal.php';

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Méthode non autorisée. Utilisez GET.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Auth (conducteur)
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded || (($decoded->role ?? '') !== 'conducteur')) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Token invalide ou rôle non autorisé'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
  $conducteurId = (int)$decoded->sub;

  if (!isset($db, $db->dbLink) || !$db->dbLink) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Connexion DB indisponible'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Liste des candidatures du conducteur, jointes à l'annonce
  $sql = "
    SELECT
      c.id,
      c.annonce_id,
      c.message,
      c.statut::text AS statut,
      c.created_at,
      c.updated_at,
      a.titre AS titre_annonce,
      a.localisation AS localisation
    FROM candidatures c
    JOIN annonces a ON a.id = c.annonce_id
    WHERE c.conducteur_id = $1
    ORDER BY c.created_at DESC
  ";
  $res = pg_query_params($db->dbLink, $sql, [$conducteurId]);
  if ($res === false) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  $rows = [];
  while ($r = pg_fetch_assoc($res)) {
    $rows[] = [
      'id'            => (int)$r['id'],
      'annonce_id'    => (int)$r['annonce_id'],
      'titre_annonce' => $r['titre_annonce'],
      'localisation'  => $r['localisation'],
      'message'       => $r['message'] ?? '',
      'statut'        => $r['statut'],           // 'en_attente' | 'refusee' | 'annulee' | 'acceptee'
      'created_at'    => $r['created_at'],
      'updated_at'    => $r['updated_at'],
    ];
  }

  echo json_encode(['success'=>true,'candidatures'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('get_candidatures_conducteur.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
