<?php
include '../config/config_principal.php';

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Méthode non autorisée. Utilisez GET.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Auth
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded || (($decoded->role ?? '') !== 'annonceur')) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Token invalide ou rôle non autorisé'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
  $annonceurId = (int)$decoded->sub;

  if (!isset($db, $db->dbLink) || !$db->dbLink) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Connexion DB indisponible'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  /**
   * Réponse attendue :
   * [
   *   { id, statut, annonce:{id,titre}, conducteur:{id,nom,prenom},
   *     vehicule:{marque,modele,couleur}, preflight:{ok}, photos_count }
   * ]
   *
   * Règle preflight.ok :
   * - 4 docs requis valides (permis, carte_grise, assurance, controle_technique)
   *   => file_url non NULL + expires_at NULL ou >= today
   * - ET au moins 1 photo véhicule.
   */
  $sql = "
    SELECT
      c.id,
      c.statut::text AS statut,
      a.id AS annonce_id,
      a.titre AS annonce_titre,
      u.id AS conducteur_id,
      u.nom AS conducteur_nom,
      u.prenom AS conducteur_prenom,
      COALESCE(c.marque_voiture,'') AS marque_voiture,
      COALESCE(c.modele_voiture,'') AS modele_voiture,
      COALESCE(c.couleur,'') AS couleur,
      (SELECT COUNT(*)::int
         FROM documents_photos_vehicule dpv
        WHERE dpv.user_id = c.conducteur_id
      ) AS photos_count,
      (SELECT COUNT(DISTINCT type)
         FROM documents_conducteur dc
        WHERE dc.user_id = c.conducteur_id
          AND dc.type IN ('permis','carte_grise','assurance','controle_technique')
          AND dc.file_url IS NOT NULL
          AND (dc.expires_at IS NULL OR dc.expires_at >= CURRENT_DATE)
      ) AS docs_valid_count
    FROM candidatures c
    JOIN annonces a ON a.id = c.annonce_id
    JOIN utilisateurs u ON u.id = c.conducteur_id
    WHERE a.annonceur_id = $1
    ORDER BY c.created_at DESC
  ";

  $res = pg_query_params($db->dbLink, $sql, [$annonceurId]);
  if ($res === false) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  $rows = [];
  while ($r = pg_fetch_assoc($res)) {
    $photos_count = (int)$r['photos_count'];
    $docs_valid_count = (int)$r['docs_valid_count'];
    $preflight_ok = ($docs_valid_count >= 4) && ($photos_count > 0);

    $rows[] = [
      'id' => (int)$r['id'],
      'statut' => $r['statut'], // 'en_attente' | 'acceptee' | 'refusee'
      'annonce' => [
        'id' => (int)$r['annonce_id'],
        'titre' => $r['annonce_titre'],
      ],
      'conducteur' => [
        'id' => (int)$r['conducteur_id'],
        'nom' => $r['conducteur_nom'],
        'prenom' => $r['conducteur_prenom'],
      ],
      'vehicule' => [
        'marque' => $r['marque_voiture'],
        'modele' => $r['modele_voiture'],
        'couleur'=> $r['couleur'],
      ],
      'preflight' => [ 'ok' => $preflight_ok ],
      'photos_count' => $photos_count,
    ];
  }

  echo json_encode(['success'=>true,'candidatures'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('annonce_candidatures.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
