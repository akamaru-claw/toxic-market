# Security — Toxic Market

## Verantwortliche Offenlegung

Wenn du eine Sicherheitslücke findest, melde sie bitte direkt an den Projekteigentümer. Öffentliche Sicherheitsdiskussionen werden nur nach Absprache geführt.

## Aktuelle Maßnahmen

- **Passwort-Hashing**: `password_hash()` mit Bcrypt, cost 12.
- **Passwort-Richtlinie**: Mindestens 8 Zeichen (Client- und Server-seitig geprüft). Empfohlen werden Buchstaben, Zahlen und Sonderzeichen.
- **CSRF**: Alle schreibenden API-Endpunkte fordern einen `csrf_token` aus der Session.
- **Sessions**: 30-Tage-Cookie. `HttpOnly`/`Secure`/`SameSite=Lax` sollte auf Serverebene (Apache/Nginx) gesetzt werden.
- **Rate Limiting**: IP-basierte Limits für Login (10 Versuche / 15 min), Registrierung (5 / 15 min) und Passwort-Reset (3 / 15 min).
- **SQL Injection**: Alle DB-Queries nutzen prepared statements.
- **XSS**: Ausgaben werden mit `htmlspecialchars()` escaped.
- **Security-Header** (via `.htaccess`):
  - `Content-Security-Policy` mit `default-src 'self'` und eingeschränkten `connect-src` für LNBits/Mempool
  - `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - `Permissions-Policy` für sensible APIs
- **API-Responses**: JSON-Endpunkte senden CSP- und NoSniff-Header, um API-Daten aus Embedded-Kontexten zu isolieren.
- **Secrets**: `data/`, `uploads/` und `.env`-Dateien sind per `.gitignore` vom Repo ausgeschlossen.
- **Input-Validierung**:
  - `create_listing`: Preis 1–21M BTC in sats, max. 5 Bild-URLs (≤500 Zeichen), Karten-ID existiert, Versand ≥ 0
  - `create_auction`: Startpreis 1–21M sats, Reserve ≥ 0, Laufzeit 1–168 h, max. 5 Bild-URLs

## Bekannte Einschränkungen / TODOs

- Nostr-Login ist deaktiviert, weil keine serverseitige BIP-340-Schnorr-Verifizierung implementiert ist.
- E-Mail-Versand verwendet aktuell `mail()` / Logging. Für Produktion SMTP/SES/Postmark einrichten.
- LNBits-API-Key wird als JSON im `data/`-Verzeichnis gespeichert. Dateiberechtigungen auf `0600` setzen.
- Keine 2FA. Wird evaluiert, sobald Nostr-Login wieder aktiviert wird.
- Dateiuploads: keine Viren- oder Bild-Manipulations-Validierung außer MIME-Typ und Größe.
- CSP erlaubt aktuell `'unsafe-inline'` für Scripts/Styles, weil Teile des Frontends inline JS/CSS nutzen. Sobald alles extern ausgelagert ist, kann `unsafe-inline` entfernt werden.

## Betriebssicherheit

- `data/`-Verzeichnis sollte via `.htaccess` oder Server-Config nicht öffentlich zugänglich sein.
- SQLite-DB sollte außerhalb des Webroots liegen, falls der Hoster das erlaubt.
