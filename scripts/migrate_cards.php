<?php
/**
 * Toxic Market — Migration: Update card names & edition titles
 * Run once on server after deploy
 */

require_once __DIR__ . '/../includes/db.php';

$db = getDB();

echo "Starting card migration...\n";

// Generation 1 — Toxic Booster - Genesis Edition (DE)
$gen1_names = [
    'The Beginning', 'The Awakening', 'The Storm', 'The Mirror',
    'The Flame', 'The Void', 'The Echo', 'The Path',
    'The Shadow', 'The Light', 'The Crown', 'The Fall',
    'The Rise', 'The Bridge', 'The Horizon', 'The Depth',
    'The Spark', 'The Silence', 'The Edge', 'The Core',
    'The Genesis'
];

$stmt1 = $db->prepare('UPDATE card_templates SET name = ?, description = ? WHERE id = ? AND generation = 1');
foreach ($gen1_names as $i => $name) {
    $id = $i + 1;
    $desc = "Toxic Booster - Genesis Edition (DE) — Card #{$id}/21";
    $stmt1->execute([$name, $desc, $id]);
    echo "  Gen1 #{$id}: {$name}\n";
}

// Generation 2 — Toxic Booster - Second Edition (DE)
$gen2_names = [
    'Jack Dorsey', 'Marc Friedrich', 'Hairtoshi', 'Antonopoulos',
    'Adam Back', 'Nick Szabo', 'Sunny Decree', 'Kanuto',
    'Sirius', 'Hal Finney', 'Alex Von Frankenberg', 'Pieter Wuille',
    'Loddi', 'Matt Corallo', 'Jack Mallers', 'Peter Todd',
    'Jameson Lopp', 'Rahim Taghizadegan', 'Nicolas Dorier',
    'Beer of Satoshi', 'Fab or Chris'
];

$stmt2 = $db->prepare('UPDATE card_templates SET name = ?, description = ? WHERE id = ? AND generation = 2');
foreach ($gen2_names as $i => $name) {
    $id = $i + 22; // Gen2 starts at ID 22
    $num = $i + 1;
    $desc = "Toxic Booster - Second Edition (DE) — Card #{$num}/21";
    $stmt2->execute([$name, $desc, $id]);
    echo "  Gen2 #{$num}: {$name}\n";
}

// Generation 3 — Remakes
$stmt3 = $db->prepare('UPDATE card_templates SET name = ?, description = ? WHERE id = ? AND generation = 3');
foreach ($gen1_names as $i => $name) {
    $id = $i + 43; // Gen3 starts at ID 43
    $num = $i + 1;
    $desc = "Toxic Booster - Genesis Edition (EN Remake) — Card #{$num}/21";
    $stmt3->execute(["{$name} (EN)", $desc, $id]);
    echo "  Gen3 #{$num}: {$name} (EN)\n";
}

echo "Migration complete!\n";
