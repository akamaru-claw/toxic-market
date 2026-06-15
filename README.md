# Toxic Market — MX12ART Sammelkarten-Plattform

> Bitcoin-basierter Marktplatz für MX12ART Toxic Booster Sammelkarten

## 🌐 Live

**https://ml-bets.com/toxic-market/**

## 🃏 Karten-Übersicht

| Generation | Motive | Auflage | Holo |
|-----------|--------|---------|------|
| Genesis 2025 | 21 | 210 Stück/motiv | #21/210 |
| Zitadelle 2026 | 21 | 210 Stück/motiv | #1, #21, #210 |
| Remake (EN) | 21 | 35 Stück/motiv | #21/210 |

Gesamt: 63 Kartenmotive · 13.440+ Karten

## 🏗️ Features

### Phase 1 ✅ (MVP)
- [x] PHP + SQLite Backend auf Strato
- [x] JWT-Auth (Email + Nostr)
- [x] REST API (cards, listings, auctions, proof-of-ownership)
- [x] Karten-Datenbank (63 Motive, 3 Generationen)
- [x] Disclaimer-Banner
- [x] Dark Theme mit Holographic-Effekten

### Phase 2 ✅ (Card Detail + Create Listing)
- [x] Karten-Detailseite (`/card/{id}`)
- [x] Angebot erstellen (`/create`)
- [x] Besitznachweis (Block-Height + Foto)
- [x] Responsive Design (Mobile-first)

### Phase 3 ✅ (Seller + Listing-Detail + Upload)
- [x] Verkäufer-Profil (`/seller/{id}`)
- [x] Listing-Detailseite (`/listing/{id}`)
- [x] Bild-Upload (max 5 Bilder + Proof-Bild)
- [x] Drag & Drop Upload
- [x] Listing bearbeiten/löschen
- [x] Meine Angebote (`my_listings`)

### Phase 4 (Zahlungen)
- [ ] Lightning-Invoices (LNBits)
- [ ] Escrow für Bid-Deposits
- [ ] Onchain-Zahlung
- [ ] Zahlungsstatus-Tracking

### Phase 5 (Auktionen)
- [ ] Auktions-Timer (Live-Countdown)
- [ ] Gebots-System
- [ ] Bid-Deposit (Lightning)
- [ ] Outbid-Notifications

### Phase 6 (Polish)
- [ ] Karten-Bilder (MX12ART Motive)
- [ ] Set-Builder (welche fehlen?)
- [ ] E-Mail-Notifications
- [ ] Public REST API v1
- [ ] SEO & Social Sharing (OG-Tags)

## 🛠️ Tech Stack

| Komponente | Technologie |
|-----------|------------|
| Frontend | Vanilla JS + Custom CSS |
| Backend | PHP 8.2 (Strato-kompatibel) |
| Datenbank | SQLite |
| Zahlungen | Lightning (geplant) |
| Auth | Email/Password + Nostr (NIP-07) |
| Bilder | Lokaler Upload (max 5MB) |
| Hosting | Strato (ml-bets.com) |
| Deploy | SFTP via `sshpass` |

## 📁 Projektstruktur

```
toxic-market/
├── api/
│   └── api.php              # REST API (alle Endpoints)
├── includes/
│   ├── auth.php              # Login/Register/Session
│   └── db.php                # SQLite + Schema + Seed
├── public/
│   ├── .htaccess             # URL-Rewriting + Security
│   ├── index.html            # Hauptseite (SPA)
│   ├── card.html             # Karten-Detail (PHP)
│   ├── create.html           # Angebot erstellen (PHP)
│   ├── listing.html          # Listing-Detail (PHP)
│   ├── seller.html           # Verkäufer-Profil (PHP)
│   ├── css/
│   │   ├── toxic.css         # Main Stylesheet (Dark Theme)
│   │   └── toxic-card.css    # Card Detail Styles
│   ├── js/
│   │   └── toxic.js          # Frontend Logic
│   ├── uploads/              # User-Uploaded Bilder
│   └── favicon.svg
├── README.md
└── CHANGELOG.md
```

## 🚀 Deploy

Credentials müssen lokal als Umgebungsvariablen gesetzt sein:

```bash
export STRATO_HOST="${STRATO_HOST:-}"
export STRATO_USER="${STRATO_USER:-}"
export STRATO_PASS="dein-passwort"
cd /home/jordy/.openclaw/workspace/toxic-market
bash deploy_toxic.sh
```

Oder alternativ über `deploy_toxic.py` (liest ebenfalls aus Env-Variablen).

## ⚠️ Wichtige Hinweise

1. **Pfade:** Alle Ressourcen nutzen `/toxic-market/` als Pfad-Prefix
2. **Strato-Cache:** Bei CSS/JS-Updates Dateinamen ändern oder `rm` + `put` via SFTP
3. **PHP-Pfade:** `$_SERVER['DOCUMENT_ROOT'] . '/toxic-market/...'`
4. **DB:** Automatisch erstellt bei erstem API-Aufruf
5. **Bilder:** Uploads gehen nach `/public/toxic-market/uploads/`

## 🔗 Verknüpfte Projekte

- **Toxic Booster Tracker**: https://ml-bets.com/toxic-booster/
- **GitHub**: https://github.com/akamaru-claw/toxic-market