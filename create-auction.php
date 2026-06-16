<?php
/**
 * Toxic Market — Create Auction Page
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/payments.php';

if (!isLoggedIn()) { header('Location: /toxic-market/'); exit; }

$db = getDB();
$user = currentUser();
$cards = $db->query('SELECT * FROM card_templates ORDER BY generation, id')->fetchAll();
$blockHeight = @file_get_contents('https://mempool.space/api/blocks/tip/height', false, stream_context_create(['http' => ['timeout' => 3]])) ?: '?';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#08080f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Toxic Market — Auktion erstellen</title>
    <link href="/toxic-market/favicon.svg" rel="icon" type="image/svg+xml">
    <link href="/toxic-market/css/toxic.css?v=2" rel="stylesheet">
    <link href="/toxic-market/css/toxic-card.css" rel="stylesheet">
</head>
<body>
    <nav id="nav">
        <div class="nav-inner">
            <a href="/toxic-market/" class="logo"><span class="logo-icon">🧪</span><span class="logo-text">Toxic Market</span></a>
            <div class="nav-links">
                <a href="/toxic-market/#auctions" class="desktop-link">Auktionen</a>
                <span class="desktop-link" style="color:var(--accent);font-weight:600;"><?= htmlspecialchars($user['display_name']) ?></span>
                <button class="hamburger" onclick="toggleMobileNav()">☰</button>
            </div>
        </div>
        <div id="mobile-nav" class="mobile-nav">
            <a href="/toxic-market/#cards">🃏 Karten</a>
            <a href="/toxic-market/#listings">🏷️ Kaufen</a>
            <a href="/toxic-market/#auctions">🔨 Auktionen</a>
            <a href="/toxic-market/dashboard">📊 Dashboard</a>
        </div>
    </nav>

    <div class="container" style="max-width:700px; padding: 30px 20px 60px;">
        <a href="/toxic-market/" style="color:var(--text-muted);text-decoration:none;font-size:14px;">← Zurück</a>
        <h1 style="font-size:1.6rem;font-weight:800;margin:20px 0 8px;">🔨 Auktion erstellen</h1>
        
        <div style="background:rgba(0,255,136,0.04);border:1px solid rgba(0,255,136,0.15);border-radius:12px;padding:14px;margin:16px 0;font-size:13px;">
            💡 <strong>Tipp:</strong> Setze einen angemessenen Startpreis. Bieter können ab 500 Sats darüber bieten. Die Auktion endet automatisch.
        </div>

        <form id="create-auction" style="margin-top:20px;">
            <div class="form-group">
                <label>Karte *</label>
                <select id="card-id" required style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                    <option value="">— Karte auswählen —</option>
                    <?php foreach ($cards as $c): ?>
                    <?php $gn = [1=>'Genesis 2025',2=>'Zitadelle 2026',3=>'Remake EN'][$c['generation']] ?? ''; ?>
                    <option value="<?= $c['id'] ?>"><?= $gn ?>: <?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Titel *</label>
                <input type="text" id="auc-title" required placeholder="z.B. Genesis #1 The Beginning — MINT Auktion" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea id="auc-desc" rows="3" placeholder="Beschreibe den Zustand, Besonderheiten..." style="width:100%;padding:10px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;resize:vertical;"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Startpreis (Sats) *</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="auc-start-price" required min="100" placeholder="10000" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                    <div style="font-size:11px;color:var(--text-dim);margin-top:4px;" id="start-price-eur"></div>
                </div>
                <div class="form-group">
                    <label>Dauer</label>
                    <select id="auc-duration" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                        <option value="24">1 Tag</option>
                        <option value="48">2 Tage</option>
                        <option value="72" selected>3 Tage</option>
                        <option value="168">7 Tage</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Zustand</label>
                    <select id="auc-condition" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                        <option value="mint">Mint (M)</option>
                        <option value="near_mint">Near Mint (NM)</option>
                        <option value="excellent">Excellent (EX)</option>
                        <option value="good">Good (G)</option>
                        <option value="played">Played (P)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Seriennummer</label>
                    <input type="text" id="auc-serial" placeholder="042/210" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
                <div class="form-group">
                    <label>Mindestgebot+</label>
                    <select id="auc-bid-step" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                        <option value="500">+500 Sats</option>
                        <option value="1000">+1.000 Sats</option>
                        <option value="5000">+5.000 Sats</option>
                        <option value="10000">+10.000 Sats</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Versand DE (Sats)</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="auc-local-ship" min="0" value="0" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
                <div class="form-group">
                    <label>Versand International (Sats)</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="auc-intl-ship" min="0" value="0" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <label>📸 Bilder (max. 5)</label>
                <div style="background:var(--bg-elevated);border:2px dashed var(--border);border-radius:12px;padding:24px;text-align:center;cursor:pointer;" id="auc-drop-zone" onclick="document.getElementById('auc-images').click()">
                    <div style="font-size:36px;">📷</div>
                    <div style="color:var(--text-muted);font-size:14px;">Klicken oder Bilder hierher ziehen</div>
                </div>
                <input type="file" id="auc-images" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
                <div id="auc-image-previews" style="display:none;margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;"></div>
            </div>

            <div class="proof-box" style="margin-top:24px;">
                <h3>🔍 Besitznachweis (Empfohlen)</h3>
                <p style="color:var(--text-muted);font-size:14px;">Schreibe <strong>Block #<?= $blockHeight ?> + "<?= htmlspecialchars($user['display_name']) ?>"</strong> auf einen Zettel neben der Karte.</p>
                <div class="form-group" style="margin-top:10px;">
                    <input type="file" id="auc-proof" accept="image/jpeg,image/png,image/webp" style="width:100%;padding:8px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-bitcoin btn-full" style="margin-top:24px;padding:16px;font-size:16px;font-weight:700;">
                🔨 Auktion starten
            </button>
            <p id="submit-error" class="error hidden" style="margin-top:12px;"></p>
            <p id="submit-success" class="hidden" style="margin-top:12px;color:var(--accent);text-align:center;font-size:15px;"></p>
        </form>
    </div>

    <footer>
        <div class="container">
            <p>🧪 Toxic Market · <strong>Kein Custody, keine Haftung.</strong> P2P-Marktplatz.</p>
        </div>
    </footer>

    <script src="/toxic-market/js/noble-curves-bundle.js?v=1"></script>
    <script src="/toxic-market/js/nostr.js?v=4"></script>
    <script src="/toxic-market/js/toxic.js?v=5"></script>
    <script>
    let aucImages = [];

    // EUR price preview
    document.getElementById('auc-start-price')?.addEventListener('input', async (e) => {
        const sats = parseInt(e.target.value) || 0;
        if (sats > 0) {
            try {
                const res = await fetch('/toxic-market/api/api.php?action=btc_price');
                const data = await res.json();
                const eur = (sats / 100000000) * data.prices.eur;
                document.getElementById('start-price-eur').textContent = `≈ €${eur.toFixed(2)}`;
            } catch(err) {}
        } else {
            document.getElementById('start-price-eur').textContent = '';
        }
    });

    // Image upload
    document.getElementById('auc-images')?.addEventListener('change', (e) => {
        Array.from(e.target.files).slice(0, 5 - aucImages.length).forEach(file => {
            const form = new FormData();
            form.append('image', file);
            fetch('/toxic-market/api/api.php?action=upload_image', {
                method: 'POST', credentials: 'same-origin', body: form
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    aucImages.push(data.url);
                    renderAucPreviews();
                }
            });
        });
    });

    function renderAucPreviews() {
        const area = document.getElementById('auc-image-previews');
        area.style.display = aucImages.length ? 'flex' : 'none';
        area.innerHTML = aucImages.map((url, i) => `
            <div style="position:relative;width:70px;height:95px;border-radius:8px;overflow:hidden;border:2px solid var(--border);">
                <img src="${url}" style="width:100%;height:100%;object-fit:cover;">
                <button onclick="aucImages.splice(${i},1);renderAucPreviews()" style="position:absolute;top:2px;right:2px;background:rgba(255,0,0,0.8);color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:11px;cursor:pointer;">✕</button>
            </div>
        `).join('');
    }

    // Submit auction
    document.getElementById('create-auction')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Wird erstellt...';

        // Upload proof image if provided
        let proofUrl = '';
        const proofFile = document.getElementById('auc-proof').files[0];
        if (proofFile) {
            const form = new FormData();
            form.append('image', proofFile);
            try {
                const res = await fetch('/toxic-market/api/api.php?action=upload_image', {method:'POST',credentials:'same-origin',body:form});
                const data = await res.json();
                if (data.success) proofUrl = data.url;
            } catch(e) {}
        }

        const data = {
            card_template_id: parseInt(document.getElementById('card-id').value),
            title: document.getElementById('auc-title').value,
            description: document.getElementById('auc-desc').value,
            starting_price_sats: parseInt(document.getElementById('auc-start-price').value),
            duration_hours: parseInt(document.getElementById('auc-duration').value),
            condition: document.getElementById('auc-condition').value,
            serial_number: document.getElementById('auc-serial').value,
            local_shipping_sats: parseInt(document.getElementById('auc-local-ship').value) || 0,
            intl_shipping_sats: parseInt(document.getElementById('auc-intl-ship').value) || 0,
            image_urls: aucImages,
            proof_image_url: proofUrl,
            proof_block_height: <?= $blockHeight === '?' ? '0' : $blockHeight ?>,
        };

        try {
            const res = await fetch('/toxic-market/api/api.php?action=create_auction', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                document.getElementById('submit-success').textContent = '✅ Auktion erstellt! Weiterleitung...';
                document.getElementById('submit-success').classList.remove('hidden');
                
                // Publish to Nostr
                if (window.NostrTM && NostrTM.hasNsec()) {
                    const nsec = NostrTM.loadNsec();
                    NostrTM.publishAuction(
                        nsec,
                        String(result.id || data.card_template_id),
                        data.title,
                        data.starting_price_sats,
                        data.description,
                        data.ends_at,
                        data.card_name || '',
                        data.generation || 1
                    ).then(evt => {
                        if (evt) toast('🌐 Auktion auf Nostr veröffentlicht!', 'success');
                    }).catch(e => console.warn('Nostr publish failed:', e));
                }
                
                setTimeout(() => { window.location.href = '/toxic-market/auction/' + result.id; }, 2000);
            } else {
                document.getElementById('submit-error').textContent = result.error || 'Fehler beim Erstellen';
                document.getElementById('submit-error').classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = '🔨 Auktion starten';
            }
        } catch (err) {
            document.getElementById('submit-error').textContent = 'Server-Fehler';
            document.getElementById('submit-error').classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = '🔨 Auktion starten';
        }
    });
    </script>
</body>
</html>