<?php
include '../config/config_principal.php';

/* Checks */
if (!isset($db) || !is_object($db) || empty($db->dbLink)) out(['success'=>false,'message'=>'DB non initialisée'], 500);

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);

$payload = validateJWT($token);
if (!$payload || empty($payload->sub)) out(['success'=>false,'message'=>'Token invalide'], 401);
$userId = (int)$payload->sub;

/* Inputs */
$type = $_POST['type'] ?? '';
$validTypes = ['permis','carte_grise','assurance','controle_technique','photos_vehicule'];
if (!in_array($type, $validTypes, true)) out(['success'=>false,'message'=>'Type invalide'], 400);

/* FS paths */
$UPLOAD_DIR = __DIR__ . '/../public/uploads/documents';
$PUBLIC_BASE = '/uploads/documents';
if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0775, true) && !is_dir($UPLOAD_DIR)) {
  out(['success'=>false,'message'=>'Impossible de créer le dossier uploads'], 500);
}

/* Photos multiples */
if ($type === 'photos_vehicule') {
  if (empty($_FILES['files'])) out(['success'=>false,'message'=>'files[] manquant'], 400);
  $count = count($_FILES['files']['name']);
  $urls  = [];
  for ($i=0; $i<$count; $i++) {
    $tmp  = $_FILES['files']['tmp_name'][$i] ?? null;
    $name = $_FILES['files']['name'][$i] ?? 'photo.jpg';
    if (!$tmp || !is_uploaded_file($tmp)) continue;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

    $fname = 'u'.$userId.'_veh_'.time().'_'.$i.'.'.$ext;
    if (!move_uploaded_file($tmp, $UPLOAD_DIR . '/' . $fname)) {
      out(['success'=>false,'message'=>'Échec d\'enregistrement du fichier'], 500);
    }
    $url = $PUBLIC_BASE . '/' . $fname;
    $urls[] = $url;

    // Insérer avec sqlCmd
    $cmd = new sqlCmd();
    $cmd->Add('user_id', $userId, 'n');
    $cmd->Add('file_url', $url, 's');

    $query = $cmd->MakeInsertQuery('documents_photos_vehicule');
    $res = $db->sql_query($query);
    if ($res === false) dberr($db->dbLink);
  }
  out(['success'=>true,'urls'=>$urls]);
}

/* Documents simples */
if (empty($_FILES['file'])) out(['success'=>false,'message'=>'file manquant'], 400);
$f = $_FILES['file'];
if (!is_uploaded_file($f['tmp_name'])) out(['success'=>false,'message'=>'upload invalide'], 400);

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','pdf','webp'])) out(['success'=>false,'message'=>'Extension non autorisée'], 400);

$fname = 'u'.$userId.'_'.$type.'_'.time().'.'.$ext;
if (!move_uploaded_file($f['tmp_name'], $UPLOAD_DIR . '/' . $fname)) {
  out(['success'=>false,'message'=>'Échec d\'enregistrement du fichier'], 500);
}
$url = $PUBLIC_BASE . '/' . $fname;

$expires_at = isset($_POST['expires_at']) && $_POST['expires_at'] !== '' ? $_POST['expires_at'] : null;

// Utiliser sqlCmd pour l'insertion avec ON CONFLICT
$cmd = new sqlCmd();
$cmd->Add('user_id', $userId, 'n');
$cmd->Add('type', $type, 's');
$cmd->Add('file_url', $url, 's');
$cmd->Add('expires_at', $expires_at, 'd');

$query = $cmd->MakeInsertQuery('documents_conducteur') . " ON CONFLICT (user_id, type) DO UPDATE SET file_url = EXCLUDED.file_url, expires_at = EXCLUDED.expires_at, updated_at = CURRENT_TIMESTAMP RETURNING id, file_url, expires_at";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$row = pg_fetch_assoc($res);

out(['success'=>true,'document'=>[
  'id'         => (int)$row['id'],
  'url'        => $row['file_url'],
  'expires_at' => $row['expires_at'],
]]);
