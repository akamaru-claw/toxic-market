# Toxic Market — TODO

> Offene Aufgaben, Bugs und geplante Verbesserungen

## 🚨 Blocker / Hohe Priorität

- [ ] **Strato-Passwort rotieren** (siehe GitHub Issue #3)
  - Das SFTP-Passwort lag in Git-History. Muss im Strato-Kundencenter geändert werden.
  - Lokale `STRATO_PASS` Umgebungsvariable aktualisieren.

- [x] **Echte E-Mail-Versand für Passwort-Reset** (siehe GitHub Issue #2)
  - PHPMailer 6.9.1 eingebunden unter `includes/PHPMailer/`.
  - `includes/email.php` unterstützt jetzt SMTP/SSL/TLS mit Config-Datei oder Umgebungsvariablen.
  - `api/api.php` verschickt Passwort-Reset-E-Mails (sobald `data/email_config.json` vorhanden ist).
  - Ohne Config bleibt mail()-Fallback aktiv.

- [ ] **LNBits Live-Config auf Server eintragen**
  - Admin-Panel im Dashboard ist implementiert (2026-06-16).
  - Nach Deploy: URL, API-Key und Fallback-Onchain-Adresse eintragen und Verbindung testen.
  - `data/`-Verzeichnis-Berechtigungen auf 0600/0750 prüfen.

## 🚧 Zahlungen & Auktionen

- [ ] Zahlungsabwicklung End-to-End testen (Listing-Kauf, Auktions-Deposit)
- [ ] Auktions-Ende: Gewinner-Benachrichtigung + Zahlungsaufforderung
- [ ] Rückerstattung von Bid-Deposits für Verlierer
- [ ] Onchain-Zahlungen manuell verifizieren (Proof-of-Payment)

## 🎨 Frontend / UX

- [ ] Echte MX12ART-Kartenbilder statt SVG-Generierung
- [ ] Bild-Upload: WebP-Konvertierung + Thumbnails
- [ ] Mobile UX: Filter-Bar für Listings optimieren
- [ ] Offline-Fallback für Karten-Browser (Service Worker)
- [ ] Dark/Light Theme Toggle

## 🔒 Security

- [x] IP-basiertes Rate-Limiting für Login, Registrierung, Passwort-Reset (`includes/rate_limit.php`)
- [x] E-Mail-Versand: Rate-Limiting + Absender-Domain-Validierung + Audit-Log (`includes/email.php`)
- [x] CSRF-Token auf API und HTML-Formularen (`create.php`, `create-auction.php`)
- [x] Session-Cookie-Flags (`HttpOnly`, `Secure`, `SameSite=Lax`) serverseitig forcieren
- [x] `session_regenerate_id(true)` bei Login/Register/Logout gegen Session-Fixation
- [x] Validierungs-Helper zentralisiert (`includes/validation.php`)
- [ ] Nostr-Login: Server-seitige Schnorr-Signatur-Verifikation implementieren
- [ ] Upload-Verzeichnis: MIME-Type-Check härten (Magick/Exif)
- [ ] SQL-Injection-Audit (aktuell vorbereitete Statements)
- [x] Passwort-Reset per E-Mail aktiviert (SMTP via PHPMailer, sobald `data/email_config.json` vorhanden)

## 🧪 Tests

- [x] Smoke-Tests für Auth + Validierung (`tests/AuthTest.php`)
- [x] Smoke-Tests für E-Mail-Validierung + SMTP-Config-Overrides (`tests/AuthTest.php`)
- [ ] PHP-Unit-Tests für Auth + DB-Migrationen
- [ ] API-Integrationstests (Status, Register, Login, Listing erstellen)
- [ ] Frontend-Tests für Kritische Pfade (Login → Listing erstellen)

## 📚 Dokumentation

- [x] `API.md`: Interne REST-API-Dokumentation
- [x] `openapi.yaml`: Public OpenAPI 3.0 Spec
- [ ] Postman Collection generieren
- [x] Admin-Handbuch für Payment-Config (im Dashboard integriert)
- [x] `SECURITY.md` und `CHANGELOG.md` gepflegt
- [ ] Contributor-Guide

## 🧹 Tech Debt

- [x] Root-Level PHP-Dateien und `public/` synchronisieren
- [x] Deploy-Scripte an aktuelle Struktur anpassen
- [x] `index.html` aus `public/` ins Repo-Root kopiert (für SPA-Routing via `.htaccess`)
- [x] Validierungsfunktionen aus `api/api.php` in `includes/validation.php` extrahiert
- [ ] HTML-Vorlagen in `public/` mit Root-PHP-Dateien synchron halten (Build-Schritt?)
- [ ] `app.css`, `app.v2.css`, `card.css` etc. — alte Dateien bereinigen
- [ ] `app.js`, `app.v2.js`, `nostr.js` — Doppelungen auflösen

## 🌐 SEO / Marketing

- [ ] Sitemap automatisch erzeugen mit aktiven Listings/Auctions
- [ ] OG-Tags für alle Seiten verifizieren
- [ ] robots.txt prüfen

## ✅ Kürzlich erledigt

Siehe `CHANGELOG.md`
