# Toxic Market — TODO

> Offene Aufgaben, Bugs und geplante Verbesserungen

## 🚨 Blocker / Hohe Priorität

- [ ] **Strato-Passwort rotieren** (siehe GitHub Issue #3)
  - Das SFTP-Passwort lag in Git-History. Muss in Strato-Kundencenter geändert werden.
  - Lokale `STRATO_PASS` Umgebungsvariable aktualisieren.

- [ ] **Echte E-Mail-Versand für Passwort-Reset** (siehe GitHub Issue #2)
  - Aktuell nur Stub in `includes/email.php`.
  - Optionen: Strato-SMTP, PHPMailer, Postmark, SendGrid.
  - Rate-Limiting implementieren.

## 🚧 Zahlungen & Auktionen

- [ ] LNBits Live-Config auf Server eintragen (via Dashboard → Admin Payment Config)
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

- [ ] Nostr-Login: Server-seitige Schnorr-Signatur-Verifikation implementieren
- [ ] CSRF-Token auf jeder POST-Seite prüfen (aktuell nur API)
- [ ] Passwort-Richtlinie verschärfen (mindestens 8 Zeichen, Sonderzeichen)
- [ ] Rate-Limiting für Login / Registrierung / Passwort-Reset
- [ ] SQL-Injection-Audit (aktuell vorbereitete Statements)
- [ ] Upload-Verzeichnis: MIME-Type-Check härten (Magick/Exif)

## 🧪 Tests

- [ ] PHP-Unit-Tests für Auth + DB-Migrationen
- [ ] API-Integrationstests (Status, Register, Login, Listing erstellen)
- [ ] Frontend-Tests für Kritische Pfade (Login → Listing erstellen)

## 📚 Dokumentation

- [ ] Public REST API-Doku (OpenAPI / Postman Collection)
- [ ] Admin-Handbuch für Payment-Config
- [ ] Contributor-Guide

## 🧹 Tech Debt

- [x] Root-Level PHP-Dateien und `public/` synchronisieren
- [x] Deploy-Scripte an aktuelle Struktur anpassen
- [ ] HTML-Vorlagen in `public/` mit Root-PHP-Dateien synchron halten (Build-Schritt?)
- [ ] `app.css`, `app.v2.css`, `card.css` etc. — alte Dateien bereinigen
- [ ] `app.js`, `app.v2.js`, `nostr.js` — Doppelungen auflösen

## 🌐 SEO / Marketing

- [ ] Sitemap automatisch erzeugen mit aktiven Listings/Auctions
- [ ] OG-Tags für alle Seiten verifizieren
- [ ] robots.txt prüfen

## ✅ Kürzlich erledigt

Siehe `CHANGELOG.md`
