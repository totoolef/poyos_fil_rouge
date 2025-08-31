<?php
include '../config/config_principal.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['success'=>false,'message'=>'Méthode non autorisée'],405);
$tok=bearer(); if(!$tok) out(['success'=>false,'message'=>'Token manquant'],401);
$dec=validateJWT($tok); if(!$dec || ($dec->role??'')!=='annonceur') out(['success'=>false,'message'=>'Rôle annonceur requis'],403);
$userId=(int)($dec->sub??0); if($userId<=0) out(['success'=>false,'message'=>'Utilisateur non identifié'],401);

$body=json_decode(file_get_contents('php://input'),true);
$candidatureId=(int)($body['candidature_id']??0);
if($candidatureId<=0) out(['success'=>false,'message'=>'candidature_id requis'],400);

/* Vérifier accès annonceur */
$sql = "SELECT c.id, c.annonce_id, c.conducteur_id, a.annonceur_id
        FROM candidatures c JOIN annonces a ON a.id=c.annonce_id
        WHERE c.id=$1 LIMIT 1";
$res=pg_query_params($db->dbLink,$sql,[$candidatureId]); if($res===false) dberr($db->dbLink);
$row=pg_fetch_assoc($res); if(!$row) out(['success'=>false,'message'=>'Candidature introuvable'],404);
if((int)$row['annonceur_id']!==$userId) out(['success'=>false,'message'=>'Accès non autorisé'],403);

$annonceId=(int)$row['annonce_id']; $conducteurId=(int)$row['conducteur_id'];

/* Contrat existant ? */
$chk=pg_query_params($db->dbLink,"SELECT * FROM contrats WHERE annonce_id=$1 AND conducteur_id=$2 ORDER BY id DESC LIMIT 1",[$annonceId,$conducteurId]);
if($chk===false) dberr($db->dbLink);
$exist=pg_fetch_assoc($chk);
if($exist){
  out(['success'=>true,'message'=>'Contrat déjà existant','data'=>$exist],200);
}

/* Construire contenu simple (tu le remplaceras par ton vrai gabarit) */
$contenu = "Contrat Poyos\n\nAnnonce: #$annonceId\nConducteur: #$conducteurId\nCandidature: #$candidatureId\n\n"
         . "Objet: Publicité adhésive sur véhicule\nDurée: selon annonce\nRémunération: selon annonce\n\n"
         . "Conditions générales: ...";

/* Enregistrer d'abord le contrat avec sqlCmd */
$cmd = new sqlCmd();
$cmd->Add('annonce_id', $annonceId, 'n');
$cmd->Add('conducteur_id', $conducteurId, 'n');
$cmd->Add('contenu_contrat', $contenu, 's');
$cmd->Add('statut', 'actif', 's');
$cmd->Add('created_at', date('Y-m-d H:i:s'), 'd');
$cmd->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$query = $cmd->MakeInsertQuery('contrats') . " RETURNING id, created_at";
$res = $db->sql_query($query);
if ($res === false) dberr($db->dbLink);
$contrat = pg_fetch_assoc($res);
$contratId = (int)$contrat['id'];

/* Tenter PDF via dompdf si dispo */
$uploadsDir = __DIR__.'/uploads/contrats';
@mkdir($uploadsDir,0775,true);
$pdfPath = "$uploadsDir/contrat_$contratId.pdf";
$pdfUrl  = "uploads/contrats/contrat_$contratId.pdf";

$madePdf=false;
if (class_exists('\Dompdf\Dompdf')) {
  $html = nl2br(htmlentities($contenu,ENT_QUOTES,'UTF-8'));
  $html = "<html><head><meta charset='utf-8'><title>Contrat Poyos</title></head><body><h3>Contrat Poyos</h3><div style='white-space:pre-wrap;font-family:Arial,sans-serif;font-size:12px;'>$html</div></body></html>";
  try {
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html); $dompdf->setPaper('A4','portrait'); $dompdf->render();
    file_put_contents($pdfPath, $dompdf->output());
    $madePdf=true;
  } catch (\Throwable $e) { $madePdf=false; }
}

/* Fallback: HTML si pas de dompdf */
if(!$madePdf){
  $htmlPath = "$uploadsDir/contrat_$contratId.html";
  $htmlUrl  = "uploads/contrats/contrat_$contratId.html";
  $htmlBody = "<html><head><meta charset='utf-8'><title>Contrat Poyos</title></head><body><pre style='font-family:Arial,sans-serif;font-size:12px;'>".htmlentities($contenu,ENT_QUOTES,'UTF-8')."</pre></body></html>";
  file_put_contents($htmlPath,$htmlBody);
  $pdfUrl = $htmlUrl;
}

/* Sauver l'URL doc avec sqlCmd */
$cmdUpdate = new sqlCmd();
$cmdUpdate->Add('contrat_pdf_url', $pdfUrl, 's');
$cmdUpdate->Add('updated_at', date('Y-m-d H:i:s'), 'd');

$queryUpdate = $cmdUpdate->MakeUpdateQuery('contrats', "id=$contratId") . " RETURNING *";
$res = $db->sql_query($queryUpdate);
if ($res === false) dberr($db->dbLink);
$full = pg_fetch_assoc($res);

out(['success'=>true,'message'=>'Contrat créé','data'=>$full],200);
