<?php
/**
 * Toxic Market — Payload Validation Helpers
 *
 * Shared between api/api.php and tests.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

/**
 * Sanitize user-facing text: strip tags, trim whitespace, collapse multiple spaces.
 */
function sanitizeUserText(string $text, int $maxLength = 255): string {
    $text = trim($text);
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $maxLength);
}

/**
 * Validate a listing payload before insert.
 * Returns a normalized data array or throws Exception on invalid input.
 */
function validateListingPayload(array $data, PDO $db): array {
    $title = sanitizeUserText($data['title'] ?? '', 120);
    if ($title === '') {
        throw new Exception('Title is required', 400);
    }

    $cardTemplateId = isset($data['card_template_id']) ? (int)$data['card_template_id'] : 0;
    if ($cardTemplateId <= 0) {
        throw new Exception('Valid card template ID required', 400);
    }
    $exists = $db->prepare('SELECT 1 FROM card_templates WHERE id = ?');
    $exists->execute([$cardTemplateId]);
    if (!$exists->fetch()) {
        throw new Exception('Card template not found', 404);
    }

    $priceSats = isset($data['price_sats']) ? (int)$data['price_sats'] : -1;
    if ($priceSats < 1 || $priceSats > 2100000000000000) {
        throw new Exception('Price must be between 1 and 21M BTC in sats', 400);
    }

    $description = sanitizeUserText($data['description'] ?? '', 2000);
    $condition = sanitizeUserText($data['condition'] ?? 'mint', 30);
    $serial = sanitizeUserText($data['serial_number'] ?? '', 60);

    $allowedConditions = ['mint', 'near_mint', 'excellent', 'good', 'played', 'poor'];
    if (!in_array(strtolower($condition), $allowedConditions, true)) {
        $condition = 'mint';
    }

    $imageUrls = $data['image_urls'] ?? [];
    if (!is_array($imageUrls)) {
        $imageUrls = [];
    }
    $imageUrls = array_slice($imageUrls, 0, 5);
    foreach ($imageUrls as $url) {
        if (!is_string($url) || strlen($url) > 500) {
            throw new Exception('Invalid image URL', 400);
        }
    }

    $proofUrl = isset($data['proof_image_url']) ? sanitizeUserText($data['proof_image_url'], 500) : '';
    if ($proofUrl !== '' && strlen($proofUrl) > 500) {
        throw new Exception('Invalid proof image URL', 400);
    }

    $proofBlockHeight = isset($data['proof_block_height']) ? (int)$data['proof_block_height'] : 0;
    if ($proofBlockHeight < 0 || $proofBlockHeight > 9999999) {
        $proofBlockHeight = 0;
    }

    $localShipping = isset($data['local_shipping_sats']) ? (int)$data['local_shipping_sats'] : 0;
    $intlShipping = isset($data['intl_shipping_sats']) ? (int)$data['intl_shipping_sats'] : 0;
    if ($localShipping < 0 || $localShipping > 2100000000000000) {
        throw new Exception('Invalid local shipping amount', 400);
    }
    if ($intlShipping < 0 || $intlShipping > 2100000000000000) {
        throw new Exception('Invalid international shipping amount', 400);
    }

    return [
        'card_template_id' => $cardTemplateId,
        'title' => $title,
        'description' => $description,
        'price_sats' => $priceSats,
        'condition' => $condition,
        'serial_number' => $serial,
        'image_urls' => $imageUrls,
        'proof_image_url' => $proofUrl,
        'proof_block_height' => $proofBlockHeight,
        'local_shipping_sats' => $localShipping,
        'intl_shipping_sats' => $intlShipping,
    ];
}

/**
 * Validate an auction payload before insert.
 */
function validateAuctionPayload(array $data, PDO $db): array {
    $title = sanitizeUserText($data['title'] ?? '', 120);
    if ($title === '') {
        throw new Exception('Title is required', 400);
    }

    $cardTemplateId = isset($data['card_template_id']) ? (int)$data['card_template_id'] : 0;
    if ($cardTemplateId <= 0) {
        throw new Exception('Valid card template ID required', 400);
    }
    $exists = $db->prepare('SELECT 1 FROM card_templates WHERE id = ?');
    $exists->execute([$cardTemplateId]);
    if (!$exists->fetch()) {
        throw new Exception('Card template not found', 404);
    }

    $startingPrice = isset($data['starting_price_sats']) ? (int)$data['starting_price_sats'] : -1;
    if ($startingPrice < 1 || $startingPrice > 2100000000000000) {
        throw new Exception('Starting price must be between 1 and 21M BTC in sats', 400);
    }

    $description = sanitizeUserText($data['description'] ?? '', 2000);
    $reserve = isset($data['reserve_price_sats']) ? (int)$data['reserve_price_sats'] : 0;
    if ($reserve < 0 || $reserve > 2100000000000000) {
        throw new Exception('Invalid reserve price', 400);
    }

    $condition = sanitizeUserText($data['condition'] ?? 'mint', 30);
    $serial = sanitizeUserText($data['serial_number'] ?? '', 60);
    $allowedConditions = ['mint', 'near_mint', 'excellent', 'good', 'played', 'poor'];
    if (!in_array(strtolower($condition), $allowedConditions, true)) {
        $condition = 'mint';
    }

    $proofUrl = isset($data['proof_image_url']) ? sanitizeUserText($data['proof_image_url'], 500) : '';
    if ($proofUrl !== '' && strlen($proofUrl) > 500) {
        throw new Exception('Invalid proof image URL', 400);
    }
    $proofBlockHeight = isset($data['proof_block_height']) ? (int)$data['proof_block_height'] : 0;
    if ($proofBlockHeight < 0 || $proofBlockHeight > 9999999) {
        $proofBlockHeight = 0;
    }

    $durationHours = isset($data['duration_hours']) ? (int)$data['duration_hours'] : 0;
    if ($durationHours < 1 || $durationHours > 168) {
        throw new Exception('Auction duration must be between 1 and 168 hours', 400);
    }

    $imageUrls = $data['image_urls'] ?? [];
    if (!is_array($imageUrls)) {
        $imageUrls = [];
    }
    $imageUrls = array_slice($imageUrls, 0, 5);
    foreach ($imageUrls as $url) {
        if (!is_string($url) || strlen($url) > 500) {
            throw new Exception('Invalid image URL', 400);
        }
    }

    $localShipping = isset($data['local_shipping_sats']) ? (int)$data['local_shipping_sats'] : 0;
    $intlShipping = isset($data['intl_shipping_sats']) ? (int)$data['intl_shipping_sats'] : 0;
    if ($localShipping < 0 || $localShipping > 2100000000000000) {
        throw new Exception('Invalid local shipping amount', 400);
    }
    if ($intlShipping < 0 || $intlShipping > 2100000000000000) {
        throw new Exception('Invalid international shipping amount', 400);
    }

    return [
        'card_template_id' => $cardTemplateId,
        'title' => $title,
        'description' => $description,
        'starting_price_sats' => $startingPrice,
        'reserve_price_sats' => $reserve,
        'duration_hours' => $durationHours,
        'condition' => $condition,
        'serial_number' => $serial,
        'proof_image_url' => $proofUrl,
        'proof_block_height' => $proofBlockHeight,
        'image_urls' => $imageUrls,
        'local_shipping_sats' => $localShipping,
        'intl_shipping_sats' => $intlShipping,
    ];
}
