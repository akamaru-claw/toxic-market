<?php
/**
 * Toxic Market — Payments (LNBits Integration)
 * 
 * Payment flow:
 * 1. Buyer clicks "Kaufen" → LNBits invoice created
 * 2. Buyer pays invoice → status checked via polling
 * 3. Seller gets notified → marks listing as sold
 * 
 * For auctions:
 * 1. Bidder places bid → deposit invoice created
 * 2. Bidder pays deposit → bid confirmed
 * 3. Auction ends → winner pays, losers get deposit refunded
 */

// BTC price helper (for display only)
function getBtcPrice(): array {
    $ch = curl_init('https://mempool.space/api/v1/prices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $prices = json_decode($response, true);
    return [
        'eur' => $prices['EUR'] ?? 0,
        'usd' => $prices['USD'] ?? 0,
    ];
}

function satsToEur(int $sats, ?float $eurPrice = null): float {
    if ($eurPrice === null) {
        $prices = getBtcPrice();
        $eurPrice = $prices['eur'];
    }
    return ($sats / 100000000) * $eurPrice;
}

function formatSats(int $sats): string {
    if ($sats >= 1000000) {
        return number_format($sats / 1000000, 3) . ' BTC';
    }
    return number_format($sats) . ' sats';
}

/**
 * Get LNBits config from file
 */
function getLNBitsConfig(): ?array {
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/lnbits_config.json';
    if (!file_exists($configFile)) return null;
    $config = json_decode(file_get_contents($configFile), true);
    if (!$config || empty($config['url']) || empty($config['api_key'])) return null;
    return $config;
}

/**
 * Create a Lightning invoice via LNBits
 * Returns ['payment_hash' => ..., 'payment_request' => ..., 'checkout_url' => ...] or null on error
 */
function createLNBitsInvoice(int $amountSats, string $memo = '', string $expirySeconds = '86400'): ?array {
    $config = getLNBitsConfig();
    if (!$config) return null;
    
    $url = rtrim($config['url'], '/') . '/api/v1/payments';
    $data = [
        'out' => false,
        'amount' => $amountSats * 1000, // msats
        'memo' => $memo ?: 'Toxic Market',
        'expiry' => (int)$expirySeconds,
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Api-Key: ' . $config['api_key'],
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) return null;
    
    $result = json_decode($response, true);
    if (!$result || empty($result['payment_hash'])) return null;
    
    return [
        'payment_hash' => $result['payment_hash'],
        'payment_request' => $result['payment_request'] ?? $result['bolt11'] ?? '',
        'checkout_url' => $result['checkout_url'] ?? null,
    ];
}

/**
 * Check if a Lightning invoice has been paid
 */
function checkLNBitsPayment(string $paymentHash): array {
    $config = getLNBitsConfig();
    if (!$config) return ['paid' => false, 'error' => 'LNBits not configured'];
    
    $url = rtrim($config['url'], '/') . '/api/v1/payments/' . $paymentHash;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-Api-Key: ' . $config['api_key'],
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return ['paid' => false, 'error' => 'LNBits API error'];
    
    $result = json_decode($response, true);
    if (!$result) return ['paid' => false, 'error' => 'Invalid response'];
    
    return [
        'paid' => $result['paid'] ?? false,
        'amount_msat' => $result['amount'] ?? 0,
        'payment_hash' => $paymentHash,
        'settled_at' => $result['settled'] ?? null,
    ];
}

/**
 * Pay a Lightning invoice (for refunds/escrow payout)
 * Returns payment_hash or null on error
 */
function payLNBitsInvoice(string $bolt11): ?string {
    $config = getLNBitsConfig();
    if (!$config) return null;
    
    $url = rtrim($config['url'], '/') . '/api/v1/payments';
    $data = [
        'out' => true,
        'bolt11' => $bolt11,
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Api-Key: ' . $config['api_key'],
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201 && $httpCode !== 200) return null;
    
    $result = json_decode($response, true);
    return $result['payment_hash'] ?? null;
}

/**
 * Generate a QR code URL for a lightning invoice
 * Uses a simple QR API
 */
function getQRCodeUrl(string $data, int $size = 300): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);
}

/**
 * Generate onchain address for user (static deposit address)
 * For now, returns admin-configured address from config
 */
function getOnchainAddress(PDO $db): string {
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/payments_config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config['onchain_address'] ?? '';
    }
    return '';
}

/**
 * Create a transaction record
 */
function createTransaction(PDO $db, string $type, ?string $listingId, ?string $auctionId, int $payerId, int $payeeId, int $amountSats, string $paymentHash = '', string $paymentRequest = ''): string {
    $id = bin2hex(random_bytes(16));
    $stmt = $db->prepare('INSERT INTO transactions (id, type, listing_id, auction_id, payer_id, payee_id, amount_sats, payment_hash, payment_request, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$id, $type, $listingId, $auctionId, $payerId, $payeeId, $amountSats, $paymentHash, $paymentRequest, 'pending']);
    return $id;
}

/**
 * Update transaction status
 */
function updateTransactionStatus(PDO $db, string $transactionId, string $status): void {
    $db->prepare('UPDATE transactions SET status = ?, settled_at = datetime(\'now\') WHERE id = ?')
        ->execute([$status, $transactionId]);
}

/**
 * Get payment config (admin settings)
 */
function getPaymentConfig(): array {
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/payments_config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config) return $config;
    }
    return ['lnbits_url' => '', 'lnbits_api_key' => '', 'onchain_address' => '', 'sandbox' => true];
}

