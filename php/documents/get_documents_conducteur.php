<?php
include '../config/config_principal.php';

/* Checks */
if (!isset($db) || !is_object($db) || empty($db->dbLink)) out(['success'=>false,'message'=>'DB non initialisée'], 500);

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);

$payload = validateJWT($token);
if (!$payload || empty($payload->sub)) out(['success'=>false,'message'=>'Token invalide'], 401);
$userId = (int)$payload->sub;

/* Documents “simples” */
$sql = "SELECT id, type::text AS type, file_url, expires_at
        FROM documents_conducteur
        WHERE user_id = $1";
$res = pg_query_params($db->dbLink, $sql, [$userId]);
if ($res === false) dberr($db->dbLink);

$byType = ['permis'=>null,'carte_grise'=>null,'assurance'=>null,'controle_technique'=>null];
while ($row = pg_fetch_assoc($res)) {
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

/* Photos véhicule (multiple) -> items [{id,url}] */
$sql2 = "SELECT id, file_url FROM documents_photos_vehicule WHERE user_id=$1 ORDER BY id ASC";
$res2 = pg_query_params($db->dbLink, $sql2, [$userId]);
if ($res2 === false) dberr($db->dbLink);

$photoItems = [];
while ($r = pg_fetch_assoc($res2)) {
  $photoItems[] = ['id' => (int)$r['id'], 'url' => $r['file_url']];
}

/* Payload */
$documents = [
  'permis'             => $byType['permis'],
  'carte_grise'        => $byType['carte_grise'],
  'assurance'          => $byType['assurance'],
  'controle_technique' => $byType['controle_technique'],
  'photos_vehicule'    => ['items' => $photoItems],
];

$ok = (
  ($documents['permis']['status'] ?? 'manquant') === 'ok' &&
  ($documents['carte_grise']['status'] ?? 'manquant') === 'ok' &&
  ($documents['assurance']['status'] ?? 'manquant') === 'ok' &&
  count($documents['photos_vehicule']['items'] ?? []) > 0
);

out(['success'=>true,'documents'=>$documents,'resume'=>['ok'=>$ok]]);