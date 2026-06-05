<?php
/**
 * Toxic Market — Lightning Payment Integration
 * 
 * Supports: LNBits (primary), manual BOLT11, on-chain fallback
 * 
 * Setup: Create a .env.payments file or set LNBits URL + API key
 * in the admin settings. For now, uses manual payment confirmation.
 */

define('PAYMENTS_CONFIG', $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/payments_config.json');

class LightningPayments {
    private ?string $lnbitsUrl = null;
    private ?string $lnbitsKey = null;
    private bool $sandboxMode = true; // Manual confirmation until LNBits is configured
    
    public function __construct() {
        // Try to load LNBits config
        $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/lnbits_config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config && isset($config['url']) && isset($config['api_key'])) {
                $this->lnbitsUrl = rtrim($config['url'], '/');
                $this->lnbitsKey = $config['api_key'];
                $this->sandboxMode = $config['sandbox'] ?? true;
            }
        }
    }
    
    /**
     * Check if LNBits is configured and reachable
     */
    public function isAvailable(): bool {
        return $this->lnbitsUrl !== null && $this->lnbitsKey !== null;
    }
    
    /**
     * Create a Lightning invoice
     * Returns: ['payment_hash' => ..., 'payment_request' => ..., 'expires_at' => ...]
     */
    public function createInvoice(int $amountSats, string $description, string $externalId = ''): array {
        if (!$this->isAvailable()) {
            return $this->createManualInvoice($amountSats, $description, $externalId);
        }
        
        $data = [
            'out' => false,
            'amount' => $amountSats,
            'memo' => substr($description, 0, 100),
            'expiry' => 3600, // 1 hour
        ];
        if ($externalId) {
            $data['unit'] = 'sat';
            $data['internal'] = false;
        }
        
        $ch = curl_init($this->lnbitsUrl . '/api/v1/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Api-Key: ' . $this->lnbitsKey,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 && $response) {
            $invoice = json_decode($response, true);
            return [
                'success' => true,
                'payment_hash' => $invoice['payment_hash'] ?? '',
                'payment_request' => $invoice['payment_request'] ?? '',
                'amount_sats' => $amountSats,
                'description' => $description,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'source' => 'lnbits',
            ];
        }
        
        // Fallback to manual
        return $this->createManualInvoice($amountSats, $description, $externalId);
    }
    
    /**
     * Check if an invoice has been paid
     */
    public function checkPayment(string $paymentHash): array {
        if (!$this->isAvailable()) {
            return ['paid' => false, 'source' => 'manual', 'message' => 'Manual confirmation required'];
        }
        
        $ch = curl_init($this->lnbitsUrl . '/api/v1/payments/' . $paymentHash);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $this->lnbitsKey,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $payment = json_decode($response, true);
        return [
            'paid' => $payment['paid'] ?? false,
            'source' => 'lnbits',
            'amount' => $payment['amount'] ?? 0,
            'details' => $payment,
        ];
    }
    
    /**
     * Manual invoice (when LNBits is not configured)
     * Buyer pays directly to seller's Lightning address or wallet
     */
    private function createManualInvoice(int $amountSats, string $description, string $externalId = ''): array {
        $db = getDB();
        $id = bin2hex(random_bytes(16));
        
        // Store transaction record
        $stmt = $db->prepare('INSERT INTO transactions (id, type, amount_sats, status, payment_hash, payment_request) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $id,
            'manual_invoice',
            $amountSats,
            'pending_manual',
            $id, // Use as reference
            '', // No BOLT11 for manual
        ]);
        
        return [
            'success' => true,
            'payment_hash' => $id,
            'payment_request' => '', // No BOLT11 - manual payment
            'amount_sats' => $amountSats,
            'description' => $description,
            'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 7), // 7 days for manual
            'source' => 'manual',
            'transaction_id' => $id,
            'instructions' => 'Zahle direkt an den Verkäufer per Lightning. Bestätige die Zahlung imListing.',
        ];
    }
    
    /**
     * Confirm a manual payment (seller confirms they received payment)
     */
    public function confirmManualPayment(string $transactionId, int $confirmerId): array {
        $db = getDB();
        
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND status = ?');
        $stmt->execute([$transactionId, 'pending_manual']);
        $tx = $stmt->fetch();
        
        if (!$tx) {
            return ['success' => false, 'error' => 'Transaction not found or already confirmed'];
        }
        
        $db->prepare('UPDATE transactions SET status = ?, settled_at = datetime(\'now\') WHERE id = ?')
            ->execute(['confirmed_manual', $transactionId]);
        
        // Update listing if applicable
        if ($tx['listing_id']) {
            $db->prepare('UPDATE listings SET is_sold = 1, buyer_id = ?, sold_at = datetime(\'now\') WHERE id = ?')
                ->execute([$tx['payer_id'] ?? 0, $tx['listing_id']]);
        }
        
        return ['success' => true, 'transaction_id' => $transactionId];
    }
}

// Helper to get BTC price for sats↔fiat conversion
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

// Sats to EUR conversion
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