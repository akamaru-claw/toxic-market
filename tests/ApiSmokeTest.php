<?php
/**
 * Toxic Market — API Smoke Tests
 *
 * Run: cd /path/to/toxic-market && php tests/ApiSmokeTest.php
 *
 * These tests exercise the REST API surface with an isolated SQLite database
 * by spinning up PHP's built-in server on localhost and using curl with a
 * cookie jar so session-based auth works end-to-end.
 */

$repoRoot = realpath(__DIR__ . '/..');
$tmpDir = sys_get_temp_dir() . '/toxic-market-api-tests-' . uniqid();
$testDbPath = $tmpDir . '/test_toxic_market.db';
$linkPath = $repoRoot . '/toxic-market';

@mkdir($tmpDir, 0750, true);

// API code expects DOCUMENT_ROOT . '/toxic-market/...' because it is deployed
// under /toxic-market/ on the production server. Create a self-referential
// symlink so the local dev server can resolve the same paths.
if (!is_link($linkPath) && !is_dir($linkPath)) {
    symlink($repoRoot, $linkPath);
}
register_shutdown_function(function () use ($tmpDir, $linkPath) {
    exec('rm -rf ' . escapeshellarg($tmpDir));
    if (is_link($linkPath)) {
        unlink($linkPath);
    }
});

// Copy repo into temp location with isolated data dir
$testRoot = $tmpDir . '/app';
exec('cp -r ' . escapeshellarg($repoRoot) . ' ' . escapeshellarg($testRoot));
$testDbPathInApp = $testRoot . '/data/test_toxic_market.db';
mkdir($testRoot . '/data', 0750, true);
putenv('TOXIC_DB_PATH=' . $testDbPathInApp);
$_ENV['TOXIC_DB_PATH'] = $testDbPathInApp;

// Start PHP dev server inside temp copy
$port = 9876;
$logFile = $tmpDir . '/server.log';
$pidFile = $tmpDir . '/server.pid';
$cmd = sprintf('cd %s && php -S localhost:%d > %s 2>&1 & echo $! > %s',
    escapeshellarg($testRoot), $port, escapeshellarg($logFile), escapeshellarg($pidFile));
exec($cmd);

// Wait for server
$baseUrl = "http://localhost:{$port}/toxic-market";
$start = microtime(true);
$ready = false;
while (microtime(true) - $start < 5) {
    $c = @file_get_contents("{$baseUrl}/api/api.php?action=status");
    if ($c !== false) {
        $ready = true;
        break;
    }
    usleep(50000);
}

if (!$ready) {
    echo "Server failed to start\n";
    echo file_get_contents($logFile);
    exit(1);
}

$passed = 0;
$failed = 0;
$cookieJar = $tmpDir . '/cookies.txt';

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

function apiCall(string $action, array $postData = [], ?string $csrf = null): array {
    global $baseUrl, $cookieJar;
    $url = "{$baseUrl}/api/api.php?action={$action}";
    $parts = ['curl', '-s', '-b', $cookieJar, '-c', $cookieJar, '-H', 'Content-Type: application/json'];
    if ($csrf) {
        $parts[] = '-H';
        $parts[] = 'X-CSRF-Token: ' . $csrf;
    }
    if ($postData) {
        $parts[] = '-X';
        $parts[] = 'POST';
        $parts[] = '-d';
        $parts[] = json_encode($postData);
    }
    $parts[] = $url;
    $escaped = array_map('escapeshellarg', $parts);
    $raw = shell_exec(implode(' ', $escaped));
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}

// 1. Status endpoint returns CSRF token
$status = apiCall('status');
assertTrue('status returns csrf_token', isset($status['csrf_token']) && strlen($status['csrf_token']) > 20, json_encode($status));
$csrf = $status['csrf_token'] ?? '';

// 2. Register a new user
$email = 'apismoke_' . uniqid() . '@example.com';
$password = 'correcthorsebatterystaple';
$reg = apiCall('register', [
    'email' => $email,
    'password' => $password,
    'display_name' => 'ApiSmoker',
    'accept_disclaimer' => true,
    'csrf_token' => $csrf,
], $csrf);
assertTrue('register new user', ($reg['success'] ?? false) === true, json_encode($reg));

// 3. Login
$login = apiCall('login', [
    'email' => $email,
    'password' => $password,
    'csrf_token' => $csrf,
], $csrf);
assertTrue('login registered user', ($login['success'] ?? false) === true, json_encode($login));

// 4. Status after login
$status2 = apiCall('status');
assertTrue('status logged_in after login', ($status2['logged_in'] ?? false) === true, json_encode($status2));
$csrf = $status2['csrf_token'] ?? $csrf;

// 5. Cards endpoint
$cards = apiCall('cards');
assertTrue('cards returns data', isset($cards['data']) && is_array($cards['data']) && count($cards['data']) > 0, json_encode($cards));
$firstCardId = $cards['data'][0]['id'] ?? 1;

// 6. Create listing
$listing = apiCall('create_listing', [
    'card_template_id' => $firstCardId,
    'title' => 'API Smoke Listing',
    'description' => 'Created by ApiSmokeTest',
    'price_sats' => 10000,
    'condition' => 'mint',
    'csrf_token' => $csrf,
], $csrf);
assertTrue('create listing', ($listing['success'] ?? false) === true, json_encode($listing));

// 7. My listings
$myListings = apiCall('my_listings');
assertTrue('my_listings returns data', isset($myListings['data']) && is_array($myListings['data']) && count($myListings['data']) > 0, json_encode($myListings));

// 8. Current block
$block = apiCall('current_block');
assertTrue('current_block returns height', isset($block['block_height']) && is_int($block['block_height']), json_encode($block));

// 9. BTC price
$price = apiCall('btc_price');
$btcEur = $price['eur'] ?? $price['prices']['eur'] ?? null;
assertTrue('btc_price returns eur', is_numeric($btcEur), json_encode($price));

// 10. Logout
$logout = apiCall('logout', ['csrf_token' => $csrf], $csrf);
assertTrue('logout success', ($logout['success'] ?? false) === true, json_encode($logout));

$status3 = apiCall('status');
assertTrue('status logged_out after logout', ($status3['logged_in'] ?? false) === false, json_encode($status3));

// Stop server
if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    if (is_numeric($pid)) {
        posix_kill(intval($pid), SIGTERM);
    }
}

echo "\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
