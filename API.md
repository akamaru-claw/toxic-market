# Toxic Market — Public REST API v1

> Dies ist die interne API-Dokumentation. Sie wird aus dem laufenden Code in `api/api.php` generiert und soll in Zukunft nach OpenAPI 3.x migriert werden.

## Basis-URL

```
https://ml-bets.com/toxic-market/api/api.php?action={action}
```

## Konventionen

- Alle Requests müssen `credentials: 'same-origin'` senden, damit Session-Cookies mitgeliefert werden.
- Schreibende Endpunkte erfordern einen `X-CSRF-Token`-Header. Das Token kann über `GET ?action=status` abgefragt werden.
- Antworten sind JSON. Bei Fehlern wird ein passender HTTP-Statuscode (`4xx`/`5xx`) zurückgegeben.
- Preise und Beträge werden in **Satoshis** übermittelt.

## Authentifizierung

### `GET ?action=status`

Session-Status abfragen. Liefert neben Benutzerdaten immer ein neues CSRF-Token.

**Antwort:**
```json
{
  "logged_in": true,
  "user": { "id": 1, "email": "...", "display_name": "..." },
  "auth_method": "email",
  "csrf_token": "<token>"
}
```

### `POST ?action=register`

Neuen Account anlegen.

**Body:**
```json
{
  "email": "user@example.com",
  "password": "mindestens8zeichen",
  "display_name": "KartenSammler",
  "accept_disclaimer": true,
  "csrf_token": "<token>"
}
```

**Regeln:**
- E-Mail muss gültig sein.
- Passwort mindestens 8 Zeichen.
- `accept_disclaimer` muss `true` sein.
- Rate-Limit: 5 Versuche / 15 Minuten pro IP.

### `POST ?action=login`

Mit E-Mail und Passwort anmelden.

**Body:**
```json
{
  "email": "user@example.com",
  "password": "...",
  "csrf_token": "<token>"
}
```

### `POST ?action=logout`

Abmelden. Löscht Session-Cookie serverseitig.

**Body:** leer oder `{ "csrf_token": "<token>" }`

### `POST ?action=request_reset`

Passwort-Reset anfordern. Aus Sicherheitsgründen gibt der Endpunkt immer eine neutrale Erfolgsmeldung zurück, auch wenn die E-Mail nicht existiert.

**Body:**
```json
{ "email": "user@example.com" }
```

### `POST ?action=reset_password`

Neues Passwort setzen.

**Body:**
```json
{
  "token": "<reset-token>",
  "password": "neues8zeichen+",
  "csrf_token": "<token>"
}
```

## Karten

### `GET ?action=cards`

Alle Kartenmotive abfragen.

**Query-Parameter:**
- `generation` (optional): `1`, `2` oder `3`
- `search` (optional): Textfilter

### `GET ?action=card&id={id}`

Detaildaten eines Kartenmotivs inklusive aktiver Listings und Auktionen.

## Listings (Festpreis)

### `GET ?action=listings`

Aktive Festpreisangebote.

**Query-Parameter:**
- `card_id`, `seller_id`, `min`, `max`, `sort`, `limit`, `offset`

### `GET ?action=listing&id={id}`

Listing-Detailseite.

### `POST ?action=create_listing`

Neues Listing erstellen (erfordert Login).

**Body:**
```json
{
  "card_template_id": 1,
  "title": "Genesis #1 The Beginning — MINT",
  "description": "...",
  "price_sats": 10000,
  "condition": "mint",
  "serial_number": "042/210",
  "local_shipping_sats": 0,
  "intl_shipping_sats": 0,
  "image_urls": ["/toxic-market/uploads/...jpg"],
  "proof_image_url": "/toxic-market/uploads/...jpg",
  "proof_block_height": 1234567
}
```

**Regeln:**
- Preis 1 – 21.000.000.000.000.000 Sats.
- Maximal 5 Bild-URLs, jeweils ≤ 500 Zeichen.
- Zustand muss einer der erlaubten Werte sein: `mint`, `near_mint`, `excellent`, `good`, `played`, `poor`.

### `POST ?action=update_listing`

Eigenes Listing aktualisieren.

**Body:** ähnlich `create_listing`, zusätzlich `id`.