/**
 * Create a purchase invoice for a listing
 * Returns transaction ID and invoice data, or null on error
 */
function createPurchaseInvoice(PDO $db, string $listingId, int $buyerId): ?array {
    // Get listing details
    $stmt = $db->prepare('SELECT l.*, u.display_name as seller_name, u.id as seller_uid FROM listings l JOIN users u ON l.seller_id = u.id WHERE l.id = ? AND l.is_sold = 0');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();
    if (!$listing) return null;
    if ($listing['seller_id'] == $buyerId) return null; // Can't buy own listing
    
    // Check for existing pending transaction
    $stmt2 = $db->prepare('SELECT * FROM transactions WHERE listing_id = ? AND payer_id = ? AND type = \'purchase\' AND status = \'pending\' ORDER BY created_at DESC LIMIT 1');
    $stmt2->execute([$listingId, $buyerId]);
    $existing = $stmt2->fetch();
    if ($existing) {
        // Check if existing invoice is still valid
        $check = checkLNBitsPayment($existing['payment_hash']);
        if ($check['paid']) {
            // Already paid — mark as settled
            updateTransactionStatus($db, $existing['id'], 'settled');
            return ['transaction_id' => $existing['id'], 'status' => 'paid', 'amount_sats' => $existing['amount_sats']];
        }
        // Return existing invoice
        return [
            'transaction_id' => $existing['id'],
            'payment_hash' => $existing['payment_hash'],
            'payment_request' => $existing['payment_request'],
            'amount_sats' => $existing['amount_sats'],
            'status' => 'pending',
        ];
    }
    
    $amountSats = (int)$listing['price_sats'];
    $memo = "Toxic Market: {$listing['title']}";
    
    // Create LNBits invoice
    $invoice = createLNBitsInvoice($amountSats, $memo);
    if (!$invoice) {
        // LNBits not configured — create manual transaction
        $txId = bin2hex(random_bytes(16));
        $stmt3 = $db->prepare('INSERT INTO transactions (id, type, listing_id, payer_id, payee_id, amount_sats, payment_hash, payment_request, status) VALUES (?, \'purchase\', ?, ?, ?, ?, \'\', \'\', \'pending\')');
        $stmt3->execute([$txId, $listingId, $buyerId, $listing['seller_id'], $amountSats]);
        return [
            'transaction_id' => $txId,
            'amount_sats' => $amountSats,
            'status' => 'manual',
            'seller_name' => $listing['seller_name'],
            'onchain_address' => getOnchainAddress($db),
        ];
    }
    
    // Create transaction record
    $txId = createTransaction($db, 'purchase', $listingId, null, $buyerId, $listing['seller_id'], $amountSats, $invoice['payment_hash'], $invoice['payment_request']);
    
    return [
        'transaction_id' => $txId,
        'payment_hash' => $invoice['payment_hash'],
        'payment_request' => $invoice['payment_request'],
        'checkout_url' => $invoice['checkout_url'],
        'amount_sats' => $amountSats,
        'status' => 'pending',
    ];
}

/**
 * Create a bid deposit invoice
 * Returns transaction ID and invoice data
 */
