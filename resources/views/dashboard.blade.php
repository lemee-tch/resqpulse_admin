<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            font-weight: 800;
            font-size: .85rem;
            color: #fff;
            letter-spacing: .5px;
            line-height: 1.1;
        }

        .sidebar-brand-text .sub {
            font-size: .65rem;
            color: rgba(255,255,255,.6);
            letter-spacing: .5px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 18px 0;
        }

        .sidebar-nav a {
            display: block;
            padding: 10px 20px;
            font-size: .82rem;
            font-weight: 500;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all .2s;
        }

        .sidebar-nav a:hover {
            color: #fff;
            background: rgba(255,255,255,.08);
        }

        .sidebar-nav a.active {
            color: #fff;
            font-weight: 700;
            border-left-color: #fff;
            background: rgba(255,255,255,.1);
        }

        .sidebar-logout {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.12);
        }

        .sidebar-logout a {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-weight: 500;
            transition: color .2s;
        }

        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap {
            margin-left: 200px  ;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: #fff;
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            position: sticky; top: 0;
            z-index: 50;
        }

        .topbar-title {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: #111827;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-bell {
            font-size: 1.2rem;
            color: #6b7280;
            cursor: pointer;
            position: relative;
        }
        .notif-badge {
            position: absolute; top: -6px; right: -8px;
            background: #dc2626; color: #fff;
            font-size: .62rem; font-weight: 800;
            min-width: 16px; height: 16px; border-radius: 20px;
            display: none; align-items: center; justify-content: center;
            padding: 0 4px; line-height: 1;
        }
        .notif-dropdown {
            display: none;
            position: absolute; top: 32px; right: -10px;
            width: 320px; max-height: 380px; overflow-y: auto;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,.14);
            z-index: 200; cursor: default;
        }
        .notif-dropdown-header {
            font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .85rem; color: #111827;
            padding: 12px 16px; border-bottom: 1px solid #f3f4f6;
        }
        .notif-item {
            display: block; padding: 10px 16px; text-decoration: none;
            border-bottom: 1px solid #f9fafb; transition: background .15s;
        }
        .notif-item:hover { background: #f8faff; }
        .notif-item-type { font-size: .82rem; font-weight: 700; color: #111827; }
        .notif-item-loc { font-size: .76rem; color: #6b7280; margin-top: 1px; }
        .notif-item-time { font-size: .7rem; color: #9ca3af; margin-top: 3px; }
        .notif-empty { padding: 20px 16px; text-align: center; color: #9ca3af; font-size: .82rem; }
        .notif-view-all {
            display: block; text-align: center; padding: 10px;
            font-size: .8rem; font-weight: 700; color: #1a3c8f; text-decoration: none;
        }
        .notif-view-all:hover { background: #f8faff; }

        /* ── Sound-enable pill ──
             Browsers block audio.play() with sound until the page has
             had a real click/keydown — see the script below. Rather
             than silently hoping the admin clicks *something* before
             the first alert arrives (which is what caused reports of
             "it didn't make a sound"), this pill is a visible,
             one-click way to unlock it immediately on page load. It
             disappears the moment sound is unlocked (by clicking it,
             or by clicking anywhere else on the page). */
        .sound-enable-btn {
            display: none;
            align-items: center;
            gap: 6px;
            background: #fef3c7;
            color: #92400e;
            border: 1.5px solid #fde68a;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: .74rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            white-space: nowrap;
            animation: soundPillPulse 2s ease-in-out infinite;
        }
        .sound-enable-btn:hover { background: #fde68a; }
        @keyframes soundPillPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(217,119,6,.25); }
            50% { box-shadow: 0 0 0 5px rgba(217,119,6,0); }
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .85rem;
            font-weight: 600;
            color: #374151;
        }

        .topbar-user i { font-size: 1.4rem; color: #6b7280; }

        /* ── CONTENT ── */
        .content {
            padding: 24px 28px;
            flex: 1;
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid #e5e7eb;
            transition: box-shadow .2s, border-color .2s;
        }

        a.stat-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        a.stat-card-link:hover .stat-card {
            box-shadow: 0 4px 14px rgba(26,60,143,.12);
            border-color: #1a3c8f;
        }

        .stat-label {
            font-size: .75rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: #111827;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-badge {
            font-size: .72rem;
            font-weight: 600;
        }

        /* ── PANEL CARDS ── */
        .panel-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 18px 20px;
            height: 100%;
        }

        .panel-title {
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #111827;
            margin-bottom: 14px;
        }

        .panel-link {
            font-size: .78rem;
            color: #1a3c8f;
            font-weight: 600;
            text-decoration: none;
        }
        .panel-link:hover { text-decoration: underline; color: #1a3c8f; }

        /* ── INCIDENT TABLE ── */
        .incident-table {
            width: 100%;
            font-size: .78rem;
        }

        .incident-table th {
            font-weight: 600;
            color: #374151;
            padding: 6px 10px;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap;
        }

        .incident-table td {
            padding: 8px 10px;
            color: #4b5563;
            border-bottom: 1px solid #f9fafb;
            vertical-align: middle;
        }

        .badge-priority {
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .priority-critical { color: #dc2626; }
        .priority-high     { color: #f97316; }
        .priority-moderate { color: #10b981; }
        .priority-low      { color: #3b82f6; }

        .status-pending  { color: #f97316; font-weight: 600; font-size: .75rem; }
        .status-onway    { color: #3b82f6; font-weight: 600; font-size: .75rem; }
        .status-resolved { color: #10b981; font-weight: 600; font-size: .75rem; }

        /* ── MAP placeholder ── */
        .map-placeholder {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            overflow: hidden;
            background: #e8edf5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: .8rem;
            position: relative;
        }

        .map-placeholder iframe,
        .map-placeholder #dashboardMap {
            width: 100%; height: 100%;
            border: none;
            pointer-events: none; /* clicks fall through to the wrapping link */
        }

        .map-link-card {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .map-link-card .map-placeholder {
            transition: box-shadow .2s;
        }

        .map-link-card:hover .map-placeholder {
            box-shadow: 0 0 0 2px #1a3c8f inset;
        }

        .map-link-card:hover .map-overlay-hint {
            opacity: 1;
        }

        .map-overlay-hint {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(26,60,143,.55);
            color: #fff;
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: .9rem;
            gap: 8px;
            opacity: 0;
            transition: opacity .2s;
            pointer-events: none;
        }

        /* chart containers */
        .chart-wrap { position: relative; }

        .chart-link-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: box-shadow .2s, border-color .2s;
            border-radius: 12px;
        }
        .chart-link-card:hover {
            box-shadow: 0 4px 14px rgba(26,60,143,.12);
        }
        .chart-link-card:hover .panel-card {
            border-color: #1a3c8f;
        }

        .stat-grid-5 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        @media (min-width: 768px) {
            .stat-grid-5 { grid-template-columns: repeat(5, 1fr); }
        }

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

<!-- ════════════ SIDEBAR ════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="bottom-tab-bar">
    <a href="{{ route('dashboard') }}" class="active"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
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
        <a href="{{ route('dashboard') }}" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
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
        <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i> Map View</a>
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

<!-- ════════════ MAIN ════════════ -->
<div class="main-wrap">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-title">Dashboard Overview</div>
        <div class="topbar-right">
            <button type="button" class="sound-enable-btn" id="soundEnableBtn" title="Click to enable alert sounds for this session">
                <i class="bi bi-volume-mute-fill"></i> Enable alert sound
            </button>
            <div class="topbar-bell" id="notifBell">
                <i class="bi bi-bell"></i>
                <span class="notif-badge" id="notifBadge"></span>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">Recent Reports</div>
                    <div id="notifList"></div>
                    <a href="{{ route('incidents.overview') }}" class="notif-view-all">View all incidents</a>
                </div>
            </div>
            <audio id="notifSound" src="{{ asset('sounds/notification.mp3') }}" preload="auto"></audio>
            <div class="topbar-user">

                <a href="{{ route('audit-log') }}" style="text-decoration:none;color:inherit;">
                    <i class="bi bi-person-circle"></i>
                     {{ auth()->user()->name }}
                </a>
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <div class="stat-grid-5">
            <a href="{{ route('incidents.overview') }}" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-label">Total Incidents</div>
                    <div class="stat-value">{{ $totalIncidents }}</div>
                    <div class="stat-badge text-success">+{{ $incidentsToday }} today</div>
                </div>
            </a>

            <a href="{{ route('critical') }}" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-label text-danger">Critical</div>
                    <div class="stat-value" style="color:#dc2626;">{{ $criticalIncidents }}</div>
                    <div class="stat-badge text-danger">High Priority</div>
                </div>
            </a>

            <a href="{{ route('sos-alerts') }}" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-label text-danger">SOS Alerts</div>
                    <div class="stat-value" style="color:#dc2626;">{{ $pendingSosCount ?? 0 }}</div>
                    <div class="stat-badge text-danger">
                        {{ ($pendingSosCount ?? 0) > 0 ? 'Pending' : 'All Clear' }}
                    </div>
                </div>
            </a>

            <a href="{{ route('incidents.overview', ['status' => 'responding']) }}" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-label">Responding</div>
                    <div class="stat-value">{{ $respondingIncidents }}</div>
                    <div class="stat-badge text-primary">Active</div>
                </div>
            </a>

            <a href="{{ route('incidents.overview', ['status' => 'resolved']) }}" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value">{{ $resolvedIncidents }}</div>
                    <div class="stat-badge text-success">Completed</div>
                </div>
            </a>


        </div>

        <!-- ── MAP + RECENT INCIDENTS ── -->
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-5">
                <a href="{{ route('mapview') }}" class="map-link-card">
                    <div class="panel-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                            <div class="panel-title" style="margin-bottom:0;">Incident Map</div>
                            <span class="panel-link">Open Map View →</span>
                        </div>
                        <div class="map-placeholder">
                            <div id="dashboardMap"></div>
                            <div class="map-overlay-hint">
                                <i class="bi bi-map"></i> View Full Map
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-7">
                <div class="panel-card">
                    <div class="panel-title">Recent Incidents</div>
                    <table class="incident-table">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Incident</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentIncidents as $inc)
                            @php
                                $priorityClass = $inc->priority ? 'priority-'.$inc->priority : '';
                                $priorityLabel = $inc->priority ? ucfirst($inc->priority) : 'Not Set';
                                $statusClass = match($inc->status) {
                                    'responding' => 'status-onway',
                                    'resolved' => 'status-resolved',
                                    default => 'status-pending',
                                };
                                $statusLabel = match($inc->status) {
                                    'responding' => 'On the way',
                                    'resolved' => 'Resolved',
                                    default => 'Pending',
                                };
                            @endphp
                            <tr>
                                <td><span class="{{ $priorityClass }}">{{ $priorityLabel }}</span></td>
                                <td>{{ $inc->emergency_type }}</td>
                                <td>{{ $inc->location }}</td>
                                <td><span class="{{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>{{ $inc->created_at->format('g:i A') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:#9ca3af;padding:16px;">No incidents reported yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div style="text-align:right; margin-top:10px;">
                        <a href="{{ route('incident') }}" class="panel-link">View all incidents →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── CHARTS ROW (linked to Reports & Analytics) ── -->
        <div class="row g-3">
            <div class="col-12 col-md-5">
                <a>
                    <div class="panel-card">
                        <div class="panel-title">Reports by Type</div>
                        <div class="d-flex align-items-center gap-4">
                            <div style="width:140px;height:140px;flex-shrink:0;">
                                <canvas id="donutChart"></canvas>
                            </div>
                            <div style="font-size:.75rem;line-height:2;">
                                @php
                                    $donutColors = ['#3b82f6','#ef4444','#6b7280','#d1d5db','#f59e0b','#10b981','#8b5cf6','#ec4899'];
                                @endphp
                                @forelse($reportsByType as $type => $count)
                                    <div><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $donutColors[$loop->index % count($donutColors)] }};margin-right:6px;"></span>{{ $type }} <span style="color:#9ca3af;margin-left:4px;">{{ $count }}</span></div>
                                @empty
                                    <div style="color:#9ca3af;">No incidents reported yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-7">
                <a>
                    <div class="panel-card">
                        <div class="panel-title">Incidents over time</div>
                        <div style="height:140px;">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <div class="card panel" style="margin-top:18px;">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px; padding-left:5px;">
        <div class="panel-title" style="font-weight:700;font-size:15.5px;">Recent Alerts</div>
        <a href="{{ route('alerts') }}" style="font-size:13px;color:#1a3c8f;font-weight:600;text-decoration:none;">View all →</a>
    </div>
    @forelse($recentAlerts as $alert)
        <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;">
            <div style="width:36px;height:36px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-megaphone" style="color:#dc2626;font-size:15px;"></i>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:13.5px;color:#111827;">{{ $alert->title }}</div>
                @if($alert->subtitle)
                    <div style="font-size:12px;color:#6b7280;">{{ $alert->subtitle }}</div>
                @endif
                <div style="font-size:11.5px;color:#9ca3af;margin-top:2px;">{{ $alert->created_at->diffForHumans() }}</div>
            </div>
        </div>
    @empty
        <div style="text-align:center;padding:24px;color:#9ca3af;font-size:13.5px;">No recent alerts.</div>
    @endforelse
</div>

    </div><!-- /content -->
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Donut chart
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($reportsByType->keys()) !!},
        datasets: [{
            data: {!! json_encode($reportsByType->values()) !!},
            backgroundColor: {!! json_encode(array_slice(['#3b82f6','#ef4444','#6b7280','#d1d5db','#f59e0b','#10b981','#8b5cf6','#ec4899'], 0, max($reportsByType->count(), 1))) !!},
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        cutout: '70%',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
        }
    },
    plugins: [{
        id: 'centerText',
        beforeDraw(chart) {
            const { ctx, chartArea: { width, height, left, top } } = chart;
            ctx.save();
            ctx.font = 'bold 22px Barlow, sans-serif';
            ctx.fillStyle = '#111827';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('{{ $reportsByType->sum() }}', left + width / 2, top + height / 2);
            ctx.restore();
        }
    }]
});

// Line chart — real data, last 7 days
const trendLabels = @json($trendWeek->keys());
const trendData = @json($trendWeek->values());

new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            data: trendData,
            borderColor: '#1a3c8f',
            borderWidth: 2,
            pointBackgroundColor: '#1a3c8f',
            pointRadius: 4,
            fill: false,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 10 } },
                grid: { color: '#f0f0f0' }
            }
        }
    }
});

