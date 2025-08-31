<?php
include '../config/config_principal.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    out(['success'=>false,'message'=>'Méthode non autorisée. Utilisez GET.'], 405);
  }

  // Auth
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = preg_replace('/^Bearer\s+/i', '', $auth);
  $decoded = validateJWT($token);
  if (!$decoded || ($decoded->role ?? '') !== 'annonceur') {
    out(['success'=>false,'message'=>'Token invalide ou rôle non autorisé'], 401);
  }
  $annonceurId = (int)$decoded->sub;

  if (!$db || !$db->dbLink) {
    out(['success'=>false,'message'=>'Connexion DB indisponible'], 500);
  }

  $candidatureId = isset($_GET['candidature_id']) ? (int)$_GET['candidature_id'] : 0;
  if ($candidatureId <= 0) out(['success'=>false,'message'=>'candidature_id manquant'], 400);

  // Vérif d’accès: la candidature doit appartenir à une annonce de cet annonceur
  $sql = "
    SELECT c.id, c.annonce_id, c.conducteur_id, c.message, c.statut::text AS statut,
           COALESCE(c.marque_voiture,'') AS marque_voiture,
           COALESCE(c.modele_voiture,'') AS modele_voiture,
           COALESCE(c.couleur,'') AS couleur,
           a.titre AS annonce_titre, a.type_pub::text AS type_pub, a.paiement_mensuel, a.duree_mois,
           u.nom, u.prenom, u.email, u.adresse, u.plaque_immatriculation
    FROM candidatures c
    JOIN annonces a ON a.id = c.annonce_id
    JOIN utilisateurs u ON u.id = c.conducteur_id
    WHERE c.id = $1 AND a.annonceur_id = $2
    LIMIT 1
  ";
  $res = pg_query_params($db->dbLink, $sql, [$candidatureId, $annonceurId]);
  if ($res === false) out(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)], 500);
  $cand = pg_fetch_assoc($res);
  if (!$cand) out(['success'=>false,'message'=>'Candidature introuvable ou non autorisée'], 404);

  $conducteurId = (int)$cand['conducteur_id'];

  // Documents simples
  $sqlDocs = "
    SELECT id, type::text AS type, file_url, expires_at
    FROM documents_conducteur
    WHERE user_id = $1
  ";
  $rd = pg_query_params($db->dbLink, $sqlDocs, [$conducteurId]);
  if ($rd === false) out(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)], 500);

  $byType = ['permis'=>null,'carte_grise'=>null,'assurance'=>null,'controle_technique'=>null];
  while ($row = pg_fetch_assoc($rd)) {
    $status = empty($row['file_url']) ? 'manquant' : 'ok';
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < strtotime('today')) $status = 'expire';
    $byType[$row['type']] = [
      'id'         => (int)$row['id'],
      'url'        => $row['file_url'],
      'status'     => $status,
      'expires_at' => $row['expires_at'] ?: null,
    ];
  }
  foreach ($byType as $k=>$v) if ($v === null) $byType[$k] = ['status'=>'manquant'];

  // Photos véhicule -> items [{id,url}]
  $sqlPhotos = "SELECT id, file_url FROM documents_photos_vehicule WHERE user_id=$1 ORDER BY id ASC";
  $rp = pg_query_params($db->dbLink, $sqlPhotos, [$conducteurId]);
  if ($rp === false) out(['success'=>false,'message'=>'Erreur SQL: '.pg_last_error($db->dbLink)], 500);

  $photoItems = [];
  while ($r = pg_fetch_assoc($rp)) $photoItems[] = ['id'=>(int)$r['id'], 'url'=>$r['file_url']];

  $documents = [
    'permis'             => $byType['permis'],
    'carte_grise'        => $byType['carte_grise'],
    'assurance'          => $byType['assurance'],
    'controle_technique' => $byType['controle_technique'],
    'photos_vehicule'    => ['items'=>$photoItems],
  ];

  $ok = (
    ($documents['permis']['status'] ?? 'manquant') === 'ok' &&
    ($documents['carte_grise']['status'] ?? 'manquant') === 'ok' &&
    ($documents['assurance']['status'] ?? 'manquant') === 'ok' &&
    ($documents['controle_technique']['status'] ?? 'manquant') === 'ok' &&
    count($documents['photos_vehicule']['items']) > 0
  );

  out([
    'success' => true,
    'candidature' => [
      'id' => (int)$cand['id'],
      'statut' => $cand['statut'],
      'message' => $cand['message'],
      'vehicule' => [
        'marque' => $cand['marque_voiture'],
        'modele' => $cand['modele_voiture'],
        'couleur'=> $cand['couleur'],
        'plaque' => $cand['plaque_immatriculation']
      ],
      'annonce' => [
        'id' => (int)$cand['annonce_id'],
        'titre' => $cand['annonce_titre'],
        'type_pub' => $cand['type_pub'],
        'paiement_mensuel' => $cand['paiement_mensuel'],
        'duree_mois' => (int)$cand['duree_mois']
      ],
      'conducteur' => [
        'id' => $conducteurId,
        'nom' => $cand['nom'],
        'prenom' => $cand['prenom'],
        'email' => $cand['email'],
        'adresse' => $cand['adresse']
      ],
      'preflight' => ['ok' => $ok]
    ],
    'documents' => $documents
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log('get_dossier_candidature.php: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Erreur interne']);
}
