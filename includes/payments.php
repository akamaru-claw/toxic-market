<?php
/**
 * Toxic Market — P2P Payment System
 * 
 * NO CUSTODY. The platform NEVER holds funds.
 * 
 * Payment flow:
 * 1. Buyer clicks "Kaufen" → Platform creates a transaction record
 * 2. Seller's payment info (Lightning address, BOLT12, on-chain) is shown to buyer
 * 3. Buyer pays directly to seller's wallet
 * 4. Buyer confirms payment on platform
 * 5. Seller confirms receipt
 * 6. Transaction complete → Listing marked as sold
 * 
 * The platform only tracks the state — money flows P2P only.
 */

class P2PPayments {
    
    /**
     * Initiate a purchase — creates transaction record, reveals seller's payment info
     */
    public function initiatePurchase(int $buyerId, string $listingId): array {
        $db = getDB();
        
        // Get listing + seller info
        $stmt = $db->prepare('SELECT l.*, u.display_name as seller_name, u.id as seller_id
            FROM listings l JOIN users u ON l.seller_id = u.id WHERE l.id = ? AND l.is_sold = 0');
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        
        if (!$listing) return ['success' => false, 'error' => 'Angebot nicht gefunden'];
        if ($listing['seller_id'] == $buyerId) return ['success' => false, 'error' => 'Du kannst nicht dein eigenes Angebot kaufen'];
        
        // Check for existing pending transaction
        $stmt2 = $db->prepare('SELECT id FROM transactions WHERE listing_id = ? AND status IN (?, ?) LIMIT 1');
        $stmt2->execute([$listingId, 'pending_buyer', 'pending_seller']);
        if ($stmt2->fetch()) return ['success' => false, 'error' => 'Es gibt bereits eine ausstehende Zahlung für dieses Angebot'];
        
        // Create transaction record
        $txId = bin2hex(random_bytes(16));
        $totalSats = $listing['price_sats'];
        
        $db->prepare('INSERT INTO transactions (id, listing_id, payer_id, payee_id, type, amount_sats, status, payment_hash, payment_request) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$txId, $listingId, $buyerId, $listing['seller_id'], 'p2p_lightning', $totalSats, 'pending_buyer', $txId, '']);
        
        return [
            'success' => true,
            'transaction_id' => $txId,
            'amount_sats' => $totalSats,
            'seller_name' => $listing['seller_name'],
            'message' => 'Zahle direkt an den Verkäufer. Bestätige danach die Zahlung hier.',
        ];
    }
    
    /**
     * Buyer confirms they sent payment
     */
    public function buyerConfirms(string $txId, int $buyerId, ?string $paymentProof = null): array {
        $db = getDB();
        
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND payer_id = ? AND status = ?');
        $stmt->execute([$txId, $buyerId, 'pending_buyer']);
        $tx = $stmt->fetch();
        
        if (!$tx) return ['success' => false, 'error' => 'Transaktion nicht gefunden oder falscher Status'];
        
        $db->prepare('UPDATE transactions SET status = ?, settled_at = datetime(\'now\') WHERE id = ?')
            ->execute(['pending_seller', $txId]);
        
        if ($paymentProof) {
            $db->prepare('UPDATE transactions SET payment_request = ? WHERE id = ?')
                ->execute([$paymentProof, $txId]);
        }
        
        return ['success' => true, 'message' => 'Zahlung bestätigt! Warte auf Bestätigung des Verkäufers.'];
    }
    
    /**
     * Seller confirms they received payment
     */
    public function sellerConfirms(string $txId, int $sellerId): array {
        $db = getDB();
        
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND payee_id = ? AND status = ?');
        $stmt->execute([$txId, $sellerId, 'pending_seller']);
        $tx = $stmt->fetch();
        
        if (!$tx) return ['success' => false, 'error' => 'Transaktion nicht gefunden oder falscher Status'];
        
        // Mark transaction complete
        $db->prepare('UPDATE transactions SET status = ?, settled_at = datetime(\'now\') WHERE id = ?')
            ->execute(['confirmed_manual', $txId]);
        
        // Mark listing as sold
        if ($tx['listing_id']) {
            $db->prepare('UPDATE listings SET is_sold = 1, buyer_id = ?, sold_at = datetime(\'now\') WHERE id = ?')
                ->execute([$tx['payer_id'], $tx['listing_id']]);
            
            // Increment seller's total_sales
            $db->prepare('UPDATE users SET total_sales = total_sales + 1 WHERE id = ?')
                ->execute([$sellerId]);
        }
        
        return ['success' => true, 'message' => 'Zahlung bestätigt! Angebot als verkauft markiert.'];
    }
    
    /**
     * Seller rejects payment (didn't receive it)
     */
    public function sellerRejects(string $txId, int $sellerId): array {
        $db = getDB();
        
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND payee_id = ? AND status = ?');
        $stmt->execute([$txId, $sellerId, 'pending_seller']);
        $tx = $stmt->fetch();
        
        if (!$tx) return ['success' => false, 'error' => 'Transaktion nicht gefunden'];
        
        $db->prepare('UPDATE transactions SET status = \'rejected\' WHERE id = ?')
            ->execute([$txId]);
        
        return ['success' => true, 'message' => 'Zahlung abgelehnt. Transaktion storniert.'];
    }
    
    /**
     * Cancel transaction (buyer cancels before paying)
     */
    public function cancelTransaction(string $txId, int $userId): array {
        $db = getDB();
        
        $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ? AND (payer_id = ? OR payee_id = ?) AND status IN (?, ?)');
        $stmt->execute([$txId, $userId, $userId, 'pending_buyer', 'pending_seller']);
        $tx = $stmt->fetch();
        
        if (!$tx) return ['success' => false, 'error' => 'Kann nicht storniert werden'];
        
        $db->prepare('UPDATE transactions SET status = \'cancelled\' WHERE id = ?')
            ->execute([$txId]);
        
        return ['success' => true, 'message' => 'Transaktion storniert.'];
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