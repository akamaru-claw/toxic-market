/**
 * Toxic Market — Nostr Integration
 * 
 * - Keypair generation (nsec/npub)
 * - Event signing (Kind 0, 1, 30018, 30019)
 * - Publishing to relays
 * - NIP-07 extension support
 * 
 * NO private keys on the server. All signing is client-side.
 */

const NOSTR_RELAYS = [
    'wss://relay.damus.io',
    'wss://nos.lol',
    'wss://relay.nostr.band',
    'wss://nostr.wine',
];

// ─── Key Encoding ───
const BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

function bech32Polymod(values) {
    let chk = 1;
    for (const v of values) {
        const b = chk >> 25;
        chk = ((chk & 0x1ffffff) << 5) ^ v;
        for (let i = 0; i < 5; i++) if ((b >> i) & 1) chk ^= [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3][i];
    }
    return chk;
}

function bech32HrpExpand(hrp) {
    const ret = [];
    for (let i = 0; i < hrp.length; i++) ret.push(hrp.charCodeAt(i) >> 5);
    ret.push(0);
    for (let i = 0; i < hrp.length; i++) ret.push(hrp.charCodeAt(i) & 31);
    return ret;
}

function bech32CreateChecksum(hrp, data) {
    const values = bech32HrpExpand(hrp).concat(data).concat([0, 0, 0, 0, 0, 0]);
    const polymod = bech32Polymod(values) ^ 1;
    const ret = [];
    for (let i = 0; i < 6; i++) ret.push((polymod >> (5 * (5 - i))) & 31);
    return ret;
}

function convertBits(data, fromBits, toBits, pad) {
    let acc = 0, bits = 0;
    const ret = [];
    const maxv = (1 << toBits) - 1;
    for (const value of data) {
        acc = (acc << fromBits) | value;
        bits += fromBits;
        while (bits >= toBits) {
            bits -= toBits;
            ret.push((acc >> bits) & maxv);
        }
    }
    if (pad && bits > 0) ret.push((acc << (toBits - bits)) & maxv);
    return ret;
}

function bech32Encode(hrp, data) {
    const converted = convertBits(data, 8, 5, true);
    const checksum = bech32CreateChecksum(hrp, converted);
    let result = hrp + '1';
    for (const d of converted) result += BECH32_CHARSET[d];
    for (const d of checksum) result += BECH32_CHARSET[d];
    return result;
}

function bech32Decode(str) {
    const pos = str.lastIndexOf('1');
    const hrp = str.slice(0, pos);
    const data = [];
    for (let i = pos + 1; i < str.length; i++) {
        const idx = BECH32_CHARSET.indexOf(str[i]);
        if (idx === -1) return null;
        data.push(idx);
    }
    return { hrp, data: convertBits(data.slice(0, -6), 5, 8, false) };
}

function bytesToHex(bytes) {
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
}

function hexToBytes(hex) {
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < hex.length; i += 2) bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
    return bytes;
}

// ─── Nostr Keys ───
function encodeNsec(privKeyHex) {
    return bech32Encode('nsec', hexToBytes(privKeyHex));
}

function encodeNpub(pubKeyHex) {
    return bech32Encode('npub', hexToBytes(pubKeyHex));
}

function decodeNsec(nsec) {
    const decoded = bech32Decode(nsec);
    if (!decoded || decoded.hrp !== 'nsec') return null;
    return bytesToHex(new Uint8Array(decoded.data));
}

function decodeNpub(npub) {
    const decoded = bech32Decode(npub);
    if (!decoded || decoded.hrp !== 'npub') return null;
    return bytesToHex(new Uint8Array(decoded.data));
}

// ─── Schnorr Signature (via Web Crypto + noble-secp256k1 CDN) ───
// We load the library dynamically
let secp256k1 = null;

async function initSecp256k1() {
    if (secp256k1) return secp256k1;
    try {
        // Try dynamic import from local vendor file
        const mod = await import('/toxic-market/js/vendor/secp256k1.js');
        secp256k1 = mod;
        return secp256k1;
    } catch(e) {
        console.warn('Failed to load local secp256k1, trying CDN fallback');
        try {
            const mod = await import('https://cdn.jsdelivr.net/npm/@noble/secp256k1@2.1.0/+esm');
            secp256k1 = mod;
            return secp256k1;
        } catch(e2) {
            console.error('No secp256k1 available:', e2);
            return null;
        }
    }
}

