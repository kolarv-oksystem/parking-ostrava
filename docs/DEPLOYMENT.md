# Deployment (webhosting bez CLI)

Tento projekt je připraven pro hosting, kde:

- nelze pravidelně spouštět Laravel Artisan příkazy
- cron umí pouze volání URL endpointu

## 1) Přednasazení (lokálně/CI)

Tyto kroky proveďte mimo produkční runtime:

1. `composer install --no-dev`
2. `php artisan key:generate`
3. `php artisan migrate --seed`

## 2) Nahrání na hosting

1. Nahrajte projekt na hosting.
2. Nastavte subdoménu tak, aby `DocumentRoot` směřoval do `public/`.
3. Nastavte práva zápisu pro `storage/` a `bootstrap/cache/`.
4. Vyplňte produkční `.env`:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_TIMEZONE=Europe/Prague`
   - `DB_*`
   - `PARKING_CAPACITY`
   - `PARKING_MANUAL_PASSWORD`
   - `PARKING_ADMIN_TOKEN`

## 3) Cron reset přes GET URL

V administraci hostingu nastavte denní cron, který zavolá:

`https://<subdomena>/api/admin/night-reset?token=<PARKING_ADMIN_TOKEN>`

Doporučený čas: po půlnoci (např. 00:05).

## 4) Provozní úkony přes Postman

Postman kolekce: `docs/postman/ParkingBookator.postman_collection.json`

Používejte hlavně:
- `Noční reset (cron GET)` pro ruční test resetu
- `Init / reseed (admin)` při změně kapacity nebo resetu hesla ruční úpravy

## Bezpečnostní doporučení

- `PARKING_ADMIN_TOKEN` používat dlouhý a náhodný
- admin endpointy nepublikovat veřejně bez tokenu
- ruční korekční heslo pravidelně měnit přes `init-or-reseed`

## Řešení chyby „Target class [Dingo\Api\Routing\Router] does not exist“

Tato aplikace **nepoužívá** balíček Dingo API. Pokud tuto chybu vidíte na hostingu, jde téměř vždy o jedno z následujících:

1. **Zastaralá Laravel cache** z jiného projektu nebo starého deploye (nejčastější). Na serveru smažte soubory v `bootstrap/cache/` kromě `.gitignore`, zejména:
   - `bootstrap/cache/routes*.php`
   - `bootstrap/cache/config.php`
   - případně `bootstrap/cache/packages.php` a `services.php`, pokud existují a po smazání je Laravel znovu vygeneruje.

   Pokud máte SSH, ekvivalent je: `php artisan route:clear` a `php artisan config:clear`.

2. **Smíchaný kód** – v `config/app.php` v poli `providers` nesmí být `Dingo\Api\Provider\LaravelServiceProvider` (nebo podobné), pokud nemáte nainstalovaný `composer require api-ecosystem-for-laravel/dingo-api` (dříve `dingo/api`).

3. **Špatná složka nasazení** – ověřte, že `DocumentRoot` a nahrané soubory opravdu patří k tomuto projektu (Parking Bookator), ne k jiné Laravel aplikaci ve stejném stromu adresářů.

Po vyčištění cache znovu zavolejte admin URL (s platným `token`).

## Migrace přes HTTP (admin token)

Endpoint `POST /api/admin/database-migrate` spouští `migrate --force` přes `Artisan`. Chráněné je stejným `PARKING_ADMIN_TOKEN` jako ostatní admin routy – token držte v tajnosti. Rollback může poškodit data; používejte jen pokud víte, co děláte.
