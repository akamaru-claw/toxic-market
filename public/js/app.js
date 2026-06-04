// Toxic Market — Frontend Logic
const API = '/api/api.php';

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
});

// API helper
async function api(action, data = null, method = 'GET') {
    const url = data && method === 'GET' 
        ? `${API}?action=${action}&${new URLSearchParams(data)}`
        : `${API}?action=${action}`;
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
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
    document.getElementById('login-btn').classList.add('hidden');
    document.getElementById('user-menu').classList.remove('hidden');
    document.getElementById('user-name').textContent = currentUser.display_name;
}

function showLoggedOut() {
    document.getElementById('login-btn').classList.remove('hidden');
    document.getElementById('user-menu').classList.add('hidden');
}

function showAuth() { document.getElementById('auth-modal').classList.remove('hidden'); }
function hideAuth() { document.getElementById('auth-modal').classList.add('hidden'); }

function switchTab(tab) {
    document.querySelectorAll('.auth-tabs .tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.auth-tabs .tab:nth-child(${tab === 'login' ? 1 : 2})`).classList.add('active');
    document.getElementById('login-form').classList.toggle('hidden', tab !== 'login');
    document.getElementById('register-form').classList.toggle('hidden', tab !== 'register');
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    try {
        const res = await api('login', { email, password }, 'POST');
        if (res.success) { hideAuth(); checkAuth(); }
        else { showError('login-error', res.error || 'Anmeldung fehlgeschlagen'); }
    } catch (e) { showError('login-error', 'Server-Fehler'); }
}

async function handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById('reg-name').value;
    const email = document.getElementById('reg-email').value;
    const password = document.getElementById('reg-password').value;
    const acceptDisclaimer = document.getElementById('reg-disclaimer').checked;
    
    if (!acceptDisclaimer) {
        showError('reg-error', 'Du musst den Disclaimer akzeptieren');
        return;
    }
    
    try {
        const res = await api('register', { display_name: name, email, password, accept_disclaimer: true }, 'POST');
        if (res.success) { hideAuth(); checkAuth(); }
        else { showError('reg-error', res.error || 'Registrierung fehlgeschlagen'); }
    } catch (e) { showError('reg-error', 'Server-Fehler'); }
}

async function logout() {
    await api('logout', null, 'POST');
    currentUser = null;
    showLoggedOut();
}

function loginNostr() {
    if (window.nostr) {
        window.nostr.getPublicKey().then(pubkey => {
            api('login', { nostr_pubkey: pubkey }, 'POST').then(res => {
                if (res.success) { hideAuth(); checkAuth(); }
            });
        });
    } else {
        alert('Kein Nostr-Extension gefunden. Installiere nos2x oder Alby.');
    }
}

function showError(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

// Cards
async function loadCards() {
    try {
        const res = await api('cards');
        cards = res.data || [];
        document.getElementById('stat-cards').textContent = cards.length;
        renderCards();
    } catch (e) { console.error('Cards load error:', e); }
}

function renderCards() {
    const grid = document.getElementById('cards-grid');
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
        
        return `
        <div class="card-item" onclick="showCard(${card.id})">
            <div class="card-img">🧪</div>
            <div class="card-info">
                <div class="card-name">${card.name}</div>
                <div class="card-meta">210 Stück</div>
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
    try {
        const card = await api('card', { id });
        // Navigate to card detail (TODO: create card detail page)
        console.log('Card:', card);
        alert(`Karte: ${card.name}\n\nDetail-Seite kommt in Phase 2!`);
    } catch (e) { console.error(e); }
}

// Listings
async function loadListings() {
    try {
        const res = await api('listings');
        const listings = res.data || [];
        document.getElementById('stat-listings').textContent = listings.length;
        const grid = document.getElementById('listings-grid');
        if (listings.length === 0) {
            grid.innerHTML = '<p class="empty">Noch keine Angebote. <a href="#" onclick="showCreateListing()">Erstelle das Erste!</a></p>';
            return;
        }
        grid.innerHTML = listings.map(l => `
            <div class="listing-item">
                <div class="listing-title">${l.title}</div>
                <div class="listing-price">${l.price_sats.toLocaleString()} sats</div>
                <div class="listing-meta">${l.condition_text} · ${l.seller_name} · ${l.card_name}</div>
                ${l.serial_number ? `<div class="listing-meta">#${l.serial_number}</div>` : ''}
            </div>
        `).join('');
    } catch (e) { console.error(e); }
}

// Auctions
async function loadAuctions() {
    try {
        const res = await api('auctions');
        const auctions = res.data || [];
        const grid = document.getElementById('auctions-grid');
        if (auctions.length === 0) {
            grid.innerHTML = '<p class="empty">Keine aktiven Auktionen.</p>';
            return;
        }
        grid.innerHTML = auctions.map(a => {
            const ends = new Date(a.ends_at);
            const timeLeft = getTimeLeft(ends);
            return `
            <div class="listing-item">
                <div class="listing-title">🔨 ${a.title}</div>
                <div class="listing-price">${(a.current_price_sats || a.starting_price_sats).toLocaleString()} sats</div>
                <div class="auction-timer">⏱ ${timeLeft}</div>
                <div class="listing-meta">${a.bid_count || 0} Gebote · ${a.seller_name}</div>
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

function showCreateListing() {
    if (!currentUser) { showAuth(); return; }
    alert('Listing-Formular kommt in Phase 2!');
}

// Proof of Ownership
async function loadBlockInfo() {
    try {
        const res = await api('current_block');
        const el = document.getElementById('proof-block-info');
        el.innerHTML = `
            <strong>Block-Height:</strong> ${res.block_height.toLocaleString()}<br>
            <strong>Block-Hash:</strong> ${res.block_hash}<br>
            <br>
            <em>Schreibe auf deinen Zettel:</em><br>
            <strong>"Block ${res.block_height} — [Dein Benutzername]"</strong><br>
            <br>
            ${res.instruction}
        `;
    } catch (e) {
        document.getElementById('proof-block-info').innerHTML = 'Fehler beim Laden der Block-Height. Versuche es erneut.';
    }
}

// Auto-load block info on page load
loadBlockInfo();