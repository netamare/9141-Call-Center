<?php
/**
 * includes/maps.php
 * GPS + mapping helpers — Adama city focused.
 * Leaflet + OpenStreetMap (no API key).
 *
 * Features:
 *  - Auto GPS on load (asks browser permission once; fails silently if denied)
 *  - Distance in km from the user's current position to the selected pin
 *  - Full list of Adama sub-cities, kebeles and landmarks (quick-pick)
 *  - Map locked to Adama city bounds
 *  - Compatible with existing render_location_picker / render_location_view callers
 */

if (!defined('ADAMA_LAT')) define('ADAMA_LAT', 8.5410);
if (!defined('ADAMA_LNG')) define('ADAMA_LNG', 39.2700);
if (!defined('ADAMA_DEFAULT_ZOOM')) define('ADAMA_DEFAULT_ZOOM', 13);

/** Approximate city bounds so the map stays over Adama. */
if (!defined('ADAMA_SOUTH')) define('ADAMA_SOUTH', 8.430);
if (!defined('ADAMA_NORTH')) define('ADAMA_NORTH', 8.620);
if (!defined('ADAMA_WEST'))  define('ADAMA_WEST',  39.180);
if (!defined('ADAMA_EAST'))  define('ADAMA_EAST',  39.360);

/**
 * Adama sub-cities, kebeles, towns and landmarks.
 * Coordinates are approximate centres — good enough for 9141 routing.
 */