// ─── Generate Keypair ───
async function generateNostrKeypair() {
    const lib = await initSecp256k1();
    if (!lib) {
        toast('Krypto-Bibliothek nicht geladen. Nostr-Features deaktiviert.', 'warning');
        return null;
    }
    
    const privKeyBytes = crypto.getRandomValues(new Uint8Array(32));
    const privKeyHex = bytesToHex(privKeyBytes);
    const pubKeyHex = bytesToHex(lib.schnorr.getPublicKey(privKeyBytes));
    
    return {
        privKey: privKeyHex,
        pubKey: pubKeyHex,
        nsec: encodeNsec(privKeyHex),
        npub: encodeNpub(pubKeyHex),
    };
}

// ─── Sign Event ───
async function signEvent(event, privKeyHex) {
    const lib = await initSecp256k1();
    if (!lib) return null;
    
    // Serialize event for signing (NIP-01)
    const serialized = JSON.stringify([
        0,
        event.pubkey,
        event.created_at,
        event.kind,
        event.tags,
        event.content,
    ]);
    
    // Hash
    const msgBuffer = new TextEncoder().encode(serialized);
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    const hashHex = bytesToHex(new Uint8Array(hashBuffer));
    event.id = hashHex;
    
    // Sign
    const sigBytes = lib.schnorr.sign(hashHex, privKeyHex);
    event.sig = bytesToHex(sigBytes);
    
    return event;
}

// ─── Publish to Relays ───
function publishToRelays(event) {
    let sent = 0;
    const total = NOSTR_RELAYS.length;
    
    for (const relayUrl of NOSTR_RELAYS) {
        try {
            const ws = new WebSocket(relayUrl);
            ws.onopen = () => {
                ws.send(JSON.stringify(['EVENT', event]));
                sent++;
                console.log(`Published to ${relayUrl}`);
                setTimeout(() => ws.close(), 2000);
            };
            ws.onerror = (e) => {
                console.warn(`Relay error: ${relayUrl}`, e);
            };
        } catch(e) {
            console.warn(`Can't connect to ${relayUrl}`, e);
        }
    }
    
    return { sent, total };
}

// ─── Create & Publish Marketplace Events ───

/**
 * Kind 0: User metadata
 */
async function publishProfile(nsecStr, name, about, picture) {
    const privKey = decodeNsec(nsecStr);
    if (!privKey) return null;
    
    const event = {
        kind: 0,
        created_at: Math.floor(Date.now() / 1000),
        tags: [],
        content: JSON.stringify({ name, about, picture }),
    };
    
    // Get pubkey from privkey
    const lib = await initSecp256k1();
    if (!lib) return null;
    event.pubkey = bytesToHex(lib.schnorr.getPublicKey(hexToBytes(privKey)));
    
    await signEvent(event, privKey);
    publishToRelays(event);
    return event;
}

/**
 * Kind 30018: Marketplace listing (NIP-15 inspired)
 * d-tag = listing id
 */
async function publishListing(nsecStr, listingId, title, price, description, cardName, generation) {
    const privKey = decodeNsec(nsecStr);
    if (!privKey) return null;
    
    const lib = await initSecp256k1();
    if (!lib) return null;
    
    const event = {
        kind: 30018,
        created_at: Math.floor(Date.now() / 1000),
        tags: [
            ['d', listingId],
            ['title', title],
            ['price', String(price), 'sats'],
            ['card', cardName],
            ['generation', String(generation)],
            ['platform', 'toxic-market'],
        ],
        content: description || `${title} — ${price} sats — Toxic Market`,
    };
    event.pubkey = bytesToHex(lib.schnorr.getPublicKey(hexToBytes(privKey)));
    
    await signEvent(event, privKey);
    publishToRelays(event);
    return event;
}

/**
 * Kind 30019: Marketplace auction (NIP-15 inspired)
 */
