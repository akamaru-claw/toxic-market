// Toxic Market — Frontend Logic
const API = '/toxic-market/api/api.php';

// State
let cards = [];
let currentFilter = 'all';
let currentUser = null;

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadCards();
    loadListings();
    loadAuctions();
    checkAuth();
    setupFilters();
    // Close mobile nav on link click
    document.querySelectorAll('#mobile-nav a').forEach(a => {
        a.addEventListener('click', () => {
            const nav = document.getElementById('mobile-nav');
            if (nav) nav.classList.add('hidden');
        });
    });
});

// Toggle mobile hamburger nav
function toggleMobileNav() {
    const nav = document.getElementById('mobile-nav');
    if (nav) nav.classList.toggle('hidden');
}

// API helper
async function api(action, data = null, method = 'GET') {
    const url = data && method === 'GET' 
        ? `${API}?action=${action}&${new URLSearchParams(data)}`
        : `${API}?action=${action}`;
    const opts = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
    return res.json();
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
    
    try {
        const res = await api('register', { display_name: name, email, password, accept_disclaimer: true }, 'POST');
        if (res.success) { hideAuth(); checkAuth(); location.reload(); }
        else { showError('reg-error', res.error || 'Registrierung fehlgeschlagen'); }
    } catch (e) { showError('reg-error', 'Server-Fehler'); }
}

async function logout() {
    await api('logout', null, 'POST');
    currentUser = null;
    showLoggedOut();
    location.reload();
}

function loginNostr() {
    if (window.nostr) {
        window.nostr.getPublicKey().then(pubkey => {
            api('login', { nostr_pubkey: pubkey }, 'POST').then(res => {
                if (res.success) { hideAuth(); checkAuth(); location.reload(); }
            });
        });
    } else {
        alert('Kein Nostr-Extension gefunden. Installiere nos2x oder Alby.');
    }
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

    grid.innerHTML = filtered.map(card => {
        const genLabel = { 1: 'Genesis 2025', 2: 'Zitadelle 2026', 3: 'Remake EN' }[card.generation];
        const genClass = `gen-${card.generation}`;
        const holoInfo = card.holo_positions?.length ? `Holo: #${card.holo_positions.join(', #')}/210` : '';
        const priceHTML = card.lowest_price ? `<div class="card-price">${card.lowest_price.toLocaleString()} sats</div>` : '';
        const listingsHTML = card.active_listings ? `<div class="card-meta">${card.active_listings} Angebot${card.active_listings > 1 ? 'e' : ''}</div>` : '';
        const total = card.generation === 3 ? 35 : 210;
        const imgUrl = card.image_url || `/toxic-market/cards/card.svg.php?id=${card.id}&gen=${card.generation}&name=${encodeURIComponent(card.name)}&holo=${(card.holo_positions?.includes(card.id) || card.holo_positions?.includes(21)) ? '1' : '0'}`;
        
        return `
        <div class="card-item" onclick="showCard(${card.id})">
            <div class="card-img"><img src="${imgUrl}" alt="${card.name}" style="width:100%;height:100%;object-fit:contain;border-radius:8px;" loading="lazy"></div>
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
}

function setupFilters() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.gen;
            renderCards();
        });
    });
    document.getElementById('card-search')?.addEventListener('input', renderCards);
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
        grid.innerHTML = listings.map(l => {
            const images = JSON.parse(l.image_urls || '[]');
            const imgUrl = images[0] || `/toxic-market/cards/card.svg.php?id=${l.card_template_id}&gen=${l.generation || 1}&name=${encodeURIComponent(l.card_name || l.title)}&holo=0`;
            const genClass = `gen-${l.generation || 1}`;
            const genLabel = {1:'Genesis 2025',2:'Zitadelle 2026',3:'Remake EN'}[l.generation] || '';
            return `
            <div class="listing-item" style="cursor:pointer;" onclick="window.location.href='/toxic-market/listing/${l.id}'">
                <div style="display:flex;gap:14px;">
                    <div style="width:80px;height:110px;background:linear-gradient(145deg,#1a1a3a,#0e0e20);border-radius:8px;overflow:hidden;flex-shrink:0;">
                        <img src="${imgUrl}" style="width:100%;height:100%;object-fit:contain;" alt="${l.title}" loading="lazy">
                    </div>
                    <div style="flex:1;min-width:0;">
                        <span class="card-gen ${genClass}" style="font-size:9px;">${genLabel}</span>
                        <div class="listing-title" style="margin-top:4px;">${l.title}</div>
                        <div class="listing-price">${l.price_sats.toLocaleString()} sats</div>
                        <div class="listing-meta">${l.condition_text} · ${l.seller_name}</div>
                        ${l.serial_number ? `<div class="listing-meta">#${l.serial_number}</div>` : ''}
                        ${l.free_shipping ? '<div style="font-size:11px;color:var(--accent);">🚚 Kostenloser Versand</div>' : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    } catch (e) { console.error(e); }
}

// Auctions
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
        grid.innerHTML = auctions.map(a => {
            const images = JSON.parse(a.image_urls || '[]');
            const imgUrl = images[0] || `/toxic-market/cards/card.svg.php?id=${a.card_template_id}&gen=${a.generation}&name=${encodeURIComponent(a.card_name || a.title)}&holo=0`;
            const ends = new Date(a.ends_at);
            const timeLeft = getTimeLeft(ends);
            const genClass = `gen-${a.generation}`;
            const genLabel = {1:'Genesis 2025',2:'Zitadelle 2026',3:'Remake EN'}[a.generation] || '';
            return `
            <div class="listing-item" style="cursor:pointer;display:flex;gap:16px;" onclick="window.location.href='/toxic-market/auction/${a.id}'">
                <div style="width:80px;height:110px;background:linear-gradient(145deg,#1a1a3a,#0e0e20);border-radius:8px;overflow:hidden;flex-shrink:0;">
                    <img src="${imgUrl}" style="width:100%;height:100%;object-fit:contain;" alt="${a.title}" loading="lazy">
                </div>
                <div style="flex:1;min-width:0;">
                    <span class="card-gen ${genClass}" style="font-size:9px;">${genLabel}</span>
                    <div class="listing-title" style="margin-top:4px;">🔨 ${a.title}</div>
                    <div class="listing-price">${(a.current_price_sats || a.starting_price_sats).toLocaleString()} sats</div>
                    <div class="auction-timer" style="font-size:13px;">⏱ ${timeLeft}</div>
                    <div class="listing-meta">${a.bid_count || 0} Gebote · ${a.seller_name}</div>
                </div>
            </div>`;
        }).join('');
    } catch (e) { console.error(e); }
}

function getTimeLeft(date) {
    const diff = date - new Date();
    if (diff < 0) return 'Beendet';
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const mins = Math.floor((diff % 3600000) / 60000);
    if (days > 0) return `${days}T ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
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