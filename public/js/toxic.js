// Toxic Market — Frontend Logic
const API = '/toxic-market/api/api.php';

// State
let cards = [];
let currentFilter = 'all';
let currentUser = null;

// Helper: safe parse image_urls (can be array or string)
function parseImages(val) {
    if (Array.isArray(val)) return val;
    if (typeof val === 'string') { try { return JSON.parse(val); } catch(e) { return []; } }
    return [];
}
function toast(msg, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column-reverse;gap:8px;max-width:360px;';
        document.body.appendChild(container);
    }
    const colors = {
        success: 'rgba(0,255,136,0.95)',
        error: 'rgba(255,68,85,0.95)',
        info: 'rgba(59,130,246,0.95)',
        warning: 'rgba(247,147,26,0.95)',
    };
    const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
    const t = document.createElement('div');
    t.style.cssText = `background:${colors[type] || colors.info};color:#fff;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.3);display:flex;gap:8px;align-items:center;animation:toast-in 0.3s ease;cursor:pointer;font-family:inherit;`;
    t.innerHTML = `<span>${icons[type] || ''}</span><span>${msg}</span>`;
    t.onclick = () => t.remove();
    container.appendChild(t);
    setTimeout(() => { if (t.parentNode) t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
}

// Add toast animation
if (!document.getElementById('toast-styles')) {
    const s = document.createElement('style');
    s.id = 'toast-styles';
    s.textContent = '@keyframes toast-in{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}';
    document.head.appendChild(s);
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadCards();
    loadListings();
    loadAuctions();
    checkAuth();
    setupFilters();
    // Close mobile nav on link click (with slide-out animation)
    document.querySelectorAll('#mobile-nav a').forEach(a => {
        a.addEventListener('click', () => {
            const nav = document.getElementById('mobile-nav');
            if (nav) { nav.classList.remove('nav-open', 'open'); }
        });
    });

    // Close mobile nav on outside tap
    document.addEventListener('click', (e) => {
        const nav = document.getElementById('mobile-nav');
        const hamburger = document.getElementById('hamburger');
        if (nav && nav.classList.contains('nav-open') && !nav.contains(e.target) && !hamburger?.contains(e.target)) {
            nav.classList.remove('nav-open', 'open');
        }
    });

    // Smooth scroll for anchor links on mobile
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', (e) => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Touch feedback for cards
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    if (isTouchDevice) {
        document.addEventListener('touchstart', (e) => {
            const card = e.target.closest('.card-item, .gen-card, .listing-item');
            if (card) card.classList.add('touch-active');
        }, { passive: true });
        document.addEventListener('touchend', () => {
            document.querySelectorAll('.touch-active').forEach(el => el.classList.remove('touch-active'));
        }, { passive: true });

        // Bid input: inputmode=numeric for mobile number pad
        document.querySelectorAll('.bid-input, input[name="bid_amount"]').forEach(input => {
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('pattern', '[0-9]*');
        });

        // Prevent double-tap zoom on buttons
        let lastTap = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTap < 300) {
                e.preventDefault();
            }
            lastTap = now;
        }, { passive: false });
    }
});

// Toggle mobile hamburger nav (with slide animation)
function toggleMobileNav() {
    const nav = document.getElementById('mobile-nav');
    if (!nav) return;
    nav.classList.toggle('nav-open');
    nav.classList.toggle('open');
    // Sync hidden class for legacy compat
    if (nav.classList.contains('nav-open')) {
        nav.classList.remove('hidden');
    }
}

// Navigate to card detail page
function showCard(cardId) {
    window.location.href = '/toxic-market/card/' + cardId;
}

// Navigate to create listing page
function showCreateListing(cardId) {
    if (cardId) {
        window.location.href = '/toxic-market/create?card=' + cardId;
    } else {
        window.location.href = '/toxic-market/create';
    }
}

// API helper
let csrfToken = null;

