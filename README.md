# Parking Bookator

Jednoduchá webová aplikace pro sdílenou správu volných parkovacích míst.

## Co aplikace umí

- zobrazuje aktuální počet volných míst (`/`)
- veřejné akce po jednom místě:
  - `Přijel jsem` (`POST /api/decrement`)
  - `Odjel jsem` (`POST /api/increment`)
- každá veřejná změna vyžaduje jméno + GPS polohu v povoleném okruhu
- zobrazuje posledních 15 změn (`GET /api/events?limit=15`)
- servisní ruční korekce stavu je dostupná jen s admin tokenem (`POST /api/admin/set-free`)
- noční reset přes hosting cron volající URL (`GET /api/admin/night-reset`)
- servisní init/reseed endpoint přes token (`POST /api/admin/init-or-reseed`)
- migrace a údržba DB přes token (`POST /api/admin/database-migrate` a související)

## Důležité omezení hostingu

Produkční provoz je navržený tak, aby nevyžadoval pravidelné spouštění `php artisan` příkazů.
Všechny provozní akce jsou řešeny přes HTTP endpointy.

## Konfigurace `.env`

```env
APP_NAME="Parking Bookator"
APP_TIMEZONE=Europe/Prague

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parking_bookator
DB_USERNAME=...
DB_PASSWORD=...

PARKING_CAPACITY=20
PARKING_MANUAL_PASSWORD=change-me
PARKING_ADMIN_TOKEN=change-this-token
PARKING_ALLOWED_LAT=50.087451
PARKING_ALLOWED_LNG=14.420671
PARKING_ALLOWED_RADIUS_METERS=250
```

## Lokální spuštění

1. Nainstalovat závislosti:
   - `composer install`
2. Připravit env:
   - `copy .env.example .env`
   - vyplnit DB údaje
3. Inicializovat DB:
   - `php artisan migrate --seed`
4. Spustit aplikaci:
   - přes web server (WAMP/Apache) nebo `php artisan serve`

## Nasazení na webhosting

1. Nahrát aplikaci, `document root` subdomény směrovat na `public/`
2. V produkci nastavit `.env` včetně `PARKING_ADMIN_TOKEN`
3. Vytvořit cron v administraci hostingu, který denně volá:
   - `GET https://<subdomena>/api/admin/night-reset?token=<PARKING_ADMIN_TOKEN>`
4. Pro servisní zásahy používat Postman kolekci v `docs/postman/ParkingBookator.postman_collection.json`

## API přehled

- `GET /api/status`
- `GET /api/events?limit=15`
- `POST /api/decrement` body: `{ "device_id": "...", "name": "...", "latitude": 50.08, "longitude": 14.42, "accuracy": 12.3 }`
- `POST /api/increment` body: `{ "device_id": "...", "name": "...", "latitude": 50.08, "longitude": 14.42, "accuracy": 12.3 }`
- `GET /api/admin/night-reset?token=...`
- `POST /api/admin/init-or-reseed?token=...`
- `POST /api/admin/set-free?token=...` body: `{ "free_spots": 12, "password": "..." }`
- `POST /api/admin/database-migrate?token=...` (JSON `{ "seed": true }` volitelně – spustí i seedery)
- `POST /api/admin/database-migrate-status?token=...`
- `POST /api/admin/database-migrate-rollback?token=...` (JSON `{ "step": 1 }`)
- `POST /api/admin/cache?token=...` (vyčistí cache/config/view/route)
