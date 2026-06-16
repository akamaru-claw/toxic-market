# Toxic Market — Entwicklungs-Tagebuch

## 2026-06-16 — Night-Dev: Code-Sync + Deploy-Scripte + Dokumentation

### Cleanup
- **Root- und `public/`-PHP-Dateien synchronisiert**: `card.php`, `create.php`, `create-auction.php`, `listing.php`, `seller.php`, `auction.php`, `dashboard.php`, `set-builder.php`, `sitemap.php` wurden aus `public/` ins Repo-Root kopiert.
- **Deploy-Scripte repariert**: `deploy_toxic.sh` und `deploy_toxic.py` laden jetzt aus dem Repo-Root (PHP-Dateien) und `public/` (CSS/JS/Karten). Sie referenzieren keine veralteten `public/*.html`-Pfade mehr.
- **Statische Root-Dateien synchronisiert**: `404.html`, `llms.txt`, `robots.txt`, `favicon.svg` aus `public/` ins Root kopiert.

### Dokumentation
- `README.md` aktualisiert: korrekte Projektstruktur, aktueller Feature-Status, Deploy-Hinweise (nur Umgebungsvariablen, kein Passwort im Repo).
- `TODO.md` angelegt: Blocker, Zahlungen/Auktionen, Security, Tests, Tech Debt.
- `CHANGELOG.md` mit diesem Eintrag ergänzt.

### GitHub Issues
- Duplikat-Issue #1 (Email-Transport) wird geschlossen, da #2 den gleichen Inhalt hat.

## 2026-06-16 — Security-Hardening nach Review

### Sicherheitsfixes
- **SFTP-Zugangsdaten aus Repo entfernt** (`README.md`, `deploy_toxic.sh`, `deploy_toxic.py`) — werden jetzt aus Umgebungsvariablen geladen.
- **Passwort-Reset** gibt den Reset-Token nicht mehr in der API-Antwort zurück; stattdessen wird `sendResetEmail()` aufgerufen (aktuell Stub, siehe Issue #2).
- **Nostr-Login vorübergehend deaktiviert**, weil keine serverseitige Schnorr-Signaturprüfung vorhanden war — Account-Übernahme per bekanntem npub möglich.
- **PHP-Fehlerausgabe gehärtet**: `display_errors=0`, Fehler nur serverseitig geloggt, Deprecation/Strict-Warnungen unterdrückt.

### Neue Dokumentation
- `SECURITY.md` mit Status aller gemeldeten Schwachstellen und offenen TODOs.
- GitHub Issues angelegt:
  - Issue #1: Serverseitige Schnorr-Verifikation für Nostr-Login
  - Issue #2: Strato-SFTP-Passwort wegen Git-History-Leak rotieren
  - Issue #3: Echte E-Mail-Versand-Infrastruktur für Passwort-Reset

## 2026-06-04 — Phase 3: Seller, Listing-Detail, Upload

### Neue Features
- **Verkäufer-Profil** (`/seller/{id}`): Avatar, Name, Bio, Statistiken, alle aktiven Angebote
- **Listing-Detailseite** (`/listing/{id}`): Großes Bild, Preis, Zustand, Seriennummer, Versand, Verkäufer-Info, Besitznachweis
- **Bild-Upload**: Drag & Drop + File Input, max 5 Bilder pro Angebot + 1 Proof-Bild
- **Listing verwalten**: `update_listing`, `delete_listing`, `my_listings` API-Endpunkte
- **Listing-Detail API**: `/api?action=listing&id=X`, `/api?action=seller&id=X`

### API-Endpunkte (neu)
- `listing` — Listing-Detail mit Verkäufer-Info
- `seller` — Seller-Profil mit aktiven Angeboten
- `upload_image` — Bild-Upload (JPG/PNG/WebP, max 5MB)
- `update_listing` — Listing bearbeiten (nur eigene)
- `delete_listing` — Listing löschen (nur eigene)
- `my_listings` — Eigene Angebote auflisten

### Design
- Premium Dark Theme mit Glasmorphismus
- Holographic Gradient-Effekte
- Inter + JetBrains Mono Fonts
- Drag & Drop Bild-Upload mit Preview
- Responsive Mobile-First Design

### Bugfixes
- **CSS-Pfad-Problem:** Strato Webroot = `/public/`, alle Pfade müssen `/toxic-market/` als Prefix haben
- **Strato-Cache:** CSS/JS-Dateien werden gecached, neue Dateinamen erzwingen Cache-Bypass
- **SFTP-Upload:** Datei zuerst `rm`, dann `put` um sicherzustellen dass die neue Version greift

## 2026-06-04 — Phase 2: Card Detail + Create Listing

### Neue Features
- Karten-Detailseite (`/card/{id}`)
- Angebot erstellen (`/create`)
- Besitznachweis (Block-Height + Username)
- Disclaimer-Banner auf jeder Seite

### API-Endpunkte
- `card` — Karten-Detail mit Varianten, Listings und Auktionen
- `create_listing` — Neues Angebot erstellen
- `current_block` — Aktuelle Bitcoin Block-Height für Proof-of-Ownership

### Datenbank-Schema
- Beweisspalten in `listings`: `proof_image_url`, `proof_block_height`, `proof_block_hash`, `proof_verified`
- `proof_verifications` Tabelle für Community-Verifikation

## 2026-06-04 — Projekt-Start + Phase 1 (MVP)

### SatStash Analyse
- Typ: Bitcoin-Marktplatz für physische Güter
- Features: Auktionen, Fixed-Price, Escrow, Seller-Stores
- Referenz für API-Design und UX

### Architektur-Entscheidungen
- PHP + SQLite (Strato-kompatibel, kein Node.js verfügbar)
- Vanilla JS Frontend (kein Framework für einfaches Hosting)
- Auth: Email/Password + Nostr
- Proof-of-Ownership via Bitcoin Block-Height
- Bilder: Lokaler Upload (kein S3/IPFS nötig)