async function api(action, data = null, method = 'GET') {
    // Refresh CSRF token on GET status calls
    if (action === 'status' && method === 'GET') {
        const url = `${API}?action=status`;
        const res = await fetch(url, { credentials: 'same-origin' });
        const json = await res.json();
        if (json.csrf_token) csrfToken = json.csrf_token;
        return json;
    }
    const url = data && method === 'GET' 
        ? `${API}?action=${action}&${new URLSearchParams(data)}`
        : `${API}?action=${action}`;
    const payload = data && method !== 'GET' ? { ...data, csrf_token: csrfToken } : data;
    const opts = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
    if (payload && method !== 'GET') opts.body = JSON.stringify(payload);
    const res = await fetch(url, opts);
    const json = await res.json();
    // Update CSRF token from response if provided
    if (json.csrf_token) csrfToken = json.csrf_token;
    return json;
}

// Image upload helper
async function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    const res = await fetch(`${API}?action=upload_image`, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    });
    return res.json();
}

// Auth
async function checkAuth() {
    try {
        const res = await api('status');
        if (res.logged_in) {
            currentUser = res.user;
            showLoggedIn();
        } else {
            showLoggedOut();
        }
    } catch (e) { showLoggedOut(); }
}

function showLoggedIn() {
    const btn = document.getElementById('login-btn');
    const menu = document.getElementById('user-menu');
    if (btn) btn.classList.add('hidden');
    if (menu) {
        menu.classList.remove('hidden');
        menu.style.display = 'flex';
    }
    const nameEl = document.getElementById('user-name');
    if (nameEl && currentUser) nameEl.textContent = currentUser.display_name;
    
    // Show create buttons when logged in
    document.querySelectorAll('#create-listing-btn, #create-listing-btn-2, #create-auction-btn').forEach(el => {
        if (el) el.classList.remove('hidden');
    });
    document.querySelectorAll('#create-auction-btn-2').forEach(el => {
        if (el) el.style.display = 'inline-flex';
    });
}

function showLoggedOut() {
    const btn = document.getElementById('login-btn');
    const menu = document.getElementById('user-menu');
    if (btn) btn.classList.remove('hidden');
    if (menu) {
        menu.classList.add('hidden');
        menu.style.display = 'none';
    }
    document.querySelectorAll('#create-listing-btn, #create-listing-btn-2, #create-auction-btn').forEach(el => {
        if (el) el.classList.add('hidden');
    });
    document.querySelectorAll('#create-auction-btn-2').forEach(el => {
        if (el) el.style.display = 'none';
    });
}

function showAuth() { 
    const m = document.getElementById('auth-modal');
    if (m) m.classList.remove('hidden'); 
}
function hideAuth() { 
    const m = document.getElementById('auth-modal');
    if (m) m.classList.add('hidden'); 
}

