# Changelog — Toxic Market

Alle nennenswerten Änderungen werden in dieser Datei dokumentiert.

## [Unreleased]

### Added
- `includes/PHPMailer/`: PHPMailer 6.9.1 als lokale SMTP-Transport-Bibliothek (Strato-kompatibel, kein Composer nötig).
- `includes/email.php`: Echter SMTP/SSL/TLS-Versand via PHPMailer; `data/email_config.json` oder Umgebungsvariablen (`TOXIC_SMTP_*`) aktivieren ihn. Ohne Config bleibt `mail()`-Fallback erhalten.
- `api/api.php`: Passwort-Reset-E-Mails werden per `sendEmail()` verschickt (HTML-Template, 60-Minuten-Link), statt nur ins Server-Log geschrieben.
- `openapi.yaml`: Erste öffentliche OpenAPI 3.0 Spezifikation für alle v1-Endpunkte (Auth, Cards, Listings, Auctions, Payments, Admin, Uploads).
- Smoke-Tests für E-Mail-Validierung und SMTP-Config-Overrides in `tests/AuthTest.php`.

### Changed
- `includes/email.php`: `getEmailConfig()` liest jetzt auch `TOXIC_SMTP_HOST`, `TOXIC_SMTP_PORT`, `TOXIC_SMTP_USER`, `TOXIC_SMTP_PASS`, `TOXIC_SMTP_SECURE` und `TOXIC_MAIL_FROM` aus der Umgebung.
- `SECURITY.md`: E-Mail-Transport als implementiert dokumentiert.
- `TODO.md`: Abgeschlossene Punkte für Passwort-Reset-E-Mail, OpenAPI-Spec und erweiterte Smoke-Tests markiert.

### Security
- Passwort-Reset-Link wird nicht mehr ins Server-Fehlerlog geschrieben, sondern per E-Mail übertragen.
- SMTP-Credentials werden nur aus der lokalen `data/email_config.json` oder Umgebungsvariablen gelesen, nicht aus dem Repo.

### Fixed
- `api/api.php`: `sendResetEmail()` liefert jetzt immer einen konsistenten booleschen Rückgabewert basierend auf `sendEmail()`.

## [Unreleased]  (vorheriger Stand)

### Added
- `API.md`: Erste Public-REST-API-Dokumentation (Endpunkte, Regeln, CSP-Header, offene TODOs).
- `includes/validation.php`: Zentrale Validierungs-Helper `sanitizeUserText()`, `validateListingPayload()`, `validateAuctionPayload()` aus `api/api.php` extrahiert, um Tests und Wiederverwendung zu ermöglichen.
- `tests/AuthTest.php`: Erstes Smoke-Test-Skelett für Auth + Listing/Auktion-Validierung (11/11 grün).
- `includes/email.php`: Rate-Limiting für E-Mail-Versand (20 / Stunde), Absender-Domain-Validierung (`@ml-bets.com`), Log-Rotation und SMTP-Config-Schema-Doku.
- Zustands-Option `poor` (PO) in `create.php`, `create-auction.php` und den `public/` Templates ergänzt.

### Changed
- `api/api.php` nutzt jetzt `includes/validation.php` statt eingebetteter Validierungsfunktionen.
- `includes/db.php` unterstützt `TOXIC_DB_PATH`-Umgebungsvariable für isolierte Test-Datenbanken.
- `SECURITY.md` aktualisiert: HTML-Formulare senden jetzt `X-CSRF-Token`, API nutzt Header-basiertes CSRF.

### Security
- E-Mail-Versand ablehnt ungültige Absender-Adressen und schreibt rotiertes Audit-Log (`data/email.log`).

### Fixed
- SECURITY.md behauptete fälschlich, HTML-Formulare senden kein CSRF-Token.