async function publishAuction(nsecStr, auctionId, title, startPrice, description, endsAt, cardName, generation) {
    const privKey = decodeNsec(nsecStr);
    if (!privKey) return null;
    
    const lib = await initSecp256k1();
    if (!lib) return null;
    
    const event = {
        kind: 30019,
        created_at: Math.floor(Date.now() / 1000),
        tags: [
            ['d', auctionId],
            ['title', title],
            ['start_price', String(startPrice), 'sats'],
            ['ends_at', String(Math.floor(new Date(endsAt).getTime() / 1000))],
            ['card', cardName],
            ['generation', String(generation)],
            ['platform', 'toxic-market'],
        ],
        content: description || `Auktion: ${title} — Start: ${startPrice} sats — Toxic Market`,
    };
    event.pubkey = bytesToHex(lib.schnorr.getPublicKey(hexToBytes(privKey)));
    
    await signEvent(event, privKey);
    publishToRelays(event);
    return event;
}

/**
 * Kind 1: Sold announcement
 */
async function publishSold(nsecStr, listingId, title, price) {
    const privKey = decodeNsec(nsecStr);
    if (!privKey) return null;
    
    const lib = await initSecp256k1();
    if (!lib) return null;
    
    const event = {
        kind: 1,
        created_at: Math.floor(Date.now() / 1000),
        tags: [
            ['e', listingId, '', 'root'],
            ['p', '', '', 'buyer'],
            ['platform', 'toxic-market'],
        ],
        content: `🤝 Sold: ${title} for ${price} sats — Toxic Market`,
    };
    event.pubkey = bytesToHex(lib.schnorr.getPublicKey(hexToBytes(privKey)));
    
    await signEvent(event, privKey);
    publishToRelays(event);
    return event;
}

/**
 * Kind 1: Bid placed
 */
async function publishBid(nsecStr, auctionId, amount) {
    const privKey = decodeNsec(nsecStr);
    if (!privKey) return null;
    
    const lib = await initSecp256k1();
    if (!lib) return null;
    
    const event = {
        kind: 1,
        created_at: Math.floor(Date.now() / 1000),
        tags: [
            ['e', auctionId, '', 'root'],
            ['price', String(amount), 'sats'],
            ['platform', 'toxic-market'],
        ],
        content: `🔨 Bid: ${amount} sats — Toxic Market`,
    };
    event.pubkey = bytesToHex(lib.schnorr.getPublicKey(hexToBytes(privKey)));
    
    await signEvent(event, privKey);
    publishToRelays(event);
    return event;
}

// ─── NIP-07 Extension Support ───
async function loginWithNip07() {
    if (!window.nostr) {
        toast('Keine Nostr-Extension gefunden. Installiere nos2x oder Alby.', 'warning');
        return null;
    }
    
    try {
        const pubKey = await window.nostr.getPublicKey();
        return { pubKey, npub: encodeNpub(pubKey), method: 'nip07' };
    } catch(e) {
        toast('NIP-07 Fehler: ' + e.message, 'error');
        return null;
    }
}

async function signWithNip07(event) {
    if (!window.nostr) return null;
    try {
        const sig = await window.nostr.signEvent(event);
        return sig;
    } catch(e) {
        toast('Signier-Fehler: ' + e.message, 'error');
        return null;
    }
}

// ─── Local Storage for nsec ───
function saveNsec(nsec) {
    try {
        // Store encrypted-ish (not truly secure, but better than plaintext)
        localStorage.setItem('tm_nsec', btoa(nsec));
    } catch(e) { console.error(e); }
}

function loadNsec() {
    try {
        const stored = localStorage.getItem('tm_nsec');
        if (!stored) return null;
        return atob(stored);
    } catch(e) { return null; }
}

function clearNsec() {
    localStorage.removeItem('tm_nsec');
}

function hasNsec() {
    return !!localStorage.getItem('tm_nsec');
}

// ─── Export ───
window.NostrTM = {
    generateKeypair,
    signEvent,
    publishToRelays,
    publishProfile,
    publishListing,
    publishAuction,
    publishSold,
    publishBid,
    loginWithNip07,
    signWithNip07,
    saveNsec,
    loadNsec,
    clearNsec,
    hasNsec,
    encodeNpub,
    encodeNsec,
    decodeNsec,
    decodeNpub,
    bytesToHex,
    hexToBytes,
    initSecp256k1,
};