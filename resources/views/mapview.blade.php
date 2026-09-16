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

        /* Pulsing ring under "pending" pins to draw the eye without being loud */
        @keyframes pinPulse {
            0%   { transform: scale(.6); opacity: .55; }
            70%  { transform: scale(1.9); opacity: 0; }
            100% { transform: scale(1.9); opacity: 0; }
        }
        .pin-pulse {
            position: absolute;
            border-radius: 50%;
            transform-origin: center;
            animation: pinPulse 1.8s ease-out infinite;
            pointer-events: none;
        }
        .pin-wrap { transition: transform .15s ease; }
        .pin-wrap:hover { transform: translateY(-2px); }

        /* ── RESPONSIVE (mobile / tablet) ── */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 14px; left: 14px;
            z-index: 300;
            width: 42px; height: 42px;
            border-radius: 10px;
            border: none;
            background: #1a3c8f;
            color: #fff;
            font-size: 1.2rem;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.25);
            cursor: pointer;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 150;
        }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 900px) {
            .mobile-menu-btn { display: flex; }
            .sidebar {
                transform: translateX(-100%) !important;
                transition: transform .25s ease;
                z-index: 200;
                width: 230px !important;
                min-width: 230px !important;
                max-width: 230px !important;
            }
            .sidebar.open { transform: translateX(0) !important; box-shadow: 4px 0 24px rgba(0,0,0,.3); }
            .sidebar-nav a { white-space: normal !important; }
            .main-wrap {
                margin-left: 0 !important;
                padding: 20px 16px 32px !important;
                padding-top: 66px !important;
                padding-bottom: 88px !important;
            }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            img, svg, canvas, iframe { max-width: 100%; }
        }

        @media (max-width: 560px) {
            .main-wrap {
                padding: 16px 12px 28px !important;
                padding-top: 62px !important;
                padding-bottom: 88px !important;
            }
        }
    
        /* ── App-style nav polish ── */
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 2px 10px;
            border-radius: 10px;
            border-left: none !important;
        }
        .sidebar-nav a i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
        .sidebar-nav a.active { border-left: none !important; background: rgba(255,255,255,.16) !important; }

        /* ── Bottom app tab bar (mobile only) ── */
        .bottom-tab-bar {
            display: none;
            position: fixed;
            left: 0; right: 0; bottom: 0;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 14px rgba(0,0,0,.08);
            z-index: 250;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .bottom-tab-bar a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            padding: 8px 2px 7px;
            color: #8a93a6;
            text-decoration: none;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .2px;
            position: relative;
        }
        .bottom-tab-bar a i { font-size: 1.15rem; }
        .bottom-tab-bar a.active { color: #1a3c8f; }
        .bottom-tab-bar .tab-badge {
            position: absolute;
            top: 3px; right: calc(50% - 20px);
            background: #dc2626;
            color: #fff;
            font-size: .58rem;
            font-weight: 800;
            line-height: 1;
            padding: 2px 5px;
            border-radius: 20px;
        }

        @media (max-width: 900px) {
            .bottom-tab-bar { display: flex; }
        }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="bottom-tab-bar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}" class="active"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
</nav>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Logo">
        <div class="sidebar-brand-text">
            <div class="title">MDRRMO</div>
            <div class="sub">ADMIN PANEL</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="{{ route('incident') }}">
            <i class="bi bi-clipboard2-pulse"></i> Incidents
            @if(($pendingIncidentsCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingIncidentsCount }}</span>
            @endif
        </a>
        <a href="{{ route('sos-alerts') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}" class="active"><i class="bi bi-geo-alt-fill"></i> Map View</a>
        <a href="{{ route('alerts') }}"><i class="bi bi-megaphone-fill"></i> Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}"><i class="bi bi-house-heart-fill"></i> Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}"><i class="bi bi-person-check-fill"></i> Residents Verification</a>
        <a href="{{ route('responder-accounts') }}"><i class="bi bi-person-badge-fill"></i> Responder Accounts</a>
        <a href="{{ route('reports-analytics') }}"><i class="bi bi-bar-chart-fill"></i> Reports &amp; Analytics</a>
        <a href="{{ route('audit-log') }}"><i class="bi bi-journal-text"></i> Audit Log</a>
        <a href="{{ route('users') }}"><i class="bi bi-people-fill"></i> User</a>
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
                    <label class="form-check-label" for="chkResp" style="font-size:.8rem;font-weight:600;">Show Responders (Accepted Missions)</label>
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

    // Lightens a #rrggbb color by `amt` (0-255) — used to build the subtle
    // top-to-bottom gradient on each pin so it reads as a glossy droplet
    // instead of a flat cutout shape.
    function lightenColor(hex, amt) {
        let c = hex.replace('#', '');
        if (c.length === 3) c = c.split('').map(ch => ch + ch).join('');
        const num = parseInt(c, 16);
        const clamp = v => Math.min(255, Math.max(0, v));
        const r = clamp((num >> 16) + amt);
        const g = clamp(((num >> 8) & 0x00FF) + amt);
        const b = clamp((num & 0x0000FF) + amt);
        return '#' + (0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1);
    }

    let pinUid = 0;

    /**
     * A polished map pin: gradient-filled teardrop, white outline so it pops
     * against any tile color, a white badge circle behind the emoji for
     * contrast/legibility, and a soft blurred shadow instead of a flat oval.
     * Pass `pulse: true` for markers that need urgent attention (e.g.
     * pending incidents) — draws an animated ring under the pin.
     */
    function emojiDivIcon(emoji, color, opts = {}) {
        const { pulse = false, size = 40 } = opts;
        const h = Math.round(size * 1.25);
        const uid = 'pin' + (pinUid++);
        const light = lightenColor(color, 55);

        const pulseHtml = pulse
            ? `<div class="pin-pulse" style="left:${size * 0.5 - size * 0.32}px; top:${size * 0.34}px; width:${size * 0.64}px; height:${size * 0.64}px; background:${color};"></div>`
            : '';

        return L.divIcon({
            className: '',
            html: `
                <div class="pin-wrap" style="position:relative;width:${size}px;height:${h}px;">
                    ${pulseHtml}
                    <svg width="${size}" height="${h}" viewBox="0 0 40 50" style="position:absolute;top:0;left:0;overflow:visible;">
                        <defs>
                            <linearGradient id="${uid}" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="${light}"/>
                                <stop offset="100%" stop-color="${color}"/>
                            </linearGradient>
                            <filter id="${uid}-shadow" x="-60%" y="-20%" width="220%" height="180%">
                                <feDropShadow dx="0" dy="2" stdDeviation="1.6" flood-color="#000" flood-opacity=".35"/>
                            </filter>
                        </defs>
                        <ellipse cx="20" cy="45.5" rx="7" ry="2.2" fill="rgba(0,0,0,.22)"/>
                        <path d="M20 2 C10.6 2 3 9.6 3 19 C3 30.5 20 47.5 20 47.5 C20 47.5 37 30.5 37 19 C37 9.6 29.4 2 20 2Z"
                              fill="url(#${uid})" stroke="#ffffff" stroke-width="2" filter="url(#${uid}-shadow)"/>
                        <circle cx="20" cy="19" r="12.5" fill="#ffffff" opacity=".95"/>
                        <circle cx="20" cy="19" r="12.5" fill="none" stroke="${color}" stroke-width="1"/>
                        <text x="20" y="24.5" text-anchor="middle" font-size="15">${emoji}</text>
                    </svg>
                </div>`,
            iconSize: [size, h],
            iconAnchor: [size / 2, h - size * 0.06],
            popupAnchor: [0, -h + 6],
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
        let firstBoundaryBounds = null;

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
                const totalPts = rings.reduce((sum, r) => sum + r.length, 0);

                // A real municipal boundary has hundreds of vertices. If what
                // we got is this sparse, the fetch almost certainly matched
                // the wrong place (or got cut off) — don't draw a bogus
                // shape, just say so.
                if (totalPts < 100) {
                    debugParts.push(`level-8 "${name}" REJECTED: only ${totalPts} pts total (looks wrong) — try Refresh Map`);
                    return;
                }

                console.log('Rosales boundary rings (lat,lon):', rings);
                const bounds = L.latLngBounds(rings.flat());
                console.log('Boundary bounds:', bounds.toBBoxString());

                // Dim everything outside Rosales: one big rectangle covering the
                // whole world with the Rosales ring(s) cut out as holes, using
                // Leaflet's even-odd fill so only the outside gets shaded.
                const worldRing = [[-85, -180], [85, -180], [85, 180], [-85, 180]];
                L.polygon([worldRing, ...rings], {
                    stroke: false,
                    fillColor: '#0b1f4d',
                    fillOpacity: 0.45,
                    interactive: false,
                }).addTo(boundaryLayer);

                // Crisp solid outline right on the municipal boundary — this is
                // the "highlight" line, no fill inside so Rosales itself stays
                // at normal map brightness.
                L.polygon(rings, {
                    color: '#ffcc00',
                    weight: 3,
                    opacity: 1,
                    fill: false,
                    interactive: false,
                }).addTo(boundaryLayer);

                municipalityDrawn = true;
                firstBoundaryBounds = firstBoundaryBounds || bounds;

                const [clat, clon] = centroidOf(rings[0]);
                L.marker([clat, clon], { icon: textDivIcon(name, 'municipality'), interactive: false }).addTo(boundaryLayer);
            } else if (level === '10') {
                barangayCount++;
                const [clat, clon] = centroidOf(rings[0]);
                L.marker([clat, clon], { icon: textDivIcon(name, 'barangay'), interactive: false }).addTo(boundaryLayer);
            }
        });

        console.log('Boundary debug:', debugParts.join(' | '));

        // Snap the view to Rosales the first time the boundary loads, so the
        // highlighted area is framed nicely instead of relying on the fixed
        // center/zoom guess.
        if (firstBoundaryBounds) {
            map.fitBounds(firstBoundaryBounds, { padding: [24, 24] });
        }

        const statusEl = document.getElementById('boundaryStatus');
        statusEl.textContent = municipalityDrawn
            ? `Rosales boundary drawn (${barangayCount} barangay labels). Debug: ${debugParts.join(' | ')}`
            : `Boundary data unavailable or looks wrong — click "Refresh Map" to re-fetch. Debug: ${debugParts.join(' | ')}`;
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

            const marker = L.marker([lat, lng], {
                icon: emojiDivIcon(iconInfo.emoji, pinColor, { pulse: statusKey === 'pending' }),
            }).addTo(incidentLayer);

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

    const agencyIcon = { PNP: '🚓', BFP: '🚒', SARS: '🛟', HCU: '🚑', MSWD: '🤝' };

    function responderName(r) {
        return r.full_name || [r.first_name, r.last_name].filter(Boolean).join(' ') || 'Responder';
    }

    function formatAcceptedAt(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    // ── Responders — plotted at the incident they accepted. There's no live
    // GPS column for responders (see MapViewController), so the incident's
    // own coordinates are the only truthful location we have: "accepted
    // and heading to / on scene at" that address. Resolved incidents drop
    // off (their responders are done), and multiple backup responders on
    // the same incident fan out slightly so they don't stack unreadably.
    function renderResponderMarkers() {
        respLayer.clearLayers();

        incidentsData.forEach(incident => {
            if (incident.status === 'resolved') return;

            const responders = incident.responders || [];
            if (!responders.length) return;

            const lat = parseFloat(incident.latitude);
            const lng = parseFloat(incident.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const n = responders.length;
            responders.forEach((r, i) => {
                const angle = (Math.PI / 4) + (i * (2 * Math.PI / n));
                const radius = 0.00035; // ~35-40m fan-out per backup responder
                const rLat = lat + Math.sin(angle) * radius;
                const rLng = lng + Math.cos(angle) * radius;

                const emoji = agencyIcon[r.agency] || '🚑';
                const marker = L.marker([rLat, rLng], {
                    icon: emojiDivIcon(emoji, '#7c3aed', { size: 34 }),
                }).addTo(respLayer);

                const acceptedAt = formatAcceptedAt(r.pivot && r.pivot.accepted_at);
                const content = `
                    <div class="incident-popup">
                        <div class="pop-type">${emoji} ${responderName(r)}</div>
                        <div class="pop-row">${r.agency ?? ''}${r.unit_station ? ' · ' + r.unit_station : ''}</div>
                        <div class="pop-row">Responding to: ${incident.emergency_type ?? 'Incident'}${incident.location ? ' — ' + incident.location : ''}</div>
                        ${acceptedAt ? `<div class="pop-row">Accepted: ${acceptedAt}</div>` : ''}
                    </div>`;
                marker.bindPopup(content);
            });
        });
    }

    function renderHqMarker() {
        if (hqMarker) map.removeLayer(hqMarker);
        hqMarker = L.marker([HQ_LAT, HQ_LNG], { icon: emojiDivIcon('🏛️', '#111827', { size: 46 }) }).addTo(map);
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
<script>
(function () {
    var btn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar || !overlay) return;

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }

    btn.addEventListener('click', function () {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', closeSidebar);
    });
})();
</script>
</body>
</html>