function adama_places(): array {
    return [
        // —— Sub-cities / main districts ——
        ['name' => 'Adama City Center / Gidduu Magaalaa', 'lat' => 8.5410, 'lng' => 39.2700],
        ['name' => 'Aba Geda (Sub-city)',                 'lat' => 8.5500, 'lng' => 39.2650],
        ['name' => 'Bole (Sub-city)',                     'lat' => 8.5350, 'lng' => 39.2800],
        ['name' => 'Boku (Sub-city)',                     'lat' => 8.5550, 'lng' => 39.2550],
        ['name' => 'Dabe / Dabe Soloke',                  'lat' => 8.5250, 'lng' => 39.2500],
        ['name' => 'Denbela (Sub-city)',                  'lat' => 8.5600, 'lng' => 39.2750],
        ['name' => 'Lugo (Sub-city)',                     'lat' => 8.5300, 'lng' => 39.2900],
        ['name' => 'Wonji Gefersa area',                  'lat' => 8.4500, 'lng' => 39.2200],
        ['name' => 'Nazret / Old Nazret side',            'lat' => 8.5480, 'lng' => 39.2680],

        // —— Kebeles / neighbourhoods ——
        ['name' => 'Kebele 01 / 01 kebele',               'lat' => 8.5425, 'lng' => 39.2710],
        ['name' => 'Kebele 02 / 02 kebele',               'lat' => 8.5390, 'lng' => 39.2750],
        ['name' => 'Kebele 03 / 03 kebele',               'lat' => 8.5450, 'lng' => 39.2680],
        ['name' => 'Kebele 04 / 04 kebele',               'lat' => 8.5360, 'lng' => 39.2620],
        ['name' => 'Kebele 05 / 05 kebele',               'lat' => 8.5480, 'lng' => 39.2780],
        ['name' => 'Kebele 06 / 06 kebele',               'lat' => 8.5330, 'lng' => 39.2850],
        ['name' => 'Kebele 07 / 07 kebele',               'lat' => 8.5520, 'lng' => 39.2600],
        ['name' => 'Kebele 08 / 08 kebele',               'lat' => 8.5280, 'lng' => 39.2700],
        ['name' => 'Kebele 09 / 09 kebele',               'lat' => 8.5580, 'lng' => 39.2720],
        ['name' => 'Kebele 10 / 10 kebele',               'lat' => 8.5200, 'lng' => 39.2550],
        ['name' => 'Kebele 11 / 11 kebele',               'lat' => 8.5650, 'lng' => 39.2800],
        ['name' => 'Kebele 12 / 12 kebele',               'lat' => 8.5150, 'lng' => 39.2650],
        ['name' => 'Kebele 13 / 13 kebele',               'lat' => 8.5700, 'lng' => 39.2550],
        ['name' => 'Kebele 14 / 14 kebele',               'lat' => 8.5100, 'lng' => 39.2800],
        ['name' => 'Kebele 15 / 15 kebele',               'lat' => 8.5450, 'lng' => 39.2500],
        ['name' => 'Kebele 16 / 16 kebele',               'lat' => 8.5550, 'lng' => 39.2900],
        ['name' => 'Kebele 17 / 17 kebele',               'lat' => 8.5250, 'lng' => 39.2950],
        ['name' => 'Kebele 18 / 18 kebele',               'lat' => 8.5600, 'lng' => 39.2450],

        // —— Landmarks ——
        ['name' => 'Adama Stadium / Football',            'lat' => 8.5385, 'lng' => 39.2680],
        ['name' => 'ASTU (Adama Science & Technology University)', 'lat' => 8.5630, 'lng' => 39.2900],
        ['name' => 'Adama Bus Station / Terminal',        'lat' => 8.5450, 'lng' => 39.2620],
        ['name' => 'Adama Railway Station',               'lat' => 8.5480, 'lng' => 39.2580],
        ['name' => 'Saint Mary Church area',              'lat' => 8.5420, 'lng' => 39.2720],
        ['name' => 'Oromia Martyrs Monument area',        'lat' => 8.5400, 'lng' => 39.2680],
        ['name' => 'Main Market / Suuqii Guddaa',         'lat' => 8.5435, 'lng' => 39.2735],
        ['name' => 'Adama Hospital / Referral area',      'lat' => 8.5470, 'lng' => 39.2760],
        ['name' => 'Police HQ / Poolisii area',           'lat' => 8.5390, 'lng' => 39.2660],
        ['name' => 'Fire Station area',                   'lat' => 8.5360, 'lng' => 39.2710],
        ['name' => 'Adama University College area',       'lat' => 8.5500, 'lng' => 39.2850],
        ['name' => 'Industrial zone / Factory side',      'lat' => 8.5100, 'lng' => 39.3000],
        ['name' => 'Expressway / Toll gate side',         'lat' => 8.5200, 'lng' => 39.2400],
        ['name' => 'Sodere road junction (south)',        'lat' => 8.5000, 'lng' => 39.2600],
        ['name' => 'Wonji Sugar Factory side',            'lat' => 8.4600, 'lng' => 39.2300],
        ['name' => 'Koka / Lake Koka road',               'lat' => 8.4800, 'lng' => 39.2000],
        ['name' => 'Mojo road junction (west)',           'lat' => 8.5300, 'lng' => 39.2000],
        ['name' => 'Awash river side (east)',             'lat' => 8.5400, 'lng' => 39.3200],
    ];
}

/** Haversine distance in kilometres between two lat/lng points. */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/** Include once in <head>, before any inline script that uses the L (Leaflet) global. */
function leaflet_assets() {
    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">' . "\n";
    echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>' . "\n";
}

/** Include once (in addition to leaflet_assets) on pages that render the geographic heat layer. */
function leaflet_heat_asset() {
    echo '<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>' . "\n";
}

/**
 * Interactive "pin your location" map:
 *  - Draggable marker
 *  - Use-My-Location (GPS) button + automatic GPS on first load
 *  - Quick-pick list of all Adama towns / kebeles / landmarks
 *  - Live distance (km) from the user's current GPS position
 *  - Map locked to Adama city bounds
 */
