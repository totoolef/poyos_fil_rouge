<?php
include "../config/config_principal.php";

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  out(['success'=>false,'message'=>'Méthode non autorisée'], 405);
}

$token = bearer();
if (!$token) out(['success'=>false,'message'=>'Token manquant'], 401);
$decoded = validateJWT($token);
if (!$decoded) out(['success'=>false,'message'=>'Token invalide'], 401);

$role = $decoded->role ?? '';
$userId = (int)($decoded->sub ?? 0);
if ($userId <= 0) out(['success'=>false,'message'=>'Utilisateur non identifié'], 401);

if ($role !== 'conducteur') out(['success'=>false,'message'=>'Rôle non autorisé'], 403);

/* Récupérer les campagnes (candidatures acceptées) avec statut de paiement */
$sql = "
  SELECT 
    c.id AS candidature_id,
    c.statut::text AS statut_candidature,
    a.id AS annonce_id,
    a.titre AS titre_annonce,
    u.nom AS nom_annonceur,
    u.prenom AS prenom_annonceur,
    u.nom_entreprise AS nom_entreprise,
    con.id AS contrat_id,
    con.statut_contrat::text AS statut_contrat,
    p.statut::text AS statut_paiement,
    p.montant_total,
    p.created_at AS paiement_created_at
  FROM candidatures c
  JOIN annonces a ON a.id = c.annonce_id
  JOIN utilisateurs u ON u.id = a.annonceur_id
  LEFT JOIN contrats con ON con.annonce_id = c.annonce_id AND con.conducteur_id = c.conducteur_id
  LEFT JOIN paiements p ON p.contrat_id = con.id
  WHERE c.conducteur_id = $1 
    AND c.statut = 'acceptee'
  ORDER BY c.created_at DESC
";

$res = pg_query_params($db->dbLink, $sql, [$userId]);
if ($res === false) dberr($db->dbLink);

$campagnes = [];
while ($row = pg_fetch_assoc($res)) {
  $campagnes[] = [
    'candidature_id' => (int)$row['candidature_id'],
    'annonce_id' => (int)$row['annonce_id'],
    'titre_annonce' => $row['titre_annonce'],
    'annonceur' => [
      'nom' => $row['nom_annonceur'],
      'prenom' => $row['prenom_annonceur'],
      'nom_entreprise' => $row['nom_entreprise']
    ],
    'contrat' => $row['contrat_id'] ? [
      'id' => (int)$row['contrat_id'],
      'statut' => $row['statut_contrat']
    ] : null,
    'paiement' => $row['statut_paiement'] ? [
      'statut' => $row['statut_paiement'],
      'montant_total' => (float)$row['montant_total'],
      'created_at' => $row['paiement_created_at']
    ] : null
  ];
}

out([
  'success' => true,
  'data' => [
    'campagnes' => $campagnes
  ]
], 200);
