<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Incidents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 200px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .sidebar-brand img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.3); }

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
        .sidebar-nav a { display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75); text-decoration: none; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s; }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.6rem; color: #111827; margin-bottom: 20px; }

        /* ── TABS (same as Alerts & Broadcast) ── */
        .tab-bar {
            display: flex; gap: 0;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 20px;
        }
        .tab-btn {
            background: none; border: none;
            padding: 10px 20px;
            font-size: .85rem; font-weight: 600;
            color: #6b7280; cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
        }
        .tab-btn.active { color: #1a3c8f; border-bottom-color: #1a3c8f; }
        .tab-btn:hover { color: #1a3c8f; }
        .tab-btn .pill-count {
            background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 800;
            padding: 1px 7px; border-radius: 20px; margin-left: 6px;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── FILTERS ── */
        .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .filter-select, .filter-date {
            appearance: none; -webkit-appearance: none;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 12px center;
            border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 34px 9px 14px;
            font-size: .82rem; color: #374151; font-family: 'Inter', sans-serif; font-weight: 500; cursor: pointer; transition: border-color .2s;
        }
        .filter-date { background: #fff; padding: 9px 14px; cursor: text; }
        .filter-select:focus, .filter-date:focus { outline: none; border-color: #1a3c8f; }
        .search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 280px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: .9rem; }
        .search-input { width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 14px 9px 34px; font-size: .82rem; color: #374151; font-family: 'Inter', sans-serif; background: #fff; transition: border-color .2s; }
        .search-input:focus { outline: none; border-color: #1a3c8f; }
        .search-input::placeholder { color: #9ca3af; }
        .btn-clear { background: #fff; color: #6b7280; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 9px 18px; font-size: .82rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: all .2s; }
        .btn-clear:hover { background: #f3f4f6; color: #374151; }
        .results-count { font-size: .8rem; color: #6b7280; margin-bottom: 14px; }
        .results-count strong { color: #111827; }

        /* ── TABLE ── */
        .table-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .incidents-table { width: 100%; border-collapse: collapse; }
        .incidents-table thead tr { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .incidents-table th { padding: 14px 18px; font-size: .78rem; font-weight: 700; color: #374151; text-align: left; white-space: nowrap; letter-spacing: .3px; }
        .incidents-table tbody tr.incident-row { border-bottom: 1px solid #f3f4f6; transition: background .15s; cursor: pointer; }
        .incidents-table tbody tr.incident-row:last-child { border-bottom: none; }
        .incidents-table tbody tr.incident-row:hover { background: #f9fafb; }
        .incidents-table td { padding: 14px 18px; font-size: .82rem; color: #4b5563; vertical-align: middle; }
        .td-id { font-weight: 700; color: #111827; }

        /* Priority badges */
        .priority-critical { color: #dc2626; font-weight: 700; font-size: .8rem; }
        .priority-high     { color: #f97316; font-weight: 700; font-size: .8rem; }
        .priority-low      { color: #3b82f6; font-weight: 700; font-size: .8rem; }
        .priority-unset    { color: #9ca3af; font-style: italic; font-size: .8rem; }

        /* Status badges */
        .badge-pending      { background: #fef3c7; color: #92400e; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-responding   { background: #e0f2fe; color: #0369a1; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-resolved     { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-review       { background: #fef3c7; color: #92400e; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        /* Inline dropdowns */
        .inline-select {
            border: 1.5px solid #e5e7eb; border-radius: 6px; padding: 4px 8px;
            font-size: .78rem; font-family: 'Inter', sans-serif; font-weight: 600;
            background: #f9fafb; cursor: pointer; transition: border-color .2s;
        }
        .inline-select:focus { outline: none; border-color: #1a3c8f; background: #fff; }

        .no-results { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }
        .no-results i { font-size: 2rem; display: block; margin-bottom: 10px; color: #d1d5db; }

        .photo-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; cursor: pointer; }
        .thumb-broken-icon { color: #d1d5db; font-size: 1.2rem; }

        .btn-approve {
            background: #10b981; color: #fff; border: none; border-radius: 6px;
            padding: 6px 14px; font-size: .78rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s;
        }
        .btn-approve:hover { background: #059669; }
        .btn-decline {
            background: #fff; color: #dc2626; border: 1.5px solid #dc2626; border-radius: 6px;
            padding: 5px 13px; font-size: .78rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s, color .2s;
        }
        .btn-decline:hover { background: #dc2626; color: #fff; }
        .review-actions { display: flex; gap: 8px; }
        .guest-note { font-size: .74rem; color: #92400e; font-style: italic; }
        .badge-declined { background: #fee2e2; color: #991b1b; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

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
        <a href="{{ route('incident') }}" class="active">
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
        <div class="page-title">Incidents</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        @php
            // Pending Review — guest-submitted reports awaiting admin
            // approval before responders are notified. Excluded from
            // Active so admins don't mistake an unreviewed guest report
            // for something already dispatched.
            $pendingReviewIncidents = $incidents->where('needs_review', true)->values();
            // Active excludes anything still pending review AND anything
            // declined — a declined report was never dispatched, so it
            // doesn't belong alongside genuinely active ones. It still
            // shows up under Resolved History below, badged "Declined".
            $activeIncidents = $incidents
                ->where('needs_review', false)
                ->where('status', '!=', 'resolved')
                ->whereNull('declined_at')
                ->values();
            $resolvedIncidents = $incidents
                ->filter(fn ($inc) => $inc->status === 'resolved' || $inc->declined_at)
                ->values();

            // Shared row-rendering data, computed once per incident.
            $rowData = function ($inc) {
                $priorityVal   = $inc->priority ?? 'unset';
                $priorityClass = 'priority-'.$priorityVal;
                $priorityLabel = $inc->priority ? ucfirst($inc->priority) : 'Not Set';
                $isResolved    = $inc->status === 'resolved';
                $isDeclined    = (bool) $inc->declined_at;
                $statusBadge   = match(true) {
                    $isDeclined              => 'badge-declined',
                    $inc->status === 'responding' => 'badge-responding',
                    $inc->status === 'resolved'   => 'badge-resolved',
                    default                        => 'badge-pending',
                };
                $statusLabel = match(true) {
                    $isDeclined              => 'Declined',
                    $inc->status === 'responding' => 'Responding',
                    $inc->status === 'resolved'   => 'Resolved',
                    default                        => 'Pending',
                };
                $reporterName = $inc->citizen?->full_name ?? ($inc->citizen_id ? 'Unknown' : 'Guest');

                $responderLabel = $inc->responders->isNotEmpty()
                    ? $inc->responders->map(fn ($r) => $r->full_name . ' (' . $r->agency . ')')->implode(', ')
                    : null;

                $incPhotos = collect();
                if ($inc->photo_path) {
                    $decoded = json_decode($inc->photo_path, true);
                    $incPhotos = is_array($decoded) ? collect($decoded) : collect([$inc->photo_path]);
                }

                return compact('priorityVal', 'priorityClass', 'priorityLabel', 'isResolved', 'isDeclined', 'statusBadge', 'statusLabel', 'reporterName','responderLabel', 'incPhotos');
            };
        @endphp

        <!-- TABS -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('active', this)">Active Incidents ({{ $activeIncidents->count() }})</button>
            <button class="tab-btn" onclick="switchTab('review', this)">
                Pending Review ({{ $pendingReviewIncidents->count() }})
                @if($pendingReviewIncidents->count() > 0)
                    <span class="pill-count">{{ $pendingReviewIncidents->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" onclick="switchTab('history', this)">Resolved History ({{ $resolvedIncidents->count() }})</button>
        </div>

        <!-- ══ TAB: ACTIVE INCIDENTS ══ -->
        <div id="tab-active" class="tab-panel active">

            <!-- FILTER BAR -->
            <div class="filter-bar">
                <select class="filter-select" id="filterPriority">
                    <option value="">All Priorities</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                    <option value="unset">Not Set</option>
                </select>

                <select class="filter-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="responding">Responding</option>
                </select>

                <select class="filter-select" id="filterType">
                    <option value="">All Types</option>
                    <option value="Fire">Fire</option>
                    <option value="Flood">Flood</option>
                    <option value="Earthquake">Earthquake</option>
                    <option value="Accident">Accident</option>
                    <option value="Medical Emergency">Medical Emergency</option>
                    <option value="Landslide">Landslide</option>
                    <option value="Other">Other</option>
                </select>

                <select class="filter-select" id="filterBarangay">
                    <option value="">All Barangays</option>
                    @foreach ([
                        'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                        'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                        'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                        'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                        'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                        'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                        'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                        'Zone IV (Poblacion)','Zone V (Poblacion)',
                    ] as $brgy)
                        <option value="{{ strtolower($brgy) }}">{{ $brgy }}</option>
                    @endforeach
                </select>

                <input type="date" class="filter-date" id="filterDate" title="Filter by date">

                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search reports...">
                </div>

                <button class="btn-clear" id="btnClear">Clear Filters</button>
            </div>

            <div class="results-count" id="resultsCount"></div>

            <!-- TABLE -->
            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Description</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Responder</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="incidentsBody">
                        @forelse($activeIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        <tr
                            class="incident-row"
                            data-priority="{{ $priorityVal }}"
                            data-status="{{ $inc->status }}"
                            data-type="{{ $inc->emergency_type }}"
                            data-date="{{ $inc->created_at->format('Y-m-d') }}"
                            data-search="{{ strtolower($inc->emergency_type.' '.$inc->location.' '.$reporterName) }}"
                            data-href="{{ route('incident.detail', $inc->id) }}"
                        >
                            <td onclick="event.stopPropagation()">
                                <form method="POST" action="{{ route('incident.priority', $inc->id) }}">
                                    @csrf @method('PATCH')
                                    <select class="inline-select {{ $priorityClass }}"
                                            name="priority"
                                            onchange="this.form.submit()"
                                            title="Set priority">
                                        <option value="" {{ !$inc->priority ? 'selected' : '' }} disabled>Not Set</option>
                                        <option value="critical" {{ $inc->priority === 'critical' ? 'selected' : '' }}>Critical</option>
                                        <option value="high"     {{ $inc->priority === 'high'     ? 'selected' : '' }}>High</option>
                                        <option value="low"      {{ $inc->priority === 'low'      ? 'selected' : '' }}>Low</option>
                                    </select>
                                </form>
                            </td>

                            <td>{{ $inc->emergency_type }}</td>
                            <td>{{ $inc->location ?: '—' }}</td>
                            <td>{{ $reporterName }}</td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $inc->description }}">
                                {{ $inc->description }}
                            </td>

                            <td onclick="event.stopPropagation()">
                                @if($incPhotos->count())
                                    <img src="{{ Storage::url($incPhotos->first()) }}"
                                         class="photo-thumb"
                                         data-bs-toggle="modal"
                                         data-bs-target="#photoModal{{ $inc->id }}"
                                         alt="Photo"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                    <i class="bi bi-image thumb-broken-icon" style="display:none;"></i>
                                @else
                                    <span style="color:#d1d5db;font-size:.75rem;">None</span>
                                @endif
                            </td>

                            <td>
                                <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                            </td>

                            <td>
                                @if($responderLabel)
                                    <span style="font-size:.78rem;font-weight:600;color:#065f46;">{{ $responderLabel }}</span>
                                @else
                                    <span style="color:#d1d5db;font-size:.78rem;">Unassigned</span>
                                @endif
                            </td>

                            <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                        </tr>

                        @if($incPhotos->count())
                        <div class="modal fade" id="photoModal{{ $inc->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius:14px;border:none;">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                                            Incident #{{ str_pad($inc->id, 4, '0', STR_PAD_LEFT) }} — Photo
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body pt-2">
                                        <img src="{{ Storage::url($incPhotos->first()) }}"
                                             style="width:100%;border-radius:10px;border:1px solid #e5e7eb;"
                                             alt="Incident photo">
                                        @if($incPhotos->count() > 1)
                                            <p style="text-align:center;font-size:.78rem;color:#6b7280;margin-top:10px;">
                                                This incident has {{ $incPhotos->count() }} photos —
                                                <a href="{{ route('incident.detail', $inc->id) }}" style="color:#1a3c8f;font-weight:600;">view all on the incident details page</a>.
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="no-results">
                                    <i class="bi bi-inbox"></i>
                                    No active incidents right now.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ TAB: PENDING REVIEW (guest reports) ══ -->
        <div id="tab-review" class="tab-panel">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#92400e;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>These were submitted by guests (not logged in) — only their GPS location is guaranteed accurate. Responders are not notified until you approve.</span>
            </div>

            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Description</th>
                            <th>Photo</th>
                            <th>Coordinates</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingReviewIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        <tr class="incident-row" data-href="{{ route('incident.detail', $inc->id) }}">
                            <td>{{ $inc->emergency_type }}</td>
                            <td>{{ $inc->location ?: '—' }}</td>
                            <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $inc->description }}">
                                <span class="guest-note">{{ $inc->description }}</span>
                            </td>
                            <td onclick="event.stopPropagation()">
                                @if($incPhotos->count())
                                    <img src="{{ Storage::url($incPhotos->first()) }}"
                                         class="photo-thumb"
                                         data-bs-toggle="modal"
                                         data-bs-target="#reviewPhotoModal{{ $inc->id }}"
                                         alt="Photo">
                                @else
                                    <span style="color:#d1d5db;font-size:.75rem;">None</span>
                                @endif
                            </td>
                            <td>
                                @if($inc->latitude && $inc->longitude)
                                    <a href="https://www.google.com/maps?q={{ $inc->latitude }},{{ $inc->longitude }}" target="_blank" onclick="event.stopPropagation()" style="color:#1a3c8f;text-decoration:none;font-weight:600;font-size:.78rem;">
                                        {{ $inc->location ?: number_format($inc->latitude, 5) . ', ' . number_format($inc->longitude, 5) }}
                                    </a>
                                @elseif($inc->location)
                                    <span style="font-size:.78rem;">{{ $inc->location }}</span>
                                @else
                                    <span style="color:#d1d5db;font-size:.75rem;">No location</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                            <td onclick="event.stopPropagation()">
                                <div class="review-actions">
                                    <form action="{{ route('incident.approve', $inc->id) }}" method="POST" class="approve-form"
                                          data-type="{{ $inc->emergency_type }}"
                                          data-reporter="{{ $reporterName }}"
                                          data-notifies-citizen="{{ $inc->citizen_id ? '1' : '0' }}">
                                        @csrf
                                        <button type="submit" class="btn-approve" title="Approve and notify responders">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('incident.decline', $inc->id) }}" method="POST" class="decline-form"
                                          data-type="{{ $inc->emergency_type }}"
                                          data-reporter="{{ $reporterName }}"
                                          data-notifies-citizen="{{ $inc->citizen_id ? '1' : '0' }}">
                                        @csrf
                                        <input type="hidden" name="decline_reason" class="decline-reason-input">
                                        <button type="submit" class="btn-decline" title="Decline this report">
                                            <i class="bi bi-x-lg"></i> Decline
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @if($incPhotos->count())
                        <div class="modal fade" id="reviewPhotoModal{{ $inc->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius:14px;border:none;">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                                            Guest Report #{{ str_pad($inc->id, 4, '0', STR_PAD_LEFT) }} — Photo
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body pt-2">
                                        <img src="{{ Storage::url($incPhotos->first()) }}"
                                             style="width:100%;border-radius:10px;border:1px solid #e5e7eb;"
                                             alt="Guest report photo">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="no-results">
                                    <i class="bi bi-check2-circle"></i>
                                    No guest reports waiting for review.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ TAB: RESOLVED HISTORY ══ -->
        <div id="tab-history" class="tab-panel">

            <!-- FILTER BAR -->
            <div class="filter-bar">
                <select class="filter-select" id="filterPriorityH">
                    <option value="">All Priorities</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                    <option value="unset">Not Set</option>
                </select>

                <select class="filter-select" id="filterTypeH">
                    <option value="">All Types</option>
                    <option value="Fire">Fire</option>
                    <option value="Flood">Flood</option>
                    <option value="Earthquake">Earthquake</option>
                    <option value="Accident">Accident</option>
                    <option value="Medical Emergency">Medical Emergency</option>
                    <option value="Landslide">Landslide</option>
                    <option value="Other">Other</option>
                </select>

                <select class="filter-select" id="filterBarangayH">
                    <option value="">All Barangays</option>
                    @foreach ([
                        'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                        'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                        'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                        'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                        'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                        'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                        'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                        'Zone IV (Poblacion)','Zone V (Poblacion)',
                    ] as $brgy)
                        <option value="{{ strtolower($brgy) }}">{{ $brgy }}</option>
                    @endforeach
                </select>

                <input type="date" class="filter-date" id="filterDateH" title="Filter by date">

                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" id="searchInputH" placeholder="Search resolved reports...">
                </div>

                <button class="btn-clear" id="btnClearH">Clear Filters</button>
            </div>

            <div class="results-count" id="resultsCountH"></div>

            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Description</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        @forelse($resolvedIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        <tr class="incident-row history-row"
                            data-priority="{{ $priorityVal }}"
                            data-type="{{ $inc->emergency_type }}"
                            data-date="{{ $inc->created_at->format('Y-m-d') }}"
                            data-search="{{ strtolower($inc->emergency_type.' '.$inc->location.' '.$reporterName) }}"
                            data-href="{{ route('incident.detail', $inc->id) }}"
                            onclick="window.location.href='{{ route('incident.detail', $inc->id) }}'">
                            <td><span class="{{ $priorityClass }}">{{ $priorityLabel }}</span></td>
                            <td>{{ $inc->emergency_type }}</td>
                            <td>{{ $inc->location }}</td>
                            <td>{{ $reporterName }}</td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $inc->description }}">
                                {{ $inc->description }}
                            </td>

                            <td onclick="event.stopPropagation()">
                                @if($incPhotos->count())
                                    <img src="{{ Storage::url($incPhotos->first()) }}"
                                         class="photo-thumb"
                                         data-bs-toggle="modal"
                                         data-bs-target="#photoModalH{{ $inc->id }}"
                                         alt="Photo"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                    <i class="bi bi-image thumb-broken-icon" style="display:none;"></i>
                                @else
                                    <span style="color:#d1d5db;font-size:.75rem;">None</span>
                                @endif
                            </td>

                            <td><span class="{{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                        </tr>

                        @if($incPhotos->count())
                        <div class="modal fade" id="photoModalH{{ $inc->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius:14px;border:none;">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                                            Incident #{{ str_pad($inc->id, 4, '0', STR_PAD_LEFT) }} — Photo
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body pt-2">
                                        <img src="{{ Storage::url($incPhotos->first()) }}"
                                             style="width:100%;border-radius:10px;border:1px solid #e5e7eb;"
                                             alt="Incident photo">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="no-results">
                                    <i class="bi bi-check2-circle"></i>
                                    No resolved incidents yet.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="historyNoMatch" class="no-results" style="display:none;">
                    <i class="bi bi-search"></i>
                    No resolved incidents match these filters.
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab switching
    function switchTab(id, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + id).classList.add('active');
        btn.classList.add('active');
    }

    (function () {
        const priorityFilter = document.getElementById('filterPriority');
        const statusFilter   = document.getElementById('filterStatus');
        const typeFilter     = document.getElementById('filterType');
        const barangayFilter = document.getElementById('filterBarangay');
        const dateFilter     = document.getElementById('filterDate');
        const searchInput    = document.getElementById('searchInput');
        const btnClear       = document.getElementById('btnClear');
        const rows           = Array.from(document.querySelectorAll('#incidentsBody tr.incident-row'));
        const resultsCount   = document.getElementById('resultsCount');
        const totalCount     = rows.length;

        function applyFilters() {
            const priority = priorityFilter.value;
            const status   = statusFilter.value;
            const type     = typeFilter.value;
            const barangay = barangayFilter.value;
            const date     = dateFilter.value;
            const search   = searchInput.value.trim().toLowerCase();
            let visible    = 0;

            rows.forEach(row => {
                // Barangay match is a substring check against the same
                // lowercased data-search text the free-text search box
                // already uses — location is free-typed text (from a
                // citizen's address, or resolved via BarangayLocationService),
                // never a clean single field, so an exact match against
                // the fixed 37-barangay list would silently miss most
                // real rows. A row whose location mentions the selected
                // barangay anywhere counts as a match.
                const ok = (!priority || row.dataset.priority === priority)
                        && (!status   || row.dataset.status   === status)
                        && (!type     || row.dataset.type     === type)
                        && (!barangay || row.dataset.search.includes(barangay))
                        && (!date     || row.dataset.date     === date)
                        && (!search   || row.dataset.search.includes(search));
                row.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> active incidents`;
        }

        priorityFilter.addEventListener('change', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        barangayFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            [priorityFilter, statusFilter, typeFilter, barangayFilter].forEach(s => s.value = '');
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        // Landing here from the Dashboard's "Critical" card (?priority=critical)
        // pre-applies the filter on the Active Incidents tab — which is
        // already "not resolved yet" by definition (see $activeIncidents
        // above: needs_review=false AND status != resolved) — so the
        // combination is exactly "all unresolved critical incidents, one
        // page, no extra clicks."
        const urlParams = new URLSearchParams(window.location.search);
        const priorityParam = urlParams.get('priority');
        if (priorityParam && ['critical', 'high', 'low', 'unset'].includes(priorityParam)) {
            priorityFilter.value = priorityParam;
        }

        rows.forEach(row => {
            row.addEventListener('click', () => {
                const href = row.dataset.href;
                if (href) window.location.href = href;
            });
        });

        applyFilters();
    })();

    // Pending Review rows are also clickable through to the detail page
    // (Approve button stops propagation on its own).
    document.querySelectorAll('#tab-review tr.incident-row').forEach(row => {
        row.addEventListener('click', () => {
            const href = row.dataset.href;
            if (href) window.location.href = href;
        });
    });

    // Resolved History tab filters (priority / type / date / search)
    (function () {
        const priorityFilter = document.getElementById('filterPriorityH');
        if (!priorityFilter) return;

        const typeFilter    = document.getElementById('filterTypeH');
        const dateFilter     = document.getElementById('filterDateH');
        const searchInput    = document.getElementById('searchInputH');
        const btnClear       = document.getElementById('btnClearH');
        const rows           = Array.from(document.querySelectorAll('#historyBody tr.history-row'));
        const resultsCount   = document.getElementById('resultsCountH');
        const noMatch        = document.getElementById('historyNoMatch');
        const totalCount     = rows.length;

        function applyFilters() {
            const priority = priorityFilter.value;
            const type     = typeFilter.value;
            const date     = dateFilter.value;
            const search   = searchInput.value.trim().toLowerCase();
            let visible    = 0;

            rows.forEach(row => {
                const ok = (!priority || row.dataset.priority === priority)
                        && (!type     || row.dataset.type     === type)
                        && (!date     || row.dataset.date     === date)
                        && (!search   || row.dataset.search.includes(search));
                row.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> resolved incidents`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        priorityFilter.addEventListener('change', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            [priorityFilter, typeFilter].forEach(s => s.value = '');
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        rows.forEach(row => {
            row.addEventListener('click', () => {
                const href = row.dataset.href;
                if (href) window.location.href = href;
            });
        });

        applyFilters();
    })();

    // Approve confirmation — SweetAlert2 (matches the confirm pattern used
    // on the Residents Verification page). Blocks the form's normal
    // submit, asks for a confirmation naming what the click will do
    // (dispatch to responders, and notify the reporter when they're a
    // logged-in citizen), and only submits once confirmed.
    document.querySelectorAll('.approve-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const type = form.dataset.type || 'report';
            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will also get a notification that their report was approved.'
                : 'This report was submitted by a guest, so there is no account to notify.';

            Swal.fire({
                icon: 'question',
                title: 'Approve this report?',
                html: `This will dispatch the <strong>${type}</strong> report to responders.<br><span style="font-size:.85em;color:#6b7280;">${notifyLine}</span>`,
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
            }).then(result => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // Decline confirmation — asks for a short reason (required), which is
    // both logged to the audit trail and, for a logged-in citizen's
    // report, sent to them as the notification body so they know why.
    document.querySelectorAll('.decline-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const type = form.dataset.type || 'report';
            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will get a notification with this reason.'
                : 'This report was submitted by a guest, so there is no account to notify.';

            Swal.fire({
                icon: 'warning',
                title: 'Decline this report?',
                html: `<div style="text-align:left;font-size:.85em;color:#6b7280;margin-bottom:8px;">Declining the <strong>${type}</strong> report — responders will NOT be dispatched.<br>${notifyLine}</div>`,
                input: 'textarea',
                inputPlaceholder: 'Reason for declining (required)…',
                inputAttributes: { 'aria-label': 'Reason for declining' },
                showCancelButton: true,
                confirmButtonText: 'Decline report',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Please enter a reason.';
                    }
                },
            }).then(result => {
                if (result.isConfirmed) {
                    form.querySelector('.decline-reason-input').value = result.value.trim();
                    form.submit();
                }
            });
        });
    });
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