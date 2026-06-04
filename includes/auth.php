<?php
/**
 * Toxic Market — Auth System
 * Nostr (NIP-07/46) + Email/Password
 */

require_once __DIR__ . '/db.php';

session_start();

define('SESSION_DURATION', 86400 * 30); // 30 days

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    return $db->prepare('SELECT * FROM users WHERE id = ?')->fetch([$_SESSION['user_id']]);
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
        // Auto-create user from Nostr
        $stmt = $db->prepare('INSERT INTO users (nostr_pubkey, display_name) VALUES (?, ?)');
        $stmt->execute([$pubkey, 'nostr_' . substr($pubkey, 0, 8)]);
        $user = $db->prepare('SELECT * FROM users WHERE id = ?')->fetch([$db->lastInsertId()]);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'nostr';
    return $user;
}

function registerWithEmail(string $email, string $password, string $displayName): ?array {
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) return null;
    
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$email, $displayName, $hash]);
    
    $user = $db->prepare('SELECT * FROM users WHERE id = ?')->fetch([$db->lastInsertId()]);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['auth_method'] = 'email';
    return $user;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function generateCSRF(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}