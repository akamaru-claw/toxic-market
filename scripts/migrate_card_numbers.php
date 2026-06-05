<?php
/**
 * Toxic Market — Migration: Add card numbers to names
 */

require_once __DIR__ . '/../includes/db.php';

$db = getDB();

echo "Adding card numbers to names...\n";

for ($gen = 1; $gen <= 3; $gen++) {
    $stmt = $db->prepare('SELECT id, name FROM card_templates WHERE generation = ? ORDER BY id');
    $stmt->execute([$gen]);
    $cards = $stmt->fetchAll();
    
    $update = $db->prepare('UPDATE card_templates SET name = ? WHERE id = ?');
    foreach ($cards as $i => $card) {
        $num = $i + 1;
        $baseName = preg_replace('/^#\d+\s+/', '', $card['name']); // Remove existing # if present
        $baseName = preg_replace('/\s+\(EN\)$/', '', $baseName);   // Remove (EN) temporarily
        $newName = "#{$num} {$baseName}";
        if ($gen === 3) $newName .= " (EN)";
        
        $update->execute([$newName, $card['id']]);
        echo "  Gen{$gen} #{$num}: {$newName}\n";
    }
}

echo "Done!\n";
