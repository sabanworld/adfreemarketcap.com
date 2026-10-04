# Sibling price feed

Server-to-server JSON for sibling apps (today: solochance.io) so they can reuse prices already synced into this database instead of spending a second CoinGecko quota.

## Endpoint

`GET /api/sibling/prices`

- Auth: `Authorization: Bearer <SIBLING_API_TOKEN>` (or `X-Sibling-Token`)
- Optional query: `symbols=BTC,LTC` (intersected with `SIBLING_API_ALLOWED_SYMBOLS`)
- Disabled when `SIBLING_API_TOKEN` is empty (404)
- Throttled (`SIBLING_API_THROTTLE`, default 60/min)
- Disallowed in `robots.txt` via the `/api` prefix

## What it returns

For each matching coin with a price: symbol, name, slug, `price_usd`, `percent_change_24h`, market cap, volume, image URL, `market_synced_at`, `last_provider`.

No visitor personal data is involved. The caller is another of our servers.

## Config

See `config/sibling_api.php` and `.env.example`.
