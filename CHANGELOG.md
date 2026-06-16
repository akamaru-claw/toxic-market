# Changelog — Toxic Market

Alle nennenswerten Änderungen werden in dieser Datei dokumentiert.

## [Unreleased]

### Security
- IP-basiertes Rate-Limiting für Login, Registrierung und Passwort-Reset (`includes/rate_limit.php`).
- Passwort-Reset-Endpunkt gibt keinen `debug_email_sent`-Flag mehr an den Client zurück.
- Rate-Limit-Fehler geben `Retry-After` mit zurück.
- Admin-Check über `isAdmin()` statt hartkodierter E-Mail; konfigurierbar via `data/admin_users.json`.
- `.gitignore` hinzugefügt: `data/*`, `uploads/*`, `.env`-Dateien und Logs werden nicht mehr committed.

### Added
- Admin-Panel im Dashboard: LNBits-URL, API-Key, Fallback-Onchain-Adresse und Sandbox-Modus können direkt gespeichert werden.
- LNBits-Verbindungstest (`api/api.php?action=lnbits_test`) für Admins.
- `isAdmin()`-Helper in `includes/auth.php`.

### Changed
- Alle Root-PHP-Seiten erhalten die gleichen PWA/Mobile-Meta-Tags wie `public/` (cache-busting `?v=2`, `viewport-fit=cover`, theme-color).

### Fixed
- Synchronisation von Mobile-Viewport-Änderungen zwischen `public/` und Root-PHP-Dateien.

## [v0.1.0] — 2026-06-15

### Added
- Initiale Version: Karten, Angebote, Auktionen, Dashboard, LNBits-Zahlungen, Benachrichtigungen, Set-Builder.
