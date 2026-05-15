<!DOCTYPE html>
<html lang="cs">
<head>
    @php
        $cssPath = public_path('css/app.css');
        $jsPath = public_path('js/app.js');
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
        $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>{{ config('app.name', 'Parking Bookator') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $cssVersion }}">
</head>
<body>
    <main class="container">
        <section class="card">
            <header class="card-header">
                <h1>Parkování ve firmě</h1>
                <p class="subtitle">Klikněte na místo pro příjezd nebo odjezd. Žlutý rámeček = primárně služební místo (lze i soukromě v areálu).</p>
            </header>

            <div class="summary">
                <span id="freeSpots">-</span>
                <span class="meta" id="capacityLabel">volných z -</span>
            </div>

            <div class="identity">
                <label for="visitorName">Jméno</label>
                <input
                    type="text"
                    id="visitorName"
                    name="visitorName"
                    maxlength="100"
                    autocomplete="name"
                    inputmode="text"
                    enterkeyhint="done"
                    required
                    placeholder="Např. Jan Novák"
                >
                <small class="gps-status" id="gpsStatus">GPS: čeká na ověření.</small>
            </div>

            <div class="parking-map" id="parkingMap" aria-live="polite">
                <button type="button" class="spot-btn" data-spot-number="1">
                    <span class="spot-number">Místo 1</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
                <button type="button" class="spot-btn" data-spot-number="2">
                    <span class="spot-number">Místo 2</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
                <button type="button" class="spot-btn" data-spot-number="3">
                    <span class="spot-number">Místo 3</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
                <button type="button" class="spot-btn" data-spot-number="4">
                    <span class="spot-number">Místo 4</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
                <button type="button" class="spot-btn" data-spot-number="5">
                    <span class="spot-number">Místo 5</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
                <button type="button" class="spot-btn" data-spot-number="6">
                    <span class="spot-number">Místo 6</span>
                    <span class="spot-badge" aria-hidden="true"></span>
                    <span class="spot-name">Volno</span>
                </button>
            </div>

            <footer class="card-footer">
                <small id="updatedAt">Poslední změna: -</small>
            </footer>
        </section>

        <section class="card log-card">
            <header class="card-header">
                <h2>Poslední záznamy</h2>
                <p class="subtitle">Zobrazeno je 10 posledních změn.</p>
            </header>
            <ul class="events-list" id="eventsList">
                <li class="events-empty">Načítám záznamy...</li>
            </ul>
            <div class="log-footer">
                <button type="button" class="btn btn-log" id="logExpandButton">Zobrazit celý log</button>
                <small class="meta" id="logRangeLabel">Aktuálně: posledních 10 záznamů.</small>
            </div>
        </section>
    </main>

    <div id="toast" class="toast hidden" role="status"></div>

    <div id="vehicleChoiceModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="vehicleModalTitle" aria-hidden="true">
        <div class="modal-backdrop" data-modal-dismiss="true"></div>
        <div class="modal-panel">
            <h2 class="modal-title" id="vehicleModalTitle">Služební místo</h2>
            <p class="modal-text" id="vehicleModalBody">Vyberte typ vozidla.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-service" id="vehicleChoiceService">Služební vozidlo</button>
                <button type="button" class="btn btn-private" id="vehicleChoicePrivate">Soukromé vozidlo</button>
                <button type="button" class="btn btn-cancel" id="vehicleChoiceCancel">Zrušit</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}?v={{ $jsVersion }}" defer></script>
</body>
</html>
