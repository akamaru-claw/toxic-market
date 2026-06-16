# Changelog — Toxic Market

Alle nennenswerten Änderungen werden in dieser Datei dokumentiert.

## [Unreleased]

### Security
- IP-basiertes Rate-Limiting für Login, Registrierung und Passwort-Reset (`includes/rate_limit.php`).
- Passwort-Reset-Endpunkt gibt keinen `debug_email_sent`-Flag mehr an den Client zurück.
- Rate-Limit-Fehler geben `Retry-After` mit zurück.
- Admin-Check über `isAdmin()` statt hartkodierter E-Mail; konfigurierbar via `data/admin_users.json`.
- `.gitignore` hinzugefügt: `data/*`, `uploads/*`, `.env`-Dateien und Logs werden nicht mehr committed.
- Passwort-Mindestlänge von 6 auf 8 Zeichen erhöht (`api/api.php`, `includes/auth.php`, `public/js/toxic.js`).
- Serverseitige Eingabevalidierung für `create_listing` und `create_auction` eingeführt: Preis-/Reserve-Grenzen, max. 5 Bild-URLs, Karten-ID-Prüfung, Versandbeträge, Startzeit-Validierung.
- Security-Header via `.htaccess`: CSP, HSTS, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, X-Content-Type-Options.
- API-Responses erhalten eigene CSP-, NoSniff- und Frame-Options-Header.

### Added
- Admin-Panel im Dashboard: LNBits-URL, API-Key, Fallback-Onchain-Adresse und Sandbox-Modus können direkt gespeichert werden.
- LNBits-Verbindungstest (`api/api.php?action=lnbits_test`) für Admins.
- `isAdmin()`-Helper in `includes/auth.php`.
- Validierungs-Helper `sanitizeUserText()`, `validateListingPayload()`, `validateAuctionPayload()` in `api/api.php`.

### Changed
- Alle Root-PHP-Seiten erhalten die gleichen PWA/Mobile-Meta-Tags wie `public/` (cache-busting `?v=2`, `viewport-fit=cover`, theme-color).
- Clientseitige Registrierung prüft nun Anzeigename (2–50 Zeichen), E-Mail-Format und Passwortlänge (≥8 Zeichen).

### Fixed
- Synchronisation von Mobile-Viewport-Änderungen zwischen `public/` und Root-PHP-Dateien.
- `index.html` fehlte im Repo-Root; `public/index.html` wurde ins Root kopiert, damit `.htaccess` SPA-Routing funktioniert.
- `create_auction` speichert nun zusätzlich `reserve_price_sats` und nutzt validierte Start-/Endzeiten.

## [v0.1.0] — 2026-06-15

### Added
- Initiale Version: Karten, Angebote, Auktionen, Dashboard, LNBits-Zahlungen, Benachrichtigungen, Set-Builder.
