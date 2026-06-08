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
function createTransaction(PDO $db, string $type, ?string $listingId, ?string $auctionId, int $payerId, int $payeeId, int $amountSats, string $paymentHash = '', string $paymentRequest = '')): string {
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