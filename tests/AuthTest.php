<?php
/**
 * Toxic Market — Smoke Tests for Auth + Validation
 *
 * Run: cd /path/to/toxic-market && php tests/AuthTest.php
 *
 * These tests exercise pure PHP helpers with an isolated SQLite database.
 */

// Override DB path via environment variable before loading db.php.
$tmpDir = sys_get_temp_dir() . '/toxic-market-tests-' . uniqid();
mkdir($tmpDir, 0750, true);
$testDbPath = $tmpDir . '/test_toxic_market.db';
putenv('TOXIC_DB_PATH=' . $testDbPath);
$_ENV['TOXIC_DB_PATH'] = $testDbPath;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/email.php';

$db = getDB();
initDB();

$passed = 0;
$failed = 0;

function assertTrue(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ {$name}\n";
        $passed++;
    } else {
        echo "❌ {$name}" . ($details ? " — {$details}" : '') . "\n";
        $failed++;
    }
}

// 1. Login validation
$hash = password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
$stmt->execute(['test@example.com', 'Tester', $hash]);

$user = loginWithEmail('test@example.com', 'correcthorse');
assertTrue('Login with correct password', $user !== null && $user['email'] === 'test@example.com');

assertTrue('Login with wrong password', loginWithEmail('test@example.com', 'wrong') === null);

// 2. Password reset token generation
$token = generateResetToken('test@example.com');
assertTrue('Reset token generated', is_string($token) && strlen($token) === 64);

$reset = verifyResetToken($token);
assertTrue('Reset token verified', $reset !== null && $reset['email'] === 'test@example.com');

// 3. Listing payload validation
try {
    validateListingPayload([
        'title' => 'Valid Listing',
        'card_template_id' => 1,
        'price_sats' => 10000,
    ], $db);
    assertTrue('Listing payload valid', true);
} catch (Exception $e) {
    assertTrue('Listing payload valid', false, $e->getMessage());
}

try {
    validateListingPayload([
        'title' => '',
        'card_template_id' => 1,
        'price_sats' => 10000,
    ], $db);
    assertTrue('Listing title required', false, 'Expected exception');
} catch (Exception $e) {
    assertTrue('Listing title required', $e->getCode() === 400 && str_contains($e->getMessage(), 'Title'));
}

try {
    validateListingPayload([
        'title' => 'Bad Price',
        'card_template_id' => 1,
        'price_sats' => 0,
    ], $db);
    assertTrue('Listing price min 1 sat', false, 'Expected exception');
} catch (Exception $e) {
    assertTrue('Listing price min 1 sat', $e->getCode() === 400 && str_contains($e->getMessage(), 'Price'));
}

// 4. Auction payload validation
try {
    validateAuctionPayload([
        'title' => 'Valid Auction',
        'card_template_id' => 1,
        'starting_price_sats' => 1000,
        'duration_hours' => 24,
    ], $db);
    assertTrue('Auction payload valid', true);
} catch (Exception $e) {
    assertTrue('Auction payload valid', false, $e->getMessage());
}

try {
    validateAuctionPayload([
        'title' => 'Too Long',
        'card_template_id' => 1,
        'starting_price_sats' => 1000,
        'duration_hours' => 200,
    ], $db);
    assertTrue('Auction duration max 168h', false, 'Expected exception');
} catch (Exception $e) {
    assertTrue('Auction duration max 168h', $e->getCode() === 400 && str_contains($e->getMessage(), 'duration'));
}

// 5. Admin helper
assertTrue('Non-admin user detected', !isAdmin($user));
$adminUser = ['id' => 99, 'email' => 'akamaru.claw@gmx.de'];
assertTrue('Admin fallback for default admin', isAdmin($adminUser));

// 7. E-Mail helper validation
assertTrue('Email invalid recipient rejected', sendEmail('not-an-email', 'Subject', 'Body') === false);
assertTrue('Email invalid sender rejected', sendEmail('user@example.com', 'Subject', 'Body', 'Evil <attacker@example.com>') === false);

putenv('TOXIC_SMTP_HOST=smtp.example.com');
putenv('TOXIC_SMTP_PORT=587');
putenv('TOXIC_SMTP_USER=test@example.com');
putenv('TOXIC_SMTP_SECURE=tls');
$emailConfig = getEmailConfig();
assertTrue('Email config env override host', ($emailConfig['smtp_host'] ?? '') === 'smtp.example.com');
assertTrue('Email config env override port', ($emailConfig['smtp_port'] ?? 0) === 587);
assertTrue('Email config env override secure', ($emailConfig['smtp_secure'] ?? '') === 'tls');
putenv('TOXIC_SMTP_HOST');
putenv('TOXIC_SMTP_PORT');
putenv('TOXIC_SMTP_USER');
putenv('TOXIC_SMTP_SECURE');

// notifyUserEmail with unknown type should not crash or send
$originalUser = currentUser();
notifyUserEmail($db, 1, 'unknown_type', 'Title', 'Message');
assertTrue('notifyUserEmail unknown type handled', true);

// Cleanup
exec('rm -rf ' . escapeshellarg($tmpDir));

echo "\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
