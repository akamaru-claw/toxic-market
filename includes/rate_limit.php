<?php
/**
 * Toxic Market — Rate Limiting
 * IP-based rate limits for auth endpoints and other sensitive actions.
 * Stores attempts in SQLite so they persist across requests.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = $_SERVER[$h];
            // X-Forwarded-For can be a comma-separated list; take the first
            if (strpos($ip, ',') !== false) {
                $parts = array_map('trim', explode(',', $ip));
                $ip = $parts[0];
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function initRateLimitsTable(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        action TEXT NOT NULL,
        attempts INTEGER DEFAULT 1,
        first_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    try {
        $db->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_action ON rate_limits(ip_address, action)');
    } catch (Exception $e) {
        // ignore
    }
}

/**
 * Check if an action is currently rate-limited.
 *
 * @param string $action e.g. 'login', 'register', 'password_reset'
 * @param int $maxAttempts max attempts in the window
 * @param int $windowSeconds window length in seconds
 * @return array ['limited' => bool, 'remaining' => int, 'retry_after' => int]
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $db = getDB();
    initRateLimitsTable($db);

    $ip = getClientIP();
    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    // Clean old rows outside the window for this IP/action
    $db->prepare('DELETE FROM rate_limits WHERE ip_address = ? AND action = ? AND first_attempt_at < ?')
        ->execute([$ip, $action, $windowStart]);

    $stmt = $db->prepare('SELECT SUM(attempts) as total, MAX(last_attempt_at) as last_attempt FROM rate_limits WHERE ip_address = ? AND action = ? AND first_attempt_at >= ?');
    $stmt->execute([$ip, $action, $windowStart]);
    $row = $stmt->fetch();

    $attempts = (int)($row['total'] ?? 0);
    $remaining = max(0, $maxAttempts - $attempts);
    $limited = $attempts >= $maxAttempts;

    $retryAfter = 0;
    if ($limited && !empty($row['last_attempt'])) {
        $last = strtotime($row['last_attempt']);
        $retryAfter = max(0, $windowSeconds - (time() - $last));
    }

    return [
        'limited' => $limited,
        'remaining' => $remaining,
        'retry_after' => $retryAfter,
    ];
}

/**
 * Record a rate-limit attempt. Call after successful validation but before heavy work.
 */
function recordRateLimitAttempt(string $action): void {
    $db = getDB();
    initRateLimitsTable($db);

    $ip = getClientIP();
    $windowStart = date('Y-m-d H:i:s', time() - 900);

    $stmt = $db->prepare('SELECT id, attempts FROM rate_limits WHERE ip_address = ? AND action = ? AND first_attempt_at >= ? ORDER BY last_attempt_at DESC LIMIT 1');
    $stmt->execute([$ip, $action, $windowStart]);
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE rate_limits SET attempts = attempts + 1, last_attempt_at = datetime(\'now\') WHERE id = ?')
            ->execute([$row['id']]);
    } else {
        $db->prepare('INSERT INTO rate_limits (ip_address, action, attempts) VALUES (?, ?, 1)')
            ->execute([$ip, $action]);
    }
}

/**
 * Reset rate-limit counter for an action (e.g. after successful login).
 */
function resetRateLimit(string $action): void {
    $db = getDB();
    initRateLimitsTable($db);
    $db->prepare('DELETE FROM rate_limits WHERE ip_address = ? AND action = ?')
        ->execute([getClientIP(), $action]);
}
