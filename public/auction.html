<?php
/**
 * Toxic Market — Auction Detail Page
 * Phase 5: Lightning bid deposits, live countdown, outbid notifications
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/payments.php';

$db = getDB();

$aucId = $_GET['id'] ?? '';
if (!$aucId) { header('Location: /toxic-market/'); exit; }

$stmt = $db->prepare('SELECT a.*, ct.name as card_name, ct.generation, ct.description as card_desc, ct.holo_positions, ct.total_print_run, ct.image_url as card_image,
    u.display_name as seller_name, u.bio as seller_bio, u.id as seller_id, u.created_at as seller_since, u.total_sales
    FROM auctions a 
    JOIN card_templates ct ON a.card_template_id = ct.id
    JOIN users u ON a.seller_id = u.id
    WHERE a.id = ?');
$stmt->execute([$aucId]);
$auction = $stmt->fetch();
if (!$auction) { header('Location: /toxic-market/'); exit; }

// Get bids
$stmt2 = $db->prepare('SELECT b.*, u.display_name as bidder_name FROM bids b JOIN users u ON b.bidder_id = u.id WHERE b.auction_id = ? ORDER BY b.amount_sats DESC LIMIT 10');
$stmt2->execute([$aucId]);
$bids = $stmt2->fetchAll();

// Get user's highest bid (if logged in)
$userHighestBid = null;
$userIsHighestBidder = false;
if (isLoggedIn()) {
    $user = currentUser();
    $stmt3 = $db->prepare('SELECT * FROM bids WHERE auction_id = ? AND bidder_id = ? ORDER BY amount_sats DESC LIMIT 1');
    $stmt3->execute([$aucId, $user['id']]);
    $userHighestBid = $stmt3->fetch();
    if (!empty($bids) && $bids[0]['bidder_id'] == $user['id']) {
        $userIsHighestBidder = true;
    }
}

$images = json_decode($auction['image_urls'], true) ?: [];
$gen = [1=>'Genesis 2025',2=>'Zitadelle 2026',3=>'Remake EN'][$auction['generation']] ?? '';
$holo = json_decode($auction['holo_positions'], true) ?: [];
$conditionLabels = ['mint'=>'Mint (M)','near_mint'=>'Near Mint (NM)','excellent'=>'Excellent (EX)','good'=>'Good (G)','played'=>'Played (P)'];
$conditionLabel = $conditionLabels[$auction['condition_text']] ?? $auction['condition_text'];
$starts = new DateTime($auction['starts_at']);
$ends = new DateTime($auction['ends_at']);
$now = new DateTime();
$isLive = ($starts <= $now && $ends >= $now && $auction['status'] === 'active');
$isEnded = ($ends < $now || $auction['status'] === 'ended');
$timeLeft = $ends->diff($now);
$currentPrice = $auction['current_price_sats'] ?? $auction['starting_price_sats'];
$minBid = $currentPrice + 500;
$depositSats = max(1000, (int)($currentPrice * 0.05));

// BTC price
$prices = getBtcPrice();
$priceEur = satsToEur($currentPrice, $prices['eur']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#08080f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>🔨 <?= htmlspecialchars($auction['title']) ?> — Toxic Market</title>
    <meta name="description" content="<?= htmlspecialchars($auction['description'] ?: $auction['card_desc']) ?> — Auktion auf Toxic Market">
    <meta property="og:title" content="🔨 <?= htmlspecialchars($auction['title']) ?> — Toxic Market Auktion">
    <meta property="og:description" content="Aktuelles Gebot: <?= number_format($currentPrice) ?> sats — <?= htmlspecialchars($auction['card_desc']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://ml-bets.com/toxic-market/auction/<?= $aucId ?>">
    <meta property="og:site_name" content="Toxic Market">
    <meta name="twitter:card" content="summary_large_image">
    <link href="/toxic-market/favicon.svg" rel="icon" type="image/svg+xml">
    <link href="/toxic-market/css/toxic.css?v=2" rel="stylesheet">
    <link href="/toxic-market/css/toxic-card.css" rel="stylesheet">
</head>
<body>
    <nav id="nav">
        <div class="nav-inner">
            <a href="/toxic-market/" class="logo"><span class="logo-icon">🧪</span><span class="logo-text">Toxic Market</span></a>
            <div class="nav-links">
                <a href="/toxic-market/#cards" class="desktop-link">Karten</a>
                <a href="/toxic-market/#auctions" class="desktop-link">Auktionen</a>
                <?php if (isLoggedIn()): ?>
                <span class="desktop-link" style="color:var(--accent);font-weight:600;font-size:14px;"><?= htmlspecialchars(currentUser()['display_name']) ?></span>
                <a href="/toxic-market/dashboard" class="desktop-link">📊 Dashboard</a>
                <button class="btn btn-sm" onclick="logout()">✕</button>
                <?php else: ?>
                <button class="btn btn-outline btn-sm" onclick="showAuth()">Anmelden</button>
                <?php endif; ?>
                <button class="hamburger" onclick="toggleMobileNav()">☰</button>
            </div>
        </div>
        <div id="mobile-nav" class="mobile-nav">
            <a href="/toxic-market/#cards">🃏 Karten</a>
            <a href="/toxic-market/#listings">🏷️ Kaufen</a>
            <a href="/toxic-market/#auctions">🔨 Auktionen</a>
            <?php if (isLoggedIn()): ?>
            <a href="/toxic-market/dashboard">📊 Dashboard</a>
            <?php else: ?>
            <a href="#" onclick="showAuth();toggleMobileNav();return false;">🔑 Anmelden</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Notification bar -->
    <div id="notification-bar" style="display:none;position:fixed;top:0;left:0;right:0;z-index:10000;padding:12px 20px;text-align:center;font-weight:600;font-size:14px;"></div>

    <div class="container" style="max-width:1000px; padding: 30px 20px 60px;">
        <a href="/toxic-market/#auctions" style="color:var(--text-muted);text-decoration:none;font-size:14px;">← Zurück zu Auktionen</a>

        <!-- Auction Status Banner -->
        <div style="margin:20px 0;padding:14px 20px;border-radius:12px;text-align:center;
            <?php if ($isLive): ?>background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.2);<?php elseif ($isEnded): ?>background:rgba(255,68,85,0.08);border:1px solid rgba(255,68,85,0.2);<?php else: ?>background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.2);<?php endif; ?>">
            <?php if ($isLive): ?>
            <span style="color:var(--accent);font-weight:700;font-size:16px;">🔴 LIVE</span>
            <span style="color:var(--text-muted);margin-left:12px;">Endet in <strong id="countdown" style="color:var(--text);"><?= $timeLeft->days ?>T <?= $timeLeft->h ?>h <?= $timeLeft->i ?>m</strong></span>
            <?php elseif ($isEnded): ?>
            <span style="color:var(--danger);font-weight:700;font-size:16px;">⏹ BEENDET</span>
            <?php else: ?>
            <span style="color:var(--bitcoin);font-weight:700;font-size:16px;">⏳ Startet am <?= $starts->format('d.m.Y H:i') ?></span>
            <?php endif; ?>
        </div>

        <!-- Outbid notification -->
        <?php if (!$userIsHighestBidder && $userHighestBid): ?>
        <div style="margin:0 0 16px;padding:12px 16px;background:rgba(255,68,85,0.1);border:1px solid rgba(255,68,85,0.3);border-radius:10px;">
            ⚠️ Du wurdest überboten! Dein Gebot: <?= number_format($userHighestBid['amount_sats']) ?> sats — Biete höher!
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;" class="card-detail-grid">
            <!-- Images -->
            <div>
                <div class="card-detail-image" style="width:100%;aspect-ratio:3/4;background:linear-gradient(145deg,#1a1a3a,#0e0e20);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:80px;overflow:hidden;border:1px solid var(--border);">
                    <?php if (!empty($images) && $images[0]): ?>
                    <img src="<?= htmlspecialchars($images[0]) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                    <?php else: ?>
                    🧪
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details -->
            <div>
                <span class="card-gen gen-<?= $auction['generation'] ?>" style="margin-bottom:8px;"><?= $gen ?></span>
                <?php if ($auction['serial_number']): ?>
                <span style="background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.2);padding:2px 10px;border-radius:10px;font-size:11px;color:var(--accent);margin-left:6px;">#<?= htmlspecialchars($auction['serial_number']) ?>/<?= $auction['total_print_run'] ?></span>
                <?php endif; ?>
                <h1 style="font-size:1.5rem;font-weight:800;margin:8px 0;"><?= htmlspecialchars($auction['title']) ?></h1>
                
                <!-- Current Bid -->
                <div style="background:var(--bg);border:1px solid var(--bitcoin);border-radius:16px;padding:24px;margin:20px 0;text-align:center;">
                    <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">
                        <?php if (count($bids) > 0): ?>Aktuelles Gebot<?php else: ?>Startpreis<?php endif; ?>
                    </div>
                    <div id="auction-price-display" style="font-size:2.8rem;font-weight:900;color:var(--bitcoin);font-family:'JetBrains Mono',monospace;transition:transform 0.3s;">
                        <?= number_format($currentPrice) ?>
                    </div>
                    <div style="font-size:14px;color:var(--text-dim);margin-top:4px;" id="price-eur">≈ €<?= number_format($priceEur, 2) ?></div>
                    <div style="font-size:12px;color:var(--text-dim);margin-top:8px;">
                        Mindestgebot: <strong style="color:var(--text);"><?= number_format($minBid) ?> sats</strong>
                        · Deposit: <strong style="color:var(--bitcoin);"><?= number_format($depositSats) ?> sats</strong>
                    </div>
                </div>

                <!-- Bid Form (Phase 5: AJAX with Lightning deposit) -->
                <?php if ($isLive): ?>
                <?php if (isLoggedIn() && currentUser()['id'] != $auction['seller_id']): ?>
                <div id="bid-section" style="margin:16px 0;">
                    <div style="display:flex;gap:10px;">
                        <input type="number" inputmode="numeric" pattern="[0-9]*" id="bid-amount" min="<?= $minBid ?>" value="<?= $minBid ?>" step="500"
                            style="flex:1;padding:12px 16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;font-size:16px;font-family:'JetBrains Mono',monospace;">
                        <button id="bid-btn" class="btn btn-bitcoin" style="padding:12px 24px;font-size:16px;" onclick="placeBidWithDeposit()">🔨 Bieten</button>
                    </div>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:6px;">
                        Min. <?= number_format($minBid) ?> sats · Deposit <?= number_format($depositSats) ?> sats via Lightning
                    </div>
                </div>

                <!-- Deposit Payment Modal -->
                <div id="deposit-modal" class="modal hidden">
                    <div class="modal-backdrop" onclick="closeDepositModal()"></div>
                    <div class="modal-content" style="max-width:440px;">
                        <button class="modal-close" onclick="closeDepositModal()">✕</button>
                        <h2 style="font-size:1.3rem;margin-bottom:8px;">⚡ Deposit bezahlen</h2>
                        <div id="deposit-amount" style="font-size:2rem;font-weight:900;color:var(--bitcoin);font-family:'JetBrains Mono',monospace;text-align:center;margin:16px 0;"></div>
                        <div id="deposit-bid-info" style="text-align:center;font-size:14px;color:var(--text-muted);margin-bottom:12px;"></div>
                        <div id="deposit-status" style="text-align:center;margin-bottom:16px;color:var(--text-muted);font-size:14px;">Erstelle Lightning-Invoice...</div>
                        <div id="deposit-qr" style="text-align:center;display:none;">
                            <img id="deposit-qr-img" style="max-width:280px;border-radius:12px;margin:12px auto;" alt="QR Code">
                            <div id="deposit-invoice-text" style="font-family:'JetBrains Mono',monospace;font-size:10px;word-break:break-all;color:var(--text-muted);background:var(--bg);padding:10px;border-radius:8px;margin-top:8px;max-height:80px;overflow-y:auto;"></div>
                            <button class="btn btn-sm" style="margin-top:8px;" onclick="copyDepositInvoice()">📋 Invoice kopieren</button>
                        </div>
                        <div id="deposit-onchain" style="display:none;margin-top:16px;padding:16px;background:var(--bg);border-radius:12px;">
                            <div style="font-size:13px;font-weight:600;margin-bottom:8px;">🔗 Onchain-Alternative</div>
                            <div id="deposit-onchain-addr" style="font-family:'JetBrains Mono',monospace;font-size:11px;word-break:break-all;color:var(--accent);"></div>
                        </div>
                        <button class="btn btn-sm" style="margin-top:12px;width:100%;" onclick="checkDepositStatus()">🔄 Zahlungsstatus prüfen</button>
                        <div id="deposit-result" style="display:none;margin-top:16px;padding:16px;border-radius:12px;text-align:center;"></div>
                    </div>
                </div>

                <?php elseif (!isLoggedIn()): ?>
                <div style="text-align:center;padding:16px;background:var(--bg-elevated);border-radius:12px;border:1px solid var(--border);">
                    <p style="color:var(--text-muted);font-size:14px;">Melde dich an, um mitzubieten</p>
                    <button class="btn btn-primary" style="margin-top:8px;" onclick="showAuth()">Anmelden</button>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:12px;background:var(--bg-elevated);border-radius:12px;border:1px solid var(--border);">
                    <p style="color:var(--text-muted);font-size:13px;">Du kannst nicht auf eigene Auktionen bieten.</p>
                </div>
                <?php endif; ?>
                <?php elseif ($isEnded): ?>
                <?php if (!empty($bids) && $bids[0]['bidder_id'] == ($user['id'] ?? 0)): ?>
                <div style="text-align:center;padding:20px;background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.3);border-radius:12px;">
                    <span style="font-size:24px;">🏆</span>
                    <div style="font-weight:700;color:var(--accent);margin-top:8px;">Du hast gewonnen!</div>
                    <div style="font-size:14px;color:var(--text-muted);margin-top:4px;">Kontaktiere den Verkäufer für die Zahlungsabwicklung.</div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Auction Info -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;font-size:13px;">
                    <div style="background:var(--bg-elevated);border-radius:8px;padding:12px;">
                        <div style="color:var(--text-dim);">Zustand</div>
                        <div style="color:var(--text);font-weight:600;"><?= $conditionLabel ?></div>
                    </div>
                    <div style="background:var(--bg-elevated);border-radius:8px;padding:12px;">
                        <div style="color:var(--text-dim);">Gebote</div>
                        <div style="color:var(--text);font-weight:600;" class="bid-count"><?= count($bids) ?></div>
                    </div>
                    <div style="background:var(--bg-elevated);border-radius:8px;padding:12px;">
                        <div style="color:var(--text-dim);">Start</div>
                        <div style="color:var(--text);font-weight:600;"><?= $starts->format('d.m. H:i') ?></div>
                    </div>
                    <div style="background:var(--bg-elevated);border-radius:8px;padding:12px;">
                        <div style="color:var(--text-dim);">Ende</div>
                        <div style="color:var(--text);font-weight:600;"><?= $ends->format('d.m. H:i') ?></div>
                    </div>
                </div>

                <?php if ($auction['description']): ?>
                <div style="margin:16px 0;">
                    <div style="font-size:13px;font-weight:600;margin-bottom:8px;">📝 Beschreibung</div>
                    <p style="font-size:14px;color:var(--text-muted);line-height:1.6;"><?= nl2br(htmlspecialchars($auction['description'])) ?></p>
                </div>
                <?php endif; ?>

                <div style="background:rgba(247,147,26,0.06);border:1px solid rgba(247,147,26,0.2);border-radius:12px;padding:16px;margin-top:16px;">
                    <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0;">
                        ⚠️ <strong>Deposit-System:</strong> Vor jedem Gebot wird ein Lightning-Deposit (5%, mind. 1.000 sats) fällig. Verlierer bekommen ihr Deposit zurück. Der Gewinner zahlt den Gesamtpreis direkt an den Verkäufer.
                    </p>
                </div>
            </div>
        </div>

        <!-- Bid History -->
        <div style="margin-top:40px;">
            <h2 style="font-size:1.2rem;margin-bottom:16px;">📜 Gebotsverlauf</h2>
            <div id="bid-history">
                <?php if (empty($bids)): ?>
                <p style="color:var(--text-dim);text-align:center;padding:24px;">Noch keine Gebote. Sei der Erste!</p>
                <?php else: ?>
                <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                    <?php foreach ($bids as $i => $bid): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;<?= $i > 0 ? 'border-top:1px solid var(--border);' : '' ?>">
                        <div>
                            <span style="font-weight:600;"><?= htmlspecialchars($bid['bidder_name']) ?></span>
                            <?php if ($bid['deposit_paid']): ?>
                            <span style="font-size:10px;background:rgba(0,255,136,0.15);color:var(--accent);padding:1px 6px;border-radius:8px;margin-left:6px;">✓ Deposit</span>
                            <?php endif; ?>
                            <span style="color:var(--text-dim);font-size:12px;margin-left:8px;"><?= date('d.m. H:i', strtotime($bid['created_at'])) ?></span>
                        </div>
                        <div style="font-family:'JetBrains Mono',monospace;color:var(--bitcoin);font-weight:700;">
                            <?= number_format($bid['amount_sats']) ?> sats
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seller Info -->
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:16px;padding:24px;margin-top:40px;">
            <div style="display:flex;align-items:center;gap:16px;">
                <a href="/toxic-market/seller/<?= $auction['seller_id'] ?>" style="width:56px;height:56px;border-radius:50%;background:var(--holo-gradient);display:flex;align-items:center;justify-content:center;font-size:24px;text-decoration:none;">👤</a>
                <div>
                    <a href="/toxic-market/seller/<?= $auction['seller_id'] ?>" style="font-weight:700;font-size:16px;color:var(--text);text-decoration:none;"><?= htmlspecialchars($auction['seller_name']) ?></a>
                    <div style="font-size:12px;color:var(--text-dim);margin-top:2px;">Seit <?= date('M Y', strtotime($auction['seller_since'])) ?> · <?= $auction['total_sales'] ?? 0 ?> Verkäufe</div>
                </div>
            </div>
            <?php if ($auction['seller_bio']): ?>
            <p style="color:var(--text-muted);font-size:13px;margin-top:12px;"><?= htmlspecialchars($auction['seller_bio']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>🧪 Toxic Market · <strong>Kein Custody, keine Haftung.</strong> P2P-Marktplatz.</p>
        </div>
    </footer>

    <script src="/toxic-market/js/nostr.js?v=4"></script>
    <script src="/toxic-market/js/toxic.js?v=5"></script>
    <script>
    const AUCTION_ID = '<?= $auction['id'] ?>';
    const CURRENT_PRICE = <?= $currentPrice ?>;
    const MIN_BID = <?= $minBid ?>;
    const DEPOSIT_SATS = <?= $depositSats ?>;
    const ENDS_AT = '<?= $ends->format('c') ?>';
    const IS_LIVE = <?= $isLive ? 'true' : 'false' ?>;
    const IS_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;
    let currentDepositTxId = null;
    let depositCheckInterval = null;

    // ─── Live Countdown ───
    <?php if ($isLive): ?>
    const endTime = new Date(ENDS_AT).getTime();
    function updateCountdown() {
        const now = Date.now();
        const diff = endTime - now;
        if (diff <= 0) {
            const el = document.getElementById('countdown');
            if (el) el.textContent = '⏹ Beendet';
            setTimeout(() => location.reload(), 3000);
            return;
        }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        const el = document.getElementById('countdown');
        if (!el) return;
        if (d > 0) el.textContent = `${d}T ${h}h ${m}m ${s}s`;
        else if (h > 0) el.textContent = `${h}h ${m}m ${s}s`;
        else el.textContent = `${m}m ${s}s`;
        // Urgency: last hour
        if (diff < 3600000) {
            el.style.color = 'var(--danger)';
        } else if (diff < 86400000) {
            el.style.color = 'var(--bitcoin)';
        }
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    // Live price polling every 15s
    let lastPrice = CURRENT_PRICE;
    setInterval(async () => {
        try {
            const res = await fetch(`/toxic-market/api/api.php?action=auction&id=${AUCTION_ID}`);
            const data = await res.json();
            if (data.current_price_sats && Number(data.current_price_sats) !== lastPrice) {
                lastPrice = Number(data.current_price_sats);
                const priceEl = document.getElementById('auction-price-display');
                if (priceEl) {
                    priceEl.textContent = Number(lastPrice).toLocaleString();
                    priceEl.style.transform = 'scale(1.1)';
                    setTimeout(() => priceEl.style.transform = 'scale(1)', 300);
                }
                // Update min bid
                const newMin = lastPrice + 500;
                const bidInput = document.getElementById('bid-amount');
                if (bidInput) bidInput.min = newMin;
                
                // Check notifications for outbid
                if (IS_LOGGED_IN) {
                    loadNotifications();
                }
            }
        } catch(e) {}
    }, 15000);
    <?php endif; ?>

    // ─── Bid with Deposit ───
    async function placeBidWithDeposit() {
        if (!IS_LOGGED_IN) { showAuth(); return; }
        
        const amount = parseInt(document.getElementById('bid-amount').value);
        if (!amount || amount < MIN_BID) {
            toast(`Mindestgebot: ${MIN_BID.toLocaleString()} sats`, 'error');
            return;
        }
        
        const btn = document.getElementById('bid-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Erstelle Gebot...';
        
        try {
            const res = await fetch('/toxic-market/api/api.php?action=bid_with_deposit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    auction_id: AUCTION_ID,
                    amount_sats: amount
                })
            });
            const data = await res.json();
            
            if (data.success && data.payment_request) {
                // Show deposit modal
                currentDepositTxId = data.transaction_id;
                document.getElementById('deposit-modal').classList.remove('hidden');
                document.getElementById('deposit-amount').textContent = Number(data.deposit_sats).toLocaleString() + ' sats';
                document.getElementById('deposit-bid-info').textContent = `Dein Gebot: ${Number(amount).toLocaleString()} sats · Deposit: ${Number(data.deposit_sats).toLocaleString()} sats`;
                document.getElementById('deposit-status').textContent = 'Scanne den QR-Code oder kopiere die Invoice:';
                document.getElementById('deposit-qr').style.display = 'block';
                document.getElementById('deposit-qr-img').src = data.qr_url;
                document.getElementById('deposit-invoice-text').textContent = data.payment_request;
                document.getElementById('deposit-result').style.display = 'none';
                document.getElementById('deposit-onchain').style.display = 'none';
                
                // Load onchain address
                loadDepositOnchain();
                
                // Start polling
                startDepositPolling();
            } else if (data.success && data.status === 'no_deposit') {
                // No LNBits — bid without deposit
                toast('Gebot abgegeben! (Kein Deposit erforderlich)', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.error || 'Gebot fehlgeschlagen', 'error');
            }
        } catch(e) {
            toast('Server-Fehler', 'error');
            console.error('Bid error:', e);
        }
        
        btn.disabled = false;
        btn.textContent = '🔨 Bieten';
    }

    function startDepositPolling() {
        if (depositCheckInterval) clearInterval(depositCheckInterval);
        depositCheckInterval = setInterval(checkDepositStatus, 8000);
        setTimeout(checkDepositStatus, 3000);
    }

    async function checkDepositStatus() {
        if (!currentDepositTxId) return;
        try {
            const res = await fetch(`/toxic-market/api/api.php?action=check_payment&transaction_id=${currentDepositTxId}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data.status === 'settled' || data.status === 'paid') {
                clearInterval(depositCheckInterval);
                depositCheckInterval = null;
                document.getElementById('deposit-status').textContent = '✅ Deposit bestätigt!';
                document.getElementById('deposit-result').style.display = 'block';
                document.getElementById('deposit-result').style.background = 'rgba(0,255,136,0.1)';
                document.getElementById('deposit-result').style.border = '1px solid rgba(0,255,136,0.3)';
                document.getElementById('deposit-result').innerHTML = '🎉 <strong>Gebot bestätigt!</strong><br>Dein Deposit wurde erhalten. Du bist der Höchstbietende!';
                toast('Deposit bestätigt! 🎉', 'success', 10000);
                setTimeout(() => location.reload(), 3000);
            } else {
                document.getElementById('deposit-status').textContent = 'Warte auf Zahlung... (prüfe automatisch)';
            }
        } catch(e) {}
    }

    function copyDepositInvoice() {
        const inv = document.getElementById('deposit-invoice-text').textContent;
        if (inv) navigator.clipboard.writeText(inv).then(() => toast('Invoice kopiert!', 'success'));
    }

    async function loadDepositOnchain() {
        try {
            const res = await fetch('/toxic-market/api/api.php?action=onchain_address', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success && data.address) {
                document.getElementById('deposit-onchain').style.display = 'block';
                document.getElementById('deposit-onchain-addr').textContent = data.address;
            }
        } catch(e) {}
    }

    function closeDepositModal() {
        document.getElementById('deposit-modal').classList.add('hidden');
        if (depositCheckInterval) {
            clearInterval(depositCheckInterval);
            depositCheckInterval = null;
        }
    }

    // ─── Notifications ───
    async function loadNotifications() {
        try {
            const res = await fetch('/toxic-market/api/api.php?action=notifications&unread_only=1&limit=5', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.data) {
                data.data.forEach(n => {
                    if (n.type === 'outbid') {
                        toast(n.title + ': ' + n.message, 'warning', 8000);
                    }
                });
            }
        } catch(e) {}
    }
    </script>
</body>
</html>