# Toxic Market — MX12ART Sammelkarten-Auktionsplattform

> Bitcoin-basierte Auktionsplattform speziell für MX12ART Toxic Booster Sammelkarten, inspiriert von SatStash.io

## 🎯 Projektziel

Eine dedizierte Auktionsplattform für die Toxic Booster Sammelkarten von Künstler MX12ART, die im Umfeld der Einundzwanzig/Zitadelle-Community entstanden sind. Die Plattform soll den Handel mit diesen limitierten Karten ermöglichen, nachdem SatStash (die ursprüngliche Plattform) offline geht.

## 📋 SatStash Feature-Analyse (Referenz)

### Kern-Features von SatStash.io

| Feature | Beschreibung |
|---------|-------------|
| **Auktionen** | Zeitlich begrenzte Auktionen mit Bid-Deposits (Escrow) |
| **Fixed-Price Listings** | Feste Preise, direkt kaufbar |
| **Bitcoin-Zahlung** | Onchain + Lightning |
| **Escrow/Deposit** | Pfand bei Geboten, Schutz für Käufer & Verkäufer |
| **Shipping** | Lokal + International, mit Preisangabe |
| **Seller-Profile** | Mit Display Name, Twitter/X, Store-Slug |
| **Kategorien** | 23 Kategorien (trading-cards, art, collectibles, etc.) |
| **Public API** | REST API v1, OpenAPI-Schema, anonym lesbar |
| **Search/Filter** | Kategorie, Free Shipping, Sortierung |
| **Bilder** | Upload mit Thumbnail-Generierung |
| **llms.txt** | AI-Agent-Discovery-Endpunkt |

### SatStash API-Endpunkte (Referenz)
- `GET /drops` — Alle aktiven Listings/Auktionen
- `GET /drops/{id}` — Einzelnes Listing
- `GET /auctions/{id}` — Einzelne Auktion
- `GET /stores/{slug}` — Seller-Store
- `GET /categories` — Verfügbare Kategorien

### Zahlungsmodell SatStash
- Bid-Deposits: Onchain oder Lightning Bitcoin
- Finalbezahlung: Onchain, Lightning, Credit Card, Afterpay, Cash App, Affirm, Klarna

---

## 🃏 Toxic Booster — Karten-Übersicht

