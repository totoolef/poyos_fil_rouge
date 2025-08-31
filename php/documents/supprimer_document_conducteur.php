<?php
include '../config/config_principal.php';

if (!isset($db) || !is_object($db) || empty($db->dbLink)) out(['success'=>false,'message'=>'DB non initialisée'], 500);

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);

$payload = validateJWT($token);
if (!$payload || empty($payload->sub)) out(['success'=>false,'message'=>'Token invalide'], 401);
$userId = (int)$payload->sub;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) out(['success'=>false,'message'=>'ID manquant'], 400);

/* Essayez documents_conducteur */
$sql = "DELETE FROM documents_conducteur WHERE id=$1 AND user_id=$2 RETURNING id";
$res = pg_query_params($db->dbLink, $sql, [$id, $userId]);
if ($res === false) dberr($db->dbLink);
if (pg_fetch_assoc($res)) out(['success'=>true]);

/* Sinon photos véhicule */
$sql2 = "DELETE FROM documents_photos_vehicule WHERE id=$1 AND user_id=$2 RETURNING id";
$res2 = pg_query_params($db->dbLink, $sql2, [$id, $userId]);
if ($res2 === false) dberr($db->dbLink);
if (pg_fetch_assoc($res2)) out(['success'=>true]);

out(['success'=>false,'message'=>'Document introuvable'], 404);
