<?php
/**
 * Toxic Market — No-Custody P2P Marketplace
 * 
 * The platform does NOT handle payments or transactions.
 * It's a listing board. Buyers contact sellers directly.
 * 
 * Payment flow: NONE on our side.
 * 1. Buyer sees listing → contacts seller (Nostr, LN address, etc.)
 * 2. They work it out themselves
 * 3. When done, seller marks listing as sold (optional)
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