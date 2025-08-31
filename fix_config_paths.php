<?php
// Script pour corriger les chemins vers config.php

echo "Recherche de tous les fichiers PHP...\n";

// Fonction pour trouver tous les fichiers .php de manière récursive
function findPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), 'vendor')) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

$phpFiles = findPhpFiles('.');

echo "Nombre de fichiers PHP trouvés : " . count($phpFiles) . "\n";

$updatedFiles = 0;
$totalReplacements = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Remplacer les chemins relatifs vers config.php par le chemin correct
    $content = str_replace("include('../config/config.php');", "include('../config/config.php');", $content);
    $content = str_replace("include __DIR__ . '/../config/config.php';", "include __DIR__ . '/../config/config.php';", $content);
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        $updatedFiles++;
        $replacements = substr_count($content, '../config/config.php') - substr_count($originalContent, '../config/config.php');
        $totalReplacements += $replacements;
        echo "✅ Mis à jour: $file ($replacements remplacements)\n";
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers PHP traités : " . count($phpFiles) . "\n";
echo "Fichiers mis à jour : $updatedFiles\n";
echo "Total de remplacements : $totalReplacements\n";
echo "Mise à jour terminée!\n";
?>
