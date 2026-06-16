# Toxic Market — Security Notes

## Reported vulnerabilities and their status

| # | Finding | Status | Commit / Issue |
|---|---------|--------|----------------|
| 1 | SFTP credentials in plaintext in public GitHub repo (README + deploy scripts) | **Fixed in current HEAD** | `97ba7a8` |
| 2 | Password-reset API returned raw reset token, enabling account takeover | **Fixed** | `ee6a11a` |
| 3 | Nostr login without signature verification, enabling identity spoofing | **Mitigated** (login disabled until proper Schnorr verification is implemented) | `c997539`, see Issue #1 |
| 4 | Debug files / `display_errors=1` in web root | **Hardened** | `c997539` |

## Immediate actions required

1. **Rotate the Strato SFTP password.** The old password is still visible in Git history even though it was removed from the current HEAD. See Issue #2.
2. **Set environment variables locally** before deploying:
   ```bash
   export STRATO_HOST="${STRATO_HOST:-}"
   export STRATO_USER="${STRATO_USER:-}"
   export STRATO_PASS="your-new-password"
   ```
3. **Do not re-enable Nostr login** until server-side BIP-340 Schnorr signature verification is in place. See Issue #1.
4. **Add a real SMTP/transactional email provider** for password-reset emails. The current `sendResetEmail()` only logs to the server error log. See Issue #3.

## Ongoing security measures

- All backend errors are logged server-side (`data/error.log`) and never displayed to users.
- CSRF tokens are required for email/password login and registration.
- Uploaded images are stored outside direct web access.
- Sensitive directories (`data/`, `includes/`, `uploads/`) are blocked by `.htaccess`.

## Responsible disclosure

If you find a security issue, please open a private GitHub issue or contact the maintainer directly. Do not post credentials or exploit details in public issues.
