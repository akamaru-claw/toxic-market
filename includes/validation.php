<?php
/**
 * Toxic Market — Payload Validation Helpers
 *
 * Shared between api/api.php and tests.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

/**
 * Max upload size in bytes.
 */
const TOXIC_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

/**
 * Max allowed image dimensions.
 */
const TOXIC_UPLOAD_MIN_WIDTH  = 32;
const TOXIC_UPLOAD_MIN_HEIGHT = 32;
const TOXIC_UPLOAD_MAX_WIDTH  = 4096;
const TOXIC_UPLOAD_MAX_HEIGHT = 4096;

/**
 * Allowed image MIME types and their canonical extensions.
 */
const TOXIC_UPLOAD_ALLOWED_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];

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

/**
 * UploadException — dedicated exception type for image uploads.
 */
class UploadException extends Exception {}

/**
 * Validate and harden an uploaded image file.
 *
 * Checks the real image type via exif_imagetype()/getimagesize(), enforces
 * minimum/maximum dimensions and file size, then re-encodes the image with GD
 * to strip EXIF metadata and eliminate polyglots. The final image is written
 * to an entropic filename owned by the uploading user.
 *
 * @param array $file The $_FILES entry.
 * @param int   $userId The uploading user's id.
 * @return array ['path' => absolute path, 'url' => public URL, 'filename' => sanitized filename]
 * @throws UploadException on validation or processing failure.
 */
function validateUploadedImage(array $file, int $userId): array {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new UploadException('No file uploaded', 400);
    }
    if ($file['size'] > TOXIC_UPLOAD_MAX_BYTES) {
        throw new UploadException('File too large (max 5 MiB)', 413);
    }

    $tmp = $file['tmp_name'];

    // Determine real image type. Prefer exif_imagetype(); fall back to getimagesize().
    $type = false;
    if (function_exists('exif_imagetype')) {
        $type = @exif_imagetype($tmp);
    }
    if ($type === false) {
        $info = @getimagesize($tmp);
        $type = $info[2] ?? false;
    }

    if ($type === false || !isset(TOXIC_UPLOAD_ALLOWED_TYPES[$type])) {
        throw new UploadException('Only JPG, PNG, WebP allowed', 400);
    }

    $ext = TOXIC_UPLOAD_ALLOWED_TYPES[$type];

    // Validate dimensions, if the info is available.
    $info = @getimagesize($tmp);
    if (!is_array($info)) {
        throw new UploadException('Could not read image dimensions', 400);
    }

    $width  = $info[0];
    $height = $info[1];
    if ($width < TOXIC_UPLOAD_MIN_WIDTH || $height < TOXIC_UPLOAD_MIN_HEIGHT) {
        throw new UploadException(
            'Image too small (min ' . TOXIC_UPLOAD_MIN_WIDTH . 'x' . TOXIC_UPLOAD_MIN_HEIGHT . ')',
            400
        );
    }
    if ($width > TOXIC_UPLOAD_MAX_WIDTH || $height > TOXIC_UPLOAD_MAX_HEIGHT) {
        throw new UploadException(
            'Image too large (max ' . TOXIC_UPLOAD_MAX_WIDTH . 'x' . TOXIC_UPLOAD_MAX_HEIGHT . ')',
            400
        );
    }

    // Re-encode with GD to strip metadata and neutralise polyglots. The
    // resulting binary is held in memory first, then atomically written to
    // the upload directory. If GD is not available we reject the upload rather
    // than storing a potentially unsafe original.
    if (!extension_loaded('gd')) {
        throw new UploadException('Image processing extension not available', 500);
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        default          => false,
    };

    if (!$src) {
        @unlink($tmp);
        throw new UploadException('Could not decode image', 400);
    }

    // Preserve transparency for PNG/WebP.
    if (in_array($ext, ['png', 'webp'], true)) {
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }

    ob_start();
    $ok = match ($ext) {
        'jpg'  => @imagejpeg($src, null, 92),
        'png'  => @imagepng($src, null, 6),
        'webp' => @imagewebp($src, null, 92),
    };
    imagedestroy($src);
    @unlink($tmp);

    $binary = ob_get_clean();
    if (!$ok || $binary === false || $binary === '') {
        throw new UploadException('Image re-encode failed', 500);
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    // Entropic filename with user-id marker for auditability.
    $filename = bin2hex(random_bytes(16)) . '_' . $userId . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (file_put_contents($filepath, $binary, LOCK_EX) === false) {
        throw new UploadException('Upload failed', 500);
    }
    chmod($filepath, 0640);

    logUpload($filename, $userId, $width, $height, $ext, strlen($binary));

    return [
        'path'     => $filepath,
        'url'      => '/toxic-market/uploads/' . $filename,
        'filename' => $filename,
    ];
}

/**
 * Delete an uploaded image by filename, only if it belongs to the given user.
 *
 * @throws UploadException
 */
function deleteUploadedImage(string $filename, int $userId): bool {
    if (!preg_match('/^[a-f0-9]{16}_\d+\.(jpg|png|webp)$/', $filename)) {
        throw new UploadException('Invalid filename', 400);
    }

    $filepath = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/uploads/' . $filename;
    if (!file_exists($filepath)) {
        return false;
    }

    $ownerMatch = preg_match('/_(\d+)\./', $filename, $m) && (int)$m[1] === $userId;
    if (!$ownerMatch) {
        throw new UploadException('Not authorized to delete this file', 403);
    }

    return unlink($filepath);
}

function logUpload(string $filename, int $userId, int $width, int $height, string $ext, int $bytes): void {
    $logDir = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    $line = sprintf(
        "%s upload user=%d file=%s size=%d dims=%dx%d type=%s\n",
        date('Y-m-d H:i:s'),
        $userId,
        $filename,
        $bytes,
        $width,
        $height,
        $ext
    );
    @file_put_contents($logDir . '/uploads.log', $line, FILE_APPEND | LOCK_EX);
}
