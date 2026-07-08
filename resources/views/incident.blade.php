<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Incidents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 170px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .sidebar-brand img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.3); }
        .sidebar-brand-text .title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .85rem; color: #fff; letter-spacing: .5px; line-height: 1.1; }
        .sidebar-brand-text .sub { font-size: .65rem; color: rgba(255,255,255,.6); }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a { display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75); text-decoration: none; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s; }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 170px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.6rem; color: #111827; margin-bottom: 24px; }

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
        .priority-moderate { color: #10b981; font-weight: 700; font-size: .8rem; }
        .priority-low      { color: #3b82f6; font-weight: 700; font-size: .8rem; }
        .priority-unset    { color: #9ca3af; font-style: italic; font-size: .8rem; }

        /* Status badges */
        .badge-pending      { background: #fef3c7; color: #92400e; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-responding   { background: #e0f2fe; color: #0369a1; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-resolved     { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

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
        <a href="{{ route('incident') }}" class="active">Incidents</a>
        <a href="{{ route('mapview') }}">Map View</a>
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
        <div class="page-title">Incidents</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <select class="filter-select" id="filterPriority">
                <option value="">All Priorities</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="moderate">Moderate</option>
                <option value="low">Low</option>
                <option value="unset">Not Set</option>
            </select>

            <select class="filter-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="responding">Responding</option>
                <option value="resolved">Resolved</option>
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
                        <th>ID</th>
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
                <tbody id="incidentsBody">
                    @forelse($incidents as $inc)
                    @php
                        $priorityVal   = $inc->priority ?? 'unset';
                        $priorityClass = $inc->priority ? 'priority-'.$inc->priority : 'priority-unset';
                        $priorityLabel = $inc->priority ? ucfirst($inc->priority) : 'Not Set';
                        $statusBadge   = match($inc->status) {
                            'responding'   => 'badge-responding',
                            'resolved'     => 'badge-resolved',
                            default        => 'badge-pending',
                        };
                        $statusLabel = match($inc->status) {
                            'responding'   => 'Responding',
                            'resolved'     => 'Resolved',
                            default        => 'Pending',
                        };
                        $reporterName = $inc->citizen?->full_name ?? 'Unknown';

                        // `photo_path` stores a JSON-encoded array of paths (e.g. '["a.jpg","b.jpg"]').
                        // Some very old rows may still have a single plain path string instead — handle both.
                        $incPhotos = collect();
                        if ($inc->photo_path) {
                            $decoded = json_decode($inc->photo_path, true);
                            $incPhotos = is_array($decoded) ? collect($decoded) : collect([$inc->photo_path]);
                        }
                    @endphp
                    <tr
                        class="incident-row"
                        data-priority="{{ $priorityVal }}"
                        data-status="{{ $inc->status }}"
                        data-type="{{ $inc->emergency_type }}"
                        data-date="{{ $inc->created_at->format('Y-m-d') }}"
                        data-search="{{ strtolower($inc->emergency_type.' '.$inc->location.' '.$reporterName) }}"
                        data-href="{{ route('incident.detail', $inc->id) }}"
                    >
                        <td class="td-id">#{{ str_pad($inc->id, 4, '0', STR_PAD_LEFT) }}</td>

                        {{-- Inline priority dropdown (click shouldn't navigate) --}}
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
                        <td>{{ $inc->location }}</td>
                        <td>{{ $reporterName }}</td>
                        <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $inc->description }}">
                            {{ $inc->description }}
                        </td>

                        {{-- Photo thumbnail (single preview — click shouldn't navigate; full set is on the details page) --}}
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

                        {{-- Status (read-only here — status can only be changed on the incident details page) --}}
                        <td>
                            <span class="badge-{{ $inc->status }}">{{ ucfirst($inc->status) }}</span>
                        </td>

                        <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                    </tr>

                    {{-- Photo preview modal (single photo — see the incident details page for all photos) --}}
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
                                No incidents reported yet.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const priorityFilter = document.getElementById('filterPriority');
    const statusFilter   = document.getElementById('filterStatus');
    const typeFilter     = document.getElementById('filterType');
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
        const date     = dateFilter.value;
        const search   = searchInput.value.trim().toLowerCase();
        let visible    = 0;

        rows.forEach(row => {
            const ok = (!priority || row.dataset.priority === priority)
                    && (!status   || row.dataset.status   === status)
                    && (!type     || row.dataset.type     === type)
                    && (!date     || row.dataset.date     === date)
                    && (!search   || row.dataset.search.includes(search));
            row.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> incidents`;
    }

    priorityFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);
    dateFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    btnClear.addEventListener('click', () => {
        [priorityFilter, statusFilter, typeFilter].forEach(s => s.value = '');
        dateFilter.value = searchInput.value = '';
        applyFilters();
    });

    // Click anywhere on a row (outside the interactive cells) to open the detail page
    rows.forEach(row => {
        row.addEventListener('click', () => {
            const href = row.dataset.href;
            if (href) window.location.href = href;
        });
    });

    applyFilters();
})();
</script>
</body>
</html>