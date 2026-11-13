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

        .sidebar { width: 250px; min-height: 100vh; background: #1a3c8f; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; }
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

        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content { padding: 28px 32px; flex: 1; min-width: 0; }
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
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-declined { background: #ffedd5; color: #9a3412; }
        .badge-resolved { background: #ccfbf1; color: #115e59; }
        /* A resident MDRRMO blocked or unblocked (item 54). */
        .badge-blocked   { background: #1f2937; color: #fff; }
        .badge-unblocked { background: #e0e7ff; color: #3730a3; }
        /* Backup for a responding team (item 55): asked, sent or not sent. */
        .badge-backup    { background: #ffedd5; color: #c2410c; }

        /* Who did it — one tag per group. */
        .actor-name { font-weight: 600; color: #111827; }
        .who-tag { display: inline-block; margin-top: 3px; font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; white-space: nowrap; letter-spacing: .2px; }
        .who-admin     { background: #dbeafe; color: #1e40af; }
        .who-resident  { background: #e0e7ff; color: #3730a3; }
        .who-guest     { background: #ffedd5; color: #c2410c; }
        .who-responder { background: #ccfbf1; color: #115e59; }
        .who-system    { background: #f3f4f6; color: #6b7280; }

        /* A dropdown with something picked stands out. */
        .filter-select.is-set { border-color: #1a3c8f; color: #1a3c8f; font-weight: 600; }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }

        /* Page links: Bootstrap 5 (AppServiceProvider), in the panel's blue (item 60). */
        .log-pages .pagination {
            --bs-pagination-color: #1a3c8f;
            --bs-pagination-hover-color: #152f72;
            --bs-pagination-focus-color: #152f72;
            --bs-pagination-focus-box-shadow: 0 0 0 .2rem rgba(26,60,143,.2);
            --bs-pagination-active-bg: #1a3c8f;
            --bs-pagination-active-border-color: #1a3c8f;
            --bs-pagination-border-color: #e5e7eb;
            --bs-pagination-font-size: .8rem;
            --bs-pagination-padding-x: .7rem;
            --bs-pagination-padding-y: .38rem;
            margin: 0;
        }
        .log-pages .page-link { font-weight: 600; }
        .log-pages p { margin: 0; font-size: .8rem; color: #6b7280; }
        .ip-cell { font-size: .74rem; color: #9ca3af; }

    </style>
</head>
<body>

@include('partials.sidebar')

<div class="main-wrap">
    <div class="content">
        <div class="page-title">Audit Log</div>
        <div class="page-sub">Everything done in ResQPulse, newest first: admin actions in this panel, residents and guests in the residents app, and responders in the responder app.</div>

        <form method="GET" action="{{ route('audit-log') }}" class="filter-bar">
            <select name="action" class="filter-select {{ request('action') ? 'is-set' : '' }}" onchange="this.form.submit()" aria-label="Action">
                <option value="">All Actions</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                @endforeach
            </select>

            {{-- Whose activity: everyone, one group, or one admin. The counts
                 follow the other filters. --}}
            <select name="who" class="filter-select {{ $whoValue !== '' ? 'is-set' : '' }}" onchange="this.form.submit()" aria-label="Whose activity">
                @foreach(['' => ['Everyone', $counts['all']], 'admin' => ['Admin', $counts['admin']], 'resident' => ['Residents & Guests', $counts['resident']], 'responder' => ['Responders', $counts['responder']]] as $value => [$label, $count])
                    <option value="{{ $value }}" {{ $whoValue === (string) $value ? 'selected' : '' }}>{{ $label }} ({{ number_format($count) }})</option>
                @endforeach
                @if($adminUsers->isNotEmpty())
                    <optgroup label="Admins">
                        @foreach($adminUsers as $admin)
                            <option value="admin-{{ $admin->id }}" {{ $whoValue === 'admin-' . $admin->id ? 'selected' : '' }}>{{ $admin->name }} ({{ number_format($adminCounts[$admin->id] ?? 0) }})</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>

            <input type="date" name="date" class="filter-date" value="{{ request('date') }}" onchange="this.form.submit()">

            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="search-input" placeholder="Search name or description..." value="{{ request('search') }}">
            </div>

            <button type="submit" class="btn-filter">Filter</button>
            <a href="{{ route('audit-log') }}" class="btn-clear" style="text-decoration:none;display:inline-block;">Clear</a>
        </form>

        <div class="table-card">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
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
                            @php
                                $whoType = $log->actor_type;
                                $whoLabel = ['admin' => 'Admin', 'resident' => 'Resident', 'guest' => 'Guest', 'responder' => 'Responder', 'system' => 'System'][$whoType] ?? 'System';
                            @endphp
                            <span class="actor-name">{{ $log->actor_name }}</span><br>
                            <span class="who-tag who-{{ $whoType }}">{{ $whoLabel }}</span>
                        </td>
                        <td><span class="badge-action badge-{{ $log->action }}">{{ ucfirst($log->action) }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="ip-cell">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                {{ request()->anyFilled(['action', 'who', 'user_id', 'date', 'search']) ? 'No entries match these filters.' : 'Nothing logged here yet.' }}
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="log-pages" style="margin-top:16px;">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.sos-alert-overlay')
</body>
</html>