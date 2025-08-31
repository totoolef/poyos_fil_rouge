# Structure PHP Réorganisée - POYOS App

## Vue d'ensemble

La structure PHP a été réorganisée en sous-dossiers explicites en français pour une meilleure organisation et maintenabilité du code.

## Structure des dossiers

```
php/
├── config/                    # Configuration et utilitaires
│   ├── config_principal.php   # Configuration principale (chemins, DB, Stripe)
│   ├── cors.php              # Configuration CORS
│   ├── jwt_utils.php         # Utilitaires JWT
│   └── sqlcmd.php            # Interface base de données
│
├── authentification/          # Gestion de l'authentification
│   ├── connexion.php         # Connexion utilisateur
│   ├── inscription_annonceur.php
│   ├── inscription_particulier.php
│   └── auth_utilisateur.php  # Validation des tokens
│
├── annonces/                 # Gestion des annonces
│   ├── creer_annonce.php
│   ├── modifier_annonce.php
│   ├── liste_annonces.php
│   ├── get_annonces_disponibles.php
│   ├── get_campagnes_annonceur.php
│   ├── get_campagnes_conducteur.php
│   └── get_suivi.php
│
├── candidatures/             # Gestion des candidatures
│   ├── candidater_annonce.php
│   ├── annuler_candidature.php
│   ├── modifier_statut_candidature.php
│   ├── get_candidatures_annonceur.php
│   ├── get_candidatures_conducteur.php
│   ├── annonce_candidatures.php
│   ├── candidature_set_statut.php
│   ├── candidature_demande_complement.php
│   ├── candidature_messages.php
│   ├── candidature_message.php
│   └── get_dossier_candidature.php
│
├── contrats/                 # Gestion des contrats et signatures
│   ├── creer_contrat.php
│   ├── get_contrat.php
│   ├── signer_contrat.php
│   ├── generer_contrat.php
│   └── envoyer_signature.php
│
├── paiements/                # Gestion des paiements Stripe
│   ├── creer_paiement.php
│   ├── get_paiement.php
│   ├── stripe_create_checkout.php
│   ├── stripe_verify_session.php
│   └── stripe_webhook.php
│
├── briefs/                   # Gestion des briefs et designs
│   ├── deposer_brief.php
│   ├── get_brief.php
│   ├── valider_bat.php
│   ├── demander_modifs_bat.php
│   ├── lister_design_assets.php
│   └── get_design_status.php
│
├── documents/                # Upload et gestion des documents
│   ├── upload_document_conducteur.php
│   ├── get_documents_conducteur.php
│   └── supprimer_document_conducteur.php
│
├── videos/                   # Gestion des vidéos de pose
│   ├── upload_pose_video.php
│   ├── get_pose_videos.php
│   ├── valider_pose_video.php
│   ├── valider_video_mensuelle.php
│   └── create_pose_videos_table.php
│
├── utilisateurs/             # Gestion des profils utilisateurs
│   ├── get_utilisateur_infos.php
│   ├── get_utilisateur_infos_conducteur.php
│   ├── modifier_parametres_annonceur.php
│   └── modifier_parametres_conducteur.php
│
├── uploads/                  # Fichiers uploadés (inchangé)
├── vendor/                   # Dépendances Composer (inchangé)
└── .htaccess                # Configuration Apache (inchangé)
```

## Configuration principale

Le fichier `config/config_principal.php` centralise :
- Les chemins vers tous les dossiers
- La configuration de la base de données
- La configuration Stripe
- L'inclusion des fichiers de base (CORS, JWT, SQL)

## Utilisation

Tous les fichiers PHP incluent maintenant :
```php
<?php
include '../config/config_principal.php';
```

## Mise à jour du front-end

Les chemins dans le front-end (fichiers Vue.js) ont été automatiquement mis à jour pour pointer vers les nouveaux emplacements des fichiers PHP.

**Mise à jour complète effectuée :**
- ✅ 30 fichiers Vue.js traités
- ✅ 23 fichiers mis à jour
- ✅ 68 remplacements de chemins effectués
- ✅ Tous les sous-dossiers inclus (pages/, components/, dialog/)
- ✅ Chemins mis à jour dans tous les appels API

## Avantages de cette organisation

1. **Clarté** : Chaque dossier a une responsabilité spécifique
2. **Maintenabilité** : Plus facile de trouver et modifier les fichiers
3. **Évolutivité** : Facile d'ajouter de nouveaux fichiers dans les bons dossiers
4. **Séparation des préoccupations** : Logique métier bien séparée
5. **Configuration centralisée** : Un seul point de configuration

## Migration effectuée

✅ Réorganisation des fichiers en sous-dossiers  
✅ Mise à jour des includes dans tous les fichiers PHP  
✅ Mise à jour des chemins dans le front-end (30 fichiers, 68 remplacements)  
✅ Configuration centralisée  
✅ Standardisation avec `include` (28 fichiers, 33 remplacements)  
✅ Correction des chemins dupliqués (4 fichiers, 4 corrections)  
✅ Correction des chemins vendor/autoload.php (2 fichiers)  
✅ Correction des chemins config.php (26 fichiers, 27 corrections)  
✅ Correction des includes dans tous les fichiers PHP (16 fichiers)  
✅ Correction du chemin dans utilisateurStores.ts  
✅ Correction finale des includes dans documents/, briefs/, candidatures/ (5 fichiers)  
✅ Vérification complète manuelle de tous les fichiers PHP (50+ fichiers)  
✅ Correction des fichiers restants : paiements/stripe_webhook.php, utilisateurs/modifier_parametres_*.php, videos/create_pose_videos_table.php  
✅ Correction du store utilisateur pour gérer les conducteurs et annonceurs  
✅ Création d'utilisateurs de test pour validation  
✅ Correction du problème de page blanche (candidatures-acceptees.vue)  
✅ Ajout de logs de debug et vérification d'authentification  
✅ Documentation de la nouvelle structure
