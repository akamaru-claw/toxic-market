# Toxic Market — Entwicklungs-Tagebuch

## 2026-06-04 — Projekt-Start

### Analyse SatStash.io
- **Typ:** Bitcoin-Marktplatz für physische Güter
- **Features:** Auktionen (zeitlich begrenzt + Bid-Deposit), Fixed-Price Listings, Seller-Stores, Escrow
- **Tech:** Next.js Frontend, Public REST API v1 (OpenAPI), Cursor-basierte Paginierung
- **Zahlungen:** Onchain + Lightning (Deposits), Credit Card / Afterpay / Klarna (Final)
- **Status:** Wird offline genommen (laut Kiba, nach Gespräch mit Entwicklern)

### Karten-Situation (von Kiba)
- **Gen 1 (Zitadelle 2025):** 21 Motive × 210 Stück, #21/210 = Holo, komplett ausverkauft
- **Gen 2 (Zitadelle 2026):** 21 neue Motive × 210 Stück, Holo bei #1, #21, #210
- **Gen 1 Remakes:** 35 engl. Nachdrucke je der 21 Gen-1-Motive
- **Specials:** Error-Karten existieren

### Entscheidungen
- Platform Fokus: **Nur Toxic Booster / MX12ART Karten** (kein General-Marketplace)
- Auth: Nostr + Email (wie Kickstr)
- Hosting: Strato (ml-bets.com)
- Backend: PHP 8.2 + SQLite (Strato-kompatibel)
- Frontend: Modern, Mobile-first
- Zahlungen: Lightning (LNBits) als Priority

### GitHub-Repo erstellt
- https://github.com/akamaru-claw/toxic-market