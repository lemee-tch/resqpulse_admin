<!-- resources/views/audit-log.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Audit Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        .sidebar { width: 200px; min-height: 100vh; background: #1a3c8f; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; }
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

        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 28px 32px; flex: 1; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.5rem; color: #111827; margin-bottom: 4px; }
        .page-sub { font-size: .82rem; color: #6b7280; margin-bottom: 20px; }

        .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-select, .filter-date {
            appearance: none; -webkit-appearance: none;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 12px center;
            border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 34px 9px 14px;
            font-size: .82rem; color: #374151; font-family: 'Inter', sans-serif; font-weight: 500; cursor: pointer;
        }
        .filter-date { background: #fff; padding: 9px 14px; }
        .search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 280px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: .9rem; }
        .search-input { width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 14px 9px 34px; font-size: .82rem; color: #374151; font-family: 'Inter', sans-serif; }
        .btn-filter { background: #1a3c8f; color: #fff; border: none; border-radius: 8px; padding: 9px 18px; font-size: .82rem; font-weight: 700; cursor: pointer; }
        .btn-clear { background: #fff; color: #6b7280; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 18px; font-size: .82rem; font-weight: 600; cursor: pointer; }

        .table-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table thead tr { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        .log-table th { padding: 12px 18px; font-size: .78rem; font-weight: 700; color: #374151; text-align: left; white-space: nowrap; }
        .log-table tbody tr { border-bottom: 1px solid #f3f4f6; }
        .log-table tbody tr:last-child { border-bottom: none; }
        .log-table tbody tr:hover { background: #f9fafb; }
        .log-table td { padding: 13px 18px; font-size: .82rem; color: #4b5563; vertical-align: top; }

        .badge-action { font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; display: inline-block; }
        .badge-login    { background: #d1fae5; color: #065f46; }
        .badge-logout   { background: #f3f4f6; color: #6b7280; }
        .badge-created  { background: #dbeafe; color: #1e40af; }
        .badge-updated  { background: #fef3c7; color: #92400e; }
        .badge-deleted  { background: #fee2e2; color: #991b1b; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        /* Actor column — small tag next to the name distinguishing a
           resident/guest (mobile app) row from an admin (web panel) row.
           Admin rows show no tag at all, since that's the default/expected
           case and a tag on every row would just be noise. */
        .actor-name { font-weight: 600; color: #111827; }
        .badge-resident { background: #e0e7ff; color: #3730a3; font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-left: 6px; white-space: nowrap; }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }
        .ip-cell { font-size: .74rem; color: #9ca3af; }

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
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn" class="active"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
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
        <a href="{{ route('audit-log') }}" class="active"><i class="bi bi-journal-text"></i> Audit Log</a>
        <a href="{{ route('users') }}"><i class="bi bi-people-fill"></i> User</a>
    </nav>
    <div class="sidebar-logout">
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <i class="bi bi-box-arrow-right"></i> Log out
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
    </div>
</aside>

<div class="main-wrap">
    <div class="content">
        <div class="page-title">Audit Log</div>
        <div class="page-sub">Every admin action and resident activity — logins, reports, profile edits, and record changes — most recent first.</div>

        <form method="GET" action="{{ route('audit-log') }}" class="filter-bar">
            <select name="actor" class="filter-select" onchange="this.form.submit()">
                <option value="">All Actors</option>
                <option value="admin" {{ request('actor') === 'admin' ? 'selected' : '' }}>Admins</option>
                <option value="resident" {{ request('actor') === 'resident' ? 'selected' : '' }}>Residents &amp; Guests</option>
            </select>

            <select name="action" class="filter-select" onchange="this.form.submit()">
                <option value="">All Actions</option>
                @foreach(['login','logout','created','updated','deleted','approved','rejected'] as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                @endforeach
            </select>

            <select name="user_id" class="filter-select" onchange="this.form.submit()">
                <option value="">All Admins</option>
                @foreach($adminUsers as $admin)
                    <option value="{{ $admin->id }}" {{ (string) request('user_id') === (string) $admin->id ? 'selected' : '' }}>{{ $admin->name }}</option>
                @endforeach
            </select>

            <input type="date" name="date" class="filter-date" value="{{ request('date') }}" onchange="this.form.submit()">

            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="search-input" placeholder="Search description..." value="{{ request('search') }}">
            </div>

            <button type="submit" class="btn-filter">Filter</button>
            <a href="{{ route('audit-log') }}" class="btn-clear" style="text-decoration:none;display:inline-block;">Clear</a>
        </form>

        <div class="table-card">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="white-space:nowrap;">
                            {{ $log->created_at->format('M d, Y g:i A') }}
                            <div style="font-size:.7rem;color:#9ca3af;">{{ $log->created_at->diffForHumans() }}</div>
                        </td>
                        <td>
                            <span class="actor-name">{{ $log->actor_name }}</span>
                            @unless($log->is_admin_action)
                                <span class="badge-resident">{{ $log->actor_name === 'Guest' ? 'Guest' : 'Resident' }}</span>
                            @endunless
                        </td>
                        <td><span class="badge-action badge-{{ $log->action }}">{{ ucfirst($log->action) }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="ip-cell">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">No audit log entries yet.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            {{ $logs->links() }}
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