### Security
- Session-Cookies werden mit `HttpOnly`, `SameSite=Lax` und bedingt `Secure` (bei HTTPS) gesetzt (`includes/auth.php`).
- `session_regenerate_id(true)` bei Login, Registrierung und Logout gegen Session-Fixation.
- Logout löscht das Session-Cookie auch clientseitig.
- IP-basiertes Rate-Limiting für Login, Registrierung und Passwort-Reset (`includes/rate_limit.php`).
- Passwort-Reset-Endpunkt gibt keinen `debug_email_sent`-Flag mehr an den Client zurück.
- Rate-Limit-Fehler geben `Retry-After` mit zurück.
- Admin-Check über `isAdmin()` statt hartkodierter E-Mail; konfigurierbar via `data/admin_users.json`.
- `.gitignore` hinzugefügt: `data/*`, `uploads/*`, `.env`-Dateien und Logs werden nicht mehr committed.
- Passwort-Mindestlänge von 6 auf 8 Zeichen erhöht (`api/api.php`, `includes/auth.php`, `public/js/toxic.js`).
- Serverseitige Eingabevalidierung für `create_listing` und `create_auction` eingeführt: Preis-/Reserve-Grenzen, max. 5 Bild-URLs, Karten-ID-Prüfung, Versandbeträge, Startzeit-Validierung.
- Security-Header via `.htaccess`: CSP, HSTS, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, X-Content-Type-Options.
- API-Responses erhalten eigene CSP-, NoSniff- und Frame-Options-Header.
- CSRF-Token-Validierung für zustandsverändernde API-Endpunkte (`create_listing`, `create_auction`) eingeführt; Formulare senden `X-CSRF-Token`-Header (`create.php`, `create-auction.php` und `public/`).

### Added
- Admin-Panel im Dashboard: LNBits-URL, API-Key, Fallback-Onchain-Adresse und Sandbox-Modus können direkt gespeichert werden.
- LNBits-Verbindungstest (`api/api.php?action=lnbits_test`) für Admins.
- `isAdmin()`-Helper in `includes/auth.php`.
- Validierungs-Helper `sanitizeUserText()`, `validateListingPayload()`, `validateAuctionPayload()` in `api/api.php`.
- `sessionSecurityInfo()`-Helper für diagnostische Cookie-Flags (keine Geheimnisse).

### Changed
- Alle Root-PHP-Seiten erhalten die gleichen PWA/Mobile-Meta-Tags wie `public/` (cache-busting `?v=2`, `viewport-fit=cover`, theme-color).
- Clientseitige Registrierung prüft nun Anzeigename (2–50 Zeichen), E-Mail-Format und Passwortlänge (≥8 Zeichen).

### Fixed
- Synchronisation von Mobile-Viewport-Änderungen zwischen `public/` und Root-PHP-Dateien.
- `index.html` fehlte im Repo-Root; `public/index.html` wurde ins Root kopiert, damit `.htaccess` SPA-Routing funktioniert.
- `create_auction` speichert nun zusätzlich `reserve_price_sats` und nutzt validierte Start-/Endzeiten.
- `create_auction` speichert nun Zustand, Seriennummer und Besitznachweis-Bild korrekt (`validateAuctionPayload()` liefert diese Felder jetzt zurück).
- `create_listing` nutzt nun vollständig `validateListingPayload()` statt roher Eingaben.
- Session-Cookies erhielten keine Flags (`HttpOnly`/`Secure`/`SameSite`) und waren dadurch anfälliger für XSS/CSRF-Leakage.
- Passwort-Reset im API-Endpunkt akzeptiert jetzt konsistent ≥8 Zeichen (vorher 6).
- `serve_image` sucht Uploads jetzt unter `DOCUMENT_ROOT/toxic-market/uploads/` statt im falschen Parent-Verzeichnis.

## [v0.1.0] — 2026-06-15

### Added
- Initiale Version: Karten, Angebote, Auktionen, Dashboard, LNBits-Zahlungen, Benachrichtigungen, Set-Builder.