function render_location_picker($mapId = 'locPicker', $latName = 'latitude', $lngName = 'longitude', $initLat = null, $initLng = null) {
    $lat = $initLat !== null && $initLat !== '' ? (float)$initLat : ADAMA_LAT;
    $lng = $initLng !== null && $initLng !== '' ? (float)$initLng : ADAMA_LNG;
    $hasInit = $initLat !== null && $initLat !== '';
    $places = adama_places();
    ?>
    <label>📍 <?= htmlspecialchars(function_exists('t_raw') ? t_raw('label_gps') : 'Location / Bakka') ?></label>
    <div class="map-card">
        <div style="margin-bottom:8px;">
            <select id="<?= $mapId ?>_place" class="map-place-select" style="width:100%; padding:10px 12px; border-radius:10px; border:1.5px solid var(--border); background:var(--panel-2); color:var(--text); font-family:var(--body); font-size:14px;">
                <option value="">— Towwanii / Kebele / Landmark Adama —</option>
                <?php foreach ($places as $i => $p): ?>
                    <option value="<?= $i ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="<?= $mapId ?>" class="map-canvas"></div>
        <div class="map-toolbar">
            <button type="button" class="btn btn-ghost btn-sm" id="<?= $mapId ?>_geo">📡 <?= htmlspecialchars(function_exists('t_raw') ? t_raw('gps_use_location') : 'Use my location') ?></button>
            <span class="map-coords" id="<?= $mapId ?>_coords"><?= $hasInit ? number_format($lat, 5) . ', ' . number_format($lng, 5) : (function_exists('t_raw') ? htmlspecialchars(t_raw('gps_not_set')) : 'Not set') ?></span>
            <span class="map-distance" id="<?= $mapId ?>_dist" style="font-family:var(--mono); font-size:12px; font-weight:700; color:var(--cyan); margin-left:auto;"></span>
        </div>
        <div class="hint"><?= htmlspecialchars(function_exists('t_raw') ? t_raw('gps_hint') : 'GPS ofumaan barbaada (permission yoo kennitan). Distance km irraa argama.') ?></div>
    </div>
    <input type="hidden" name="<?= htmlspecialchars($latName) ?>" id="<?= $mapId ?>_lat" value="<?= $hasInit ? htmlspecialchars((string)$initLat) : '' ?>">
    <input type="hidden" name="<?= htmlspecialchars($lngName) ?>" id="<?= $mapId ?>_lng" value="<?= $hasInit ? htmlspecialchars((string)$initLng) : '' ?>">
    <script>
    (function(){
        var places = <?= json_encode(array_map(function($p) {
            return ['name' => $p['name'], 'lat' => (float)$p['lat'], 'lng' => (float)$p['lng']];
        }, $places), JSON_UNESCAPED_UNICODE) ?>;

        var south = <?= ADAMA_SOUTH ?>, north = <?= ADAMA_NORTH ?>;
        var west  = <?= ADAMA_WEST ?>,  east  = <?= ADAMA_EAST ?>;
        var bounds = L.latLngBounds([south, west], [north, east]);

        var map = L.map('<?= $mapId ?>', {
            scrollWheelZoom: false,
            maxBounds: bounds,
            maxBoundsViscosity: 0.85,
            minZoom: 11
        }).setView([<?= $lat ?>, <?= $lng ?>], <?= $hasInit ? 15 : ADAMA_DEFAULT_ZOOM ?>);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var latInput = document.getElementById('<?= $mapId ?>_lat');
        var lngInput = document.getElementById('<?= $mapId ?>_lng');
        var coordsLabel = document.getElementById('<?= $mapId ?>_coords');
        var distLabel = document.getElementById('<?= $mapId ?>_dist');
        var placeSelect = document.getElementById('<?= $mapId ?>_place');
        var geoBtn = document.getElementById('<?= $mapId ?>_geo');

        var marker = L.marker([<?= $lat ?>, <?= $lng ?>], { draggable: true }).addTo(map);
        var userMarker = null;   // blue dot for "you are here"
        var accuracyCircle = null; // GPS accuracy radius
        var userLat = null;
        var userLng = null;
        var userAccuracy = null; // meters

        function haversineKm(lat1, lng1, lat2, lng2) {
            var R = 6371;
            var dLat = (lat2 - lat1) * Math.PI / 180;
            var dLng = (lng2 - lng1) * Math.PI / 180;
            var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
                    Math.sin(dLng/2) * Math.sin(dLng/2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function formatAccuracy(m) {
            if (m == null || isNaN(m)) return '';
            if (m < 1) return '±' + (m * 100).toFixed(0) + ' cm';
            if (m < 1000) return '±' + Math.round(m) + ' m';
            return '±' + (m / 1000).toFixed(2) + ' km';
        }

        function updateDistance(pinLat, pinLng) {
            if (!distLabel) return;
            var parts = [];
            if (userLat !== null && userLng !== null) {
                var km = haversineKm(userLat, userLng, pinLat, pinLng);
                if (km < 0.1) {
                    parts.push((km * 1000).toFixed(0) + ' m away');
                } else {
                    parts.push(km.toFixed(2) + ' km away');
                }
            }
            if (userAccuracy != null) {
                parts.push(formatAccuracy(userAccuracy));
            }
            distLabel.textContent = parts.join(' · ');
        }

        function setPoint(lat, lng, fromUser) {
            lat = Math.max(south, Math.min(north, lat));
            lng = Math.max(west,  Math.min(east,  lng));
            marker.setLatLng([lat, lng]);
            if (latInput) latInput.value = lat.toFixed(6);
            if (lngInput) lngInput.value = lng.toFixed(6);
            if (coordsLabel) {
                var t = lat.toFixed(5) + ', ' + lng.toFixed(5);
                if (fromUser) t += ' ✓';
                if (fromUser && userAccuracy != null) t += ' ' + formatAccuracy(userAccuracy);
                coordsLabel.textContent = t;
            }
            updateDistance(lat, lng);
        }

        function setUserPosition(lat, lng, accuracy) {
            userLat = lat;
            userLng = lng;
            userAccuracy = (accuracy != null && !isNaN(accuracy)) ? accuracy : null;

            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
            } else {
                userMarker = L.circleMarker([lat, lng], {
                    radius: 8,
                    color: '#fff',
                    weight: 2,
                    fillColor: '#2563EB',
                    fillOpacity: 0.95
                }).addTo(map);
            }
            var popupTxt = 'You are here / Ati as jirta';
            if (userAccuracy != null) popupTxt += '<br>Accuracy: ' + formatAccuracy(userAccuracy);
            userMarker.bindPopup(popupTxt);

            // Accuracy circle on map
            if (userAccuracy != null && userAccuracy > 0) {
                if (accuracyCircle) {
                    accuracyCircle.setLatLng([lat, lng]);
                    accuracyCircle.setRadius(userAccuracy);
                } else {
                    accuracyCircle = L.circle([lat, lng], {
                        radius: userAccuracy,
                        color: '#2563EB',
                        weight: 1,
                        fillColor: '#3B82F6',
                        fillOpacity: 0.12,
                        interactive: false
                    }).addTo(map);
                }
            }

            var pin = marker.getLatLng();
            updateDistance(pin.lat, pin.lng);
        }

        marker.on('dragend', function(e) {
            var p = e.target.getLatLng();
            setPoint(p.lat, p.lng, false);
        });

        map.on('click', function(e) {
            setPoint(e.latlng.lat, e.latlng.lng, false);
        });

        // Quick-pick place
        if (placeSelect) {
            placeSelect.addEventListener('change', function() {
                var i = this.value;
                if (i === '' || !places[i]) return;
                var p = places[i];
                setPoint(p.lat, p.lng, false);
                map.setView([p.lat, p.lng], 15);
            });
        }

        // Manual GPS button
        function doGeo() {
            if (!navigator.geolocation) {
                if (coordsLabel) coordsLabel.textContent = 'GPS not supported';
                return;
            }
            if (geoBtn) geoBtn.disabled = true;
            navigator.geolocation.getCurrentPosition(function(pos) {
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                var acc = pos.coords.accuracy;
                setUserPosition(lat, lng, acc);
                setPoint(lat, lng, true);
                map.setView([lat, lng], 16);
                if (geoBtn) geoBtn.disabled = false;
            }, function() {
                if (coordsLabel) coordsLabel.textContent = 'GPS denied / failed';
                if (geoBtn) geoBtn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 20000 });
        }
        if (geoBtn) geoBtn.addEventListener('click', doGeo);

        // —— Auto GPS on load (silent if denied) ——
        var autoDone = false;
        function autoLocate() {
            if (autoDone || !navigator.geolocation) return;
            // If form already has coords, still get user position for distance only
            var needPin = !(latInput && latInput.value);
            navigator.geolocation.getCurrentPosition(function(pos) {
                autoDone = true;
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                var acc = pos.coords.accuracy;
                setUserPosition(lat, lng, acc);
                if (needPin) {
                    setPoint(lat, lng, true);
                    map.setView([lat, lng], 16);
                } else {
                    updateDistance(parseFloat(latInput.value), parseFloat(lngInput.value));
                }
            }, function(){ /* silent fail */ },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
        }
        autoLocate();
        setTimeout(autoLocate, 1800);
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') autoLocate();
        });

        // Initial distance if we already have a pin
        <?php if ($hasInit): ?>
        // wait a moment for possible autoLocate
        setTimeout(function(){ updateDistance(<?= $lat ?>, <?= $lng ?>); }, 500);
        <?php endif; ?>
    })();
    </script>
    <?php
}

