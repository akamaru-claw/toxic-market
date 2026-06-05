<?php
/**
 * Toxic Market — Auth System
 * Nostr (NIP-07/46) + Email/Password
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

session_start();

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

function loginWithEmail(string $email, string $password): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'email';
    return $user;
}

function loginWithNostr(string $pubkey): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE nostr_pubkey = ?');
    $stmt->execute([$pubkey]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $db->prepare('INSERT INTO users (nostr_pubkey, display_name) VALUES (?, ?)');
        $stmt->execute([$pubkey, 'nostr_' . substr($pubkey, 0, 8)]);
        $userId = $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'nostr';
    return $user;
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
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'email';
    return $user;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
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