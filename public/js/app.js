(function () {
    var freeSpotsEl = document.getElementById('freeSpots');
    var capacityLabelEl = document.getElementById('capacityLabel');
    var updatedAtEl = document.getElementById('updatedAt');
    var visitorNameInput = document.getElementById('visitorName');
    var gpsStatusEl = document.getElementById('gpsStatus');
    var eventsListEl = document.getElementById('eventsList');
    var toastEl = document.getElementById('toast');
    var parkingMapEl = document.getElementById('parkingMap');
    var vehicleModalEl = document.getElementById('vehicleChoiceModal');
    var vehicleModalTitle = document.getElementById('vehicleModalTitle');
    var vehicleModalBody = document.getElementById('vehicleModalBody');
    var vehicleChoiceService = document.getElementById('vehicleChoiceService');
    var vehicleChoicePrivate = document.getElementById('vehicleChoicePrivate');
    var vehicleChoiceCancel = document.getElementById('vehicleChoiceCancel');
    var logExpandButton = document.getElementById('logExpandButton');
    var logRangeLabel = document.getElementById('logRangeLabel');
    var spotButtons = Array.prototype.slice.call(
        parkingMapEl.querySelectorAll('.spot-btn[data-spot-number]')
    );

    var STORAGE_DEVICE_ID = 'parking_device_id';
    var STORAGE_USER_NAME = 'parking_user_name';
    var SHORT_LOG_LIMIT = 10;
    var FULL_LOG_LIMIT = 100;

    var state = {
        free_spots: 0,
        capacity_total: 0,
        reserved_service_spots_count: 0,
        spots: []
    };

    var logExpanded = false;
    var pendingDialog = null;

    function createDeviceId() {
        var existing = localStorage.getItem(STORAGE_DEVICE_ID);
        if (existing) {
            return existing;
        }

        var generated = 'dev_' + Date.now() + '_' + Math.random().toString(36).slice(2, 11);
        localStorage.setItem(STORAGE_DEVICE_ID, generated);
        return generated;
    }

    var deviceId = createDeviceId();

    function restoreName() {
        var savedName = localStorage.getItem(STORAGE_USER_NAME);
        if (savedName) {
            visitorNameInput.value = savedName;
        }
    }

    function showToast(message, isError) {
        toastEl.textContent = message;
        toastEl.style.borderColor = isError ? 'rgba(239,68,68,0.7)' : 'rgba(16,185,129,0.7)';
        toastEl.classList.remove('hidden');
        window.clearTimeout(showToast._timer);
        showToast._timer = window.setTimeout(function () {
            toastEl.classList.add('hidden');
        }, 2600);
    }

    function setLoading(loading) {
        spotButtons.forEach(function (button) {
            button.disabled = loading;
        });
        vehicleChoiceService.disabled = loading;
        vehicleChoicePrivate.disabled = loading;
        vehicleChoiceCancel.disabled = loading;
        logExpandButton.disabled = loading;
    }

    function setGpsStatus(message, isError) {
        gpsStatusEl.textContent = 'GPS: ' + message;
        gpsStatusEl.classList.toggle('error', Boolean(isError));
    }

    function getSpotByNumber(spotNumber) {
        return state.spots.find(function (spot) {
            return Number(spot.spot_number) === Number(spotNumber);
        }) || null;
    }

    function isServiceZoneSpot(spotNumber) {
        var n = Number(spotNumber);
        var count = Number(state.reserved_service_spots_count || 0);
        return count > 0 && n >= 1 && n <= count;
    }

    function openActionDialog(config) {
        pendingDialog = config;
        vehicleModalTitle.textContent = config.title;
        vehicleModalBody.textContent = config.body;
        vehicleChoicePrivate.textContent = config.primaryButtonLabel;
        vehicleChoiceService.classList.toggle('hidden', !config.showServiceOption);
        vehicleModalEl.classList.remove('hidden');
        vehicleModalEl.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.setTimeout(function () {
            vehicleChoiceCancel.focus();
        }, 50);
    }

    function closeActionDialog() {
        pendingDialog = null;
        vehicleModalEl.classList.add('hidden');
        vehicleModalEl.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function applyState(payload) {
        state.free_spots = Number(payload.free_spots || 0);
        state.capacity_total = Number(payload.capacity_total || 0);
        state.reserved_service_spots_count = Number(payload.reserved_service_spots_count || 0);
        state.spots = Array.isArray(payload.spots) ? payload.spots : [];

        freeSpotsEl.textContent = String(state.free_spots);
        capacityLabelEl.textContent = 'volných z ' + state.capacity_total;

        if (payload.updated_at) {
            var time = new Date(payload.updated_at);
            updatedAtEl.textContent = 'Poslední změna: ' + time.toLocaleString('cs-CZ');
        } else {
            updatedAtEl.textContent = 'Poslední změna: -';
        }

        spotButtons.forEach(function (button) {
            var spotNumber = Number(button.getAttribute('data-spot-number'));
            var spot = getSpotByNumber(spotNumber);
            var numberEl = button.querySelector('.spot-number');
            var nameEl = button.querySelector('.spot-name');
            var badgeEl = button.querySelector('.spot-badge');

            button.classList.remove('is-free', 'is-occupied', 'is-missing', 'spot-btn--service-zone', 'spot-btn--reserve-on');

            if (!spot) {
                button.classList.add('is-missing');
                button.disabled = true;
                numberEl.textContent = 'Místo ' + spotNumber;
                nameEl.textContent = 'Mimo kapacitu';
                badgeEl.textContent = '';
                badgeEl.style.display = 'none';
                return;
            }

            button.disabled = false;
            numberEl.textContent = 'Místo ' + spot.spot_number;

            if (isServiceZoneSpot(spot.spot_number)) {
                button.classList.add('spot-btn--service-zone');
                badgeEl.style.display = 'inline-block';
                if (!spot.is_occupied) {
                    badgeEl.textContent = spot.is_reserved_service ? 'Rezervace' : 'Služební';
                    if (spot.is_reserved_service) {
                        button.classList.add('spot-btn--reserve-on');
                    }
                } else {
                    badgeEl.textContent = 'Služební';
                }
            } else {
                badgeEl.textContent = '';
                badgeEl.style.display = 'none';
            }

            if (spot.is_occupied) {
                button.classList.add('is-occupied');
                nameEl.textContent = spot.occupied_by_name || 'Obsazeno';
            } else {
                button.classList.add('is-free');
                nameEl.textContent = 'Volno';
            }
        });
    }

    function api(url, options) {
        return fetch(url, Object.assign({
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        }, options)).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw new Error(data.message || 'API chyba');
                }
                return data;
            });
        });
    }

    function refreshStatus() {
        var url = '/api/status?device_id=' + encodeURIComponent(deviceId);
        return api(url).then(function (payload) {
            applyState(payload);
        });
    }

    function vehicleLabel(vehicleType) {
        if (vehicleType === 'service') {
            return 'služební';
        }
        if (vehicleType === 'private') {
            return 'soukromé';
        }
        return '-';
    }

    function actionLabel(eventItem) {
        var spotNumber = eventItem.spot_number || '-';
        var vehicle = vehicleLabel(eventItem.vehicle_type);

        if (eventItem.action === 'spot_arrive') {
            return 'Obsazeno (' + vehicle + ')';
        }
        if (eventItem.action === 'spot_leave') {
            return 'Uvolněno (' + vehicle + ')';
        }
        if (eventItem.action === 'service_reserve_on') {
            return 'Zapnuta služební rezervace';
        }
        if (eventItem.action === 'service_reserve_off') {
            return 'Vypnuta služební rezervace';
        }
        if (eventItem.action === 'set_manual') {
            return 'Ruční korekce stavu';
        }
        return 'Změna stavu';
    }

    function actorLabel(eventItem) {
        if (eventItem.user_name) {
            return eventItem.user_name;
        }
        if (eventItem.actor) {
            return eventItem.actor;
        }
        return 'Někdo';
    }

    function renderEvents(events) {
        eventsListEl.innerHTML = '';

        if (!events.length) {
            var emptyEl = document.createElement('li');
            emptyEl.className = 'events-empty';
            emptyEl.textContent = 'Zatím nejsou žádné záznamy.';
            eventsListEl.appendChild(emptyEl);
            return;
        }

        events.forEach(function (eventItem) {
            var item = document.createElement('li');
            item.className = 'event-item event-grid';

            var createdAt = eventItem.created_at
                ? new Date(eventItem.created_at).toLocaleString('cs-CZ')
                : 'neznámý čas';
            var actor = actorLabel(eventItem);
            var spotText = eventItem.spot_number ? 'Místo ' + eventItem.spot_number : 'Systém';
            var action = actionLabel(eventItem);

            var timeEl = document.createElement('span');
            timeEl.className = 'event-time';
            timeEl.textContent = createdAt;

            var actorEl = document.createElement('span');
            actorEl.className = 'event-actor';
            actorEl.textContent = actor;

            var spotEl = document.createElement('span');
            spotEl.className = 'event-spot';
            spotEl.textContent = spotText;

            var actionEl = document.createElement('span');
            actionEl.className = 'event-action';
            actionEl.textContent = action;

            item.appendChild(timeEl);
            item.appendChild(actorEl);
            item.appendChild(spotEl);
            item.appendChild(actionEl);
            eventsListEl.appendChild(item);
        });
    }

    function refreshEvents(limit) {
        var resolvedLimit = typeof limit === 'number' ? limit : (logExpanded ? FULL_LOG_LIMIT : SHORT_LOG_LIMIT);
        return api('/api/events?limit=' + resolvedLimit).then(function (payload) {
            var events = Array.isArray(payload.events) ? payload.events : [];
            renderEvents(events);
            if (resolvedLimit >= FULL_LOG_LIMIT) {
                logRangeLabel.textContent = 'Aktuálně: posledních 100 záznamů.';
            } else {
                logRangeLabel.textContent = 'Aktuálně: posledních 10 záznamů.';
            }
        });
    }

    function validateName() {
        var name = visitorNameInput.value.trim();
        if (name.length < 2) {
            throw new Error('Vyplňte prosím jméno (alespoň 2 znaky).');
        }

        visitorNameInput.value = name;
        localStorage.setItem(STORAGE_USER_NAME, name);
        return name;
    }

    function resolveGeoError(error) {
        if (!error) {
            return 'Nepodařilo se získat GPS polohu.';
        }

        if (error.code === error.PERMISSION_DENIED) {
            return 'Povolte sdílení polohy pro tento web.';
        }

        if (error.code === error.POSITION_UNAVAILABLE) {
            return 'GPS poloha není aktuálně dostupná.';
        }

        if (error.code === error.TIMEOUT) {
            return 'Vypršel časový limit pro získání GPS polohy.';
        }

        return 'Nepodařilo se získat GPS polohu.';
    }

    function getCurrentPosition() {
        if (!navigator.geolocation) {
            return Promise.reject(new Error('Toto zařízení nepodporuje GPS geolokaci.'));
        }

        return new Promise(function (resolve, reject) {
            navigator.geolocation.getCurrentPosition(resolve, function (error) {
                reject(new Error(resolveGeoError(error)));
            }, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        });
    }

    function submitSpotToggleWithOptions(spotNumber, vehicleType, isOccupied) {
        var name;

        try {
            name = validateName();
        } catch (error) {
            showToast(error.message, true);
            visitorNameInput.focus();
            return Promise.resolve();
        }

        setLoading(true);

        var payload = {
            device_id: deviceId,
            name: name,
            spot_number: Number(spotNumber)
        };

        if (vehicleType) {
            payload.vehicle_type = vehicleType;
        }

        var requestPromise;
        var needsGps = !isOccupied && vehicleType !== 'service';

        if (needsGps) {
            setGpsStatus('zjišťuji polohu...', false);
            requestPromise = getCurrentPosition().then(function (position) {
                payload.latitude = Number(position.coords.latitude);
                payload.longitude = Number(position.coords.longitude);
                payload.accuracy = Number(position.coords.accuracy || 0);
                setGpsStatus('poloha ověřena, odesílám zápis...', false);
                return api('/api/spots/toggle', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            });
        } else {
            if (isOccupied) {
                setGpsStatus('uvolňuji místo (GPS není potřeba).', false);
            } else {
                setGpsStatus('služební vozidlo - GPS není potřeba.', false);
            }
            requestPromise = api('/api/spots/toggle', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
        }

        return requestPromise.then(function (responsePayload) {
            applyState(responsePayload);
            setGpsStatus('ověřeno.', false);
            showToast(isOccupied ? 'Místo uvolněno.' : 'Místo obsazeno.', false);
            return refreshEvents();
        }).catch(function (error) {
            setGpsStatus('ověření selhalo.', true);
            showToast(error.message, true);
        }).finally(function () {
            setLoading(false);
        });
    }

    function handleSpotClick(spotNumber) {
        var spot = getSpotByNumber(spotNumber);
        var isOccupied = spot ? Boolean(spot.is_occupied) : false;

        if (isOccupied) {
            openActionDialog({
                title: 'Uvolnit místo ' + spotNumber,
                body: 'Potvrďte uvolnění tohoto místa.',
                showServiceOption: false,
                primaryButtonLabel: 'Uvolnit místo',
                primaryAction: function () {
                    return submitSpotToggleWithOptions(spotNumber, null, true);
                }
            });
            return;
        }

        if (isServiceZoneSpot(spotNumber)) {
            openActionDialog({
                title: 'Místo ' + spotNumber + ' (služební zóna)',
                body: 'Vyberte typ vozidla.',
                showServiceOption: true,
                primaryButtonLabel: 'Soukromé vozidlo',
                primaryAction: function () {
                    return submitSpotToggleWithOptions(spotNumber, 'private', false);
                },
                serviceAction: function () {
                    return submitSpotToggleWithOptions(spotNumber, 'service', false);
                }
            });
            return;
        }

        openActionDialog({
            title: 'Obsadit místo ' + spotNumber,
            body: 'Potvrďte obsazení soukromým vozidlem.',
            showServiceOption: false,
            primaryButtonLabel: 'Soukromé vozidlo',
            primaryAction: function () {
                return submitSpotToggleWithOptions(spotNumber, 'private', false);
            }
        });
    }

    spotButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var spotNumber = Number(button.getAttribute('data-spot-number'));
            handleSpotClick(spotNumber);
        });
    });

    vehicleChoiceService.addEventListener('click', function () {
        if (!pendingDialog || typeof pendingDialog.serviceAction !== 'function') {
            return;
        }
        var action = pendingDialog.serviceAction;
        closeActionDialog();
        action();
    });

    vehicleChoicePrivate.addEventListener('click', function () {
        if (!pendingDialog || typeof pendingDialog.primaryAction !== 'function') {
            return;
        }
        var action = pendingDialog.primaryAction;
        closeActionDialog();
        action();
    });

    vehicleChoiceCancel.addEventListener('click', function () {
        closeActionDialog();
    });

    vehicleModalEl.querySelectorAll('[data-modal-dismiss="true"]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeActionDialog();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !vehicleModalEl.classList.contains('hidden')) {
            closeActionDialog();
        }
    });

    visitorNameInput.addEventListener('blur', function () {
        var name = visitorNameInput.value.trim();
        if (name) {
            localStorage.setItem(STORAGE_USER_NAME, name);
            visitorNameInput.value = name;
        }
    });

    logExpandButton.addEventListener('click', function () {
        if (logExpanded) {
            return;
        }
        logExpanded = true;
        logExpandButton.classList.add('hidden');
        refreshEvents(FULL_LOG_LIMIT).catch(function (error) {
            showToast(error.message, true);
        });
    });

    restoreName();

    Promise.all([refreshStatus(), refreshEvents(SHORT_LOG_LIMIT)]).catch(function (error) {
        showToast(error.message, true);
    });

    window.setInterval(function () {
        Promise.all([refreshStatus(), refreshEvents()]).catch(function (error) {
            showToast(error.message, true);
        });
    }, 9000);
})();