function switchTab(tab) {
    document.querySelectorAll('.auth-tabs .tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.auth-tabs .tab:nth-child(${tab === 'login' ? 1 : 2})`)?.classList.add('active');
    const loginForm = document.getElementById('login-form');
    const regForm = document.getElementById('register-form');
    if (loginForm) loginForm.classList.toggle('hidden', tab !== 'login');
    if (regForm) regForm.classList.toggle('hidden', tab !== 'register');
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('login-email')?.value;
    const password = document.getElementById('login-password')?.value;
    try {
        const res = await api('login', { email, password }, 'POST');
        if (res.success) { hideAuth(); checkAuth(); location.reload(); }
        else { showError('login-error', res.error || 'Anmeldung fehlgeschlagen'); }
    } catch (e) { showError('login-error', 'Server-Fehler'); }
}

async function handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById('reg-name')?.value;
    const email = document.getElementById('reg-email')?.value;
    const password = document.getElementById('reg-password')?.value;
    const acceptDisclaimer = document.getElementById('reg-disclaimer')?.checked;
    
    if (!acceptDisclaimer) {
        showError('reg-error', 'Du musst den Disclaimer akzeptieren');
        return;
    }
    
    // Generate Nostr keypair
    let nostrPubkey = null;
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = '🔑 Generiere Nostr-Schlüssel...'; }
    
    try {
        if (window.NostrTM) {
            const keypair = await NostrTM.generateNostrKeypair();
            if (keypair) {
                nostrPubkey = keypair.pubKey;
                // Save nsec to localStorage (user's responsibility)
                NostrTM.saveNsec(keypair.nsec);
                
                // Show the user their keys
                toast('🔑 Nostr-Schlüssel generiert!', 'success');
                console.log('Your npub:', keypair.npub);
                console.log('nsec saved to localStorage. Back it up!');
            }
        }
    } catch(e) {
        console.warn('Nostr key generation failed:', e);
    }
    
    try {
        const res = await api('register', { 
            display_name: name, email, password, 
            accept_disclaimer: true,
            nostr_pubkey: nostrPubkey 
        }, 'POST');
        if (res.success) { 
            hideAuth(); 
            checkAuth(); 
            // Publish Nostr profile metadata
            if (window.NostrTM && NostrTM.hasNsec()) {
                const nsec = NostrTM.loadNsec();
                NostrTM.publishProfile(nsec, name, '', '').catch(e => console.warn('Profile publish failed:', e));
            }
            location.reload(); 
        }
        else { showError('reg-error', res.error || 'Registrierung fehlgeschlagen'); }
    } catch (e) { showError('reg-error', 'Server-Fehler'); }
    
    if (btn) { btn.disabled = false; btn.textContent = 'Account erstellen'; }
}

async function logout() {
    await api('logout', null, 'POST');
    currentUser = null;
    showLoggedOut();
    location.reload();
}

async function loginNostr() {
    if (!window.NostrTM) { toast('Nostr nicht verfügbar', 'warning'); return; }
    
    const result = await NostrTM.loginWithNip07();
    if (!result) return;
    
    try {
        const res = await api('login', { nostr_pubkey: result.pubKey }, 'POST');
        if (res.success) {
            hideAuth();
            checkAuth();
            location.reload();
        } else {
            toast(res.error || 'Nostr-Login fehlgeschlagen', 'error');
        }
    } catch(e) { toast('Server-Fehler', 'error'); }
}

async function loginWithNsec() {
    const input = document.getElementById('login-nsec');
    const key = input ? input.value.trim() : '';
    if (!key) { toast('Bitte nsec oder npub eingeben', 'warning'); return; }
    
    let pubKeyHex = null;
    let nsec = null;
    
    if (key.startsWith('nsec1')) {
        if (!window.NostrTM) { toast('Nostr-Bibliothek nicht geladen', 'warning'); return; }
        const privKeyHex = NostrTM.decodeNsec(key);
        if (!privKeyHex) { toast('Ungültiger nsec-Schlüssel', 'error'); return; }
        // Initialize crypto lib to derive pubkey
        const lib = await NostrTM.initSecp256k1();
        if (!lib) { toast('Krypto-Bibliothek nicht geladen', 'warning'); return; }
        pubKeyHex = bytesToHex(new Uint8Array(lib.schnorr.getPublicKey(hexToBytes(privKeyHex))));
        nsec = key;
    } else if (key.startsWith('npub1')) {
        if (!window.NostrTM) { toast('Nostr-Bibliothek nicht geladen', 'warning'); return; }
        const decoded = NostrTM.decodeNpub(key);
        if (!decoded) { toast('Ungültiger npub-Schlüssel', 'error'); return; }
        pubKeyHex = decoded;
    } else if (/^[0-9a-f]{64}$/i.test(key)) {
        pubKeyHex = key.toLowerCase();
    } else {
        toast('Ungültiges Format. nsec1..., npub1... oder Hex.', 'error');
        return;
    }
    
    try {
        const res = await api('login', { nostr_pubkey: pubKeyHex }, 'POST');
        if (res.success) {
            // Save nsec locally if provided (for signing)
            if (nsec && window.NostrTM) {
                NostrTM.saveNsec(nsec);
            }
            hideAuth();
            checkAuth();
            location.reload();
        } else {
            toast(res.error || 'Login fehlgeschlagen', 'error');
        }
    } catch(e) { toast('Server-Fehler', 'error'); }
}

