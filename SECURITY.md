# Security — Toxic Market

## Verantwortliche Offenlegung

Wenn du eine Sicherheitslücke findest, melde sie bitte direkt an den Projekteigentümer. Öffentliche Sicherheitsdiskussionen werden nur nach Absprache geführt.

## Aktuelle Maßnahmen

- **Passwort-Hashing**: `password_hash()` mit Bcrypt, cost 12.
- **CSRF**: Alle schreibenden API-Endpunkte fordern einen `csrf_token` aus der Session.
- **Sessions**: 30-Tage-Cookie, `HttpOnly`/`Secure`/`SameSite=Lax` sollte auf Serverebene (Apache/Nginx) gesetzt werden.
- **Rate Limiting**: IP-basierte Limits für Login (10 Versuche / 15 min), Registrierung (5 / 15 min) und Passwort-Reset (3 / 15 min).
- **SQL Injection**: Alle DB-Queries nutzen prepared statements.
- **XSS**: Ausgaben werden mit `htmlspecialchars()` escaped.
- **Secrets**: `data/`, `uploads/` und `.env`-Dateien sind per `.gitignore` vom Repo ausgeschlossen.

## Bekannte Einschränkungen / TODOs

- Nostr-Login ist deaktiviert, weil keine serverseitige BIP-340-Schnorr-Verifizierung implementiert ist.
- E-Mail-Versand verwendet aktuell `mail()` / Logging. Für Produktion SMTP/SES/Postmark einrichten.
- LNBits-API-Key wird als JSON im `data/`-Verzeichnis gespeichert. Dateiberechtigungen auf `0600` setzen.
- Keine 2FA. Wird evaluiert, sobald Nostr-Login wieder aktiviert wird.
- Dateiuploads: keine Viren- oder Bild-Manipulations-Validierung außer MIME-Typ und Größe.
- Kein Content-Security-Policy-Header. Wird als separates Issue erfasst.

## Betriebssicherheit

- `data/`-Verzeichnis sollte via `.htaccess` oder Server-Config nicht öffentlich zugänglich sein.
- SQLite-DB sollte außerhalb des Webroots liegen, falls der Hoster das erlaubt.
