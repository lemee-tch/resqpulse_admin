<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Map View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
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
            width: 200px;
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
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
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
            background: #e8edf5;
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
            z-index: 400;
            line-height: 1.9;
        }
        .map-legend .leg-item { display: flex; align-items: center; gap: 6px; }
        .leg-emoji { font-size: .9rem; width: 16px; text-align: center; }

        /* Refresh / Satellite buttons */
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
            z-index: 400;
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

        /* Leaflet popup content */
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

        /* Plain text labels (municipality + barangay names) drawn over the map */
        .map-text-label {
            font-family: 'Inter', Arial, sans-serif;
            white-space: nowrap;
            color: #1a1a1a;
            text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff;
            pointer-events: none;
        }
        .map-text-label.municipality { font-weight: 800; font-size: 16px; }
        .map-text-label.barangay { font-weight: 600; font-size: 11px; }

        .status-note { font-size: .78rem; color: #6b7280; margin-top: -8px; margin-bottom: 12px; }
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
        <a href="{{ route('sos-alerts') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}" class="active">Map View</a>
        <a href="{{ route('alerts') }}">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('responder-accounts') }}">Responder Accounts</a>
        <a href="{{ route('reports-analytics') }}">Reports &amp; Analytics</a>
        <a href="{{ route('users') }}">User</a>
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

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">{{ session('success') }}</div>
        @endif

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

            <!-- Satellite toggle -->
            <button class="btn-refresh" id="btnSatellite" style="left:14px; right:auto;" onclick="toggleSatellite()">
                <i class="bi bi-globe-americas"></i> Satellite View
            </button>

            <!-- Refresh -->
            <button class="btn-refresh" id="btnRefresh" onclick="refreshMap()">
                <i class="bi bi-arrow-clockwise"></i> Refresh Map
            </button>
        </div>

        <div class="status-note" id="boundaryStatus"></div>

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
                <div class="form-check form-check-inline m-0">
                    <input class="form-check-input" type="checkbox" id="chkBoundary" checked onchange="toggleLayer('boundary', this.checked)">
                    <label class="form-check-label" for="chkBoundary" style="font-size:.8rem;font-weight:600;">Show Rosales Boundary &amp; Barangay Labels</label>
                </div>
            </div>
        </div>

        <form id="refreshBoundaryForm" action="{{ route('mapview.refresh-boundaries') }}" method="POST" class="d-none">
            @csrf
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // ── Data passed from Laravel controller ──
    const incidentsData = @json($incidents);
    const evacCentersData = @json($evacCenters);
    const boundaryData = @json($boundaryData);

    const typeIconMap = {
        fire:     { emoji: '🔥' },
        flood:    { emoji: '🌊' },
        medical:  { emoji: '🚑' },
        accident: { emoji: '🚗' },
    };
    const defaultIcon = { emoji: '⚠️' };

    const statusColorMap = {
        pending:    '#92400e',
        responding: '#1e40af',
        resolved:   '#065f46',
    };

    function getTypeIcon(type) {
        const key = (type || '').toLowerCase().trim();
        return typeIconMap[key] || defaultIcon;
    }

    const statusLabel = { pending: 'Pending', responding: 'Responding', resolved: 'Resolved' };
    const statusClass = { pending: 'pop-pending', responding: 'pop-responding', resolved: 'pop-resolved' };

    const evacStatusLabel = { open: 'Open', full: 'Full', closed: 'Closed' };
    const evacStatusClass = { open: 'pop-open', full: 'pop-full', closed: 'pop-closed' };

    // MDRRMO HQ
    const HQ_LAT = 15.8955;
    const HQ_LNG = 120.6265;

    let map;
    let incidentLayer = L.layerGroup();
    let evacLayer = L.layerGroup();
    let respLayer = L.layerGroup();
    let boundaryLayer = L.layerGroup();
    let hqMarker = null;
    let roadLayer, satelliteLayer;

    function emojiDivIcon(emoji, color) {
        return L.divIcon({
            className: '',
            html: `
                <div style="position:relative;width:36px;height:44px;">
                    <svg width="36" height="44" viewBox="0 0 36 44" style="position:absolute;top:0;left:0;">
                        <ellipse cx="18" cy="40" rx="6" ry="3" fill="rgba(0,0,0,.2)"/>
                        <path d="M18 0 C8 0 0 8 0 18 C0 30 18 44 18 44 C18 44 36 30 36 18 C36 8 28 0 18 0Z" fill="${color}"/>
                        <text x="18" y="23" text-anchor="middle" font-size="16">${emoji}</text>
                    </svg>
                </div>`,
            iconSize: [36, 44],
            iconAnchor: [18, 44],
            popupAnchor: [0, -40],
        });
    }

    function textDivIcon(text, cssClass) {
        return L.divIcon({
            className: '',
            html: `<div class="map-text-label ${cssClass}">${text}</div>`,
            iconSize: [0, 0],
        });
    }

    function initMap() {
        map = L.map('map', { zoomControl: true }).setView([15.8952, 120.6263], 13);

        roadLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
        }).addTo(map);

        // Free satellite imagery — no API key required.
        satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri', maxZoom: 19,
        });

        incidentLayer.addTo(map);
        evacLayer.addTo(map);
        respLayer.addTo(map);
        boundaryLayer.addTo(map);

        renderBoundaryAndLabels();
        renderIncidentMarkers();
        renderEvacuationMarkers();
        renderResponderMarkers();
        renderHqMarker();
    }

    function toggleSatellite() {
        const btn = document.getElementById('btnSatellite');
        const isSatellite = map.hasLayer(satelliteLayer);

        if (isSatellite) {
            map.removeLayer(satelliteLayer);
            map.addLayer(roadLayer);
            btn.innerHTML = '<i class="bi bi-globe-americas"></i> Satellite View';
        } else {
            map.removeLayer(roadLayer);
            map.addLayer(satelliteLayer);
            btn.innerHTML = '<i class="bi bi-map"></i> Map View';
        }
    }

    // ── Rosales municipal boundary (dashed) + barangay name labels ──
    function pointsEqual(a, b) {
        return Math.abs(a[0] - b[0]) < 1e-7 && Math.abs(a[1] - b[1]) < 1e-7;
    }

    function joinSegments(segments) {
        const segs = segments.map(s => s.slice());
        const rings = [];
        while (segs.length) {
            let ring = segs.shift();
            let extended = true;
            while (extended && !pointsEqual(ring[0], ring[ring.length - 1])) {
                extended = false;
                for (let i = 0; i < segs.length; i++) {
                    const seg = segs[i];
                    if (pointsEqual(ring[ring.length - 1], seg[0])) { ring = ring.concat(seg.slice(1)); segs.splice(i, 1); extended = true; break; }
                    if (pointsEqual(ring[ring.length - 1], seg[seg.length - 1])) { ring = ring.concat(seg.slice(0, -1).reverse()); segs.splice(i, 1); extended = true; break; }
                    if (pointsEqual(ring[0], seg[seg.length - 1])) { ring = seg.slice(0, -1).concat(ring); segs.splice(i, 1); extended = true; break; }
                    if (pointsEqual(ring[0], seg[0])) { ring = seg.slice(1).reverse().concat(ring); segs.splice(i, 1); extended = true; break; }
                }
            }
            rings.push(ring);
        }
        return rings;
    }

    function ringsFromRelation(el) {
        if (!el.members) return [];
        const segs = el.members
            .filter(m => m.type === 'way' && m.geometry && (m.role === 'outer' || m.role === ''))
            .map(m => m.geometry.map(pt => [pt.lat, pt.lon]));
        if (!segs.length) return [];
        return joinSegments(segs).filter(r => r.length >= 3);
    }

    function centroidOf(ring) {
        let cx = 0, cy = 0;
        ring.forEach(([lat, lon]) => { cx += lat; cy += lon; });
        return [cx / ring.length, cy / ring.length];
    }

    function renderBoundaryAndLabels() {
        boundaryLayer.clearLayers();
        let barangayCount = 0;
        let municipalityDrawn = false;

        const totalElements = (boundaryData.elements || []).length;
        const relations = (boundaryData.elements || []).filter(el => el.type === 'relation');
        const debugParts = [`${totalElements} elements returned`, `${relations.length} relations`];

        relations.forEach(el => {
            const level = el.tags && el.tags.admin_level;
            const name = (el.tags && (el.tags.name || el.tags['name:en'])) || 'Unnamed';
            const rings = ringsFromRelation(el);

            if (level === '8') {
                debugParts.push(`level-8 "${name}": ${el.members ? el.members.length : 0} members → ${rings.length} ring(s), ${rings[0] ? rings[0].length : 0} pts`);
            }

            if (!rings.length) return;

            if (level === '8') {
                console.log('Rosales boundary rings (lat,lon):', rings);
                const bounds = L.latLngBounds(rings.flat());
                console.log('Boundary bounds:', bounds.toBBoxString());

                L.polygon(rings, {
                    color: '#ff0000', weight: 4, opacity: 1, dashArray: '10,6',
                    fillColor: '#f2ecb0', fillOpacity: 0.3,
                }).addTo(boundaryLayer);
                municipalityDrawn = true;

                const [clat, clon] = centroidOf(rings[0]);
                L.marker([clat, clon], { icon: textDivIcon(name, 'municipality'), interactive: false }).addTo(boundaryLayer);
            } else if (level === '10') {
                barangayCount++;
                const [clat, clon] = centroidOf(rings[0]);
                L.marker([clat, clon], { icon: textDivIcon(name, 'barangay'), interactive: false }).addTo(boundaryLayer);
            }
        });

        console.log('Boundary debug:', debugParts.join(' | '));

        const statusEl = document.getElementById('boundaryStatus');
        statusEl.textContent = municipalityDrawn
            ? `Rosales boundary drawn (${barangayCount} barangay labels). Debug: ${debugParts.join(' | ')}`
            : `Boundary data unavailable. Debug: ${debugParts.join(' | ')}`;
    }

    function renderIncidentMarkers() {
        incidentLayer.clearLayers();

        incidentsData.forEach(incident => {
            const lat = parseFloat(incident.latitude);
            const lng = parseFloat(incident.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const iconInfo = getTypeIcon(incident.emergency_type);
            const statusKey = (incident.status || 'pending').toLowerCase();
            const pinColor = statusColorMap[statusKey] || statusColorMap.pending;
            const badgeClass = statusClass[statusKey] || 'pop-pending';
            const badgeLabel = statusLabel[statusKey] || incident.status || 'Pending';

            const marker = L.marker([lat, lng], { icon: emojiDivIcon(iconInfo.emoji, pinColor) }).addTo(incidentLayer);

            const content = `
                <div class="incident-popup">
                    <div class="pop-type">${iconInfo.emoji} ${incident.emergency_type ?? 'Unknown'}</div>
                    <div class="pop-row">${incident.location ?? ''}</div>
                    ${incident.description ? `<div class="pop-row">${incident.description}</div>` : ''}
                    <span class="pop-status ${badgeClass}">${badgeLabel}</span>
                </div>`;
            marker.bindPopup(content);
        });
    }

    function renderEvacuationMarkers() {
        evacLayer.clearLayers();

        evacCentersData.forEach(center => {
            const lat = parseFloat(center.latitude);
            const lng = parseFloat(center.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = L.marker([lat, lng], { icon: emojiDivIcon('🏕️', '#1a3c8f') }).addTo(evacLayer);

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
            marker.bindPopup(content);
        });
    }

    // ── Responders (still a placeholder feed — no responders-location table yet) ──
    function renderResponderMarkers() {
        respLayer.clearLayers();

        const responders = [
            { lat: 15.8990, lng: 120.6260, name: 'Responder Unit 1', note: 'En route to Zone V' },
            { lat: 15.8870, lng: 120.6320, name: 'Responder Unit 2', note: 'On standby - Zone I' },
        ];

        responders.forEach(r => {
            const marker = L.marker([r.lat, r.lng], { icon: emojiDivIcon('🚑', '#7c3aed') }).addTo(respLayer);
            marker.bindPopup(`<div class="incident-popup"><b>🚑 ${r.name}</b><br>${r.note}</div>`);
        });
    }

    function renderHqMarker() {
        if (hqMarker) map.removeLayer(hqMarker);
        hqMarker = L.marker([HQ_LAT, HQ_LNG], { icon: emojiDivIcon('🏛️', '#111827') }).addTo(map);
        hqMarker.bindPopup(`<div class="incident-popup"><b>🏛️ MDRRMO HQ</b><br>Operations Center</div>`);
    }

    function toggleLayer(type, show) {
        let layer;
        if (type === 'evac') layer = evacLayer;
        if (type === 'resp') layer = respLayer;
        if (type === 'incidents') layer = incidentLayer;
        if (type === 'boundary') layer = boundaryLayer;
        if (!layer) return;
        if (show) map.addLayer(layer); else map.removeLayer(layer);
    }

    function refreshMap() {
        // Also clears the server-side boundary cache so stale/missing
        // boundary data gets a fresh Overpass fetch, not just a reload.
        document.getElementById('refreshBoundaryForm').submit();
    }

    initMap();
</script>
@include('partials.sos-alert-overlay')
</body>
</html>