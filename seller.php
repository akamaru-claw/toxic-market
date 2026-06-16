<?php
/**
 * Toxic Market — Seller Profile Page
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /toxic-market/'); exit; }

$db = getDB();
$stmt = $db->prepare('SELECT id, display_name, bio, avatar_url, reputation_score, total_sales, total_purchases, nostr_pubkey, created_at FROM users WHERE id = ?');
$stmt->execute([$id]);
$seller = $stmt->fetch();
if (!$seller) { header('Location: /toxic-market/'); exit; }

// Active listings
$stmt2 = $db->prepare('SELECT l.*, ct.name as card_name, ct.generation, ct.holo_positions
    FROM listings l JOIN card_templates ct ON l.card_template_id = ct.id
    WHERE l.seller_id = ? AND l.is_sold = 0 ORDER BY l.created_at DESC');
$stmt2->execute([$id]);
$listings = $stmt2->fetchAll();

// Active auctions
$stmt3 = $db->prepare('SELECT a.*, ct.name as card_name, ct.generation,
    (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) as bid_count
    FROM auctions a JOIN card_templates ct ON a.card_template_id = ct.id
    WHERE a.seller_id = ? AND a.status = \'active\' ORDER BY a.ends_at ASC');
$stmt3->execute([$id]);
$auctions = $stmt3->fetchAll();

// Sold items count
$stats = [
    'listings' => count($listings),
    'auctions' => count($auctions),
    'sales' => $seller['total_sales'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#08080f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($seller['display_name']) ?> — Toxic Market</title>
    <link href="/toxic-market/favicon.svg" rel="icon" type="image/svg+xml">
    <link href="/toxic-market/css/toxic.css?v=2" rel="stylesheet">
    <link href="/toxic-market/css/toxic-card.css" rel="stylesheet">
</head>
<body>
    <nav id="nav">
        <div class="nav-inner">
            <a href="/toxic-market/" class="logo"><span class="logo-icon">🧪</span><span class="logo-text">Toxic Market</span></a>
            <div class="nav-links">
                <a href="/toxic-market/#cards">Karten</a>
                <a href="/toxic-market/#listings">Kaufen</a>
                <button id="hamburger" class="hamburger" onclick="toggleMobileNav()">☰</button>
            </div>
        </div>
        <div id="mobile-nav" class="mobile-nav">
            <a href="/toxic-market/#cards">Karten</a>
            <a href="/toxic-market/#listings">Kaufen</a>
            <a href="/toxic-market/#auctions">Auktionen</a>
            <?php if (isLoggedIn()): ?>
            <a href="/toxic-market/dashboard">Dashboard</a>
            <?php else: ?>
            <button class="btn btn-outline btn-full" onclick="showAuth()">Anmelden</button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container" style="max-width:900px; padding: 30px 20px 60px;">
        <a href="/toxic-market/" style="color:var(--text-muted);text-decoration:none;font-size:14px;">← Zurück</a>

        <!-- Profile Header -->
        <div style="background:var(--card-glass);backdrop-filter:blur(10px);border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;margin:24px 0;text-align:center;">
            <div style="width:80px;height:80px;border-radius:50%;background:var(--holo-gradient);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 16px;">👤</div>
            <h1 style="font-size:1.8rem;font-weight:800;margin:0;"><?= htmlspecialchars($seller['display_name']) ?></h1>
            <?php if ($seller['nostr_pubkey']): ?>
            <div style="margin-top:16px;">
                <button class="btn btn-outline btn-sm" onclick="copyNostr()" style="font-size:12px;">
                    🔑 Nostr-Pubkey kopieren
                </button>
            </div>
            <script>
            function copyNostr() {
                navigator.clipboard.writeText('<?= htmlspecialchars($seller['nostr_pubkey']) ?>').then(() => {
                    toast('Nostr-Pubkey kopiert!', 'success');
                });
            }
            </script>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">Mitglied seit <?= date('M Y', strtotime($seller['created_at'])) ?></div>
            
            <?php if ($seller['bio']): ?>
            <p style="color:var(--text-muted);font-size:14px;margin-top:16px;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.6;"><?= htmlspecialchars($seller['bio']) ?></p>
            <?php endif; ?>

            <div style="display:flex;justify-content:center;gap:32px;margin-top:24px;">
                <div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--accent);"><?= $stats['listings'] ?></div>
                    <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;">Angebote</div>
                </div>
                <div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--bitcoin);"><?= $stats['sales'] ?></div>
                    <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;">Verkäufe</div>
                </div>
                <div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--purple);"><?= $stats['auctions'] ?></div>
                    <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;">Auktionen</div>
                </div>
            </div>
        </div>

        <!-- Active Listings -->
        <div style="margin-bottom:40px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:16px;">🏷️ Angebote (<?= count($listings) ?>)</h2>
            <?php if (empty($listings)): ?>
            <p style="color:var(--text-dim);text-align:center;padding:24px;">Keine aktiven Angebote.</p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                <?php foreach ($listings as $l):
                    $genLabel = [1=>'Genesis 2025',2=>'Zitadelle 2026',3=>'Remake EN'][$l['generation']] ?? '';
                    $genClass = "gen-{$l['generation']}";
                    $images = json_decode($l['image_urls'], true) ?: [];
                    $holo = json_decode($l['holo_positions'], true) ?: [];
                    $imgUrl = !empty($images[0]) ? $images[0] : "/toxic-market/cards/card.svg.php?id={$l['card_template_id']}&gen={$l['generation']}&name=" . urlencode($l['card_name']) . "&holo=0";
                ?>
                <a href="/toxic-market/listing/<?= $l['id'] ?>" style="text-decoration:none;display:block;">
                    <div class="listing-item" style="display:flex;gap:14px;">
                        <div style="width:60px;height:80px;border-radius:8px;overflow:hidden;flex-shrink:0;">
                            <img src="<?= htmlspecialchars($imgUrl) ?>" style="width:100%;height:100%;object-fit:contain;" alt="" loading="lazy">
                        </div>
                        <div>
                            <span class="card-gen <?= $genClass ?>" style="font-size:9px;"><?= $genLabel ?></span>
                            <div style="font-weight:600;color:var(--text);font-size:14px;margin-top:2px;"><?= htmlspecialchars($l['title']) ?></div>
                            <div style="color:var(--bitcoin);font-weight:700;font-family:'JetBrains Mono',monospace;font-size:14px;"><?= number_format($l['price_sats']) ?> sats</div>
                            <div style="font-size:11px;color:var(--text-dim);"><?= $l['condition_text'] ?><?= $l['serial_number'] ? ' · #'.$l['serial_number'] : '' ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Active Auctions -->
        <div style="margin-bottom:40px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:16px;">🔨 Auktionen (<?= count($auctions) ?>)</h2>
            <?php if (empty($auctions)): ?>
            <p style="color:var(--text-dim);text-align:center;padding:24px;">Keine aktiven Auktionen.</p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                <?php foreach ($auctions as $a):
                    $genLabel = [1=>'Genesis 2025',2=>'Zitadelle 2026',3=>'Remake EN'][$a['generation']] ?? '';
                    $currentPrice = $a['current_price_sats'] ?? $a['starting_price_sats'];
                ?>
                <a href="/toxic-market/auction/<?= $a['id'] ?>" style="text-decoration:none;display:block;">
                    <div class="listing-item">
                        <div style="font-weight:600;color:var(--text);">🔨 <?= htmlspecialchars($a['title']) ?></div>
                        <div style="color:var(--bitcoin);font-weight:700;font-family:'JetBrains Mono',monospace;"><?= number_format($currentPrice) ?> sats</div>
                        <div style="font-size:11px;color:var(--text-dim);"><?= $a['bid_count'] ?> Gebote · Endet <?= date('d.m. H:i', strtotime($a['ends_at'])) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>🧪 Toxic Market · <strong>Kein Custody, keine Haftung.</strong> P2P-Marktplatz.</p>
        </div>
    </footer>

    <script src="/toxic-market/js/noble-curves-bundle.js?v=1"></script>
    <script src="/toxic-market/js/nostr.js?v=4"></script>
    <script src="/toxic-market/js/toxic.js?v=5"></script>
</body>
</html>