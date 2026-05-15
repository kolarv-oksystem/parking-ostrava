# Parking Bookator

Jednoduchá webová aplikace pro sdílenou správu 6 parkovacích míst.

## Co aplikace umí

- zobrazuje mapu 6 míst (`/`) a stav obsazení po jednotlivých místech
- kliknutím na služební místo (žlutý rámeček) se otevře volba **služební / soukromé vozidlo** a poté se obsadí (`POST /api/spots/toggle` s `vehicle_type`)
- kliknutím na ostatní místa se obsadí po potvrzení (vždy s GPS v areálu)
- obsazené místo nese jméno řidiče
- volitelné API pro přepnutí jen příznaku rezervace: `POST /api/spots/toggle-service-reservation` (např. skripty)
- GPS pravidla:
  - ve služební zóně: `vehicle_type: "service"` = obsazení bez GPS a zapnutá služební rezervace
  - ve služební zóně: `vehicle_type: "private"` = obsazení s GPS, rezervace se vypne
  - mimo služební zónu: obsazení vždy s GPS
- zobrazuje posledních 15 změn (`GET /api/events?limit=15`)
- servisní ruční korekce stavu je dostupná jen s admin tokenem (`POST /api/admin/set-free`)
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

PARKING_CAPACITY=6
PARKING_RESERVED_SERVICE_SPOTS_COUNT=1
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
3. Pro servisní zásahy používat Postman kolekci v `docs/postman/ParkingBookator.postman_collection.json`

## API přehled

- `GET /api/status`
- `GET /api/events?limit=15`
- `POST /api/spots/toggle` body příklad služební zóna bez GPS: `{ "device_id", "name", "spot_number": 1, "vehicle_type": "service" }`
- `POST /api/spots/toggle` body příklad soukromě ve služební zóně: `{ ..., "spot_number": 1, "vehicle_type": "private", "latitude", "longitude", "accuracy" }`
- `POST /api/spots/toggle` body ostatní místa: `{ ..., "spot_number": 2, "latitude", "longitude", "accuracy" }`
- `POST /api/spots/toggle-service-reservation` body: `{ "device_id": "...", "name": "...", "spot_number": 1 }` (volitelné, mimo UI)
- `POST /api/admin/init-or-reseed?token=...`
- `POST /api/admin/set-free?token=...` body: `{ "free_spots": 12, "password": "..." }`
- `POST /api/admin/database-migrate?token=...` (JSON `{ "seed": true }` volitelně – spustí i seedery)
- `POST /api/admin/database-migrate-status?token=...`
- `POST /api/admin/database-migrate-rollback?token=...` (JSON `{ "step": 1 }`)
- `POST /api/admin/cache?token=...` (vyčistí cache/config/view/route)