function showError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

// Cards
async function loadCards() {
    try {
        const res = await api('cards');
        cards = res.data || [];
        const statEl = document.getElementById('stat-cards');
        if (statEl) statEl.textContent = cards.length;
        renderCards();
    } catch (e) { console.error('Cards load error:', e); }
}

function renderCards() {
    const grid = document.getElementById('cards-grid');
    if (!grid) return;
    let filtered = cards;
    if (currentFilter !== 'all') {
        filtered = cards.filter(c => c.generation == currentFilter);
    }
    const search = document.getElementById('card-search')?.value?.toLowerCase() || '';
    if (search) {
        filtered = filtered.filter(c => c.name.toLowerCase().includes(search) || c.description.toLowerCase().includes(search));
    }
    // Price filter (based on lowest_price from API)
    const priceFilter = document.getElementById('price-filter')?.value || 'all';
    if (priceFilter !== 'all') {
        filtered = filtered.filter(c => {
            const price = c.lowest_price || 0;
            if (priceFilter === '0-1000') return price > 0 && price <= 1000;
            if (priceFilter === '1000-5000') return price >= 1000 && price <= 5000;
            if (priceFilter === '5000-20000') return price >= 5000 && price <= 20000;
            if (priceFilter === '20000-50000') return price >= 20000 && price <= 50000;
            if (priceFilter === '50000+') return price >= 50000;
            return true;
        });
    }
    // Sort
    const sortFilter = document.getElementById('sort-filter')?.value || 'id';
    if (sortFilter === 'name') filtered.sort((a, b) => a.name.localeCompare(b.name));
    else if (sortFilter === 'price-asc') filtered.sort((a, b) => (a.lowest_price || 999999) - (b.lowest_price || 999999));
    else if (sortFilter === 'price-desc') filtered.sort((a, b) => (b.lowest_price || 0) - (a.lowest_price || 0));

    grid.innerHTML = filtered.map(card => {
        const genLabel = { 1: 'Genesis 2025', 2: 'Zitadelle 2026', 3: 'Remake EN' }[card.generation];
        const genClass = `gen-${card.generation}`;
        const holoArr = Array.isArray(card.holo_positions) ? card.holo_positions : [];
        const isHolo = holoArr.includes(Number(card.id)) || holoArr.includes(21);
        const holoInfo = holoArr.length ? `Holo: #${holoArr.join(', #')}/${card.generation === 3 ? '35' : '210'}` : '';
        const priceHTML = card.lowest_price ? `<div class="card-price">${Number(card.lowest_price).toLocaleString()} sats</div>` : '';
        const listingsHTML = card.active_listings ? `<div class="card-meta">${card.active_listings} Angebot${card.active_listings > 1 ? 'e' : ''}</div>` : '';
        const total = card.generation === 3 ? 35 : 210;
        const imgUrl = card.image_url || `/toxic-market/cards/card.svg.php?id=${card.id}&gen=${card.generation}&name=${encodeURIComponent(card.name)}&holo=${isHolo ? '1' : '0'}`;
        
        return `
        <div class="card-item" onclick="showCard(${card.id})">
            <div class="card-img"><img src="${imgUrl}" alt="${card.name}" style="width:100%;height:100%;object-fit:contain;border-radius:8px;" loading="lazy" onerror="this.src='/toxic-market/cards/card.svg.php?id=${card.id}&gen=${card.generation}&name=${encodeURIComponent(card.name)}&holo=0'"></div>
            <div class="card-info">
                <div class="card-name">${card.name}</div>
                <div class="card-meta">${total} Stück</div>
                ${holoInfo ? `<div class="card-holo">${holoInfo}</div>` : ''}
                <span class="card-gen ${genClass}">${genLabel}</span>
                ${priceHTML}
                ${listingsHTML}
            </div>
        </div>`;
    }).join('');
    
    if (filtered.length === 0) {
        grid.innerHTML = '<p style="text-align:center;color:var(--text-dim);padding:40px;grid-column:1/-1;">Keine Karten gefunden.</p>';
    }
}