function createBidDepositInvoice(PDO $db, string $auctionId, int $bidderId, int $bidAmount): ?array {
    // Get auction details
    $stmt = $db->prepare('SELECT a.*, u.display_name as seller_name FROM auctions a JOIN users u ON a.seller_id = u.id WHERE a.id = ? AND a.status = \'active\'');
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch();
    if (!$auction) return null;
    if ($auction['seller_id'] == $bidderId) return null;
    
    // Check auction hasn't ended
    $ends = new DateTime($auction['ends_at']);
    if ($ends < new DateTime()) return null;
    
    // Deposit is 5% of bid, minimum 1000 sats
    $depositSats = max(1000, (int)($bidAmount * 0.05));
    $memo = "Toxic Market: Deposit for auction \'{$auction['title']}\'";
    
    // Create LNBits invoice
    $invoice = createLNBitsInvoice($depositSats, $memo);
    if (!$invoice) {
        // LNBits not available — allow bid without deposit (P2P honor system)
        $depositPaid = 0;
        $paymentHash = '';
        $paymentRequest = '';
    } else {
        $depositPaid = 0;
        $paymentHash = $invoice['payment_hash'];
        $paymentRequest = $invoice['payment_request'];
    }
    
    // Create bid
    $stmt2 = $db->prepare('INSERT INTO bids (auction_id, bidder_id, amount_sats, deposit_paid, deposit_refunded, deposit_invoice, deposit_payment_hash, deposit_payment_request) VALUES (?, ?, ?, ?, 0, ?, ?, ?)');
    $stmt2->execute([$auctionId, $bidderId, $bidAmount, $depositPaid ? 1 : 0, $paymentRequest, $paymentHash, $paymentRequest]);
    $bidId = $db->lastInsertId();
    
    // Update auction current price
    $db->prepare('UPDATE auctions SET current_price_sats = ? WHERE id = ?')->execute([$bidAmount, $auctionId]);
    
    // Create transaction record
    $txId = createTransaction($db, 'bid_deposit', null, $auctionId, $bidderId, $auction['seller_id'], $depositSats, $paymentHash, $paymentRequest);
    
    // Notify previous highest bidder they were outbid
    $stmt3 = $db->prepare('SELECT bidder_id FROM bids WHERE auction_id = ? AND bidder_id != ? ORDER BY amount_sats DESC LIMIT 1');
    $stmt3->execute([$auctionId, $bidderId]);
    $prevBidder = $stmt3->fetch();
    if ($prevBidder) {
        createNotification($db, $prevBidder['bidder_id'], 'outbid', 'Überboten!', "Jemand hat auf \'{$auction['title']}\' mit " . number_format($bidAmount) . " sats geboten. Willst du höher gehen?", $auctionId);
    }
    
    return [
        'bid_id' => $bidId,
        'transaction_id' => $txId,
        'payment_hash' => $paymentHash,
        'payment_request' => $paymentRequest,
        'checkout_url' => $invoice['checkout_url'] ?? null,
        'deposit_sats' => $depositSats,
        'status' => $invoice ? 'pending' : 'no_deposit',
    ];
}

/**
 * Check and settle a payment
 */
function checkAndSettlePayment(PDO $db, string $transactionId): array {
    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$transactionId]);
    $tx = $stmt->fetch();
    if (!$tx) return ['status' => 'not_found'];
    
    if ($tx['status'] === 'settled') return ['status' => 'paid', 'transaction' => $tx];
    if (empty($tx['payment_hash'])) return ['status' => 'manual', 'transaction' => $tx];
    
    // Check LNBits payment status
    $check = checkLNBitsPayment($tx['payment_hash']);
    if ($check['paid']) {
        updateTransactionStatus($db, $transactionId, 'settled');
        
        // Handle based on transaction type
        if ($tx['type'] === 'purchase' && $tx['listing_id']) {
            // Mark listing as sold
            $db->prepare('UPDATE listings SET is_sold = 1, buyer_id = ?, sold_at = datetime(\'now\') WHERE id = ?')
                ->execute([$tx['payer_id'], $tx['listing_id']]);
            // Update seller stats
            $db->prepare('UPDATE users SET total_sales = total_sales + 1 WHERE id = ?')
                ->execute([$tx['payee_id']]);
            // Notify seller
            createNotification($db, $tx['payee_id'], 'sale', 'Karte verkauft!', "Deine Karte wurde für " . number_format($tx['amount_sats']) . " sats verkauft.", $tx['listing_id']);
            // Notify buyer
            createNotification($db, $tx['payer_id'], 'purchase', 'Zahlung bestätigt', "Deine Zahlung für " . number_format($tx['amount_sats']) . " sats wurde bestätigt. Kontaktiere den Verkäufer für den Versand.", $tx['listing_id']);
        } elseif ($tx['type'] === 'bid_deposit' && $tx['auction_id']) {
            // Mark bid deposit as paid
            $db->prepare('UPDATE bids SET deposit_paid = 1 WHERE auction_id = ? AND bidder_id = ? AND deposit_payment_hash = ?')
                ->execute([$tx['auction_id'], $tx['payer_id'], $tx['payment_hash']]);
        }
        
        // Reload transaction
        $stmt2 = $db->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt2->execute([$transactionId]);
        $tx = $stmt2->fetch();
        return ['status' => 'paid', 'transaction' => $tx];
    }
    
    return ['status' => 'pending', 'transaction' => $tx];
}

/**
 * Create an in-app notification
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/email.php';

function createNotification(PDO $db, int $userId, string $type, string $title, string $message, string $relatedId = ''): void {
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $title, $message, $relatedId]);
    
    // Send email for important notification types
    try {
        notifyUserEmail($db, $userId, $type, $title, $message);
    } catch (Exception $e) {
        // Email failures should not break the main flow
        error_log('Email notification failed: ' . $e->getMessage());
    }
}

/**
 * Get notifications for a user
 */
function getNotifications(PDO $db, int $userId, int $limit = 20): array {
    $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Mark notifications as read
 */
function markNotificationsRead(PDO $db, int $userId): void {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
        ->execute([$userId]);
}

/**
 * Get user's onchain address (admin-configured fallback)
 */
function getUserOnchainAddress(PDO $db, int $userId): string {
    // Check if user has set their own address
    $stmt = $db->prepare('SELECT onchain_address FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && !empty($user['onchain_address'])) {
        return $user['onchain_address'];
    }
    // Fallback to admin-configured address
    return getOnchainAddress($db);
}