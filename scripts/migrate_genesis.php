<?php
/**
 * Toxic Market — Migration: Real Genesis card names
 */

require_once __DIR__ . '/../includes/db.php';

$db = getDB();

echo "Updating Genesis card names...\n";

$gen1_names = [
    'Satoshi', 'Niko Jilch', 'Der Pleb', 'Einundzwanzig Stammtisch',
    'Nodesignal', 'Fab', 'Blocktrainer', 'Seed or Chris',
    'Plebrap', 'Bitcoin Hotel', 'Pioniere Münzweg', 'Christian Decker',
    'Markus Turm', 'Jonas Nick', 'Netdiver', 'Dennis',
    'Paddepadde', 'Maurice-Effekt', 'Zitadelle', 'Der Gigi',
    'Einundzwanzig Magazin'
];

// Gen 1
$stmt1 = $db->prepare('UPDATE card_templates SET name = ?, description = ? WHERE id = ? AND generation = 1');
foreach ($gen1_names as $i => $name) {
    $id = $i + 1;
    $desc = "Toxic Booster - Genesis Edition (DE) — Card #{$id}/21";
    $stmt1->execute([$name, $desc, $id]);
    echo "  Gen1 #{$id}: {$name}\n";
}

// Gen 3 Remakes (same names + EN)
$stmt3 = $db->prepare('UPDATE card_templates SET name = ?, description = ? WHERE id = ? AND generation = 3');
foreach ($gen1_names as $i => $name) {
    $id = $i + 43;
    $num = $i + 1;
    $desc = "Toxic Booster - Genesis Edition (EN Remake) — Card #{$num}/21";
    $stmt3->execute(["{$name} (EN)", $desc, $id]);
    echo "  Gen3 #{$num}: {$name} (EN)\n";
}

echo "Done!\n";
