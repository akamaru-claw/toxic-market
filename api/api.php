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
            ]);
            break;

        case 'register':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $display_name = $data['display_name'] ?? '';
            $accept_disclaimer = $data['accept_disclaimer'] ?? false;
            
            if (!$email || !$password || !$display_name) {
                throw new Exception('Email, password and display name required', 400);
            }
            if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters', 400);
            if (!$accept_disclaimer) throw new Exception('You must accept the disclaimer', 400);
            
            try {
                $user = registerWithEmail($email, $password, $display_name);
            } catch (Exception $e) {
                throw new Exception('Registration failed: ' . $e->getMessage(), 500);
            }
            if (!$user) throw new Exception('Email already registered', 409);
            
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'login':
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['nostr_pubkey'])) {
                $user = loginWithNostr($data['nostr_pubkey']);
            } else {
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
            
            // Add dynamic image URLs
            foreach ($cards as &$card) {
                if (empty($card['image_url'])) {
                    $card['image_url'] = '/toxic-market/cards/card.svg.php?id=' . $card['id'] . '&gen=' . $card['generation'] . '&name=' . urlencode($card['name']) . '&holo=' . (in_array($card['id'], json_decode($card['holo_positions'], true) ?: []) ? '1' : '0');
                }
            }
            
            // Decode JSON fields
            foreach ($cards as &$card) {
                $card['holo_positions'] = json_decode($card['holo_positions'], true);
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
            
            // Add dynamic image URL
            if (empty($card['image_url'])) {
                $card['image_url'] = '/toxic-market/cards/card.svg.php?id=' . $card['id'] . '&gen=' . $card['generation'] . '&name=' . urlencode($card['name']) . '&holo=' . (in_array(21, $card['holo_positions'] ?? []) ? '1' : '0');
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
            
            foreach ($listings as &$l) { $l['image_urls'] = json_decode($l['image_urls'], true); }
            
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
            $stmt = $db->prepare('INSERT INTO listings (id, seller_id, card_template_id, title, description, price_sats, condition_text, serial_number, image_urls, local_shipping_sats, intl_shipping_sats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id, $user['id'], $data['card_template_id'] ?? null,
                $data['title'], $data['description'] ?? '', $data['price_sats'],
                $data['condition'] ?? 'mint', $data['serial_number'] ?? '',
                json_encode($data['image_urls'] ?? []),
                $data['local_shipping_sats'] ?? 0, $data['intl_shipping_sats'] ?? 0
            ]);
            
            echo json_encode(['success' => true, 'id' => $id]);
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
            
            $stmt = $db->prepare('INSERT INTO auctions (id, seller_id, card_template_id, title, description, starting_price_sats, current_price_sats, serial_number, image_urls, condition_text, local_shipping_sats, intl_shipping_sats, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id, $user['id'], $data['card_template_id'] ?? null,
                $data['title'], $data['description'] ?? '',
                $startingPrice, $startingPrice,
                $data['serial_number'] ?? '',
                json_encode($data['image_urls'] ?? []),
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

        // === INITIATE PAYMENT ===
        case 'initiate_payment':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $listingId = $data['listing_id'] ?? '';
            $paymentMethod = $data['payment_method'] ?? 'lightning'; // lightning, onchain, manual
            
            // Get listing
            $stmt = $db->prepare('SELECT l.*, ct.name as card_name, u.display_name as seller_name, u.id as seller_id
                FROM listings l 
                JOIN card_templates ct ON l.card_template_id = ct.id
                JOIN users u ON l.seller_id = u.id
                WHERE l.id = ? AND l.is_sold = 0');
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch();
            
            if (!$listing) throw new Exception('Listing not found or already sold', 404);
            if ($listing['seller_id'] == $user['id']) throw new Exception('Cannot buy your own listing', 400);
            
            $totalSats = $listing['price_sats'];
            if ($data['include_shipping'] && $data['shipping_region'] === 'de') {
                $totalSats += $listing['local_shipping_sats'];
            } elseif ($data['include_shipping'] && $data['shipping_region'] === 'intl') {
                $totalSats += $listing['intl_shipping_sats'];
            }
            
            $description = "Toxic Market: {$listing['title']} - " . formatSats($totalSats);
            
            $ln = new LightningPayments();
            $invoice = $ln->createInvoice($totalSats, $description, $listingId);
            
            // Create transaction record
            $txId = bin2hex(random_bytes(16));
            $stmt = $db->prepare('INSERT INTO transactions (id, type, listing_id, payer_id, payee_id, amount_sats, payment_hash, payment_request, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $txId,
                'purchase',
                $listingId,
                $user['id'],
                $listing['seller_id'],
                $totalSats,
                $invoice['payment_hash'] ?? $txId,
                $invoice['payment_request'] ?? '',
                'pending'
            ]);
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $txId,
                'amount_sats' => $totalSats,
                'amount_eur' => round(satsToEur($totalSats), 2),
                'payment_method' => $invoice['source'],
                'payment_hash' => $invoice['payment_hash'] ?? $txId,
                'payment_request' => $invoice['payment_request'] ?? '',
                'expires_at' => $invoice['expires_at'] ?? '',
                'instructions' => $invoice['instructions'] ?? '',
                'listing' => [
                    'id' => $listing['id'],
                    'title' => $listing['title'],
                    'price_sats' => $listing['price_sats'],
                    'seller_name' => $listing['seller_name'],
                ],
            ]);
            break;

        // === CHECK PAYMENT STATUS ===
        case 'payment_status':
            $txId = $_GET['id'] ?? '';
            $stmt = $db->prepare('SELECT t.*, l.title as listing_title, u.display_name as payer_name FROM transactions t LEFT JOIN listings l ON t.listing_id = l.id LEFT JOIN users u ON t.payer_id = u.id WHERE t.id = ?');
            $stmt->execute([$txId]);
            $tx = $stmt->fetch();
            if (!$tx) throw new Exception('Transaction not found', 404);
            
            $paid = false;
            if ($tx['status'] === 'pending' && $tx['payment_hash']) {
                $ln = new LightningPayments();
                $check = $ln->checkPayment($tx['payment_hash']);
                if ($check['paid']) {
                    $db->prepare('UPDATE transactions SET status = ?, settled_at = datetime(\'now\') WHERE id = ?')
                        ->execute(['paid', $txId]);
                    $paid = true;
                }
            } elseif (in_array($tx['status'], ['paid', 'confirmed_manual'])) {
                $paid = true;
            }
            
            echo json_encode([
                'transaction_id' => $txId,
                'status' => $paid ? 'paid' : $tx['status'],
                'amount_sats' => $tx['amount_sats'],
                'listing_title' => $tx['listing_title'] ?? '',
                'payer_name' => $tx['payer_name'] ?? '',
                'created_at' => $tx['created_at'],
                'settled_at' => $tx['settled_at'] ?? null,
            ]);
            break;

        // === CONFIRM MANUAL PAYMENT (seller confirms) ===
        case 'confirm_payment':
            $user = requireAuth();
            if ($method !== 'POST') throw new Exception('POST required', 405);
            $data = json_decode(file_get_contents('php://input'), true);
            
            $txId = $data['transaction_id'] ?? '';
            $stmt = $db->prepare('SELECT t.*, l.seller_id FROM transactions t LEFT JOIN listings l ON t.listing_id = l.id WHERE t.id = ?');
            $stmt->execute([$txId]);
            $tx = $stmt->fetch();
            if (!$tx) throw new Exception('Transaction not found', 404);
            if ($tx['payee_id'] != $user['id'] && $tx['seller_id'] != $user['id']) {
                throw new Exception('Only the seller can confirm payment', 403);
            }
            
            $ln = new LightningPayments();
            $result = $ln->confirmManualPayment($txId, $user['id']);
            echo json_encode($result);
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

        default:
            throw new Exception('Unknown action: ' . $action, 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['error' => $e->getMessage(), 'status' => $e->getCode()]);
}