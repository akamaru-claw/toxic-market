<?php
/**
 * Toxic Market — Email Notifications (Basic)
 * Uses PHP mail() for Strato compatibility
 * Can be extended with SMTP or API-based services later
 */

function sendEmail(string $to, string $subject, string $body, string $from = 'Toxic Market <noreply@ml-bets.com>'): bool {
    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: ToxicMarket/1.0',
    ];
    
    // Wrap in basic HTML template
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,sans-serif;background:#0a0a1a;color:#fff;max-width:600px;margin:0 auto;padding:20px;">' .
        '<div style="background:linear-gradient(135deg,#1a1a3a,#0e0e20);border:1px solid #2a2a4a;border-radius:16px;padding:24px;">' .
        '<div style="text-align:center;margin-bottom:20px;"><span style="font-size:28px;">🧪</span><h2 style="color:#f7931a;margin:8px 0 0;">Toxic Market</h2></div>' .
        $body .
        '<hr style="border-color:#2a2a4a;margin:24px 0;">' .
        '<p style="font-size:12px;color:#888;">Toxic Market — P2P Marktplatz für MX12ART Sammelkarten. Kein Custody, keine Haftung.</p>' .
        '</div></body></html>';
    
    return mail($to, $subject, $html, implode("\r\n", $headers));
}

/**
 * Send notification email for key events
 */
function notifyUserEmail(PDO $db, int $userId, string $type, string $title, string $message): void {
    // Get user email
    $stmt = $db->prepare('SELECT email, display_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || empty($user['email'])) return;
    
    // Only send email for important events
    $emailTypes = ['outbid', 'sale', 'auction_won', 'payment_confirmed'];
    if (!in_array($type, $emailTypes)) return;
    
    $subject = match($type) {
        'outbid' => '⚡ Du wurdest überboten! — Toxic Market',
        'sale' => '💰 Karte verkauft! — Toxic Market',
        'auction_won' => '🏆 Auktion gewonnen! — Toxic Market',
        'payment_confirmed' => '✅ Zahlung bestätigt — Toxic Market',
        default => '🧪 Toxic Market — ' . $title,
    };
    
    $body = '<h3 style="color:#f7931a;">' . htmlspecialchars($title) . '</h3>' .
        '<p style="color:#ccc;line-height:1.6;">' . htmlspecialchars($message) . '</p>' .
        '<a href="https://ml-bets.com/toxic-market/dashboard" style="display:inline-block;background:#f7931a;color:#000;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin-top:12px;">Zum Dashboard →</a>';
    
    sendEmail($user['email'], $subject, $body);
}