### Generation 1 (Zitadelle 2025)
- **21 Motive**, jeder 210x nummeriert (#001–#210)
- Gesamtauflage: 4.410 Karten
- **7 Karten pro Booster**
- Kart #21/210 = Holokarte (21 Stück pro Motiv = 441 Holo-Karten gesamt)
- Komplett ausverkauft

### Generation 2 (Zitadelle 2026)
- **21 neue Motive**, jeder 210x nummeriert
- Kart #1/210, #21/210, #210/210 = Holokarten (3 Holo-Varianten pro Motiv!)
- **Remakes der Gen 1**: 35 englischsprachige Nachdrucke je der 21 Gen-1-Motive

### Sonderkarten
- Holo-Karten: #21/210 (Gen 1), #1/210, #21/210, #210/210 (Gen 2)
- Error-Karten (Druckfehler-Varianten)
- Komplett-Sets

---

## 🏗️ Architektur-Plan

### Phase 1: MVP (Woche 1-2)
- [ ] Projekt-Setup: Next.js + SQLite + LNBits/LND
- [ ] Datenbank-Schema: cards, auctions, listings, users, bids
- [ ] Auth: Nostr-Login (wie Kickstr) + Email-Fallback
- [ ] Frontend: Karte-Detailseiten, Auktions-UI
- [ ] Bitcoin-Zahlung: LNURL/Lightning-Invoices

### Phase 2: Core Features (Woche 3-4)
- [ ] Auktions-System: Timer, Bid-Deposit, Outbid-Refund
- [ ] Fixed-Price Listings
- [ ] Bild-Upload (IPFS oder lokales Storage)
- [ ] Seller-Profile & Stores
- [ ] Such- und Filtersystem

### Phase 3: Karten-spezifisch (Woche 5-6)
- [ ] Karten-Datenbank mit MX12ART-Motiven
- [ ] Seriennummern-Verifikation
- [ ] Holo/Error/Special-Variant Erkennung
- [ ] Set-Builder (welche Karten fehlen fürs Komplett-Set?)
- [ ] Integration mit Toxic Booster Tracker (bestehende API)

### Phase 4: Zahlungen & Sicherheit (Woche 7-8)
- [ ] Lightning-Invoices via LNBits/LND
- [ ] Escrow-System für Bid-Deposits
- [ ] Shipping-Kostenrechner (DE, EU, International)
- [ ] Dispute-Resolution-System
- [ ] Reputationssystem für Verkäufer

### Phase 5: Polish & Launch (Woche 9-10)
- [ ] Responsive Design (Mobile-first)
- [ ] Public REST API (wie SatStash)
- [ ] llms.txt für AI-Agent-Discovery
- [ ] SEO & Social Sharing
- [ ] Deployment auf Strato (ml-bets.com)

---

## 🛠️ Tech Stack

| Komponente | Technologie |
|-----------|------------|
| Frontend | Next.js 14 + Tailwind CSS |
| Backend | PHP 8.2 (kompatibel mit Strato-Hosting) |
| Datenbank | SQLite (wie Toxic Booster Tracker) |
| Zahlungen | LNBits API / LND REST |
| Auth | Nostr (NIP-07/46) + Email/Password |
| Bilder | Lokales Storage + Thumbnails |
| Hosting | Strato (ml-bets.com Subdomain) |
| CI/CD | GitHub Actions → SFTP Deploy |

---

## 📐 Datenbank-Schema (Draft)

```sql
-- users
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nostr_pubkey TEXT UNIQUE,
    email TEXT UNIQUE,
    display_name TEXT NOT NULL,
    bio TEXT,
    avatar_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- card_templates (21 Motive pro Generation)
CREATE TABLE card_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,           -- z.B. "Genesis Card #1 - The Beginning"
    generation INTEGER NOT NULL,  -- 1 oder 2
    artist TEXT DEFAULT 'MX12ART',
    image_url TEXT,
    description TEXT,
    total_print_run INTEGER DEFAULT 210
);

-- card_variants (Holos, Errors, Remakes)
CREATE TABLE card_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER REFERENCES card_templates(id),
    variant_type TEXT NOT NULL,   -- 'standard', 'holo', 'error', 'remake_english'
    serial_number TEXT,            -- z.B. "021/210"
    description TEXT
);

-- listings (festpreis)
CREATE TABLE listings (
    id TEXT PRIMARY KEY,          -- UUID
    seller_id INTEGER REFERENCES users(id),
    card_template_id INTEGER REFERENCES card_templates(id),
    card_variant_id INTEGER REFERENCES card_variants(id),
    title TEXT NOT NULL,
    description TEXT,
    price_sats INTEGER NOT NULL,
    condition TEXT,               -- 'mint', 'near_mint', 'good', 'played'
    serial_number TEXT,           -- z.B. "042/210"
    image_urls TEXT,              -- JSON array
    is_sold BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- auctions
CREATE TABLE auctions (
    id TEXT PRIMARY KEY,          -- UUID
    seller_id INTEGER REFERENCES users(id),
    card_template_id INTEGER REFERENCES card_templates(id),
    card_variant_id INTEGER REFERENCES card_variants(id),
    title TEXT NOT NULL,
    description TEXT,
    starting_price_sats INTEGER NOT NULL,
    current_price_sats INTEGER,
    reserve_price_sats INTEGER,
    deposit_sats INTEGER NOT NULL,  -- Bid-Deposit
    serial_number TEXT,
    image_urls TEXT,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status TEXT DEFAULT 'pending', -- 'pending', 'active', 'ended', 'cancelled'
    winner_id INTEGER REFERENCES users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- bids
CREATE TABLE bids (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    auction_id TEXT REFERENCES auctions(id),
    bidder_id INTEGER REFERENCES users(id),
    amount_sats INTEGER NOT NULL,
    deposit_paid INTEGER DEFAULT 0,
    deposit_refunded BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- transactions (Escrow)
CREATE TABLE transactions (
    id TEXT PRIMARY KEY,          -- UUID
    type TEXT NOT NULL,           -- 'deposit', 'payment', 'refund'
    listing_id TEXT,
    auction_id TEXT,
    payer_id INTEGER REFERENCES users(id),
    payee_id INTEGER REFERENCES users(id),
    amount_sats INTEGER NOT NULL,
    payment_hash TEXT,
    payment_request TEXT,         -- BOLT11 invoice
    status TEXT DEFAULT 'pending', -- 'pending', 'paid', 'refunded'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔗 Verknüpfte Projekte

- **Toxic Booster Tracker**: https://ml-bets.com/toxic-booster/ (bestehende Karten-Datenbank)
- **GitHub (Tracker)**: https://github.com/akamaru-claw/toxic-booster-tracker
- **SatStash Referenz**: https://satstash.io (wird offline genommen)

---

## 📝 Entwicklungs-Tagebuch

Siehe [CHANGELOG.md](./CHANGELOG.md) für Änderungen.