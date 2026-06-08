<?php
/**
 * Toxic Market — API Endpoints
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/payments.php';

/**
 * Auto-end expired auctions
 */
function autoEndExpiredAuctions(PDO $db): void {
    $stmt = $db->prepare("UPDATE auctions SET status = 'ended' WHERE status = 'active' AND ends_at <= datetime('now')");
    $stmt->execute();
    
    // Set winner for ended auctions with bids
    $ended = $db->query("SELECT id FROM auctions WHERE status = 'ended' AND winner_id IS NULL")->fetchAll();
    foreach ($ended as $auction) {
        // Highest bidder wins
        $stmt2 = $db->prepare('SELECT bidder_id FROM bids WHERE auction_id = ? ORDER BY amount_sats DESC LIMIT 1');
        $stmt2->execute([$auction['id']]);
        $winner = $stmt2->fetch();
        if ($winner) {
            $db->prepare('UPDATE auctions SET winner_id = ? WHERE id = ?')->execute([$winner['bidder_id'], $auction['id']]);
            // Increment seller's total_sales
            $db->prepare('UPDATE users SET total_sales = total_sales + 1 WHERE id = (SELECT seller_id FROM auctions WHERE id = ?)')->execute([$auction['id']]);
        }
    }
}

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        // === AUTH ===
        case 'status':
            echo json_encode([
                'logged_in' => isLoggedIn(),
                'user' => currentUser(),
                'auth_method' => $_SESSION['auth_method'] ?? null,
                'csrf_token' => generateCSRF(),
            ]);
            break;

        case 'register':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $display_name = $data['display_name'] ?? '';
            $accept_disclaimer = $data['accept_disclaimer'] ?? false;
            $nostr_pubkey = $data['nostr_pubkey'] ?? null;
            $csrf = $data['csrf_token'] ?? '';
            
            if (!$email || !$password || !$display_name) {
                throw new Exception('Email, password and display name required', 400);
            }
            if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters', 400);
            if (!$accept_disclaimer) throw new Exception('You must accept the disclaimer', 400);
            if (!verifyCSRF($csrf)) throw new Exception('Invalid CSRF token', 403);
            
            // Validate nostr pubkey if provided (64 hex chars)
            if ($nostr_pubkey && !preg_match('/^[0-9a-f]{64}$/i', $nostr_pubkey)) {
                throw new Exception('Invalid nostr pubkey format', 400);
            }
            
            try {
                $user = registerWithEmail($email, $password, $display_name);
                
                // Save nostr pubkey if provided
                if ($user && $nostr_pubkey) {
                    $db->prepare('UPDATE users SET nostr_pubkey = ? WHERE id = ?')
                        ->execute([$nostr_pubkey, $user['id']]);
                    $user['nostr_pubkey'] = $nostr_pubkey;
                }
            } catch (Exception $e) {
                throw new Exception('Registration failed: ' . $e->getMessage(), 500);
            }
            if (!$user) throw new Exception('Email already registered', 409);
            
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'login':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $csrf = $data['csrf_token'] ?? '';
            
            if (isset($data['nostr_pubkey'])) {
                $user = loginWithNostr($data['nostr_pubkey']);
            } else {
                if (!verifyCSRF($csrf)) throw new Exception('Invalid CSRF token', 403);
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                $user = loginWithEmail($email, $password);
            }
            
            if (!$user) throw new Exception('Invalid credentials', 401);
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'logout':
            logout();
            echo json_encode(['success' => true]);
            break;

        // === CARDS ===
        case 'cards':
            $generation = $_GET['generation'] ?? null;
            $search = $_GET['search'] ?? '';
            
            $sql = 'SELECT ct.*, 
                     (SELECT COUNT(*) FROM listings WHERE card_template_id = ct.id AND is_sold = 0) as active_listings,
                     (SELECT COUNT(*) FROM auctions WHERE card_template_id = ct.id AND status = \'active\') as active_auctions,
                     (SELECT MIN(price_sats) FROM listings WHERE card_template_id = ct.id AND is_sold = 0) as lowest_price
                     FROM card_templates ct WHERE 1=1';
            $params = [];
            
            if ($generation) {
                $sql .= ' AND ct.generation = ?';
                $params[] = (int)$generation;
            }
            if ($search) {
                $sql .= ' AND (ct.name LIKE ? OR ct.description LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= ' ORDER BY ct.generation, ct.id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $cards = $stmt->fetchAll();
            
            // Decode JSON fields FIRST
            foreach ($cards as &$card) {
                $card['holo_positions'] = json_decode($card['holo_positions'], true);
            }
            
            // Add dynamic image URLs
            foreach ($cards as &$card) {
                $card['card_number'] = (($card['id'] - 1) % 21) + 1;
                if (empty($card['image_url'])) {
                    $holo = in_array($card['card_number'], $card['holo_positions'] ?? []) ? '1' : '0';
                    $holoPos = urlencode(json_encode($card['holo_positions'] ?? []));
                    $card['image_url'] = '/toxic-market/cards/card.svg.php?id=' . $card['id'] . '&gen=' . $card['generation'] . '&name=' . urlencode($card['name']) . '&holo=' . $holo . '&holo_positions=' . $holoPos;
                }
            }
            
            echo json_encode(['data' => $cards, 'meta' => ['total' => count($cards)]]);
            break;

        case 'card':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare('SELECT * FROM card_templates WHERE id = ?');
            $stmt->execute([$id]);
            $card = $stmt->fetch();
            
            if (!$card) throw new Exception('Card not found', 404);
            $card['holo_positions'] = json_decode($card['holo_positions'], true);
            $card['card_number'] = (($card['id'] - 1) % 21) + 1;
            
            // Add dynamic image URL
            if (empty($card['image_url'])) {
                $holo = in_array($card['card_number'], $card['holo_positions'] ?? []) ? '1' : '0';
                $holoPos = urlencode(json_encode($card['holo_positions'] ?? []));
                $card['image_url'] = '/toxic-market/cards/card.svg.php?id=' . $card['id'] . '&gen=' . $card['generation'] . '&name=' . urlencode($card['name']) . '&holo=' . $holo . '&holo_positions=' . $holoPos;
            }
            
            // Get variants
            $stmt2 = $db->prepare('SELECT * FROM card_variants WHERE template_id = ?');
            $stmt2->execute([$id]);
            $card['variants'] = $stmt2->fetchAll();
            
            // Get active listings and auctions
            $stmt3 = $db->prepare('SELECT * FROM listings WHERE card_template_id = ? AND is_sold = 0 ORDER BY price_sats ASC');
            $stmt3->execute([$id]);
            $card['listings'] = $stmt3->fetchAll();
            
            $stmt4 = $db->prepare('SELECT * FROM auctions WHERE card_template_id = ? AND status = \'active\' ORDER BY ends_at ASC');
            $stmt4->execute([$id]);
            $card['auctions'] = $stmt4->fetchAll();
            
            // Decode JSON in listings/auctions
            foreach ($card['listings'] as &$l) { $l['image_urls'] = json_decode($l['image_urls'], true); }
            foreach ($card['auctions'] as &$a) { $a['image_urls'] = json_decode($a['image_urls'], true); }
            
            echo json_encode($card);
            break;

        // === LISTINGS ===
        case 'listings':
            $generation = $_GET['generation'] ?? null;
            $sort = $_GET['sort'] ?? 'newest';
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 24)));
            $cursor = $_GET['cursor'] ?? null;
            
            $sql = 'SELECT l.*, ct.name as card_name, ct.generation, ct.image_url as card_image,
                     u.display_name as seller_name
                     FROM listings l
                     JOIN card_templates ct ON l.card_template_id = ct.id
                     JOIN users u ON l.seller_id = u.id
                     WHERE l.is_sold = 0';
            $params = [];
            
            if ($generation) {
                $sql .= ' AND ct.generation = ?';
                $params[] = (int)$generation;
            }
            if ($cursor) {
                $sql .= ' AND l.created_at < ?';
                $params[] = $cursor;
            }
            
            $orderBy = match($sort) {
                'price_low' => 'ORDER BY l.price_sats ASC',
                'price_high' => 'ORDER BY l.price_sats DESC',
                'newest' => 'ORDER BY l.created_at DESC',
                default => 'ORDER BY l.is_featured DESC, l.created_at DESC',
            };
            $sql .= " $orderBy LIMIT $limit + 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            $hasMore = count($results) > $limit;
            $listings = array_slice($results, 0, $limit);
            
            foreach ($listings as &$l) { 
                $l['image_urls'] = json_decode($l['image_urls'], true);
                $l['free_shipping'] = ($l['local_shipping_sats'] == 0 && $l['intl_shipping_sats'] == 0);
            }
            
            echo json_encode([
                'data' => $listings,
                'page' => ['has_more' => $hasMore, 'next_cursor' => $hasMore ? end($listings)['created_at'] : null]
            ]);
            break;

        case 'auctions':
            $sql = 'SELECT a.*, ct.name as card_name, ct.generation, ct.image_url as card_image,
                     u.display_name as seller_name,
                     (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) as bid_count
                     FROM auctions a
                     JOIN card_templates ct ON a.card_template_id = ct.id
                     JOIN users u ON a.seller_id = u.id
                     WHERE a.status = \'active\' AND a.ends_at > datetime(\'now\')
                     ORDER BY a.ends_at ASC';
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $auctions = $stmt->fetchAll();
            
            foreach ($auctions as &$a) { $a['image_urls'] = json_decode($a['image_urls'], true); }
            
            echo json_encode(['data' => $auctions]);
            break;

        // === PLACEHOLDER: Create listing (needs auth) ===
        case 'create_listing':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = bin2hex(random_bytes(16));
            $stmt = $db->prepare('INSERT INTO listings (id, seller_id, card_template_id, title, description, price_sats, condition_text, serial_number, image_urls, proof_image_url, proof_block_height, local_shipping_sats, intl_shipping_sats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id, $user['id'], $data['card_template_id'] ?? null,
                $data['title'], $data['description'] ?? '', $data['price_sats'],
                $data['condition'] ?? 'mint', $data['serial_number'] ?? '',
                json_encode($data['image_urls'] ?? []),
                $data['proof_image_url'] ?? '', $data['proof_block_height'] ?? 0,
                $data['local_shipping_sats'] ?? 0, $data['intl_shipping_sats'] ?? 0
            ]);
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        // === ADMIN: Payment Config ===
        case 'payment_config':
            $user = requireAuth();
            if ($user['email'] !== 'akamaru.claw@gmx.de') throw new Exception('Admin only', 403);
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $config = [
                'lnbits_url' => $data['lnbits_url'] ?? '',
                'lnbits_api_key' => $data['lnbits_api_key'] ?? '',
                'onchain_address' => $data['onchain_address'] ?? '',
                'sandbox' => $data['sandbox'] ?? true,
                'updated_at' => date('c'),
            ];
            
            $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/payments_config.json';
            if (!is_dir(dirname($configFile))) mkdir(dirname($configFile), 0755, true);
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            
            // Also update LNBits config
            if ($config['lnbits_url'] && $config['lnbits_api_key']) {
                $lnbitsFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/lnbits_config.json';
                file_put_contents($lnbitsFile, json_encode([
                    'url' => $config['lnbits_url'],
                    'api_key' => $config['lnbits_api_key'],
                    'sandbox' => $config['sandbox'],
                ]));
            }
            
            echo json_encode(['success' => true, 'message' => 'Payment config updated']);
            break;

        // === AUCTION TIME REMAINING ===
        case 'auction_time':
            // Also auto-end expired auctions on every time check
            autoEndExpiredAuctions($db);
            
            $id = $_GET['id'] ?? '';
            $stmt = $db->prepare('SELECT ends_at, status, current_price_sats, starting_price_sats FROM auctions WHERE id = ?');
            $stmt->execute([$id]);
            $auction = $stmt->fetch();
            
            if (!$auction) throw new Exception('Auction not found', 404);
            
            $endsAt = strtotime($auction['ends_at']);
            $now = time();
            $remaining = max(0, $endsAt - $now);
            
            echo json_encode([
                'auction_id' => $id,
                'status' => $remaining > 0 ? 'active' : 'ended',
                'ends_at' => $auction['ends_at'],
                'remaining_seconds' => $remaining,
                'remaining_formatted' => gmdate('H:i:s', $remaining),
                'current_price_sats' => $auction['current_price_sats'],
                'starting_price_sats' => $auction['starting_price_sats'],
            ]);
            break;

        // === TOGGLE COLLECTION ===
        case 'toggle_collection':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $cardId = intval($data['card_id'] ?? 0);
            $owned = $data['owned'] ?? true;
            
            if (!$cardId) throw new Exception('Card ID required', 400);
            
            // Create table if not exists
            $db->exec('CREATE TABLE IF NOT EXISTS user_collection (
                user_id INTEGER REFERENCES users(id),
                card_template_id INTEGER REFERENCES card_templates(id),
                acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, card_template_id)
            )');
            
            if ($owned) {
                $db->prepare('INSERT OR IGNORE INTO user_collection (user_id, card_template_id) VALUES (?, ?)')
                    ->execute([$user['id'], $cardId]);
            } else {
                $db->prepare('DELETE FROM user_collection WHERE user_id = ? AND card_template_id = ?')
                    ->execute([$user['id'], $cardId]);
            }
            
            echo json_encode(['success' => true, 'card_id' => $cardId, 'owned' => $owned]);
            break;

        // === MY COLLECTION ===
        case 'my_collection':
            $user = requireAuth();
            
            // Create table if not exists
            $db->exec('CREATE TABLE IF NOT EXISTS user_collection (
                user_id INTEGER REFERENCES users(id),
                card_template_id INTEGER REFERENCES card_templates(id),
                acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, card_template_id)
            )');
            
            $stmt = $db->prepare('SELECT ct.*, uc.acquired_at FROM card_templates ct JOIN user_collection uc ON ct.id = uc.card_template_id WHERE uc.user_id = ? ORDER BY ct.generation, ct.id');
            $stmt->execute([$user['id']]);
            $collection = $stmt->fetchAll();
            
            echo json_encode(['data' => $collection, 'total' => count($collection)]);
            break;

        // === CATEGORIES (SatStash-compatible) ===
        case 'categories':
            echo json_encode(['data' => ['trading-cards', 'art', 'collectibles', 'bitcoin', 'accessories'], 'meta' => ['api_version' => 'v1']]);
            break;

        // === CREATE AUCTION ===
        case 'create_auction':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = bin2hex(random_bytes(16));
            $startingPrice = intval($data['starting_price_sats'] ?? 0);
            $duration = intval($data['duration_hours'] ?? 72); // default 3 days
            $startsAt = $data['starts_at'] ?? date('Y-m-d H:i:s');
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . " +{$duration} hours"));
            
            $stmt = $db->prepare('INSERT INTO auctions (id, seller_id, card_template_id, title, description, starting_price_sats, current_price_sats, serial_number, image_urls, proof_image_url, proof_block_height, condition_text, local_shipping_sats, intl_shipping_sats, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id, $user['id'], $data['card_template_id'] ?? null,
                $data['title'], $data['description'] ?? '',
                $startingPrice, $startingPrice,
                $data['serial_number'] ?? '',
                json_encode($data['image_urls'] ?? []),
                $data['proof_image_url'] ?? '', $data['proof_block_height'] ?? 0,
                $data['condition'] ?? 'mint',
                $data['local_shipping_sats'] ?? 0, $data['intl_shipping_sats'] ?? 0,
                $startsAt, $endsAt, 'active'
            ]);
            
            echo json_encode(['success' => true, 'id' => $id, 'ends_at' => $endsAt]);
            break;

        // === PLACE BID ===
        case 'place_bid':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $auctionId = $data['auction_id'] ?? '';
            $bidAmount = intval($data['amount_sats'] ?? 0);
            
            // Validate auction
            $stmt = $db->prepare('SELECT a.*, u.id as seller_uid FROM auctions a JOIN users u ON a.seller_id = u.id WHERE a.id = ? AND a.status = ?');
            $stmt->execute([$auctionId, 'active']);
            $auction = $stmt->fetch();
            if (!$auction) throw new Exception('Auction not found or not active', 404);
            if ($auction['seller_id'] == $user['id']) throw new Exception('Cannot bid on your own auction', 400);
            
            $ends = new DateTime($auction['ends_at']);
            if ($ends < new DateTime()) throw new Exception('Auction has ended', 400);
            
            $minBid = ($auction['current_price_sats'] ?? $auction['starting_price_sats']) + 500;
            if ($bidAmount < $minBid) throw new Exception("Minimum bid is {$minBid} sats", 400);
            
            // Create bid
            $stmt = $db->prepare('INSERT INTO bids (auction_id, bidder_id, amount_sats) VALUES (?, ?, ?)');
            $stmt->execute([$auctionId, $user['id'], $bidAmount]);
            
            // Update auction current price
            $db->prepare('UPDATE auctions SET current_price_sats = ? WHERE id = ?')->execute([$bidAmount, $auctionId]);
            
            echo json_encode(['success' => true, 'bid_amount' => $bidAmount, 'new_current_price' => $bidAmount]);
            break;

        // === AUCTION DETAIL ===
        case 'auction':
            $id = $_GET['id'] ?? '';
            $stmt = $db->prepare('SELECT a.*, ct.name as card_name, ct.generation, ct.holo_positions, ct.total_print_run,
                u.display_name as seller_name, u.id as seller_id
                FROM auctions a 
                JOIN card_templates ct ON a.card_template_id = ct.id
                JOIN users u ON a.seller_id = u.id
                WHERE a.id = ?');
            $stmt->execute([$id]);
            $auction = $stmt->fetch();
            if (!$auction) throw new Exception('Auction not found', 404);
            
            $auction['image_urls'] = json_decode($auction['image_urls'], true);
            $auction['holo_positions'] = json_decode($auction['holo_positions'], true);
            
            // Get recent bids
            $stmt2 = $db->prepare('SELECT b.*, u.display_name as bidder_name FROM bids b JOIN users u ON b.bidder_id = u.id WHERE b.auction_id = ? ORDER BY b.amount_sats DESC LIMIT 20');
            $stmt2->execute([$id]);
            $auction['bids'] = $stmt2->fetchAll();
            $auction['bid_count'] = count($auction['bids']);
            
            echo json_encode($auction);
            break;

        // === BIDS FOR AUCTION ===
        case 'bids':
            $auctionId = $_GET['auction_id'] ?? '';
            $stmt = $db->prepare('SELECT b.*, u.display_name as bidder_name FROM bids b JOIN users u ON b.bidder_id = u.id WHERE b.auction_id = ? ORDER BY b.amount_sats DESC LIMIT 50');
            $stmt->execute([$auctionId]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // === BLOCK HASH (for proof of ownership) ===
        case 'current_block':
            $ch = curl_init('https://mempool.space/api/blocks/tip/height');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $height = curl_exec($ch);
            curl_close($ch);
            
            $ch2 = curl_init('https://mempool.space/api/block-hash/' . $height);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
            $hash = curl_exec($ch2);
            curl_close($ch2);
            
            echo json_encode([
                'block_height' => (int)$height,
                'block_hash' => $hash,
                'timestamp' => time(),
                'instruction' => 'Schreibe die Block-Height und deinen Benutzernamen auf einen Zettel, halte ihn neben die Karte und fotografiere beides zusammen.'
            ]);
            break;

        // === LISTING DETAIL ===
        case 'listing':
            $id = $_GET['id'] ?? '';
            $stmt = $db->prepare('SELECT l.*, ct.name as card_name, ct.generation, ct.description as card_desc, ct.holo_positions, ct.total_print_run,
                u.display_name as seller_name, u.bio as seller_bio, u.id as seller_id, u.created_at as seller_since, u.total_sales
                FROM listings l 
                JOIN card_templates ct ON l.card_template_id = ct.id
                JOIN users u ON l.seller_id = u.id
                WHERE l.id = ?');
            $stmt->execute([$id]);
            $listing = $stmt->fetch();
            if (!$listing) throw new Exception('Listing not found', 404);
            $listing['image_urls'] = json_decode($listing['image_urls'], true);
            $listing['holo_positions'] = json_decode($listing['holo_positions'], true);
            echo json_encode($listing);
            break;

        // === SELLER PROFILE ===
        case 'seller':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare('SELECT id, display_name, bio, avatar_url, reputation_score, total_sales, total_purchases, created_at FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $seller = $stmt->fetch();
            if (!$seller) throw new Exception('Seller not found', 404);
            
            $stmt2 = $db->prepare('SELECT l.*, ct.name as card_name, ct.generation, ct.holo_positions
                FROM listings l JOIN card_templates ct ON l.card_template_id = ct.id
                WHERE l.seller_id = ? AND l.is_sold = 0 ORDER BY l.created_at DESC');
            $stmt2->execute([$id]);
            $seller['listings'] = $stmt2->fetchAll();
            foreach ($seller['listings'] as &$l) {
                $l['image_urls'] = json_decode($l['image_urls'], true);
                $l['holo_positions'] = json_decode($l['holo_positions'], true);
            }
            echo json_encode($seller);
            break;

        // === IMAGE UPLOAD ===
        case 'upload_image':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded', 400);
            }
            $file = $_FILES['image'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) throw new Exception('File too large (max 5MB)', 400);
            
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file['type'], $allowed)) throw new Exception('Only JPG, PNG, WebP allowed', 400);
            
            $ext = match($file['type']) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'
            };
            $filename = bin2hex(random_bytes(8)) . '_' . $user['id'] . '.' . $ext;
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Upload failed', 500);
            }
            
            $url = '/toxic-market/uploads/' . $filename;
            echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
            break;

        // === UPDATE LISTING ===
        case 'update_listing':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? '';
            
            $stmt = $db->prepare('SELECT * FROM listings WHERE id = ? AND seller_id = ?');
            $stmt->execute([$id, $user['id']]);
            $listing = $stmt->fetch();
            if (!$listing) throw new Exception('Listing not found or not yours', 404);
            
            $fields = []; $values = [];
            foreach (['title', 'description', 'price_sats', 'condition_text', 'serial_number', 'local_shipping_sats', 'intl_shipping_sats'] as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            if (isset($data['image_urls'])) {
                $fields[] = "image_urls = ?";
                $values[] = json_encode($data['image_urls']);
            }
            if (isset($data['proof_image_url'])) {
                $fields[] = "proof_image_url = ?";
                $values[] = $data['proof_image_url'];
            }
            
            if (!empty($fields)) {
                $values[] = $id;
                $db->prepare('UPDATE listings SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
            }
            echo json_encode(['success' => true]);
            break;

        // === DELETE LISTING ===
        case 'delete_listing':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? '';
            
            $stmt = $db->prepare('SELECT * FROM listings WHERE id = ? AND seller_id = ? AND is_sold = 0');
            $stmt->execute([$id, $user['id']]);
            if (!$stmt->fetch()) throw new Exception('Listing not found or not yours', 404);
            
            $db->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        // === DELETE AUCTION ===
        case 'delete_auction':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? '';
            
            $stmt = $db->prepare('SELECT * FROM auctions WHERE id = ? AND seller_id = ?');
            $stmt->execute([$id, $user['id']]);
            $auction = $stmt->fetch();
            if (!$auction) throw new Exception('Auction not found or not yours', 404);
            
            // Only allow delete if no bids yet or status is ended
            $stmt2 = $db->prepare('SELECT COUNT(*) as c FROM bids WHERE auction_id = ?');
            $stmt2->execute([$id]);
            $bids = $stmt2->fetch()['c'];
            if ($bids > 0 && $auction['status'] === 'active') {
                throw new Exception('Cannot delete active auction with bids. End it instead.', 400);
            }
            
            $db->prepare('DELETE FROM bids WHERE auction_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM auctions WHERE id = ?')->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;
            
        // === END AUCTION EARLY ===
        case 'end_auction':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? '';
            
            $stmt = $db->prepare('SELECT * FROM auctions WHERE id = ? AND seller_id = ? AND status = ?');
            $stmt->execute([$id, $user['id'], 'active']);
            $auction = $stmt->fetch();
            if (!$auction) throw new Exception('Auction not found or already ended', 404);
            
            $db->prepare('UPDATE auctions SET ends_at = datetime("now"), status = "ended" WHERE id = ?')->execute([$id]);
            autoEndExpiredAuctions($db); // to process winner
            
            echo json_encode(['success' => true]);
            break;

        // === MY LISTINGS ===
        case 'my_listings':
            $user = requireAuth();
            $stmt = $db->prepare('SELECT l.*, ct.name as card_name, ct.generation 
                FROM listings l JOIN card_templates ct ON l.card_template_id = ct.id
                WHERE l.seller_id = ? ORDER BY l.created_at DESC');
            $stmt->execute([$user['id']]);
            $listings = $stmt->fetchAll();
            foreach ($listings as &$l) { $l['image_urls'] = json_decode($l['image_urls'], true); }
            echo json_encode(['data' => $listings]);
            break;

        // === BTC PRICE ===
        case 'btc_price':
            $prices = getBtcPrice();
            echo json_encode(['prices' => $prices, 'updated' => date('c')]);
            break;

        // === REQUEST PASSWORD RESET ===
        case 'request_reset':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $email = trim($data['email'] ?? '');
            
            if (!$email) throw new Exception('Email required', 400);
            
            $token = generateResetToken($email);
            if (!$token) {
                // Don't reveal whether email exists
                echo json_encode(['success' => true, 'message' => 'Wenn die E-Mail existiert, wurde ein Reset-Token generiert.']);
                break;
            }
            
            echo json_encode(['success' => true, 'token' => $token, 'message' => 'Reset-Token generiert.']);
            break;

        // === VERIFY RESET TOKEN ===
        case 'verify_reset':
            $token = $_GET['token'] ?? '';
            $reset = verifyResetToken($token);
            if (!$reset) throw new Exception('Invalid or expired token', 400);
            echo json_encode(['success' => true, 'email' => substr($reset['email'], 0, 3) . '***' . strstr($reset['email'], '@')]);
            break;

        // === RESET PASSWORD ===
        case 'reset_password':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? '';
            $newPassword = $data['password'] ?? '';
            
            if (strlen($newPassword) < 6) throw new Exception('Passwort muss mindestens 6 Zeichen haben', 400);
            
            if (!resetPassword($token, $newPassword)) {
                throw new Exception('Token ungültig oder abgelaufen', 400);
            }
            
            echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich zurückgesetzt.']);
            break;

        // === USER PROFILE UPDATE ===
        case 'update_profile':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $bio = $data['bio'] ?? $user['bio'];
            $displayName = $data['display_name'] ?? $user['display_name'];
            
            if (strlen($displayName) < 2 || strlen($displayName) > 30) {
                throw new Exception('Display name must be 2-30 characters', 400);
            }
            
            $stmt = $db->prepare('UPDATE users SET bio = ?, display_name = ? WHERE id = ?');
            $stmt->execute([trim($bio), trim($displayName), $user['id']]);
            
            echo json_encode(['success' => true, 'display_name' => trim($displayName), 'bio' => trim($bio)]);
            break;

        // === PAYMENT: Create Lightning Invoice ===
        case 'create_invoice':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $amountSats = intval($data['amount_sats'] ?? 0);
            $type = $data['type'] ?? 'listing'; // listing, auction_deposit, auction_bid
            $listingId = $data['listing_id'] ?? null;
            $auctionId = $data['auction_id'] ?? null;
            $memo = $data['memo'] ?? 'Toxic Market Payment';
            
            if ($amountSats < 100) throw new Exception('Minimum 100 sats', 400);
            if ($amountSats > 10000000) throw new Exception('Maximum 10,000,000 sats', 400);
            
            // Determine payee
            $payeeId = 0; // platform escrow
            if ($type === 'listing' && $listingId) {
                $stmt = $db->prepare('SELECT seller_id, price_sats FROM listings WHERE id = ? AND is_sold = 0');
                $stmt->execute([$listingId]);
                $listing = $stmt->fetch();
                if (!$listing) throw new Exception('Listing not found', 404);
                $payeeId = $listing['seller_id'];
                $amountSats = $data['amount_sats'] ?? $listing['price_sats'];
                $memo = "Toxic Market: {$listing['price_sats']} sats for listing {$listingId}";
            } elseif ($type === 'auction_deposit' && $auctionId) {
                $stmt = $db->prepare('SELECT starting_price_sats FROM auctions WHERE id = ? AND status = ?');
                $stmt->execute([$auctionId, 'active']);
                $auction = $stmt->fetch();
                if (!$auction) throw new Exception('Auction not found or not active', 404);
                // Deposit = 5% of starting price, min 1000 sats
                $amountSats = max(1000, intval($auction['starting_price_sats'] * 0.05));
                $memo = "Toxic Market: Bid deposit for auction {$auctionId}";
            }
            
            // Create LNBits invoice
            $invoice = createLNBitsInvoice($amountSats, $memo);
            if (!$invoice) {
                // Fallback: return manual payment info
                $txId = createTransaction($db, $type, $listingId, $auctionId, $user['id'], $payeeId, $amountSats, '', '');
                echo json_encode([
                    'success' => true,
                    'transaction_id' => $txId,
                    'type' => $type,
                    'amount_sats' => $amountSats,
                    'payment_method' => 'manual',
                    'message' => 'Lightning nicht konfiguriert. Bitte kontaktiere den Verkäufer direkt.',
                    'seller_id' => $payeeId,
                ]);
                break;
            }
            
            // Record transaction
            $txId = createTransaction($db, $type, $listingId, $auctionId, $user['id'], $payeeId, $amountSats, $invoice['payment_hash'], $invoice['payment_request']);
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $txId,
                'payment_hash' => $invoice['payment_hash'],
                'payment_request' => $invoice['payment_request'],
                'checkout_url' => $invoice['checkout_url'],
                'qr_url' => getQRCodeUrl('lightning:' . $invoice['payment_request']),
                'amount_sats' => $amountSats,
                'type' => $type,
                'expires_in' => 86400,
            ]);
            break;

        // === PAYMENT: Check Payment Status ===
        case 'check_payment':
            $user = requireAuth();
            $txId = $_GET['transaction_id'] ?? '';
            $paymentHash = $_GET['payment_hash'] ?? '';
            
            if ($txId) {
                $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND payer_id = ?');
                $stmt->execute([$txId, $user['id']]);
                $tx = $stmt->fetch();
                if (!$tx) throw new Exception('Transaction not found', 404);
                
                if ($tx['status'] === 'settled') {
                    echo json_encode(['success' => true, 'status' => 'settled', 'transaction' => $tx]);
                    break;
                }
                
                // Check with LNBits
                if (!empty($tx['payment_hash'])) {
                    $payment = checkLNBitsPayment($tx['payment_hash']);
                    if ($payment['paid']) {
                        updateTransactionStatus($db, $txId, 'settled');
                        
                        // If listing payment, mark as sold
                        if ($tx['type'] === 'listing' && $tx['listing_id']) {
                            $db->prepare('UPDATE listings SET is_sold = 1, buyer_id = ? WHERE id = ?')
                                ->execute([$user['id'], $tx['listing_id']]);
                            // Increment buyer's total_purchases
                            $db->prepare('UPDATE users SET total_purchases = total_purchases + 1 WHERE id = ?')
                                ->execute([$user['id']]);
                            // Increment seller's total_sales
                            $db->prepare('UPDATE users SET total_sales = total_sales + 1 WHERE id = (SELECT seller_id FROM listings WHERE id = ?)')
                                ->execute([$tx['listing_id']]);
                        }
                        
                        // If auction deposit, mark bid deposit as paid
                        if ($tx['type'] === 'auction_deposit' && $tx['auction_id']) {
                            // Find the latest unpaid bid for this auction by this user
                            $stmt2 = $db->prepare('SELECT id FROM bids WHERE auction_id = ? AND bidder_id = ? AND deposit_paid = 0 ORDER BY id DESC LIMIT 1');
                            $stmt2->execute([$tx['auction_id'], $user['id']]);
                            $bid = $stmt2->fetch();
                            if ($bid) {
                                $db->prepare('UPDATE bids SET deposit_paid = 1, deposit_invoice = ? WHERE id = ?')
                                    ->execute([$tx['payment_hash'], $bid['id']]);
                            }
                        }
                        
                        echo json_encode(['success' => true, 'status' => 'settled', 'transaction_id' => $txId]);
                        break;
                    }
                }
                
                echo json_encode(['success' => true, 'status' => 'pending', 'transaction_id' => $txId]);
            } else {
                throw new Exception('Transaction ID required', 400);
            }
            break;

        // === PAYMENT: Get Onchain Address ===
        case 'onchain_address':
            $addr = getOnchainAddress($db);
            echo json_encode([
                'success' => true,
                'address' => $addr,
                'message' => $addr ? 'Sende Bitcoin an diese Adresse für Onchain-Zahlungen.' : 'Onchain-Adresse noch nicht konfiguriert.',
            ]);
            break;

        // === MY TRANSACTIONS ===
        case 'my_transactions':
            $user = requireAuth();
            $type = $_GET['type'] ?? null;
            
            $sql = 'SELECT t.*, ct.name as card_name FROM transactions t 
                    LEFT JOIN listings l ON t.listing_id = l.id 
                    LEFT JOIN card_templates ct ON l.card_template_id = ct.id
                    WHERE t.payer_id = ? OR t.payee_id = ?';
            $params = [$user['id'], $user['id']];
            if ($type) {
                $sql .= ' AND t.type = ?';
                $params[] = $type;
            }
            $sql .= ' ORDER BY t.created_at DESC LIMIT 50';
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll();
            
            echo json_encode(['data' => $transactions, 'total' => count($transactions)]);
            break;

        // === MARK LISTING SOLD ===
        case 'mark_sold':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $listingId = $data['listing_id'] ?? '';
            
            $stmt = $db->prepare('SELECT * FROM listings WHERE id = ? AND seller_id = ?');
            $stmt->execute([$listingId, $user['id']]);
            $listing = $stmt->fetch();
            if (!$listing) throw new Exception('Listing not found or not yours', 404);
            
            $db->prepare('UPDATE listings SET is_sold = 1, sold_at = datetime(\'now\') WHERE id = ?')
                ->execute([$listingId]);
            $db->prepare('UPDATE users SET total_sales = total_sales + 1 WHERE id = ?')
                ->execute([$user['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Listing als verkauft markiert']);
            break;

        // === MY LISTINGS (manage) ===
        case 'my_listings':
            $user = requireAuth();
            $stmt = $db->prepare('SELECT l.*, ct.name as card_name, ct.generation, ct.holo_positions FROM listings l JOIN card_templates ct ON l.card_template_id = ct.id WHERE l.seller_id = ? ORDER BY l.created_at DESC');
            $stmt->execute([$user['id']]);
            $listings = $stmt->fetchAll();
            foreach ($listings as &$l) {
                $l['image_urls'] = json_decode($l['image_urls'], true);
                $l['holo_positions'] = json_decode($l['holo_positions'], true);
            }
            echo json_encode(['data' => $listings]);
            break;

        // === NOTIFICATIONS ===
        case 'notifications':
            $user = requireAuth();
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';
            
            $sql = 'SELECT * FROM notifications WHERE user_id = ?';
            $params = [$user['id']];
            if ($unreadOnly) {
                $sql .= ' AND is_read = 0';
            }
            $sql .= ' ORDER BY created_at DESC LIMIT ?';
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll();
            
            // Count unread
            $stmt2 = $db->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt2->execute([$user['id']]);
            $unreadCount = $stmt2->fetch()['count'];
            
            echo json_encode(['data' => $notifications, 'unread_count' => $unreadCount]);
            break;

        // === MARK NOTIFICATIONS READ ===
        case 'mark_notifications_read':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['id'] ?? null;
            
            if ($notificationId) {
                $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                    ->execute([$notificationId, $user['id']]);
            } else {
                markNotificationsRead($db, $user['id']);
            }
            echo json_encode(['success' => true]);
            break;

        // === CREATE PURCHASE INVOICE ===
        case 'create_purchase_invoice':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $listingId = $data['listing_id'] ?? '';
            
            if (!$listingId) throw new Exception('Listing ID required', 400);
            
            $result = createPurchaseInvoice($db, $listingId, $user['id']);
            if (!$result) throw new Exception('Could not create invoice. Check listing and try again.', 400);
            
            $result['success'] = true;
            if (isset($result['payment_request']) && $result['payment_request']) {
                $result['qr_url'] = getQRCodeUrl('lightning:' . $result['payment_request']);
            }
            echo json_encode($result);
            break;

        // === BID WITH DEPOSIT ===
        case 'bid_with_deposit':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $auctionId = $data['auction_id'] ?? '';
            $bidAmount = intval($data['amount_sats'] ?? 0);
            
            if (!$auctionId || $bidAmount <= 0) throw new Exception('Auction ID and amount required', 400);
            
            // Verify minimum bid
            $stmt = $db->prepare('SELECT a.*, ct.name as card_name FROM auctions a JOIN card_templates ct ON a.card_template_id = ct.id WHERE a.id = ? AND a.status = ?');
            $stmt->execute([$auctionId, 'active']);
            $auction = $stmt->fetch();
            if (!$auction) throw new Exception('Auction not found or not active', 404);
            if ($auction['seller_id'] == $user['id']) throw new Exception('Cannot bid on own auction', 400);
            
            $currentPrice = $auction['current_price_sats'] ?? $auction['starting_price_sats'];
            $minBid = $currentPrice + 500;
            if ($bidAmount < $minBid) throw new Exception("Minimum bid is {$minBid} sats", 400);
            
            // Check auction hasn't ended
            $ends = new DateTime($auction['ends_at']);
            if ($ends < new DateTime()) throw new Exception('Auction has ended', 400);
            
            $result = createBidDepositInvoice($db, $auctionId, $user['id'], $bidAmount);
            if (!$result) throw new Exception('Could not create bid deposit', 400);
            
            $result['success'] = true;
            $result['min_bid'] = $minBid;
            $result['auction_title'] = $auction['title'];
            if (isset($result['payment_request']) && $result['payment_request']) {
                $result['qr_url'] = getQRCodeUrl('lightning:' . $result['payment_request']);
            }
            echo json_encode($result);
            break;

        // === CHECK BID DEPOSIT ===
        case 'check_bid_deposit':
            $user = requireAuth();
            $bidId = $_GET['bid_id'] ?? '';
            if (!$bidId) throw new Exception('Bid ID required', 400);
            
            $stmt = $db->prepare('SELECT b.*, t.status as tx_status, t.payment_hash FROM bids b LEFT JOIN transactions t ON t.auction_id = b.auction_id AND t.payer_id = b.bidder_id AND t.type = ? WHERE b.id = ?');
            $stmt->execute(['bid_deposit', $bidId]);
            $bid = $stmt->fetch();
            if (!$bid) throw new Exception('Bid not found', 404);
            if ($bid['bidder_id'] != $user['id']) throw new Exception('Not your bid', 403);
            
            $depositPaid = (bool)$bid['deposit_paid'];
            
            // Check LNBits if we have a payment hash
            if (!$depositPaid && !empty($bid['deposit_payment_hash'])) {
                $check = checkLNBitsPayment($bid['deposit_payment_hash']);
                if ($check['paid']) {
                    $db->prepare('UPDATE bids SET deposit_paid = 1 WHERE id = ?')->execute([$bidId]);
                    $depositPaid = true;
                }
            }
            
            echo json_encode(['success' => true, 'bid_id' => $bidId, 'deposit_paid' => $depositPaid, 'amount_sats' => $bid['amount_sats']]);
            break;

        // === PAYMENT CONFIG (Admin) ===
        case 'payment_config':
            $user = requireAuth();
            if ($method === 'GET') {
                $config = getPaymentConfig();
                // Don't expose full API key to frontend
                $safe = [
                    'lnbits_url' => $config['lnbits_url'] ?? '',
                    'lnbits_api_key_set' => !empty($config['lnbits_api_key']),
                    'onchain_address' => $config['onchain_address'] ?? '',
                    'sandbox' => $config['sandbox'] ?? true,
                ];
                echo json_encode(['success' => true, 'config' => $safe]);
            } elseif ($method === 'POST') {
                // Save payment config
                $data = json_decode(file_get_contents('php://input'), true);
                $configDir = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data';
                if (!is_dir($configDir)) mkdir($configDir, 0755, true);
                
                $existing = getPaymentConfig();
                $config = [
                    'lnbits_url' => $data['lnbits_url'] ?? $existing['lnbits_url'] ?? '',
                    'lnbits_api_key' => $data['lnbits_api_key'] ?? $existing['lnbits_api_key'] ?? '',
                    'onchain_address' => $data['onchain_address'] ?? $existing['onchain_address'] ?? '',
                    'sandbox' => $data['sandbox'] ?? $existing['sandbox'] ?? true,
                ];
                
                file_put_contents($configDir . '/payments_config.json', json_encode($config, JSON_PRETTY_PRINT));
                
                // Also save LNBits config separately for payments.php compatibility
                if (!empty($config['lnbits_url']) && !empty($config['lnbits_api_key'])) {
                    $lnbitsConfig = [
                        'url' => $config['lnbits_url'],
                        'api_key' => $config['lnbits_api_key'],
                    ];
                    file_put_contents($configDir . '/lnbits_config.json', json_encode($lnbitsConfig, JSON_PRETTY_PRINT));
                }
                
                echo json_encode(['success' => true, 'message' => 'Zahlungskonfiguration gespeichert']);
            }
            break;

        // === SERVE IMAGE (secure proxy) ===
        case 'serve_image':
            $filename = $_GET['file'] ?? '';
            if (!preg_match('/^[a-f0-9]{16}_\d+\.(jpg|png|webp)$/', $filename)) {
                http_response_code(400);
                exit;
            }
            $filepath = $_SERVER['DOCUMENT_ROOT'] . '/../toxic-market/uploads/' . $filename;
            if (!file_exists($filepath)) {
                http_response_code(404);
                exit;
            }
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $mime = match($ext) { 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' };
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=86400');
            readfile($filepath);
            exit;

        default:
            throw new Exception('Unknown action: ' . $action, 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['error' => $e->getMessage(), 'status' => $e->getCode()]);
}