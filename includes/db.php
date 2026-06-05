<?php
/**
 * Toxic Market — Database Setup & Connection
 * SQLite-based, Strato-compatible
 */

define('DB_PATH', $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/toxic_market.db');

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    // Schema migrations (add columns to existing tables if missing)
    $migrations = [
        'ALTER TABLE listings ADD COLUMN generation INTEGER DEFAULT 1',
        'ALTER TABLE auctions ADD COLUMN generation INTEGER DEFAULT 1',
        'ALTER TABLE users ADD COLUMN nostr_pubkey TEXT',
        'ALTER TABLE users ADD COLUMN bio TEXT',
        'ALTER TABLE users ADD COLUMN avatar_url TEXT',
        'ALTER TABLE users ADD COLUMN total_sales INTEGER DEFAULT 0',
        'ALTER TABLE users ADD COLUMN total_purchases INTEGER DEFAULT 0',
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* Column already exists, ignore */ }
    }

    return $db;
}

function initDB(): void {
    $db = getDB();
    
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nostr_pubkey TEXT UNIQUE,
        email TEXT UNIQUE,
        display_name TEXT NOT NULL,
        password_hash TEXT,
        bio TEXT DEFAULT \'\',
        avatar_url TEXT DEFAULT \'\',
        reputation_score INTEGER DEFAULT 0,
        total_sales INTEGER DEFAULT 0,
        total_purchases INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS card_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        generation INTEGER NOT NULL,
        artist TEXT DEFAULT \'MX12ART\',
        image_url TEXT DEFAULT \'\',
        description TEXT DEFAULT \'\',
        total_print_run INTEGER DEFAULT 210,
        holo_positions TEXT DEFAULT \'[]\',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS card_variants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_id INTEGER REFERENCES card_templates(id),
        variant_type TEXT NOT NULL,
        serial_number TEXT DEFAULT \'\',
        description TEXT DEFAULT \'\',
        image_url TEXT DEFAULT \'\',
        is_holo BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS listings (
        id TEXT PRIMARY KEY,
        seller_id INTEGER REFERENCES users(id),
        card_template_id INTEGER REFERENCES card_templates(id),
        card_variant_id INTEGER REFERENCES card_variants(id),
        title TEXT NOT NULL,
        description TEXT DEFAULT \'\',
        price_sats INTEGER NOT NULL,
        condition_text TEXT DEFAULT \'mint\',
        serial_number TEXT DEFAULT \'\',
        image_urls TEXT DEFAULT \'[]\',
        proof_image_url TEXT DEFAULT \'\',
        proof_block_height INTEGER DEFAULT 0,
        proof_block_hash TEXT DEFAULT \'\',
        proof_verified BOOLEAN DEFAULT 0,
        proof_verified_by INTEGER REFERENCES users(id),
        proof_verified_at DATETIME,
        is_sold BOOLEAN DEFAULT 0,
        buyer_id INTEGER REFERENCES users(id),
        local_shipping_sats INTEGER DEFAULT 0,
        intl_shipping_sats INTEGER DEFAULT 0,
        free_shipping BOOLEAN DEFAULT 0,
        is_featured BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        sold_at DATETIME
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS auctions (
        id TEXT PRIMARY KEY,
        seller_id INTEGER REFERENCES users(id),
        card_template_id INTEGER REFERENCES card_templates(id),
        card_variant_id INTEGER REFERENCES card_variants(id),
        title TEXT NOT NULL,
        description TEXT DEFAULT \'\',
        starting_price_sats INTEGER NOT NULL,
        current_price_sats INTEGER,
        reserve_price_sats INTEGER DEFAULT 0,
        deposit_sats INTEGER NOT NULL DEFAULT 0,
        serial_number TEXT DEFAULT \'\',
        image_urls TEXT DEFAULT \'[]\',
        proof_image_url TEXT DEFAULT \'\',
        proof_block_height INTEGER DEFAULT 0,
        proof_block_hash TEXT DEFAULT \'\',
        proof_verified BOOLEAN DEFAULT 0,
        condition_text TEXT DEFAULT \'mint\',
        local_shipping_sats INTEGER DEFAULT 0,
        intl_shipping_sats INTEGER DEFAULT 0,
        free_shipping BOOLEAN DEFAULT 0,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        status TEXT DEFAULT \'active\',
        winner_id INTEGER REFERENCES users(id),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS proof_verifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        listing_id TEXT REFERENCES listings(id),
        auction_id TEXT REFERENCES auctions(id),
        verifier_id INTEGER REFERENCES users(id),
        proof_type TEXT DEFAULT \'block_hash\',
        status TEXT DEFAULT \'pending\',
        comment TEXT DEFAULT \'\',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS bids (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        auction_id TEXT REFERENCES auctions(id),
        bidder_id INTEGER REFERENCES users(id),
        amount_sats INTEGER NOT NULL,
        deposit_invoice TEXT DEFAULT \'\',
        deposit_paid BOOLEAN DEFAULT 0,
        deposit_refunded BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS transactions (
        id TEXT PRIMARY KEY,
        type TEXT NOT NULL,
        listing_id TEXT REFERENCES listings(id),
        auction_id TEXT REFERENCES auctions(id),
        payer_id INTEGER REFERENCES users(id),
        payee_id INTEGER REFERENCES users(id),
        amount_sats INTEGER NOT NULL,
        payment_hash TEXT DEFAULT \'\',
        payment_request TEXT DEFAULT \'\',
        status TEXT DEFAULT \'pending\',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        settled_at DATETIME
    )');

    // Add columns if they don't exist (safe migration)
    try { $db->exec('ALTER TABLE transactions ADD COLUMN shipping_region TEXT DEFAULT \'\''); } catch(Exception $e) {}
    try { $db->exec('ALTER TABLE listings ADD COLUMN free_shipping BOOLEAN DEFAULT 0'); } catch(Exception $e) {}
    try { $db->exec('ALTER TABLE listings ADD COLUMN payment_method TEXT DEFAULT \'manual\''); } catch(Exception $e) {}

    $db->exec('CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        user_id INTEGER REFERENCES users(id),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL
    )');

    // Seed card templates if empty
    $count = $db->query('SELECT COUNT(*) FROM card_templates')->fetchColumn();
    if ($count == 0) {
        seedCards($db);
    }
}

function seedCards(PDO $db): void {
    // Generation 1 — Toxic Booster - Genesis Edition (DE)
    $gen1_names = [
        'Satoshi', 'Niko Jilch', 'Der Pleb', 'Einundzwanzig Stammtisch',
        'Nodesignal', 'Fab', 'Blocktrainer', 'Seed or Chris',
        'Plebrap', 'Bitcoin Hotel', 'Pioniere Münzweg', 'Christian Decker',
        'Markus Turm', 'Jonas Nick', 'Netdiver', 'Dennis',
        'Paddepadde', 'Maurice-Effekt', 'Zitadelle', 'Der Gigi',
        'Einundzwanzig Magazin'
    ];
    
    $stmt = $db->prepare('INSERT INTO card_templates (name, generation, description, holo_positions) VALUES (?, 1, ?, ?)');
    foreach ($gen1_names as $i => $name) {
        $num = $i + 1;
        $desc = "Toxic Booster - Genesis Edition (DE) — Card #{$num}/21";
        $holo = json_encode([21]); // Only #21/210 is holo in Gen 1
        $stmt->execute([$name, $desc, $holo]);
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

    $stmt2 = $db->prepare('INSERT INTO card_templates (name, generation, description, holo_positions) VALUES (?, 2, ?, ?)');
    foreach ($gen2_names as $i => $name) {
        $num = $i + 1;
        $desc = "Toxic Booster - Second Edition (DE) — Card #{$num}/21";
        $holo = json_encode([1, 21, 210]); // #1, #21, #210 are holo in Gen 2
        $stmt2->execute([$name, $desc, $holo]);
    }

    // Gen 1 Remakes (English versions)
    $stmt3 = $db->prepare('INSERT INTO card_templates (name, generation, description, holo_positions) VALUES (?, 3, ?, ?)');
    foreach ($gen1_names as $i => $name) {
        $num = $i + 1;
        $desc = "Toxic Booster - Genesis Edition (EN Remake) — Card #{$num}/21";
        $holo = json_encode([21]); // Only #21 is holo
        $stmt3->execute(["{$name} (EN)", $desc, $holo]);
    }
}

// Initialize on include
initDB();