<?php
/**
 * Toxic Market — API Endpoints
 */

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
            
            if (!$email || !$password || !$display_name) {
                throw new Exception('Email, password and display name required', 400);
            }
            if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters', 400);
            
            $user = registerWithEmail($email, $password, $display_name);
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

        default:
            throw new Exception('Unknown action: ' . $action, 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode(['error' => $e->getMessage(), 'status' => $e->getCode()]);
}