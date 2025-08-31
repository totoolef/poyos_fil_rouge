<?php
// Script pour mettre à jour les chemins PHP dans le front-end

$vueFiles = glob('../../src/**/*.vue');

$pathMappings = [
    // Authentification
    'connexion.php' => 'authentification/connexion.php',
    'inscription_annonceur.php' => 'authentification/inscription_annonceur.php',
    'inscription_particulier.php' => 'authentification/inscription_particulier.php',
    
    // Annonces
    'creer_annonce.php' => 'annonces/creer_annonce.php',
    'modifier_annonce.php' => 'annonces/modifier_annonce.php',
    'liste_annonces.php' => 'annonces/liste_annonces.php',
    'get_annonces_disponibles.php' => 'annonces/get_annonces_disponibles.php',
    'get_campagnes_annonceur.php' => 'annonces/get_campagnes_annonceur.php',
    'get_campagnes_conducteur.php' => 'annonces/get_campagnes_conducteur.php',
    'get_suivi.php' => 'annonces/get_suivi.php',
    
    // Candidatures
    'candidater_annonce.php' => 'candidatures/candidater_annonce.php',
    'annuler_candidature.php' => 'candidatures/annuler_candidature.php',
    'modifier_statut_candidature.php' => 'candidatures/modifier_statut_candidature.php',
    'get_candidatures_annonceur.php' => 'candidatures/get_candidatures_annonceur.php',
    'get_candidatures_conducteur.php' => 'candidatures/get_candidatures_conducteur.php',
    'annonce_candidatures.php' => 'candidatures/annonce_candidatures.php',
    'candidature_set_statut.php' => 'candidatures/candidature_set_statut.php',
    'candidature_demande_complement.php' => 'candidatures/candidature_demande_complement.php',
    'candidature_messages.php' => 'candidatures/candidature_messages.php',
    'candidature_message.php' => 'candidatures/candidature_message.php',
    'get_dossier_candidature.php' => 'candidatures/get_dossier_candidature.php',
    
    // Contrats
    'creer_contrat.php' => 'contrats/creer_contrat.php',
    'get_contrat.php' => 'contrats/get_contrat.php',
    'signer_contrat.php' => 'contrats/signer_contrat.php',
    'generer_contrat.php' => 'contrats/generer_contrat.php',
    
    // Paiements
    'creer_paiement.php' => 'paiements/creer_paiement.php',
    'get_paiement.php' => 'paiements/get_paiement.php',
    'stripe_create_checkout.php' => 'paiements/stripe_create_checkout.php',
    'stripe_verify_session.php' => 'paiements/stripe_verify_session.php',
    
    // Briefs
    'deposer_brief.php' => 'briefs/deposer_brief.php',
    'get_brief.php' => 'briefs/get_brief.php',
    'valider_bat.php' => 'briefs/valider_bat.php',
    'demander_modifs_bat.php' => 'briefs/demander_modifs_bat.php',
    'lister_design_assets.php' => 'briefs/lister_design_assets.php',
    'get_design_status.php' => 'briefs/get_design_status.php',
    
    // Documents
    'get_documents_conducteur.php' => 'documents/get_documents_conducteur.php',
    'upload_document_conducteur.php' => 'documents/upload_document_conducteur.php',
    'supprimer_document_conducteur.php' => 'documents/supprimer_document_conducteur.php',
    
    // Vidéos
    'get_pose_videos.php' => 'videos/get_pose_videos.php',
    'upload_pose_video.php' => 'videos/upload_pose_video.php',
    'valider_pose_video.php' => 'videos/valider_pose_video.php',
    'valider_video_mensuelle.php' => 'videos/valider_video_mensuelle.php',
    
    // Utilisateurs
    'get_utilisateur_infos.php' => 'utilisateurs/get_utilisateur_infos.php',
    'get_utilisateur_infos_conducteur.php' => 'utilisateurs/get_utilisateur_infos_conducteur.php',
    'modifier_parametres_annonceur.php' => 'utilisateurs/modifier_parametres_annonceur.php',
    'modifier_parametres_conducteur.php' => 'utilisateurs/modifier_parametres_conducteur.php'
];

foreach ($vueFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    foreach ($pathMappings as $oldPath => $newPath) {
        $content = str_replace($oldPath, $newPath, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "Mis à jour: $file\n";
    }
}

echo "Mise à jour des chemins front-end terminée!\n";
?>
