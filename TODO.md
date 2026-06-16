# Toxic Market — TODO

> Offene Aufgaben, Bugs und geplante Verbesserungen

## 🚨 Blocker / Hohe Priorität

- [ ] **Strato-Passwort rotieren** (siehe GitHub Issue #3)
  - Das SFTP-Passwort lag in Git-History. Muss im Strato-Kundencenter geändert werden.
  - Lokale `STRATO_PASS` Umgebungsvariable aktualisieren.

- [ ] **Echte E-Mail-Versand für Passwort-Reset** (siehe GitHub Issue #2)
  - Aktuell nur Stub in `includes/email.php`.
  - Optionen: Strato-SMTP, PHPMailer, Postmark, SendGrid.
  - Rate-Limiting ist implementiert (2026-06-16).

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
- [x] Admin-Check über `isAdmin()` statt hartkodierter E-Mail
- [x] `.gitignore` für `data/`, `uploads/`, `.env`, Logs
- [x] Passwort-Mindestlänge auf 8 Zeichen erhöht (`api/api.php`, `includes/auth.php`, `public/js/toxic.js`)
- [x] Serverseitige Eingabevalidierung für `create_listing` und `create_auction` (Preis, Bild-URLs, Karten-ID, Versand)
- [x] Content-Security-Policy, XSS-Protection, HSTS, X-Frame-Options, Referrer-Policy in `.htaccess` und API-Responses
- [x] Session-Cookie-Flags (`HttpOnly`, `Secure`, `SameSite=Lax`) serverseitig forcieren
- [x] `session_regenerate_id(true)` bei Login/Register/Logout gegen Session-Fixation
- [ ] Nostr-Login: Server-seitige Schnorr-Signatur-Verifikation implementieren
- [x] CSRF-Token auf jeder POST-Seite prüfen (aktuell nur API)
- [ ] Upload-Verzeichnis: MIME-Type-Check härten (Magick/Exif)
- [ ] SQL-Injection-Audit (aktuell vorbereitete Statements)
- [ ] Passwort-Reset per E-Mail aktivieren (SMTP/Postmark/SES)

## 🧪 Tests

- [ ] PHP-Unit-Tests für Auth + DB-Migrationen
- [ ] API-Integrationstests (Status, Register, Login, Listing erstellen)
- [ ] Frontend-Tests für Kritische Pfade (Login → Listing erstellen)

## 📚 Dokumentation

- [ ] Public REST API-Doku (OpenAPI / Postman Collection)
- [x] Admin-Handbuch für Payment-Config (im Dashboard integriert)
- [x] `SECURITY.md` und `CHANGELOG.md` gepflegt
- [ ] Contributor-Guide

## 🧹 Tech Debt

- [x] Root-Level PHP-Dateien und `public/` synchronisieren
- [x] Deploy-Scripte an aktuelle Struktur anpassen
- [x] `index.html` aus `public/` ins Repo-Root kopiert (für SPA-Routing via `.htaccess`)
- [ ] HTML-Vorlagen in `public/` mit Root-PHP-Dateien synchron halten (Build-Schritt?)
- [ ] `app.css`, `app.v2.css`, `card.css` etc. — alte Dateien bereinigen
- [ ] `app.js`, `app.v2.js`, `nostr.js` — Doppelungen auflösen

## 🌐 SEO / Marketing

- [ ] Sitemap automatisch erzeugen mit aktiven Listings/Auctions
- [ ] OG-Tags für alle Seiten verifizieren
- [ ] robots.txt prüfen

## ✅ Kürzlich erledigt

Siehe `CHANGELOG.md`
