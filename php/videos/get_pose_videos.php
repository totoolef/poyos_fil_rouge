<?php
include '../config/config_principal.php';

if ($_SERVER['REQUEST_METHOD']!=='GET') out(['success'=>false,'message'=>'Méthode non autorisée'],405);

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'],401);
$jwt = validateJWT($token);
if (!$jwt) out(['success'=>false,'message'=>'Token invalide'],403);

$candidatureId = $_GET['candidature_id'] ?? null;
if(!$candidatureId) out(['success'=>false,'message'=>'candidature_id requis'],400);

$q = "SELECT id,url,statut,created_at FROM pose_videos WHERE candidature_id=$1 ORDER BY created_at DESC";
$r = pg_query_params($db->dbLink,$q,[$candidatureId]);
if(!$r) out(['success'=>false,'message'=>pg_last_error($db->dbLink)],500);

$data = [];
while($row=pg_fetch_assoc($r)){ $data[]=$row; }

out(['success'=>true,'data'=>$data]);
