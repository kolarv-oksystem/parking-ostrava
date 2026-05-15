<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Parking Bookator') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="container">
        <section class="card">
            <header class="card-header">
                <h1>Parkování ve firmě</h1>
                <p class="subtitle">Aktuální počet volných míst</p>
            </header>

            <div class="meter" id="statusMeter">
                <span class="free-number" id="freeSpots">-</span>
                <span class="label">volných míst</span>
                <span class="meta" id="capacityLabel">z kapacity -</span>
            </div>

            <div class="identity">
                <label for="visitorName">Jméno</label>
                <input
                    type="text"
                    id="visitorName"
                    name="visitorName"
                    maxlength="100"
                    autocomplete="name"
                    required
                    placeholder="Např. Jan Novák"
                >
                <small class="gps-status" id="gpsStatus">GPS: čeká na ověření.</small>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-danger" id="arriveButton">Přijel jsem</button>
                <button type="button" class="btn btn-success" id="leaveButton">Odjel jsem</button>
            </div>

            <footer class="card-footer">
                <small id="updatedAt">Poslední změna: -</small>
            </footer>
        </section>

        <section class="card log-card">
            <header class="card-header">
                <h2>Poslední záznamy</h2>
                <p class="subtitle">Zobrazeno je max. 15 posledních změn.</p>
            </header>
            <ul class="events-list" id="eventsList">
                <li class="events-empty">Načítám záznamy...</li>
            </ul>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