/**
 * Small read-only map showing one fixed pin.
 * If the browser has GPS, also shows "You are here" + distance in km.
 */
function render_location_view($lat, $lng, $mapId = 'locView') {
    if ($lat === null || $lng === null || $lat === '' || $lng === '') return;
    $lat = (float)$lat; $lng = (float)$lng;
    ?>
    <div class="map-card">
        <div id="<?= $mapId ?>" class="map-canvas map-canvas-sm"></div>
        <div class="map-toolbar">
            <span class="map-coords"><?= number_format($lat, 5) ?>, <?= number_format($lng, 5) ?></span>
            <span class="map-distance" id="<?= $mapId ?>_dist" style="font-family:var(--mono); font-size:12px; font-weight:700; color:var(--cyan); margin-left:auto;"></span>
        </div>
    </div>
    <script>
    (function(){
        var pinLat = <?= $lat ?>, pinLng = <?= $lng ?>;
        var map = L.map('<?= $mapId ?>', { scrollWheelZoom: false, dragging: !L.Browser.mobile }).setView([pinLat, pinLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        L.marker([pinLat, pinLng]).addTo(map);

        var distEl = document.getElementById('<?= $mapId ?>_dist');
        function haversineKm(lat1, lng1, lat2, lng2) {
            var R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLng = (lng2-lng1)*Math.PI/180;
            var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)*Math.sin(dLng/2);
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }
        if (navigator.geolocation && distEl) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var uLat = pos.coords.latitude, uLng = pos.coords.longitude;
                var acc = pos.coords.accuracy;
                L.circleMarker([uLat, uLng], {
                    radius: 7, color: '#fff', weight: 2, fillColor: '#2563EB', fillOpacity: 0.95
                }).addTo(map).bindPopup('You are here' + (acc ? '<br>Accuracy: ±' + Math.round(acc) + ' m' : ''));
                if (acc && acc > 0) {
                    L.circle([uLat, uLng], {
                        radius: acc, color: '#2563EB', weight: 1,
                        fillColor: '#3B82F6', fillOpacity: 0.12, interactive: false
                    }).addTo(map);
                }
                var km = haversineKm(uLat, uLng, pinLat, pinLng);
                var txt = (km < 0.1) ? (km*1000).toFixed(0) + ' m away' : km.toFixed(2) + ' km away';
                if (acc != null && !isNaN(acc)) txt += ' · ±' + Math.round(acc) + ' m';
                distEl.textContent = txt;
            }, function(){}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
        }
    })();
    </script>
    <?php
}
