# Toxic Market — Security

> Status: Work in progress. Letzter Review: 2026-06-16.

## ✅ Erledigt

| Thema | Status | Details |
|-------|--------|---------|
| SFTP-Passwort aus Repo entfernt | ✅ | `README.md`, `deploy_toxic.sh`, `deploy_toxic.py` laden nur aus `STRATO_*` Env-Variablen. |
| Passwort-Reset Token nicht im API-Response | ✅ | `request_reset` gibt nur generische Meldung zurück. Token wird an `sendResetEmail()` übergeben. |
| PHP-Fehlerausgabe deaktiviert | ✅ | `display_errors=0`, Logging nur in `data/error.log`. |
| Sensible Verzeichnisse blockiert | ✅ | `.htaccess` blockiert `data/`, `includes/`, `uploads/`. |
| SQL-Injection | ✅ | Alle DB-Zugriffe über vorbereitete Statements (`PDO::prepare`). |

## 🚧 Offen / Bekannt

| Thema | Status | Risiko | Tracking |
|-------|--------|--------|----------|
| Strato-Passwort rotieren | 🚧 | Hoch: Passwort in Git-History sichtbar | GitHub Issue #3 |
| Echte E-Mail-Versand für Passwort-Reset | 🚧 | Mittel: Reset funktioniert ohne Mail nicht | GitHub Issue #2 |
| Nostr-Login ohne Schnorr-Verify | 🚧 | Hoch: Account-Übernahme per npub | `includes/auth.php` + TODO.md |
| Rate-Limiting fehlt | 🚧 | Mittel: Brute-Force auf Login/Register/Reset | TODO.md |
| Upload MIME-Check | 🚧 | Niedrig: Nur Dateityp-String geprüft | TODO.md |

## 📋 Maßnahmen

1. **Strato-Passwort rotieren**
   - Im Strato-Kundencenter das SFTP/SSH-Passwort ändern.
   - Lokale `STRATO_PASS` Umgebungsvariable aktualisieren.
   - Kein neuer Deploy mit dem alten Passwort.

2. **E-Mail-Transport**
   - Möglichkeiten: Strato-SMTP, PHPMailer, Postmark, SendGrid, AWS SES.
   - Tokens dürfen nicht geloggt werden.
   - Rate-Limit: max. 3 Reset-Versuche pro E-Mail / 15 Minuten.

3. **Nostr-Login**
   - Server-seitige BIP-340/Schnorr-Verifikation mit `php-bolt11` oder `sop` notwendig.
   - Challenge-Response-Flow: Server gibt Nonce, Client signiert, Server prüft.

4. **Rate-Limiting**
   - IP-basiertes Limit für Login / Register / Reset.
   - In SQLite-Tabelle `rate_limits` persistieren.

## 🔒 Verantwortlichkeiten

- **Akamaru** führt keine Deploys auf Strato ohne ausdrückliche Zustimmung von Kiba durch.
- Neue Credentials werden nur in Umgebungsvariablen oder `data/` (nicht versioniert) gespeichert.
- Sicherheitsrelevante Änderungen werden in GitHub-Issues dokumentiert.
