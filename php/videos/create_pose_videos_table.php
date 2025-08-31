<?php
include('../config/config_principal.php');

// Vérifier si la table existe
$checkTable = "SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'pose_videos'
);";

$result = pg_query($db->dbLink, $checkTable);
$exists = pg_fetch_result($result, 0, 0);

if ($exists === 'f') {
    echo "Création de la table pose_videos...\n";
    
    $createTable = "CREATE TABLE pose_videos (
        id SERIAL PRIMARY KEY,
        candidature_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        url TEXT NOT NULL,
        statut VARCHAR(50) DEFAULT 'en_attente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (candidature_id) REFERENCES candidatures(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    );";
    
    $result = pg_query($db->dbLink, $createTable);
    if ($result) {
        echo "Table pose_videos créée avec succès!\n";
    } else {
        echo "Erreur lors de la création de la table: " . pg_last_error($db->dbLink) . "\n";
    }
} else {
    echo "La table pose_videos existe déjà.\n";
}

// Afficher la structure de la table
$describeTable = "SELECT column_name, data_type, is_nullable, column_default 
                  FROM information_schema.columns 
                  WHERE table_name = 'pose_videos' 
                  ORDER BY ordinal_position;";

$result = pg_query($db->dbLink, $describeTable);
echo "\nStructure de la table pose_videos:\n";
while ($row = pg_fetch_assoc($result)) {
    echo "- {$row['column_name']}: {$row['data_type']} " . 
         ($row['is_nullable'] === 'NO' ? 'NOT NULL' : 'NULL') . 
         ($row['column_default'] ? " DEFAULT {$row['column_default']}" : '') . "\n";
}
?>