### `POST ?action=delete_listing`

Eigenes Listing löschen.

**Body:**
```json
{ "id": "<listing-id>" }
```

### `POST ?action=mark_sold`

Listing als verkauft markieren.

**Body:**
```json
{ "id": "<listing-id>" }
```

### `GET ?action=my_listings`

Eigene Listings (Login nötig).

## Auktionen

### `GET ?action=auctions`

Aktive Auktionen.

### `GET ?action=auction&id={id}`

Auktionsdetails inklusive Gebote.

### `POST ?action=create_auction`

Neue Auktion starten (Login nötig).

**Body:**
```json
{
  "card_template_id": 1,
  "title": "Genesis #1 — Auktion",
  "description": "...",
  "starting_price_sats": 10000,
  "reserve_price_sats": 50000,
  "duration_hours": 72,
  "condition": "near_mint",
  "serial_number": "001/210",
  "local_shipping_sats": 0,
  "intl_shipping_sats": 0,
  "image_urls": [...],
  "proof_image_url": "...",
  "proof_block_height": 1234567
}
```

### `POST ?action=place_bid`

Auf eine Auktion bieten (Login nötig).

**Body:**
```json
{
  "auction_id": "<auction-id>",
  "amount_sats": 15000
}
```

### `POST ?action=bid_with_deposit`

Gebot inklusive Lightning-Deposit erstellen.

**Body:**
```json
{
  "auction_id": "<auction-id>",
  "amount_sats": 20000
}
```

**Antwort:**
```json
{
  "success": true,
  "payment_request": "lnbc...",
  "payment_hash": "..."
}
```

### `POST ?action=check_bid_deposit`

Deposit-Status prüfen.

**Body:**
```json
{ "payment_hash": "..." }
```

### `POST ?action=delete_auction`

Eigene Auktion löschen (nur wenn noch keine Gebote).

### `POST ?action=end_auction`

Auktion manuell beenden (Verkäufer).

## Zahlungen

### `GET ?action=btc_price`

Aktueller BTC-Preis in EUR und USD (Mempool.space).

### `GET ?action=current_block`

Aktuelle Blockheight.

### `POST ?action=create_invoice`

Lightning-Invoice für ein Listing erstellen.

**Body:**
```json
{
  "listing_id": "<listing-id>",
  "shipping_region": "de"
}
```

### `POST ?action=create_purchase_invoice`

Alternative Invoice-Erstellung.

### `POST ?action=check_payment`

Payment-Hash Status prüfen.

**Body:**
```json
{ "payment_hash": "..." }
```

### `GET ?action=onchain_address`

Onchain-Adresse des eingeloggten Verkäufers abfragen.

### `GET ?action=my_transactions`

Eigene Zahlungen/Transaktionen.

## Benachrichtigungen

### `GET ?action=notifications`

In-App-Benachrichtigungen.

### `POST ?action=mark_notifications_read`

Alle Notifications als gelesen markieren.

## Admin (nur `isAdmin()`)

### `POST ?action=payment_config`

LNBits-Konfiguration speichern.

**Body:**
```json
{
  "lnbits_url": "https://...",
  "lnbits_api_key": "...",
  "fallback_onchain_address": "bc1q...",
  "sandbox_mode": false
}
```

### `GET ?action=lnbits_test`

Verbindungstest zur LNBits-Instanz.

## Uploads

### `POST ?action=upload_image`

Bild-Upload (multipart/form-data).

- Feldname: `image`
- Erlaubte Typen: JPEG, PNG, WebP
- Maximale Größe: 5 MB

**Antwort:**
```json
{
  "success": true,
  "url": "/toxic-market/uploads/2026/06/abc123.jpg"
}
```

## CSP / Sicherheitsheader

Die API setzt für JSON-Responses folgende Header:

- `Content-Type: application/json`
- `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none';`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Offene TODOs

- [ ] OpenAPI 3.0 YAML/JSON exportieren.
- [ ] Postman-Collection generieren.
- [ ] API-Versionierung (`/api/v1/...`) einführen.
- [ ] Paginierung auf allen List-Endpunkten standardisieren.
- [ ] Webhook-Endpunkte für LNBits-Zahlungen dokumentieren.
