<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – {{ $status ? ucfirst($status) : 'All Incidents' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 200px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12); }
        .sidebar-brand img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.3); }
        .sidebar-brand-text .title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .85rem; color: #fff; letter-spacing: .5px; line-height: 1.1; }
        .sidebar-brand-text .sub { font-size: .65rem; color: rgba(255,255,255,.6); letter-spacing: .5px; }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a { display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75); text-decoration: none; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s; }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.6rem; color: #111827; display: flex; align-items: center; gap: 10px; }
        .page-sub { font-size: .84rem; color: #6b7280; margin-bottom: 22px; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #0d2e7a; }

        .status-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        .status-tab {
            font-size: .8rem; font-weight: 700; padding: 7px 16px; border-radius: 20px;
            text-decoration: none; color: #4b5563; background: #fff; border: 1px solid #e5e7eb;
            transition: all .15s;
        }
        .status-tab:hover { border-color: #1a3c8f; color: #1a3c8f; }
        .status-tab.active { background: #1a3c8f; color: #fff; border-color: #1a3c8f; }

        .source-badge {
            font-size: .68rem; font-weight: 800; padding: 3px 10px; border-radius: 20px; letter-spacing: .3px;
        }
        .source-sos      { background: #fee2e2; color: #b91c1c; }
        .source-incident { background: #eef2ff; color: #3730a3; }

        /* ── TABLE (same visual language as the Incidents / Critical pages) ── */
        .table-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .overview-table { width: 100%; border-collapse: collapse; }
        .overview-table thead tr { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .overview-table th { padding: 14px 18px; font-size: .78rem; font-weight: 700; color: #374151; text-align: left; white-space: nowrap; letter-spacing: .3px; }
        .overview-table tbody tr.overview-row { border-bottom: 1px solid #f3f4f6; transition: background .15s; cursor: pointer; }
        .overview-table tbody tr.overview-row:last-child { border-bottom: none; }
        .overview-table tbody tr.overview-row:hover { background: #f8faff; }
        .overview-table td { padding: 14px 18px; font-size: .82rem; color: #4b5563; vertical-align: middle; }

        .badge-pending    { background: #fef3c7; color: #92400e; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-responding { background: #e0f2fe; color: #0369a1; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-review     { background: #fde68a; color: #78350f; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-resolved   { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 2.2rem; color: #9ca3af; margin-bottom: 10px; display: block; }

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
    <a href="{{ route('incident') }}" class="active"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
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

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        @php
            $titles = [
                null         => 'All Incidents',
                'pending'    => 'Pending',
                'responding' => 'Responding',
                'resolved'   => 'Resolved',
            ];
            $pageTitle = $titles[$status] ?? ucfirst($status);
        @endphp

        <div class="page-header">
            <div class="page-title"><i class="bi bi-clipboard2-pulse" style="color:#1a3c8f;"></i> {{ $pageTitle }}</div>
            <a href="{{ route('dashboard') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
        <div class="page-sub">
            Regular incidents and SOS alerts together — SOS reports never show on the regular Incidents page, so this is the only place a status count here actually matches what you see below.
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <div style="margin-bottom:16px;">
            <form action="{{ route('incidents.recheck-locations') }}" method="POST"
                  onsubmit="return confirm('Re-check locations for every still-pending incident/SOS? This corrects any mislabeled locations and flags anything actually outside Rosales for review. Already-resolved or already-responding incidents are left alone — this only affects ones still pending.');">
                @csrf
                <button type="submit" style="background:#fff;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 16px;font-size:.82rem;font-weight:700;color:#374151;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                    <i class="bi bi-arrow-repeat"></i> Recheck Locations
                </button>
            </form>
        </div>

        <div class="status-tabs">
            <a href="{{ route('incidents.overview') }}" class="status-tab {{ $status === null ? 'active' : '' }}">All</a>
            <a href="{{ route('incidents.overview', ['status' => 'pending']) }}" class="status-tab {{ $status === 'pending' ? 'active' : '' }}">Pending</a>
            <a href="{{ route('incidents.overview', ['status' => 'responding']) }}" class="status-tab {{ $status === 'responding' ? 'active' : '' }}">Responding</a>
            <a href="{{ route('incidents.overview', ['status' => 'resolved']) }}" class="status-tab {{ $status === 'resolved' ? 'active' : '' }}">Resolved</a>
        </div>

        <div class="table-card">
            @if($incidents->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    Nothing here right now.
                </div>
            @else
                <table class="overview-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Status</th>
                            <th>Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($incidents as $inc)
                            @php
                                $isSos = $inc->emergency_type === 'SOS Emergency';
                                $reporterName = $inc->citizen?->full_name ?? ($inc->citizen_id ? 'Unknown' : 'Guest');
                                $detailUrl = $isSos
                                    ? route('sos.detail', $inc->id)
                                    : route('incident.detail', $inc->id);

                                $statusBadge = match(true) {
                                    $inc->needs_review            => 'badge-review',
                                    $inc->status === 'responding' => 'badge-responding',
                                    $inc->status === 'resolved'   => 'badge-resolved',
                                    default                        => 'badge-pending',
                                };
                                $statusLabel = $inc->needs_review
                                    ? 'Pending Review'
                                    : ucfirst($inc->status);
                            @endphp
                            <tr class="overview-row" onclick="window.location.href='{{ $detailUrl }}'">
                                <td>
                                    <span class="source-badge {{ $isSos ? 'source-sos' : 'source-incident' }}">
                                        {{ $isSos ? 'SOS' : 'INCIDENT' }}
                                    </span>
                                </td>
                                <td>{{ $inc->emergency_type }}</td>
                                <td>{{ $inc->location ?: '—' }}</td>
                                <td>{{ $reporterName }}</td>
                                <td><span class="{{ $statusBadge }}">{{ $statusLabel }}</span></td>
                                <td>{{ $inc->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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