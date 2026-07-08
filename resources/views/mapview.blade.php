<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Map View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 170px;
            min-height: 100vh;
            background: #1a3c8f;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 16px 16px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .sidebar-brand img {
            width: 38px; height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,.3);
        }
        .sidebar-brand-text .title {
            font-family: 'Barlow', sans-serif;
            font-weight: 800; font-size: .85rem;
            color: #fff; letter-spacing: .5px; line-height: 1.1;
        }
        .sidebar-brand-text .sub {
            font-size: .65rem; color: rgba(255,255,255,.6); letter-spacing: .5px;
        }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a {
            display: block; padding: 10px 20px;
            font-size: .82rem; font-weight: 500;
            color: rgba(255,255,255,.75); text-decoration: none;
            border-left: 3px solid transparent; transition: all .2s;
        }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active {
            color: #fff; font-weight: 700;
            border-left-color: #fff; background: rgba(255,255,255,.1);
        }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a {
            display: flex; align-items: center; gap: 8px;
            font-size: .82rem; color: rgba(255,255,255,.75);
            text-decoration: none; font-weight: 500; transition: color .2s;
        }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 170px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 28px 32px; flex: 1; }

        .page-title {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: 1.5rem; color: #111827; margin-bottom: 18px;
        }

        /* ── MAP CARD ── */
        .map-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            position: relative;
            margin-bottom: 16px;
        }

        #map {
            width: 100%;
            height: 600px;
        }

        /* Legend overlay */
        .map-legend {
            position: absolute;
            top: 12px; right: 12px;
            background: rgba(255,255,255,.95);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
            z-index: 5;
            line-height: 1.9;
        }
        .map-legend .leg-item { display: flex; align-items: center; gap: 6px; }
        .leg-emoji { font-size: .9rem; width: 16px; text-align: center; }

        /* Refresh button */
        .btn-refresh {
            position: absolute;
            bottom: 14px; right: 14px;
            background: #fff;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: .78rem;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            z-index: 5;
            display: flex; align-items: center; gap: 6px;
            transition: border-color .2s, box-shadow .2s;
        }
        .btn-refresh:hover { border-color: #1a3c8f; box-shadow: 0 2px 8px rgba(26,60,143,.15); }

        /* ── BOTTOM CONTROLS ── */
        .controls-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .control-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 20px;
            font-size: .8rem;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .control-card .ctrl-label {
            font-weight: 700;
            color: #111827;
            margin-right: 4px;
            font-size: .8rem;
        }

        .leg-inline { display: flex; align-items: center; gap: 5px; color: #4b5563; }

        /* Checkbox toggle */
        .form-check-input:checked { background-color: #1a3c8f; border-color: #1a3c8f; }

        /* Custom popup content (rendered inside Google InfoWindow) */
        .incident-popup { font-family: 'Inter', sans-serif; font-size: .78rem; min-width: 170px; }
        .incident-popup .pop-type { font-weight: 700; font-size: .85rem; margin-bottom: 4px; text-transform: capitalize; }
        .incident-popup .pop-row { color: #6b7280; margin-bottom: 2px; }
        .incident-popup .pop-status { font-weight: 600; margin-top: 6px; display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .72rem; }
        .pop-pending  { background: #fef3c7; color: #92400e; }
        .pop-responding { background: #dbeafe; color: #1e40af; }
        .pop-resolved { background: #d1fae5; color: #065f46; }
        .pop-open     { background: #d1fae5; color: #065f46; }
        .pop-full     { background: #fee2e2; color: #991b1b; }
        .pop-closed   { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Logo">
        <div class="sidebar-brand-text">
            <div class="title">MDRRMO</div>
            <div class="sub">ADMIN PANEL</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('incident') }}">Incidents</a>
        <a href="{{ route('mapview') }}" class="active">Map View</a>
        <a href="{{ route('alerts') }}">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('reports-analytics') }}">Reports &amp; Analytics</a>
        <a href="{{ route('users') }}">User</a>
        <a href="#">Settings</a>
    </nav>
    <div class="sidebar-logout">
        <a href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <i class="bi bi-box-arrow-right"></i> Log out
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
</aside>

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">
        <div class="page-title">Map View</div>

        <!-- MAP -->
        <div class="map-card">
            <div id="map"></div>

            <!-- Legend -->
            <div class="map-legend">
                <div class="leg-item"><span class="leg-emoji">🔥</span> Fire</div>
                <div class="leg-item"><span class="leg-emoji">🌊</span> Flood</div>
                <div class="leg-item"><span class="leg-emoji">🚑</span> Medical</div>
                <div class="leg-item"><span class="leg-emoji">🚗</span> Accident</div>
                <div class="leg-item"><span class="leg-emoji">⚠️</span> Other</div>
                <div class="leg-item"><span class="leg-emoji">🏕️</span> Evac. Center</div>
            </div>

            <!-- Refresh -->
            <button class="btn-refresh" onclick="refreshMap()">
                <i class="bi bi-arrow-clockwise"></i> Refresh Map
            </button>
            <button class="btn-refresh" id="btnSatellite" style="left:14px; right:auto;" onclick="toggleSatellite()">
                <i class="bi bi-globe-americas"></i> Satellite View
            </button>
        </div>

        <!-- BOTTOM CONTROLS -->
        <div class="controls-row">
            <!-- Show Incidents -->
            <div class="control-card">
                <span class="ctrl-label">Status Key</span>
                <div class="leg-inline"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#92400e;"></span> Pending</div>
                <div class="leg-inline"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1e40af;"></span> Responding</div>
                <div class="leg-inline"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#065f46;"></span> Resolved</div>
            </div>

            <!-- Toggles -->
            <div class="control-card gap-3">
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="chkIncidents" checked onchange="toggleLayer('incidents', this.checked)">
                    <label class="form-check-label" for="chkIncidents" style="font-size:.8rem;font-weight:600;">Show Reported Incidents</label>
                </div>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="chkEvac" checked onchange="toggleLayer('evac', this.checked)">
                    <label class="form-check-label" for="chkEvac" style="font-size:.8rem;font-weight:600;">Show Evacuation Centers</label>
                </div>
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="chkResp" checked onchange="toggleLayer('resp', this.checked)">
                    <label class="form-check-label" for="chkResp" style="font-size:.8rem;font-weight:600;">Show Responders</label>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Incidents passed from Laravel controller ──
    // Each item: { id, emergency_type, location, latitude, longitude, description, status, created_at }
    const incidentsData = @json($incidents);

    // ── Real evacuation centers, from the evacuation_centers table ──
    // Each item: { id, name, barangay, latitude, longitude, capacity, occupancy, status }
    const evacCentersData = @json($evacCenters);

    // ── Icon + color mapping per emergency_type ──
    const typeIconMap = {
        fire:     { emoji: '🔥', color: '#dc2626' },
        flood:    { emoji: '🌊', color: '#3b82f6' },
        medical:  { emoji: '🚑', color: '#7c3aed' },
        accident: { emoji: '🚗', color: '#f97316' },
    };
    const statusColorMap = {
        pending:    '#92400e',
        responding: '#1e40af',
        resolved:   '#065f46',
    };
    
    const defaultIcon = { emoji: '⚠️', color: '#6b7280' };

    function getTypeIcon(type) {
        const key = (type || '').toLowerCase().trim();
        return typeIconMap[key] || defaultIcon;
    }

    const statusLabel = { pending: 'Pending', responding: 'Responding', resolved: 'Resolved' };
    const statusClass = { pending: 'pop-pending', responding: 'pop-responding', resolved: 'pop-resolved' };

    const evacStatusLabel = { open: 'Open', full: 'Full', closed: 'Closed' };
    const evacStatusClass = { open: 'pop-open', full: 'pop-full', closed: 'pop-closed' };

    let map;
    let incidentMarkers = [];
    let evacLayerMarkers = [];
    let respLayerMarkers = [];
    let activeInfoWindow = null;

    function makeMarkerIcon(emoji, color) {
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">
                <ellipse cx="18" cy="40" rx="6" ry="3" fill="rgba(0,0,0,.2)"/>
                <path d="M18 0 C8 0 0 8 0 18 C0 30 18 44 18 44 C18 44 36 30 36 18 C36 8 28 0 18 0Z" fill="${color}"/>
                <text x="18" y="23" text-anchor="middle" font-size="16">${emoji}</text>
            </svg>`;
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(36, 44),
            anchor: new google.maps.Point(18, 44),
        };
    }

    function initMap() {
        // Centered on Rosales, Pangasinan
        map = new google.maps.Map(document.getElementById('map'), {
            center: { lat: 15.8952, lng: 120.6263 },
            zoom: 14,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });

        renderIncidentMarkers();
        renderEvacuationMarkers();
        renderResponderMarkers();
    }

    function toggleSatellite() {
        const btn = document.getElementById('btnSatellite');
        const isSatellite = map.getMapTypeId() === google.maps.MapTypeId.HYBRID;

        map.setMapTypeId(isSatellite ? google.maps.MapTypeId.ROADMAP : google.maps.MapTypeId.HYBRID);

        btn.innerHTML = isSatellite
            ? '<i class="bi bi-globe-americas"></i> Satellite View'
            : '<i class="bi bi-map"></i> Map View';
    }

    // ── Plot every citizen-reported incident at its GPS location ──
    function renderIncidentMarkers() {
        incidentMarkers.forEach(m => m.setMap(null));
        incidentMarkers = [];

        incidentsData.forEach(incident => {
            const lat = parseFloat(incident.latitude);
            const lng = parseFloat(incident.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const statusKey = (incident.status || 'pending').toLowerCase();
            const badgeClass = statusClass[statusKey] || 'pop-pending'; 
            const badgeLabel = statusLabel[statusKey] || incident.status || 'Pending';
            const iconInfo = getTypeIcon(incident.emergency_type);
            const pinColor = statusColorMap[statusKey] || defaultIcon.color;
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                icon: makeMarkerIcon(iconInfo.emoji, pinColor),
                title: incident.emergency_type,
            });


            const content = `
                <div class="incident-popup">
                    <div class="pop-type">${iconInfo.emoji} ${incident.emergency_type ?? 'Unknown'}</div>
                    <div class="pop-row">${incident.location ?? ''}</div>
                    ${incident.description ? `<div class="pop-row">${incident.description}</div>` : ''}
                    <span class="pop-status ${badgeClass}">${badgeLabel}</span>
                </div>`;

            const infoWindow = new google.maps.InfoWindow({ content });

            marker.addListener('click', () => {
                if (activeInfoWindow) activeInfoWindow.close();
                infoWindow.open(map, marker);
                activeInfoWindow = infoWindow;
            });

            incidentMarkers.push(marker);
        });
    }

    // ── Evacuation Centers (real data from the evacuation_centers table) ──
    function renderEvacuationMarkers() {
        evacLayerMarkers.forEach(m => m.setMap(null));
        evacLayerMarkers = [];

        evacCentersData.forEach(center => {
            const lat = parseFloat(center.latitude);
            const lng = parseFloat(center.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                icon: makeMarkerIcon('🏕️', '#1a3c8f'),
                title: center.name,
            });

            const statusKey = (center.status || 'open').toLowerCase();
            const badgeClass = evacStatusClass[statusKey] || 'pop-open';
            const badgeLabel = evacStatusLabel[statusKey] || center.status || 'Open';

            const content = `
                <div class="incident-popup">
                    <div class="pop-type">🏕️ ${center.name}</div>
                    <div class="pop-row">Brgy. ${center.barangay ?? ''}</div>
                    <div class="pop-row">Occupancy: ${center.occupancy ?? 0} / ${center.capacity ?? 0}</div>
                    <span class="pop-status ${badgeClass}">${badgeLabel}</span>
                </div>`;

            const infoWindow = new google.maps.InfoWindow({ content });

            marker.addListener('click', () => {
                if (activeInfoWindow) activeInfoWindow.close();
                infoWindow.open(map, marker);
                activeInfoWindow = infoWindow;
            });

            evacLayerMarkers.push(marker);
        });
    }

    // ── Responders (still a placeholder feed — no responders table yet) ──
    function renderResponderMarkers() {
        const responders = [
            { lat: 15.8990, lng: 120.6260, name: 'Responder Unit 1', note: 'En route to Zone V' },
            { lat: 15.8870, lng: 120.6320, name: 'Responder Unit 2', note: 'On standby - Zone I' },
        ];

        responders.forEach(r => {
            const marker = new google.maps.Marker({
                position: { lat: r.lat, lng: r.lng },
                map: map,
                icon: makeMarkerIcon('🚑', '#7c3aed'),
                title: r.name,
            });
            const infoWindow = new google.maps.InfoWindow({
                content: `<div class="incident-popup"><b>🚑 ${r.name}</b><br>${r.note}</div>`
            });
            marker.addListener('click', () => {
                if (activeInfoWindow) activeInfoWindow.close();
                infoWindow.open(map, marker);
                activeInfoWindow = infoWindow;
            });
            respLayerMarkers.push(marker);
        });
    }

    // ── Toggle layers ──
    function toggleLayer(type, show) {
        let markers;
        if (type === 'evac') markers = evacLayerMarkers;
        if (type === 'resp') markers = respLayerMarkers;
        if (type === 'incidents') markers = incidentMarkers;
        if (!markers) return;
        markers.forEach(m => m.setMap(show ? map : null));
    }

    // ── Refresh map: re-fetch incidents/centers via AJAX and redraw ──
    function refreshMap() {
        const btn = document.querySelector('.btn-refresh i');
        btn.style.transition = 'transform .5s';
        btn.style.transform = 'rotate(360deg)';
        setTimeout(() => btn.style.transform = '', 600);

        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(() => {
                // Simplest reliable approach: full reload to get fresh data.
                // Swap this for a dedicated JSON endpoint later if you want it AJAX-only.
                window.location.reload();
            })
            .catch(() => window.location.reload());
    }
</script>

<!-- Google Maps JS API — loaded last, calls initMap on load -->
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initMap"></script>

</body>
</html>