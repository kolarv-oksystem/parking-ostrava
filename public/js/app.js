(function () {
    var freeSpotsEl = document.getElementById('freeSpots');
    var capacityLabelEl = document.getElementById('capacityLabel');
    var updatedAtEl = document.getElementById('updatedAt');
    var statusMeterEl = document.getElementById('statusMeter');
    var arriveButton = document.getElementById('arriveButton');
    var leaveButton = document.getElementById('leaveButton');
    var visitorNameInput = document.getElementById('visitorName');
    var gpsStatusEl = document.getElementById('gpsStatus');
    var eventsListEl = document.getElementById('eventsList');
    var toastEl = document.getElementById('toast');

    var STORAGE_DEVICE_ID = 'parking_device_id';
    var STORAGE_USER_NAME = 'parking_user_name';

    var state = {
        free_spots: 0,
        capacity_total: 0
    };

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
        }, 2400);
    }

    function setLoading(loading) {
        arriveButton.disabled = loading;
        leaveButton.disabled = loading;
    }

    function setGpsStatus(message, isError) {
        gpsStatusEl.textContent = 'GPS: ' + message;
        gpsStatusEl.classList.toggle('error', Boolean(isError));
    }

    function applyState(payload) {
        state.free_spots = Number(payload.free_spots || 0);
        state.capacity_total = Number(payload.capacity_total || 0);

        freeSpotsEl.textContent = String(state.free_spots);
        capacityLabelEl.textContent = 'z kapacity ' + state.capacity_total;

        if (payload.updated_at) {
            var time = new Date(payload.updated_at);
            updatedAtEl.textContent = 'Poslední změna: ' + time.toLocaleString('cs-CZ');
        } else {
            updatedAtEl.textContent = 'Poslední změna: -';
        }

        var ratio = state.capacity_total > 0 ? state.free_spots / state.capacity_total : 0;
        statusMeterEl.classList.remove('state-ok', 'state-warn', 'state-bad');
        if (ratio > 0.45) {
            statusMeterEl.classList.add('state-ok');
        } else if (ratio > 0.15) {
            statusMeterEl.classList.add('state-warn');
        } else {
            statusMeterEl.classList.add('state-bad');
        }
    }

    function api(url, options) {
        return fetch(url, Object.assign({
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
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

    function actionLabel(action) {
        if (action === 'arrive') {
            return 'přijel';
        }
        if (action === 'leave') {
            return 'odjel';
        }
        if (action === 'set_manual') {
            return 'provedl ruční korekci';
        }
        if (action === 'reset_nightly') {
            return 'provedl noční reset';
        }
        return 'provedl změnu';
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
            item.className = 'event-item';

            var createdAt = eventItem.created_at
                ? new Date(eventItem.created_at).toLocaleString('cs-CZ')
                : 'neznámý čas';
            var action = actionLabel(eventItem.action);
            var actor = actorLabel(eventItem);

            item.textContent = actor + ' ' + action + ' v ' + createdAt + '.';
            eventsListEl.appendChild(item);
        });
    }

    function refreshEvents() {
        return api('/api/events?limit=15').then(function (payload) {
            var events = Array.isArray(payload.events) ? payload.events : [];
            renderEvents(events);
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

    function submitAction(url, successMessage) {
        var name;

        try {
            name = validateName();
        } catch (error) {
            showToast(error.message, true);
            visitorNameInput.focus();
            return Promise.resolve();
        }

        setLoading(true);
        setGpsStatus('zjišťuji polohu...', false);

        return getCurrentPosition().then(function (position) {
            setGpsStatus('poloha ověřena, odesílám zápis...', false);
            return api(url, {
                method: 'POST',
                body: JSON.stringify({
                    device_id: deviceId,
                    name: name,
                    latitude: Number(position.coords.latitude),
                    longitude: Number(position.coords.longitude),
                    accuracy: Number(position.coords.accuracy || 0)
                })
            });
        }).then(function (payload) {
            applyState(payload);
            setGpsStatus('ověřeno.', false);
            showToast(successMessage, false);
            return refreshEvents();
        }).catch(function (error) {
            setGpsStatus('ověření selhalo.', true);
            showToast(error.message, true);
        }).finally(function () {
            setLoading(false);
        });
    }

    arriveButton.addEventListener('click', function () {
        submitAction('/api/decrement', 'Příjezd zaznamenán.');
    });

    leaveButton.addEventListener('click', function () {
        submitAction('/api/increment', 'Odjezd zaznamenán.');
    });

    visitorNameInput.addEventListener('blur', function () {
        var name = visitorNameInput.value.trim();
        if (name) {
            localStorage.setItem(STORAGE_USER_NAME, name);
            visitorNameInput.value = name;
        }
    });

    restoreName();

    Promise.all([refreshStatus(), refreshEvents()]).catch(function (error) {
        showToast(error.message, true);
    });

    window.setInterval(function () {
        Promise.all([refreshStatus(), refreshEvents()]).catch(function (error) {
            showToast(error.message, true);
        });
    }, 7000);
})();
