<?php
// Script pour corriger les anciens includes dans les fichiers PHP

echo "Recherche de fichiers avec anciens includes...\n";

$directories = [
    'candidatures',
    'contrats', 
    'paiements',
    'briefs',
    'documents',
    'videos',
    'utilisateurs'
];

$updatedFiles = 0;
$totalReplacements = 0;

foreach ($directories as $dir) {
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Patterns pour les anciens includes
        $oldPatterns = [
            "/include __DIR__ \. '\/cors\.php';\s*include __DIR__ \. '\/sqlcmd\.php';\s*include __DIR__ \. '\/\.\.\/config\/config\.php';\s*include __DIR__ \. '\/jwt_utils\.php';/",
            "/include\('cors\.php'\);\s*include\('sqlcmd\.php'\);\s*include\('\.\.\/config\/config\.php'\);\s*include\('jwt_utils\.php'\);/",
            "/include __DIR__ \. '\/cors\.php';\s*include __DIR__ \. '\/sqlcmd\.php';\s*include __DIR__ \. '\/jwt_utils\.php';/",
            "/include\('cors\.php'\);\s*include\('sqlcmd\.php'\);\s*include\('jwt_utils\.php'\);/"
        ];
        
        $fileUpdated = false;
        foreach ($oldPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "include '../config/config_principal.php';", $content);
                $fileUpdated = true;
            }
        }
        
        if ($fileUpdated) {
            file_put_contents($file, $content);
            $updatedFiles++;
            echo "✅ Mis à jour: $file\n";
        }
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers mis à jour : $updatedFiles\n";
echo "Correction terminée!\n";
?>