// Dashboard preview map — non-interactive, click-through to full Map View
(function () {
    const incidents = @json($mapIncidents);
    const rosalesCenter = [15.8952, 120.6263];

    const map = L.map('dashboardMap', {
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        touchZoom: false,
        attributionControl: false,
    }).setView(rosalesCenter, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map);

    const statusColor = { pending: '#92400e', responding: '#1e40af', acknowledged: '#1e40af' };
    const typeEmoji = {
        Fire: '🔥', Flood: '🌊', Earthquake: '🏚️', Accident: '🚗',
        'Medical Emergency': '🚑', Landslide: '⛰️', 'SOS Emergency': '🆘',
    };

    function pinIcon(emoji, color) {
        return L.divIcon({
            className: '',
            html: `<div style="width:26px;height:26px;border-radius:50%;background:${color};
                        border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);
                        display:flex;align-items:center;justify-content:center;font-size:13px;">${emoji}</div>`,
            iconSize: [26, 26],
            iconAnchor: [13, 13],
        });
    }

    const points = [];
    incidents.forEach(inc => {
        const lat = parseFloat(inc.latitude);
        const lng = parseFloat(inc.longitude);
        if (isNaN(lat) || isNaN(lng)) return;
        points.push([lat, lng]);

        const emoji = typeEmoji[inc.emergency_type] || '⚠️';
        const color = statusColor[inc.status] || '#92400e';
        L.marker([lat, lng], { icon: pinIcon(emoji, color) }).addTo(map);
    });

    if (points.length) {
        map.fitBounds(points, { padding: [24, 24], maxZoom: 15 });
    }
})();
</script>

