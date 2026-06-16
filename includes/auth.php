<?php
/**
 * Toxic Market — Auth System
 * Nostr (NIP-07/46) + Email/Password
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookies before starting the session.
    // Secure flag only when HTTPS is actually used; on plain HTTP it would drop the cookie.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days
        'path' => '/toxic-market/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('SESSION_DURATION', 86400 * 30); // 30 days

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): array {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    return currentUser();
}

function isAdmin(?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/admin_users.json';
    if (!file_exists($configFile)) {
        // Default admin fallback: original project admin email
        return ($user['email'] === 'akamaru.claw@gmx.de');
    }
    $admins = json_decode(file_get_contents($configFile), true);
    if (!is_array($admins)) return false;
    return in_array($user['email'], $admins, true);
}

function loginWithEmail(string $email, string $password): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    
    // Regenerate session ID on successful login to prevent session fixation.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'email';
    return $user;
}

function loginWithNostr(string $pubkey): ?array {
    // SECURITY: Nostr login currently disabled because no Schnorr signature verification is available server-side.
    // Without signature verification, anyone knowing a pubkey can take over the account.
    // TODO: implement challenge-response with verified BIP-340 Schnorr signature before re-enabling.
    return null;
}

function nostrLoginDisabled(): bool {
    return true;
}

function registerWithEmail(string $email, string $password, string $displayName): ?array {
    $db = getDB();
    
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) return null;
    
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$email, $displayName, $hash]);
    
    $userId = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Regenerate session ID on successful registration to prevent session fixation.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'email';
    return $user;
}

function logout(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        session_destroy();
    }
    // Best-effort deletion of the session cookie on the client.
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $params['path'] ?? '/toxic-market/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }
}

/**
 * Return basic session metadata for diagnostics (admin-only).
 * Does NOT expose the session ID or secrets.
 */
function sessionSecurityInfo(): array {
    $params = session_get_cookie_params();
    return [
        'httponly' => !empty($params['httponly']),
        'secure' => !empty($params['secure']),
        'samesite' => $params['samesite'] ?? 'Not set',
        'cookie_path' => $params['path'] ?? '/',
    ];
}

function generateResetToken(string $email): ?string {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return null;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Create reset_tokens table if not exists
    $db->exec('CREATE TABLE IF NOT EXISTS reset_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token TEXT, expires_at DATETIME, used INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id))');
    
    $stmt = $db->prepare('INSERT INTO reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $token, $expires]);
    
    return $token;
}

function verifyResetToken(string $token): ?array {
    $db = getDB();
    $db->exec('CREATE TABLE IF NOT EXISTS reset_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token TEXT, expires_at DATETIME, used INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id))');
    
    $stmt = $db->prepare('SELECT rt.*, u.email FROM reset_tokens rt JOIN users u ON rt.user_id = u.id WHERE rt.token = ? AND rt.used = 0 AND rt.expires_at > datetime(\'now\')');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function resetPassword(string $token, string $newPassword): bool {
    if (strlen($newPassword) < 8) {
        throw new Exception('Password must be at least 8 characters', 400);
    }
    $reset = verifyResetToken($token);
    if (!$reset) return false;
    
    $db = getDB();
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $reset['user_id']]);
    $db->prepare('UPDATE reset_tokens SET used = 1 WHERE token = ?')->execute([$token]);
    
    return true;
}

function generateCSRF(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}