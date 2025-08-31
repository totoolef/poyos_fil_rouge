<?php
include '../config/config_principal.php';

header('Content-Type: application/json; charset=utf-8');

/* 1) Méthode */
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  out(['success'=>false,'message'=>'Méthode non autorisée'], 405);
}

/* 2) Auth JWT (rôle annonceur) */
$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);

$decoded = validateJWT($token);
if (!$decoded || ($decoded->role ?? '') !== 'annonceur') {
  out(['success'=>false,'message'=>'Token invalide ou rôle non autorisé'], 401);
}
$annonceurId = (int)($decoded->sub ?? 0);
if ($annonceurId <= 0) out(['success'=>false,'message'=>'Utilisateur non identifié'], 401);

/* 3) Récup champs */
$candidatureId = isset($_POST['candidature_id']) ? (int)$_POST['candidature_id'] : 0;
if ($candidatureId <= 0) out(['success'=>false,'message'=>'candidature_id requis'], 400);

/* Champs fonctionnels (texte) */
$contraintes  = trim($_POST['contraintes'] ?? '');
$commentaires = trim($_POST['commentaires'] ?? '');
/* zones[] peut venir en array ou en string CSV */
if (isset($_POST['zones']) && is_array($_POST['zones'])) {
  $zones = $_POST['zones'];
} elseif (!empty($_POST['zones'])) {
  $zones = array_map('trim', explode(',', $_POST['zones']));
} else {
  $zones = [];
}

/* champs_json si fourni directement (prioritaire) */
$champsJsonRaw = $_POST['champs_json'] ?? null;
if ($champsJsonRaw) {
  json_decode($champsJsonRaw);
  if (json_last_error() !== JSON_ERROR_NONE) out(['success'=>false,'message'=>'champs_json invalide (JSON)'], 400);
  $champsJson = $champsJsonRaw;
} else {
  $payload = [
    'contraintes'  => $contraintes,
    'zones'        => array_values(array_filter($zones, fn($z) => $z !== '')),
    'commentaires' => $commentaires
  ];
  $champsJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/* 4) Vérifier que la candidature appartient à l'annonceur */
$sql = "
  SELECT c.conducteur_id, c.annonce_id, a.annonceur_id
  FROM candidatures c
  JOIN annonces a ON a.id = c.annonce_id
  WHERE c.id = $1
  LIMIT 1
";
$res = pg_query_params($db->dbLink, $sql, [$candidatureId]);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);
if (!$row) out(['success'=>false,'message'=>'Candidature introuvable'], 404);
if ((int)$row['annonceur_id'] !== $annonceurId) out(['success'=>false,'message'=>'Accès non autorisé à cette candidature'], 403);

$annonceId    = (int)$row['annonce_id'];
$conducteurId = (int)$row['conducteur_id'];

/* 5) Créer le brief avec sqlCmd */
$cmd = new sqlCmd();
$cmd->Add('annonce_id', $annonceId, 'n');
$cmd->Add('conducteur_id', $conducteurId, 'n');
$cmd->Add('champs_json', $champsJson, 's');

$query = $cmd->MakeInsertQuery('design_briefs') . " RETURNING id, created_at";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$brief = pg_fetch_assoc($res);
$briefId = (int)$brief['id'];

/* 6) Upload des logos (facultatif) */
$uploadedAssets = [];
$baseDir = __DIR__ . '/uploads/briefs/' . $candidatureId;
if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }

if (!empty($_FILES['logos'])) {
  $files = $_FILES['logos'];
  $fileCount = is_array($files['name']) ? count($files['name']) : 0;

  for ($i = 0; $i < $fileCount; $i++) {
    $name = $files['name'][$i] ?? null;
    $tmp  = $files['tmp_name'][$i] ?? null;
    $err  = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    $size = $files['size'][$i] ?? 0;
    $type = $files['type'][$i] ?? '';

    if ($err !== UPLOAD_ERR_OK || !$tmp || !is_uploaded_file($tmp)) continue;

    // Validation simple
    if ($size > 10 * 1024 * 1024) continue; // 10 MB
    $allowed = ['image/png','image/jpeg','image/jpg','image/webp','image/svg+xml','application/pdf'];
    if (!in_array($type, $allowed, true)) continue;

    $safeName = preg_replace('/[^A-Za-z0-9_.-]/','_', $name ?: ('logo_'.$i));
    $dest = $baseDir . '/' . (time()) . '_' . $safeName;

    if (!move_uploaded_file($tmp, $dest)) continue;

    $publicUrl = 'uploads/briefs/' . $candidatureId . '/' . basename($dest);

    // Enregistrer dans design_assets avec sqlCmd
    $cmdAsset = new sqlCmd();
    $cmdAsset->Add('brief_id', $briefId, 'n');
    $cmdAsset->Add('type', 'brief', 's');
    $cmdAsset->Add('url', $publicUrl, 's');
    $cmdAsset->Add('version', 1, 'n');

    $queryAsset = $cmdAsset->MakeInsertQuery('design_assets') . " RETURNING id, created_at";
    $resA = $db->sql_query($queryAsset);
    if ($resA === false) dberr($db->dbLink);
    $rowA = pg_fetch_assoc($resA);

    $uploadedAssets[] = [
      'id'         => (int)$rowA['id'],
      'type'       => 'brief',
      'url'        => $publicUrl,
      'version'    => 1,
      'created_at' => $rowA['created_at'] ?? null
    ];
  }
}

/* 7) Réponse */
out([
  'success' => true,
  'message' => 'Brief déposé',
  'data' => [
    'brief' => [
      'id'             => $briefId,
      'annonce_id'     => $annonceId,
      'conducteur_id'  => $conducteurId,
      'candidature_id' => $candidatureId,
      'created_at'     => $brief['created_at'] ?? null,
    ],
    'assets' => $uploadedAssets
  ]
], 200);