{{-- ══════ Topbar notification bell — polling + sound ══════
     The bell icon, badge, dropdown markup and <audio id="notifSound">
     already existed in the topbar above, and the backend endpoint
     (IncidentController::latestIncidentNotifications, route
     'notifications.incidents') already worked — but nothing ever
     called it. This is what was missing: poll it, fill the dropdown,
     and play the sound when a genuinely NEW (non-SOS) report shows up.
     SOS reports are excluded server-side already (they have their own
     siren via sos-alert-overlay), so this never double-announces.

     Guest reports and outside-Rosales reports are NOT excluded here —
     latestIncidentNotifications() only filters out SOS Emergency, so
     they trigger the badge/list/sound exactly like any other report
     (needs_review only withholds the RESPONDER push, never this admin
     notification). --}}
<script>
(function () {
    const bell = document.getElementById('notifBell');
    const badge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const list = document.getElementById('notifList');
    const sound = document.getElementById('notifSound');
    const soundBtn = document.getElementById('soundEnableBtn');
    if (!bell || !badge || !dropdown || !list || !sound) return;

    const POLL_MS = 15000;
    const LAST_SEEN_KEY = 'resqpulse_last_seen_incident_at';
    // Starts from whatever was persisted last visit, so a page refresh
    // doesn't re-announce reports already seen. Empty on a first-ever
    // visit — the first poll below is treated as a baseline, not a
    // burst of "new" reports, so it never dings for the whole history.
    let lastSeenAt = localStorage.getItem(LAST_SEEN_KEY) || null;

    // ── Audio unlock ──────────────────────────────────────────────
    // Browsers refuse audio.play() unless the page has had a real user
    // gesture (click/keydown/tap) — poll() runs on a setInterval, so
    // with no unlock the FIRST alert after a fresh page load never
    // makes a sound, no matter what triggered it (guest report,
    // outside-Rosales, or an ordinary one). This was the actual cause
    // behind "it didn't make a sound": the badge/list still updated
    // (that part doesn't need audio permission), but sound.play()'s
    // promise was silently rejected because nothing had been clicked
    // on the page yet since it loaded.
    //
    // Fix: a visible "Enable alert sound" pill in the topbar gives the
    // admin an obvious, one-click way to grant that permission the
    // moment the dashboard opens, instead of hoping their first click
    // anywhere happens to land before the first real alert. Clicking
    // ANYTHING on the page still unlocks it too (see the document
    // listeners below) — the pill just makes it discoverable and hides
    // itself once sound is actually unlocked.
    let soundUnlocked = false;

    function showSoundPill() {
        if (soundBtn && !soundUnlocked) soundBtn.style.display = 'flex';
    }

    function hideSoundPill() {
        if (soundBtn) soundBtn.style.display = 'none';
    }

    function unlockNotifSound() {
        if (soundUnlocked) return;
        sound.muted = true;
        sound.play().then(function () {
            sound.pause();
            sound.currentTime = 0;
            sound.muted = false;
            soundUnlocked = true;
            hideSoundPill();
        }).catch(function () {
            // Still blocked (e.g. this "click" happened before the
            // browser considered it a real gesture) — leave the pill
            // showing so the admin can try again.
        });
    }

    document.addEventListener('click', unlockNotifSound);
    document.addEventListener('keydown', unlockNotifSound, { once: true });
    document.addEventListener('touchstart', unlockNotifSound, { once: true, passive: true });
    if (soundBtn) {
        soundBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            unlockNotifSound();
        });
    }

    // Show the pill once the page has settled, unless sound is somehow
    // already unlocked (e.g. a very fast click during page load beat
    // this to it).
    showSoundPill();

    function timeAgo(isoString) {
        if (!isoString) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - new Date(isoString).getTime()) / 1000));
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' min ago';
        const hours = Math.floor(minutes / 60);
        return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(recent) {
        if (!recent || !recent.length) {
            list.innerHTML = '<div class="notif-empty">No recent reports.</div>';
            return;
        }
        list.innerHTML = recent.map(function (inc) {
            return '<a href="{{ url('/incidents') }}/' + inc.id + '" class="notif-item">' +
                '<div class="notif-item-type">' + escapeHtml(inc.emergency_type) + '</div>' +
                '<div class="notif-item-loc">' + escapeHtml(inc.location || 'Location unavailable') + '</div>' +
                '<div class="notif-item-time">' + timeAgo(inc.created_at) + '</div>' +
                '</a>';
        }).join('');
    }

    async function poll() {
        try {
            const url = "{{ route('notifications.incidents') }}" + (lastSeenAt ? ('?since=' + encodeURIComponent(lastSeenAt)) : '');
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();

            render(data.recent);

            if (lastSeenAt && data.newCount > 0) {
                badge.textContent = data.newCount > 9 ? '9+' : data.newCount;
                badge.style.display = 'flex';
                sound.currentTime = 0;
                sound.play().catch(() => {
                    // Still not unlocked — make sure the pill is visible
                    // so the admin notices and can fix it for the next
                    // alert, instead of this failing silently forever.
                    showSoundPill();
                });
            }

            lastSeenAt = data.serverTime;
            localStorage.setItem(LAST_SEEN_KEY, lastSeenAt);
        } catch (e) { /* silent — next poll tries again */ }
    }

    bell.addEventListener('click', function (e) {
        e.stopPropagation();
        const opening = dropdown.style.display !== 'block';
        dropdown.style.display = opening ? 'block' : 'none';
        if (opening) {
            badge.style.display = 'none';
        }
    });
    document.addEventListener('click', function () {
        dropdown.style.display = 'none';
    });
    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });

    poll();
    setInterval(poll, POLL_MS);
})();
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