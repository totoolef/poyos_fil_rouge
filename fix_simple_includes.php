<?php
// Script simple pour corriger les includes

$files = [
    'contrats/get_contrat.php',
    'contrats/creer_contrat.php',
    'contrats/signer_contrat.php',
    'videos/valider_pose_video.php',
    'videos/valider_video_mensuelle.php',
    'videos/upload_pose_video.php',
    'briefs/lister_design_assets.php',
    'briefs/valider_bat.php',
    'briefs/demander_modifs_bat.php',
    'briefs/deposer_brief.php',
    'briefs/get_brief.php',
    'briefs/get_design_status.php',
    'candidatures/get_dossier_candidature.php',
    'candidatures/annonce_candidatures.php',
    'documents/upload_document_conducteur.php',
    'documents/supprimer_document_conducteur.php',
    'documents/get_documents_conducteur.php',
    'paiements/stripe_verify_session.php',
    'paiements/get_paiement.php',
    'paiements/creer_paiement.php',
    'paiements/stripe_webhook.php',
    'paiements/stripe_create_checkout.php'
];

$updatedFiles = 0;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Remplacer les anciens includes par le nouveau
        $newContent = preg_replace(
            '/^<\?php\s*include\s*\(\s*[\'"]cors\.php[\'"]\s*\);\s*include\s*\(\s*[\'"]\.\.\/config\/config\.php[\'"]\s*\);\s*include\s*\(\s*[\'"]jwt_utils\.php[\'"]\s*\);/m',
            '<?php' . "\n" . 'include \'../config/config_principal.php\';',
            $content
        );
        
        $newContent = preg_replace(
            '/^<\?php\s*include\s*\(\s*[\'"]cors\.php[\'"]\s*\);\s*include\s*\(\s*[\'"]\.\.\/config\/config\.php[\'"]\s*\);/m',
            '<?php' . "\n" . 'include \'../config/config_principal.php\';',
            $newContent
        );
        
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            $updatedFiles++;
            echo "✅ Mis à jour: $file\n";
        }
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers mis à jour : $updatedFiles\n";
echo "Correction terminée!\n";
?>
