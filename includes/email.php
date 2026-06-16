<?php
/**
 * Toxic Market — Email Notifications
 *
 * Strato-compatible fallback uses PHP mail().
 * SMTP/SES/Postmark can be enabled by placing a config in data/email_config.json.
 *
 * Config schema (data/email_config.json):
 * {
 *   "transport": "smtp",
 *   "smtp_host": "smtp.strato.de",
 *   "smtp_port": 465,
 *   "smtp_user": "noreply@ml-bets.com",
 *   "smtp_pass": "APP-PASSWORD",
 *   "smtp_secure": "ssl",
 *   "from": "Toxic Market <noreply@ml-bets.com>",
 *   "reply_to": "Toxic Market <noreply@ml-bets.com>"
 * }
 *
 * Environment overrides (useful for testing or CI):
 *   TOXIC_SMTP_HOST, TOXIC_SMTP_PORT, TOXIC_SMTP_USER,
 *   TOXIC_SMTP_PASS, TOXIC_SMTP_SECURE, TOXIC_MAIL_FROM
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/rate_limit.php';

// PHPMailer for SMTP/SES transports (Strato-compatible, no Composer required).
$phpmailerAutoload = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/PHPMailer/PHPMailer.php';
if (file_exists($phpmailerAutoload)) {
    require_once $phpmailerAutoload;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/PHPMailer/SMTP.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/PHPMailer/Exception.php';
}

const EMAIL_LOG_MAX_BYTES = 1048576; // 1 MiB per log file

function getEmailConfig(): array {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data/email_config.json';
    $config = [];
    if (file_exists($path)) {
        $json = file_get_contents($path);
        if ($json !== false) {
            $parsed = json_decode($json, true);
            if (is_array($parsed)) $config = $parsed;
        }
    }

    // Allow environment overrides for CI/local testing without touching data file.
    $envMap = [
        'TOXIC_SMTP_HOST' => 'smtp_host',
        'TOXIC_SMTP_PORT' => 'smtp_port',
        'TOXIC_SMTP_USER' => 'smtp_user',
        'TOXIC_SMTP_PASS' => 'smtp_pass',
        'TOXIC_SMTP_SECURE' => 'smtp_secure',
        'TOXIC_MAIL_FROM' => 'from',
    ];
    foreach ($envMap as $env => $key) {
        $value = getenv($env);
        if ($value !== false && $value !== '') {
            $config[$key] = $value;
        }
    }
    if (isset($config['smtp_port'])) {
        $config['smtp_port'] = (int) $config['smtp_port'];
    }

    return $config;
}

function rotateEmailLog(string $logPath): void {
    if (!file_exists($logPath) || filesize($logPath) < EMAIL_LOG_MAX_BYTES) return;
    $rotated = $logPath . '.1';
    if (file_exists($rotated)) @unlink($rotated);
    @rename($logPath, $rotated);
}

function logEmail(string $message): void {
    $logDir = $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/data';
    $logPath = $logDir . '/email.log';
    rotateEmailLog($logPath);
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

function isValidFromAddress(string $from): bool {
    // Only allow @ml-bets.com sender addresses to prevent spoofing.
    return (bool) preg_match('/<[^>]+@ml-bets\.com>$/i', $from);
}

function sendEmail(string $to, string $subject, string $body, string $from = 'Toxic Market <noreply@ml-bets.com>'): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmail("REJECT invalid recipient: {$to}");
        return false;
    }
    if (!isValidFromAddress($from)) {
        logEmail("REJECT invalid from address: {$from}");
        return false;
    }

    $limit = checkRateLimit('send_email', 20, 3600);
    if ($limit['limited']) {
        logEmail("RATE-LIMIT send_email to {$to}: retry_after={$limit['retry_after']}");
        return false;
    }

    $config = getEmailConfig();
    $transport = $config['transport'] ?? 'mail';

    $headers = [
        'From: ' . $from,
        'Reply-To: ' . ($config['reply_to'] ?? $from),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: ToxicMarket/1.0',
    ];

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,sans-serif;background:#0a0a1a;color:#fff;max-width:600px;margin:0 auto;padding:20px;">' .
        '<div style="background:linear-gradient(135deg,#1a1a3a,#0e0e20);border:1px solid #2a2a4a;border-radius:16px;padding:24px;">' .
        '<div style="text-align:center;margin-bottom:20px;"><span style="font-size:28px;">🧪</span><h2 style="color:#f7931a;margin:8px 0 0;">Toxic Market</h2></div>' .
        $body .
        '<hr style="border-color:#2a2a4a;margin:24px 0;">' .
        '<p style="font-size:12px;color:#888;">Toxic Market — P2P Marktplatz für MX12ART Sammelkarten. Kein Custody, keine Haftung.</p>' .
        '</div></body></html>';

    // Prefer SMTP if PHPMailer is available and config is complete.
    if ($transport === 'smtp' && !empty($config['smtp_host']) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->Port = (int)($config['smtp_port'] ?? 587);
            $mail->SMTPAuth = !empty($config['smtp_user']);
            if ($mail->SMTPAuth) {
                $mail->Username = $config['smtp_user'];
                $mail->Password = $config['smtp_pass'] ?? '';
            }
            $secure = $config['smtp_secure'] ?? '';
            if (in_array(strtolower($secure), ['ssl', 'tls'], true)) {
                $mail->SMTPSecure = strtolower($secure);
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(preg_replace('/^.*<|>.*$/', '', $from), preg_replace('/<.*$/', '', $from));
            $mail->addAddress($to);
            $mail->addReplyTo(preg_replace('/^.*<|>.*$/', '', $config['reply_to'] ?? $from));
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->XMailer = 'ToxicMarket/1.0';
            $result = $mail->send();
            recordRateLimitAttempt('send_email');
            logEmail(($result ? 'OK' : 'FAIL') . " smtp to {$to} subject={$subject}");
            return $result;
        } catch (Exception $e) {
            logEmail("SMTP error for {$to}: " . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            logEmail("SMTP fatal for {$to}: " . $e->getMessage());
            return false;
        }
    }

    $result = mail($to, $subject, $html, implode("\r\n", $headers));
    recordRateLimitAttempt('send_email');
    logEmail(($result ? 'OK' : 'FAIL') . " mail() to {$to} subject={$subject}");
    return $result;
}

/**
 * Send notification email for key events
 */
function notifyUserEmail(PDO $db, int $userId, string $type, string $title, string $message): void {
    $stmt = $db->prepare('SELECT email, display_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || empty($user['email'])) return;

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
