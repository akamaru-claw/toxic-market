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
- [x] Session-basierte Auth (Email + Nostr vorbereitet)
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

### Phase 4 ✅/🚧 (Zahlungen — Grundgerüst da, Konfiguration nötig)
- [x] Lightning-Invoice-Erstellung (LNBits-Integration)
- [x] Onchain-Adresse auslesen
- [x] Zahlungsstatus-Tracking (check_payment)
- [ ] LNBits Live-Config auf Server
- [ ] Echte Zahlungsabwicklung testen

### Phase 5 ✅/🚧 (Auktionen)
- [x] Auktions-Timer (Live-Countdown)
- [x] Gebots-System
- [x] Bid-Deposit (Lightning) — Grundgerüst
- [x] Outbid-Notifications (in-app)
- [ ] Outbid-E-Mail-Notifications

### Phase 6 ✅/🚧 (Polish)
- [x] Set-Builder (welche Karten fehlen?)
- [x] SEO & Social Sharing (OG-Tags)
- [ ] Karten-Bilder (MX12ART Motive) — aktuell SVG-Generierung
- [ ] Public REST API v1
- [ ] E-Mail-Notifications

## 🛠️ Tech Stack

| Komponente | Technologie |
|-----------|------------|
| Frontend | Vanilla JS + Custom CSS |
| Backend | PHP 8.2 (Strato-kompatibel) |
| Datenbank | SQLite |
| Zahlungen | Lightning (LNBits) + Onchain |
| Auth | Email/Password + Nostr (NIP-07, server-seitig deaktiviert) |
| Bilder | Lokaler Upload (max 5MB) |
| Hosting | Strato (ml-bets.com) |
| Deploy | SFTP via `sshpass` oder Python/`paramiko` |

## 📁 Projektstruktur

```
toxic-market/
├── api/
│   └── api.php              # REST API (alle Endpoints)
├── includes/
│   ├── auth.php             # Login/Register/Session/CSRF
│   ├── db.php               # SQLite + Schema + Seed
│   ├── payments.php         # LNBits + Onchain + Transaktionen
│   └── email.php            # Mail-Transport (aktuell Stub)
├── cards/
│   └── card.svg.php         # Dynamische SVG-Karten
├── public/                  # Development-Version der UI
│   ├── .htaccess
│   ├── *.html               # Statische HTML-Vorlagen
│   ├── *.php                # PHP-Vorlagen (kopiert nach Root)
│   ├── css/
│   │   └── toxic.css        # Main Stylesheet (Dark Theme)
│   │   └── toxic-card.css
│   └── js/
│       ├── toxic.js         # Frontend Logic
│       ├── nostr.js         # Nostr-Auth (deaktiviert)
│       └── noble-curves-bundle.js
├── data/                    # SQLite + Configs (geschützt via .htaccess)
├── uploads/                 # User-Uploaded Bilder (geschützt)
├── scripts/                 # Migrationsskripte
├── .htaccess                # URL-Rewriting + Security (deploybar)
├── card.php                 # Deploybare Karten-Detailseite
├── create.php               # Deploybare Angebotsseite
├── create-auction.php       # Deploybare Auktionserstellseite
├── listing.php              # Deploybare Listing-Detailseite
├── seller.php               # Deploybare Verkäufer-Profilseite
├── auction.php              # Deploybare Auktionsdetailseite
├── dashboard.php            # Deploybares Dashboard
├── set-builder.php          # Deploybarer Set-Builder
├── deploy_toxic.sh          # Bash SFTP-Deploy
├── deploy_toxic.py          # Python SFTP-Deploy
├── README.md
├── CHANGELOG.md
├── TODO.md
└── SECURITY.md
```

> **Hinweis:** `public/` ist die Entwicklungs-Referenz für UI-Dateien. Die deploybaren PHP-Dateien liegen im Repo-Root. Vor einem Deploy werden Änderungen aus `public/` ins Root kopiert.

## 🚀 Deploy

Credentials müssen lokal als **Umgebungsvariablen** gesetzt sein. Das Passwort wird nicht mehr im Repo gespeichert:

```bash
export STRATO_HOST="${STRATO_HOST:-}"
export STRATO_USER="${STRATO_USER:-}"
export STRATO_PASS="dein-passwort"
cd /home/jordy/.openclaw/workspace/toxic-market
bash deploy_toxic.sh
```

Oder alternativ:

```bash
python3 deploy_toxic.py
```

Trockenlauf:

```bash
bash deploy_toxic.sh --dry-run   # nicht unterstützt, Script führt aus
python3 deploy_toxic.py --dry-run
```

**Wichtig:** Keine Deploys auf Strato ohne Abstimmung mit Kiba.

## ⚠️ Wichtige Hinweise

1. **Pfade:** Alle Ressourcen nutzen `/toxic-market/` als Pfad-Prefix
2. **Strato-Cache:** Bei CSS/JS-Updates Dateinamen ändern oder `?v=2` Cache-Busting nutzen
3. **PHP-Pfade:** `$_SERVER['DOCUMENT_ROOT'] . '/toxic-market/...'`
4. **DB:** Automatisch erstellt bei erstem API-Aufruf (`data/toxic_market.db`)
5. **Bilder:** Uploads gehen nach `/public/toxic-market/uploads/` (via .htaccess geschützt)
6. **Sicherheit:** Nostr-Login ist server-seitig deaktiviert, bis Schnorr-Signaturen verifiziert werden. Siehe `SECURITY.md`.

## 🔗 Verknüpfte Projekte

- **Toxic Booster Tracker**: https://ml-bets.com/toxic-booster/
- **GitHub**: https://github.com/akamaru-claw/toxic-market
- **API-Dokumentation**: [API.md](./API.md) · [OpenAPI](./openapi.yaml) · [Postman Collection](./Toxic_Market_API.postman_collection.json)
