<?php
/**
 * Toxic Market — Create Listing Page (with image upload)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /toxic-market/');
    exit;
}

$db = getDB();
$user = currentUser();
$cards = $db->query('SELECT * FROM card_templates ORDER BY generation, id')->fetchAll();

// Get current block height
$ctx = stream_context_create(['http' => ['timeout' => 3]]);
$block_height = @file_get_contents('https://mempool.space/api/blocks/tip/height', false, $ctx) ?: '?';
$preselectedCard = intval($_GET['card'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#08080f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Toxic Market — Angebot erstellen</title>
    <link href="/toxic-market/favicon.svg" rel="icon" type="image/svg+xml">
    <link href="/toxic-market/css/toxic.css" rel="stylesheet">
    <link href="/toxic-market/css/toxic-card.css" rel="stylesheet">
</head>
<body>
    <nav id="nav">
        <div class="nav-inner">
            <a href="/toxic-market/" class="logo"><span class="logo-icon">🧪</span><span class="logo-text">Toxic Market</span></a>
            <div class="nav-links">
                <a href="/toxic-market/#cards" class="desktop-link">Karten</a>
                <a href="/toxic-market/#listings" class="desktop-link">Kaufen</a>
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
        <h1 style="font-size:1.6rem;font-weight:800;margin:20px 0 8px;">🏷️ Angebot erstellen</h1>
        
        <div style="background:rgba(247,147,26,0.08);border:1px solid rgba(247,147,26,0.25);border-radius:12px;padding:14px;margin:16px 0;font-size:13px;color:var(--text-muted);">
            ⚠️ <strong>Wichtig:</strong> Du handelst direkt mit dem Käufer. Toxic Market verwahrt kein Geld und übernimmt keine Haftung. Lade einen Besitznachweis hoch, um Vertrauen zu schaffen.
        </div>

        <!-- Image preview area -->
        <div id="image-preview-area" style="display:none;margin:20px 0;">
            <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--text-muted);">📸 Vorschau</div>
            <div id="image-previews" style="display:flex;gap:10px;flex-wrap:wrap;"></div>
        </div>

        <form id="create-listing" style="margin-top:20px;">
            <div class="form-group">
                <label>Karte *</label>
                <select id="card-id" required style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                    <option value="">— Karte auswählen —</option>
                    <?php foreach ($cards as $c): ?>
                    <?php $gn = [1=>'Genesis 2025',2=>'Zitadelle 2026',3=>'Remake EN'][$c['generation']] ?? ''; ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $preselectedCard ? 'selected' : '' ?>><?= $gn ?>: <?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Titel *</label>
                <input type="text" id="listing-title" required placeholder="z.B. Genesis #1 The Beginning - MINT" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea id="listing-desc" rows="3" placeholder="Zustand, Besonderheiten, etc." style="width:100%;padding:10px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;resize:vertical;"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Preis (Sats) *</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="listing-price" required min="1" placeholder="5000" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
                <div class="form-group">
                    <label>Zustand</label>
                    <select id="listing-condition" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                        <option value="mint">Mint (M)</option>
                        <option value="near_mint">Near Mint (NM)</option>
                        <option value="excellent">Excellent (EX)</option>
                        <option value="good">Good (G)</option>
                        <option value="played">Played (P)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Seriennummer</label>
                    <input type="text" id="listing-serial" placeholder="042/210" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Versand DE (Sats)</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="listing-local-ship" min="0" value="0" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
                <div class="form-group">
                    <label>Versand International (Sats)</label>
                    <input type="number" inputmode="numeric" pattern="[0-9]*" id="listing-intl-ship" min="0" value="0" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <label>📸 Bilder (max. 5)</label>
                <div style="background:var(--bg-elevated);border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:var(--transition);" id="drop-zone" onclick="document.getElementById('listing-images').click()">
                    <div style="font-size:40px;margin-bottom:8px;">📷</div>
                    <div style="color:var(--text-muted);font-size:14px;">Klicken oder Bilder hierher ziehen</div>
                    <div style="color:var(--text-dim);font-size:12px;margin-top:4px;">JPG/PNG/WebP, max 5MB pro Bild</div>
                </div>
                <input type="file" id="listing-images" accept="image/jpeg,image/png,image/webp" multiple style="display:none;">
                <div id="image-preview-area" style="display:none;margin-top:12px;">
                    <div id="image-previews" style="display:flex;gap:10px;flex-wrap:wrap;"></div>
                </div>
            </div>

            <div class="proof-box" style="margin-top:24px;">
                <h3>🔍 Besitznachweis (Empfohlen)</h3>
                <p style="color:var(--text-muted);font-size:14px;">Schreibe <strong>Block #<?= $block_height ?> + deinen Benutzernamen "<?= htmlspecialchars($user['display_name']) ?>"</strong> auf einen Zettel und fotografiere ihn neben der Karte.</p>
                <div class="proof-hash-box">
                    <strong>Aktueller Block:</strong> #<?= $block_height ?><br>
                    <strong>Dein Zettel-Text:</strong> "Block <?= $block_height ?> — <?= htmlspecialchars($user['display_name']) ?>"
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label>Besitznachweis-Bild</label>
                    <input type="file" id="proof-image" accept="image/jpeg,image/png,image/webp" style="width:100%;padding:12px;font-size:16px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary btn-full" style="margin-top:24px;padding:14px;font-size:16px;">
                🏷️ Angebot veröffentlichen
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

    <script src="/toxic-market/js/nostr.js"></script>
    <script src="/toxic-market/js/toxic.js"></script>
    <script>
    let uploadedImages = [];

    // Drag & drop support
    const dropZone = document.getElementById('drop-zone');
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = 'var(--accent)'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = 'var(--border)'; });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--border)';
        handleFiles(e.dataTransfer.files);
    });

    document.getElementById('listing-images').addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        const remaining = 5 - uploadedImages.length;
        if (remaining <= 0) { toast('Maximal 5 Bilder erlaubt.', 'warning'); return; }
        const toUpload = Array.from(files).slice(0, remaining);
        toUpload.forEach(file => uploadImage(file));
    }

    async function uploadImage(file) {
        const formData = new FormData();
        formData.append('image', file);
        try {
            const res = await fetch('/toxic-market/api/api.php?action=upload_image', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                uploadedImages.push(data.url);
                renderPreviews();
            } else {
                toast('Upload-Fehler: ' + (data.error || 'Unbekannt'), 'error');
            }
        } catch (e) {
            toast('Upload-Fehler: Server nicht erreichbar', 'error');
        }
    }

    function renderPreviews() {
        const area = document.getElementById('image-preview-area');
        const container = document.getElementById('image-previews');
        if (uploadedImages.length === 0) { area.style.display = 'none'; return; }
        area.style.display = 'block';
        container.innerHTML = uploadedImages.map((url, i) => `
            <div style="position:relative;width:80px;height:100px;border-radius:8px;overflow:hidden;border:2px solid var(--border);">
                <img src="${url}" style="width:100%;height:100%;object-fit:cover;">
                <button onclick="removeImage(${i})" style="position:absolute;top:2px;right:2px;background:rgba(255,0,0,0.8);color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
            </div>
        `).join('');
    }

    function removeImage(index) {
        uploadedImages.splice(index, 1);
        renderPreviews();
    }

    // Form submission
    document.getElementById('create-listing').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Wird erstellt...';

        // Upload proof image first if provided
        let proofUrl = '';
        const proofFile = document.getElementById('proof-image').files[0];
        if (proofFile) {
            const formData = new FormData();
            formData.append('image', proofFile);
            try {
                const res = await fetch('/toxic-market/api/api.php?action=upload_image', {
                    method: 'POST', credentials: 'same-origin', body: formData
                });
                const data = await res.json();
                if (data.success) proofUrl = data.url;
            } catch (e) { /* continue without proof */ }
        }

        const data = {
            card_template_id: parseInt(document.getElementById('card-id').value),
            title: document.getElementById('listing-title').value,
            description: document.getElementById('listing-desc').value,
            price_sats: parseInt(document.getElementById('listing-price').value),
            condition: document.getElementById('listing-condition').value,
            serial_number: document.getElementById('listing-serial').value,
            local_shipping_sats: parseInt(document.getElementById('listing-local-ship').value) || 0,
            intl_shipping_sats: parseInt(document.getElementById('listing-intl-ship').value) || 0,
            image_urls: uploadedImages,
            proof_image_url: proofUrl,
            proof_block_height: <?= $block_height === '?' ? '0' : $block_height ?>,
        };

        if (!data.card_template_id || !data.title || !data.price_sats) {
            document.getElementById('submit-error').textContent = 'Bitte alle Pflichtfelder ausfüllen.';
            document.getElementById('submit-error').classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = '🏷️ Angebot veröffentlichen';
            return;
        }

        try {
            const res = await fetch('/toxic-market/api/api.php?action=create_listing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                document.getElementById('submit-success').textContent = '✅ Angebot erstellt! Weiterleitung...';
                document.getElementById('submit-success').classList.remove('hidden');
                
                // Publish to Nostr
                if (window.NostrTM && NostrTM.hasNsec()) {
                    const nsec = NostrTM.loadNsec();
                    NostrTM.publishListing(
                        nsec,
                        String(result.listing_id || data.card_template_id),
                        data.title,
                        data.price_sats,
                        data.description,
                        data.card_name || '',
                        data.generation || 1
                    ).then(evt => {
                        if (evt) toast('🌐 Auf Nostr veröffentlicht!', 'success');
                    }).catch(e => console.warn('Nostr publish failed:', e));
                }
                
                setTimeout(() => { window.location.href = '/toxic-market/'; }, 2000);
            } else {
                document.getElementById('submit-error').textContent = result.error || 'Fehler beim Erstellen';
                document.getElementById('submit-error').classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = '🏷️ Angebot veröffentlichen';
            }
        } catch (err) {
            document.getElementById('submit-error').textContent = 'Server-Fehler';
            document.getElementById('submit-error').classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = '🏷️ Angebot veröffentlichen';
        }
    });
    </script>
</body>
</html>