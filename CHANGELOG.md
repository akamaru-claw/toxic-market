# Toxic Market — Entwicklungs-Tagebuch

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