function setupFilters() {
    document.querySelectorAll('.filter-btn[data-gen]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn[data-gen]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.gen;
            renderCards();
        });
    });
    document.getElementById('card-search')?.addEventListener('input', renderCards);
    document.getElementById('price-filter')?.addEventListener('change', renderCards);
    document.getElementById('sort-filter')?.addEventListener('change', renderCards);
    document.getElementById('filter-images')?.addEventListener('change', loadListings);
}

async function showCard(id) {
    window.location.href = '/toxic-market/card/' + id;
}

// Listings
async function loadListings() {
    try {
        const res = await api('listings');
        const listings = res.data || [];
        const statEl = document.getElementById('stat-listings');
        if (statEl) statEl.textContent = listings.length;
        const grid = document.getElementById('listings-grid');
        if (!grid) return;
        if (listings.length === 0) {
            grid.innerHTML = '<p class="empty">Noch keine Angebote. <a href="#" onclick="showCreateListing()">Erstelle das Erste!</a></p>';
            return;
        }
        const filterImages = document.getElementById('filter-images')?.checked;
        let html = '';
        for (const l of listings) {
            const images = parseImages(l.image_urls);
            const hasImage = Array.isArray(images) && images.length > 0 && images[0];
            if (filterImages && !hasImage) continue;
            const imgUrl = hasImage ? images[0] : `/toxic-market/cards/card.svg.php?id=${l.card_template_id}&gen=${l.generation || 1}&name=${encodeURIComponent(l.card_name || l.title)}&holo=0`;
            const genClass = `gen-${l.generation || 1}`;
            const genLabel = {1:'Genesis 2025',2:'Zitadelle 2026',3:'Remake EN'}[l.generation] || '';
            html += `
            <div class="listing-item" style="cursor:pointer;" onclick="window.location.href='/toxic-market/listing/${l.id}'">
                <div style="display:flex;gap:14px;">
                    <div style="width:80px;height:110px;background:linear-gradient(145deg,#1a1a3a,#0e0e20);border-radius:8px;overflow:hidden;flex-shrink:0;">
                        <img src="${imgUrl}" style="width:100%;height:100%;object-fit:contain;" alt="${l.title}" loading="lazy" onerror="this.src='/toxic-market/cards/card.svg.php?id=${l.card_template_id}&gen=${l.generation||1}&name=${encodeURIComponent(l.card_name||l.title)}&holo=0'">
                    </div>
                    <div style="flex:1;min-width:0;">
                        <span class="card-gen ${genClass}" style="font-size:9px;">${genLabel}</span>
                        <div class="listing-title" style="margin-top:4px;">${l.title}</div>
                        <div class="listing-price">${(l.price_sats || 0).toLocaleString()} sats</div>
                        <div class="listing-meta">${l.condition_text || ''}${l.seller_name ? ' · ' + l.seller_name : ''}</div>
                        ${l.serial_number ? `<div class="listing-meta">#${l.serial_number}</div>` : ''}
                    </div>
                </div>
            </div>`;
        }
        grid.innerHTML = html;
    } catch (e) { console.error(e); }
}

// Auctions
let auctionTimers = [];

async function loadAuctions() {
    try {
        const res = await api('auctions');
        const auctions = res.data || [];
        const grid = document.getElementById('auctions-grid');
        if (!grid) return;
        if (auctions.length === 0) {
            grid.innerHTML = '<p class="empty">Keine aktiven Auktionen. <a href="/toxic-market/create-auction">Erstelle die erste!</a></p>';
            return;
        }
        // Clear old timers
        auctionTimers.forEach(id => clearInterval(id));
        auctionTimers = [];
        
        grid.innerHTML = auctions.map(a => {
            const images = parseImages(a.image_urls);
            const imgUrl = (Array.isArray(images) && images.length > 0 && images[0]) ? images[0] : `/toxic-market/cards/card.svg.php?id=${a.card_template_id || 1}&gen=${a.generation || 1}&name=${encodeURIComponent(a.card_name || a.title || 'Karte')}&holo=0`;
            const genClass = `gen-${a.generation || 1}`;
            const genLabel = {1:'Genesis 2025',2:'Zitadelle 2026',3:'Remake EN'}[a.generation || 1] || '';
            const price = (a.current_price_sats || a.starting_price_sats || 0);
            return `
            <div class="listing-item" style="cursor:pointer;display:flex;gap:16px;" onclick="window.location.href='/toxic-market/auction/${a.id}'">
                <div style="width:80px;height:110px;background:linear-gradient(145deg,#1a1a3a,#0e0e20);border-radius:8px;overflow:hidden;flex-shrink:0;">
                    <img src="${imgUrl}" style="width:100%;height:100%;object-fit:contain;" alt="${a.title || ''}" loading="lazy" onerror="this.src='/toxic-market/cards/card.svg.php?id=${a.card_template_id || 1}&gen=${a.generation || 1}&name=Karte&holo=0'">
                </div>
                <div style="flex:1;min-width:0;">
                    <span class="card-gen ${genClass}" style="font-size:9px;">${genLabel}</span>
                    <div class="listing-title" style="margin-top:4px;">🔨 ${a.title || 'Auktion'}</div>
                    <div class="listing-price">${Number(price).toLocaleString()} sats</div>
                    <div class="auction-timer" data-ends="${a.ends_at}" id="timer-${a.id}" style="font-size:13px;">⏱ ...</div>
                    <div class="listing-meta">${a.bid_count || 0} Gebote${a.seller_name ? ' · ' + a.seller_name : ''}</div>
                </div>
            </div>`;
        }).join('');
        
        // Start countdown timers
        auctions.forEach(a => {
            updateAuctionTimer(`timer-${a.id}`, a.ends_at);
        });
        
        // Refresh auctions every 60s
        const refreshId = setInterval(() => {
            auctionTimers.forEach(id => clearInterval(id));
            loadAuctions();
        }, 60000);
        auctionTimers.push(refreshId);
    } catch (e) { console.error(e); }
}

function updateAuctionTimer(elId, endsAt) {
    const ends = new Date(endsAt).getTime();
    function tick() {
        const el = document.getElementById(elId);
        if (!el) return;
        const diff = ends - Date.now();
        if (diff <= 0) { el.textContent = '⏹ Beendet'; el.style.color = 'var(--danger)'; return; }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        if (d > 0) el.textContent = `⏱ ${d}T ${h}h ${m}m`;
        else if (h > 0) el.textContent = `⏱ ${h}h ${m}m ${s}s`;
        else el.textContent = `⏱ ${m}m ${s}s`;
        // Urgency colors
        if (diff < 3600000) el.style.color = 'var(--danger)';
        else if (diff < 86400000) el.style.color = 'var(--bitcoin)';
    }
    tick();
    const id = setInterval(tick, 1000);
    auctionTimers.push(id);
}

// ─── Password Reset ───
let resetToken = '';

function showResetPassword() {
    document.getElementById('login-form').classList.add('hidden');
    document.getElementById('register-form').classList.add('hidden');
    document.getElementById('reset-form').classList.remove('hidden');
    document.querySelector('.auth-divider').classList.add('hidden');
    document.querySelector('.auth-tabs').classList.add('hidden');
}

function backToLogin() {
    document.getElementById('login-form').classList.remove('hidden');
    document.getElementById('register-form').classList.add('hidden');
    document.getElementById('reset-form').classList.add('hidden');
    document.querySelector('.auth-divider').classList.remove('hidden');
    document.querySelector('.auth-tabs').classList.remove('hidden');
}

async function handleRequestReset() {
    const email = document.getElementById('reset-email').value.trim();
    if (!email) { toast('E-Mail eingeben', 'error'); return; }
    try {
        const res = await fetch(API + '?action=request_reset', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        if (data.success && data.token) {
            resetToken = data.token;
            document.getElementById('reset-token-area').classList.remove('hidden');
            document.getElementById('reset-token-display').textContent = data.token;
            toast('Token generiert! Setze jetzt dein Passwort zurück.', 'success');
        } else {
            toast(data.message || 'E-Mail nicht gefunden', data.success ? 'info' : 'error');
        }
    } catch(e) {
        toast('Server-Fehler', 'error');
    }
}

async function handleResetPassword() {
    const password = document.getElementById('reset-new-password').value;
    if (!resetToken) { toast('Erst Token generieren', 'warning'); return; }
    if (password.length < 6) { toast('Mind. 6 Zeichen', 'error'); return; }
    try {
        const res = await fetch(API + '?action=reset_password', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: resetToken, password })
        });
        const data = await res.json();
        if (data.success) {
            toast('Passwort geändert! Jetzt anmelden.', 'success');
            backToLogin();
        } else {
            toast(data.error || 'Fehler beim Zurücksetzen', 'error');
        }
    } catch(e) {
        toast('Server-Fehler', 'error');
    }
}

function showCreateListing(cardId) {
    if (!currentUser) { showAuth(); return; }
    if (cardId) {
        window.location.href = '/toxic-market/create?card=' + cardId;
    } else {
        window.location.href = '/toxic-market/create';
    }
}

// Auth check for protected pages
function showAuthFirst() {
    if (!currentUser) { showAuth(); return false; }
    return true;
}
async function loadBlockInfo() {
    try {
        const res = await api('current_block');
        const el = document.getElementById('proof-block-info');
        if (!el) return;
        el.innerHTML = `
            <strong>Block-Height:</strong> ${res.block_height.toLocaleString()}<br>
            <strong>Block-Hash:</strong> <span style="word-break:break-all;">${res.block_hash}</span><br>
            <br>
            <em>Schreibe auf deinen Zettel:</em><br>
            <strong>"Block ${res.block_height} — [Dein Benutzernamen]"</strong><br>
            <br>
            ${res.instruction}
        `;
    } catch (e) {
        const el = document.getElementById('proof-block-info');
        if (el) el.innerHTML = 'Fehler beim Laden der Block-Height. Versuche es erneut.';
    }
}

// Auto-load block info
loadBlockInfo();

// ─── Notification polling ───
async function checkNotifications() {
    if (!currentUser) return;
    try {
        const res = await fetch('/toxic-market/api/api.php?action=notifications&unread_only=1&limit=3', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.data && data.data.length > 0) {
            data.data.forEach(n => {
                const typeIcons = { outbid: '⚡', sale: '💰', purchase: '🛒', bid_deposit: '🔨' };
                const icon = typeIcons[n.type] || '🔔';
                toast(`${icon} ${n.title}: ${n.message}`, n.type === 'outbid' ? 'warning' : 'info', 6000);
            });
        }
    } catch(e) {}
}

// Check notifications on load and every 60s
if (currentUser) {
    checkNotifications();
    setInterval(checkNotifications, 60000);
    updateNotificationBadge();
    setInterval(updateNotificationBadge, 30000);
}

// ─── Notification Badge in Nav ───
async function updateNotificationBadge() {
    if (!currentUser) return;
    try {
        const res = await fetch('/toxic-market/api/api.php?action=notifications&unread_only=1&limit=1', { credentials: 'same-origin' });
        const data = await res.json();
        const count = data.data ? data.data.length : 0;
        // Find dashboard link and add badge
        const dashLinks = document.querySelectorAll('a[href*="/toxic-market/dashboard"]');
        dashLinks.forEach(link => {
            // Remove old badge
            const oldBadge = link.querySelector('.notif-badge-dot');
            if (oldBadge) oldBadge.remove();
            if (count > 0) {
                const badge = document.createElement('span');
                badge.className = 'notif-badge-dot';
                badge.style.cssText = 'position:absolute;top:-4px;right:-4px;width:8px;height:8px;background:var(--danger);border-radius:50%;';
                link.style.position = 'relative';
                link.appendChild(badge);
            }
        });
    } catch(e) {}
}