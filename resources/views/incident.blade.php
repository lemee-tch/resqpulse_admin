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
            width: 250px; min-height: 100vh; background: #1a3c8f;
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
        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
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
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        /* Phones: the tabs scroll sideways instead of pushing the page wider. */
        .tab-bar { overflow-x: auto; scrollbar-width: none; }
        .tab-bar::-webkit-scrollbar { display: none; }
        .tab-btn { white-space: nowrap; flex-shrink: 0; }
        .main-wrap, .content { min-width: 0; }
        @media (max-width: 560px) { .tab-btn { padding: 10px 12px; } }

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
        .badge-forreview    { background: #ede9fe; color: #5b21b6; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        .badge-pending      { white-space: nowrap; }

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
        .review-actions { align-items: center; white-space: nowrap; }
        .td-location { max-width: 360px; }
        .td-action { width: 1%; text-align: right; white-space: nowrap; }
        .btn-view {
            display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
            background: #fff; color: #1a3c8f; border: 1.5px solid #c7d2fe; border-radius: 6px;
            padding: 5px 12px; font-size: .78rem; font-weight: 700; text-decoration: none;
            transition: background .2s, color .2s, border-color .2s;
        }
        .btn-view:hover { background: #1a3c8f; border-color: #1a3c8f; color: #fff; }
        /* Resolved History: Delete (declined reports only) next to View. */
        .row-actions { display: inline-flex; align-items: center; gap: 8px; }
        .row-actions form { margin: 0; }
        .btn-delete {
            display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
            background: #fff; color: #b91c1c; border: 1.5px solid #fecaca; border-radius: 6px;
            padding: 5px 11px; font-size: .78rem; font-weight: 700; cursor: pointer;
            transition: background .2s, color .2s, border-color .2s;
        }
        .btn-delete:hover { background: #dc2626; border-color: #dc2626; color: #fff; }
        .map-link { margin-left: 6px; font-size: .74rem; font-weight: 600; color: #1a3c8f; text-decoration: none; white-space: nowrap; }
        .map-link:hover { text-decoration: underline; }
        .guest-note { font-size: .74rem; color: #92400e; font-style: italic; }
        .badge-declined { background: #fee2e2; color: #991b1b; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        /* Decline-reason modal — same dropdown pattern as the Residents
           Verification rejection modal. */
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; display: block; }
        .form-control-m {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-control-m:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .btn-decline-confirm {
            background: #dc2626; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-decline-confirm:hover { background: #b91c1c; }

        /* Priority choice in the Approve dialog — the admin picks it. */
        .prio-choices { display: flex; gap: 8px; margin: 14px 0 12px; }
        .prio-input { position: absolute; opacity: 0; pointer-events: none; }
        .prio-choice {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
            margin: 0; padding: 11px 6px; border-radius: 10px; border: 2px solid currentColor;
            background: #fff; cursor: pointer; user-select: none;
            font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .95rem;
            transition: background .15s, color .15s;
        }
        .prio-choice i { display: none; }
        .prio-choice.prio-critical { color: #dc2626; }
        .prio-choice.prio-high     { color: #f97316; }
        .prio-choice.prio-low      { color: #3b82f6; }
        .prio-choice.prio-critical:hover { background: #fef2f2; }
        .prio-choice.prio-high:hover     { background: #fff7ed; }
        .prio-choice.prio-low:hover      { background: #eff6ff; }
        .prio-input:checked + .prio-choice { color: #fff; }
        .prio-input:checked + .prio-choice i { display: inline; }
        .prio-input:checked + .prio-choice.prio-critical { background: #dc2626; border-color: #dc2626; }
        .prio-input:checked + .prio-choice.prio-high     { background: #f97316; border-color: #f97316; }
        .prio-input:checked + .prio-choice.prio-low      { background: #3b82f6; border-color: #3b82f6; }
        .prio-input:focus-visible + .prio-choice { outline: 3px solid #1a3c8f; outline-offset: 2px; }
        .approve-intro { font-size: .9rem; color: #374151; }
        .approve-note  { font-size: .8rem; color: #6b7280; }

        /* Note at the top of the Pending and For Review tabs. */
        .tab-note {
            display: flex; gap: 10px; align-items: flex-start;
            border-radius: 10px; padding: 12px 16px; font-size: .82rem; line-height: 1.5;
            margin-bottom: 16px;
        }
        .tab-note i { margin-top: 1px; }
        .tab-note--pending { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .tab-note--review  { background: #f5f3ff; border: 1px solid #ddd6fe; color: #5b21b6; }

        /* For Review actions: the report PDF, Return. */
        .btn-pdf {
            display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
            background: #fff; color: #b91c1c; border: 1.5px solid #fecaca; border-radius: 6px;
            padding: 5px 11px; font-size: .78rem; font-weight: 700; text-decoration: none;
            transition: background .2s, color .2s, border-color .2s;
        }
        .btn-pdf:hover { background: #fef2f2; border-color: #f87171; color: #991b1b; }
        .btn-return {
            background: #fff; color: #b45309; border: 1.5px solid #f59e0b; border-radius: 6px;
            padding: 5px 13px; font-size: .78rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
            transition: background .2s, color .2s;
        }
        .btn-return:hover { background: #f59e0b; color: #fff; }
        .review-actions form { margin: 0; }

        /* Resolved tab: add an incident handled outside the app. */
        .btn-add-report {
            margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
            background: #1a3c8f; color: #fff; border: none; border-radius: 8px;
            padding: 9px 16px; font-size: .82rem; font-weight: 700; text-decoration: none;
            white-space: nowrap; transition: background .2s, box-shadow .2s;
        }
        .btn-add-report:hover { background: #0d2e7a; color: #fff; box-shadow: 0 4px 12px rgba(26,60,143,.25); }

        /* Small tags next to the type. */
        .tag-manual, .tag-resent, .tag-returned {
            display: inline-flex; align-items: center; gap: 3px; margin-left: 6px;
            padding: 1px 8px; border-radius: 20px; font-size: .66rem; font-weight: 700;
            vertical-align: middle; white-space: nowrap;
        }
        .tag-manual   { background: #e0e7ff; color: #3730a3; }
        .tag-resent   { background: #f5f3ff; color: #6d28d9; }
        .tag-returned { background: #fff7ed; color: #c2410c; }

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
@include('partials.sidebar')

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">
        <div class="page-title">Incidents</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
            </div>
        @endif

        @php
            // One list per tab — see Incident::getStageAttribute():
            //   Pending     every new report waits here until an admin
            //               approves it (only then are responders notified,
            //               Admin\IncidentController::approve()) or declines it
            //   Active      approved and sent to the responders: waiting for
            //               one to accept, or being responded to
            //   For Review  the responders sent their Final report; the admin
            //               marks it resolved or sends it back
            //   Resolved    resolved, declined (badged "Declined"), and
            //               incidents handled outside the app that MDRRMO
            //               added with "Add Resolved Report"
            $pendingIncidents   = $incidents->filter(fn ($inc) => $inc->stage === 'pending')->values();
            $activeIncidents    = $incidents->filter(fn ($inc) => $inc->stage === 'active')->values();
            $forReviewIncidents = $incidents->filter(fn ($inc) => $inc->stage === 'for_review')->values();
            $resolvedIncidents  = $incidents->filter(fn ($inc) => $inc->stage === 'resolved')->values();

            $barangayList = [
                'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                'Zone IV (Poblacion)','Zone V (Poblacion)',
            ];

            // Shared row-rendering data, computed once per incident.
            $rowData = function ($inc) {
                $priorityVal   = $inc->priority ?? 'unset';
                $priorityClass = 'priority-'.$priorityVal;
                $priorityLabel = $inc->priority ? ucfirst($inc->priority) : 'Not Set';
                $isDeclined    = (bool) $inc->declined_at;
                $isManual      = $inc->isManualRecord();
                $statusLabel   = $inc->status_label;
                $statusBadge   = match ($statusLabel) {
                    'Pending Review' => 'badge-review',
                    'Declined'       => 'badge-declined',
                    'For Review'     => 'badge-forreview',
                    'Resolved'       => 'badge-resolved',
                    'Responding'     => 'badge-responding',
                    default          => 'badge-pending',
                };
                // Not shown in the tables (it's on the Incident Details page),
                // but still searchable from the search box.
                $reporterName = $inc->reporter_name;

                return compact('priorityVal', 'priorityClass', 'priorityLabel', 'isDeclined', 'isManual', 'statusBadge', 'statusLabel', 'reporterName');
            };

            // "Juan Dela Cruz (BFP)" / "Juan Dela Cruz (BFP) +2"
            $teamSummary = function ($inc) {
                $team = $inc->responders->sortBy(fn ($r) => $r->pivot->accepted_at)->values();
                if ($team->isEmpty()) {
                    return '—';
                }
                $lead = $team->first();
                $name = trim($lead->full_name . ($lead->agency ? " ({$lead->agency})" : ''));

                return $team->count() > 1 ? $name . ' +' . ($team->count() - 1) : $name;
            };
        @endphp

        <!-- TABS — Pending → Active → For Review → Resolved, the order an
             incident moves through them. -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending', this)">Pending ({{ $pendingIncidents->count() }})</button>
            <button class="tab-btn" data-tab="active" onclick="switchTab('active', this)">Active ({{ $activeIncidents->count() }})</button>
            <button class="tab-btn" data-tab="for-review" onclick="switchTab('for-review', this)">For Review ({{ $forReviewIncidents->count() }})</button>
            <button class="tab-btn" data-tab="resolved" onclick="switchTab('resolved', this)">Resolved ({{ $resolvedIncidents->count() }})</button>
        </div>

        <!-- ══ TAB: PENDING (every new report, waiting for review before dispatch) ══ -->
        <div id="tab-pending" class="tab-panel active">
            <div class="tab-note tab-note--pending">
                <i class="bi bi-info-circle"></i>
                <span>Every new report waits here for your review. When you approve it, you choose its priority (Critical, High or Low) and the responders are notified. Open <strong>View</strong> to check the photos, description and reporter first.</span>
            </div>

            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        <tr class="incident-row" data-href="{{ route('incident.detail', $inc->id) }}">
                            <td>{{ $inc->emergency_type }}</td>
                            <td class="td-location">
                                {{ $inc->location ?: '—' }}
                                @if($inc->latitude && $inc->longitude)
                                    <a href="https://www.google.com/maps?q={{ $inc->latitude }},{{ $inc->longitude }}" target="_blank"
                                       class="map-link" title="Open the pin in Google Maps" onclick="event.stopPropagation()">
                                        <i class="bi bi-geo-alt-fill"></i> Map
                                    </a>
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
                                        {{-- Filled in by the Approve dialog (Critical / High / Low). --}}
                                        <input type="hidden" name="priority" value="{{ $inc->priority }}">
                                        <button type="submit" class="btn-approve" title="Set priority and notify responders">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn-decline" title="Decline this report"
                                            data-bs-toggle="modal" data-bs-target="#declineModal{{ $inc->id }}">
                                        <i class="bi bi-x-lg"></i> Decline
                                    </button>
                                    <a href="{{ route('incident.detail', $inc->id) }}" class="btn-view" onclick="event.stopPropagation()">View <i class="bi bi-chevron-right"></i></a>
                                </div>
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="4">
                                <div class="no-results">
                                    <i class="bi bi-check2-circle"></i>
                                    No reports waiting for review.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Decline dialogs. Kept outside the table: a <form> inside a
                 table row ends up empty in the browser (its fields are
                 left outside it), so the reason could never be read. --}}
            @foreach($pendingIncidents as $inc)
                <div class="modal fade" id="declineModal{{ $inc->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-radius:14px;border:none;">
                            <form action="{{ route('incident.decline', $inc->id) }}" method="POST" class="decline-form"
                                  data-type="{{ $inc->emergency_type }}"
                                  data-notifies-citizen="{{ $inc->citizen_id ? '1' : '0' }}">
                                @csrf
                                <div class="modal-header border-0 pb-0">
                                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Decline this report?</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">
                                        Declining the <strong>{{ $inc->emergency_type }}</strong> report — responders will NOT be dispatched.
                                        {{ $inc->citizen_id ? 'The reporter will get a notification with this reason.' : 'This report was submitted by a guest, so there is no account to notify.' }}
                                    </p>
                                    <label class="form-label-m">Reason for declining</label>
                                    <select name="decline_reason" class="form-control-m" required>
                                        <option value="" disabled selected>Select a reason...</option>
                                        <option value="Duplicate of another report already received">Duplicate of another report already received</option>
                                        <option value="Spam, prank, or test submission">Spam, prank, or test submission</option>
                                        <option value="Insufficient details to dispatch responders">Insufficient details to dispatch responders</option>
                                        <option value="Location is outside Rosales / MDRRMO service area">Location is outside Rosales / MDRRMO service area</option>
                                        <option value="Unable to verify — no credible evidence of an emergency">Unable to verify — no credible evidence of an emergency</option>
                                        <option value="Reported by mistake (accidental submission)">Reported by mistake (accidental submission)</option>
                                    </select>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="submit" class="btn-decline-confirm">Decline Report</button>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- ══ TAB: ACTIVE (approved — for the responders to accept and respond) ══ -->
        <div id="tab-active" class="tab-panel">

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
                    <option value="pending">Awaiting Responder</option>
                    <option value="responding">Responding</option>
                </select>

                <select class="filter-select" id="filterType">
                    <option value="">All Types</option>
                    @foreach(\App\Models\Incident::TYPES as $typeOption)
                        <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                    @endforeach
                </select>

                <select class="filter-select" id="filterBarangay">
                    <option value="">All Barangays</option>
                    @foreach ($barangayList as $brgy)
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
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="incidentsBody">
                        @forelse($activeIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        {{-- Photos, reporter, description and responders are on the
                             Incident Details page (the row and View open it). --}}
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
                            <td>
                                {{ $inc->emergency_type }}
                                @if($inc->report_returned_at && $inc->report_return_reason)
                                    <span class="tag-returned" title="{{ $inc->report_return_reason }}"><i class="bi bi-arrow-return-left"></i> Report sent back</span>
                                @endif
                            </td>
                            <td class="td-location">{{ $inc->location ?: '—' }}</td>
                            <td><span class="{{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                            <td class="td-action"><a href="{{ route('incident.detail', $inc->id) }}" class="btn-view" onclick="event.stopPropagation()">View <i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
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

        <!-- ══ TAB: FOR REVIEW (the responders' Final report, waiting for the admin) ══ -->
        <div id="tab-for-review" class="tab-panel">
            <div class="tab-note tab-note--review">
                <i class="bi bi-clipboard2-check"></i>
                <span>The responding team sent their <strong>Final report</strong> for these incidents. Open the report and check it, then <strong>Mark as Resolved</strong> — or <strong>Return</strong> it to the responders with what needs fixing. A returned report goes back to Active until they send it again.</span>
            </div>

            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Responding Team</th>
                            <th>Report Sent</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forReviewIncidents as $inc)
                        @php
                            extract($rowData($inc));
                            $sentAt = $inc->report_submitted_at ?? $inc->updated_at;
                        @endphp
                        <tr class="incident-row" data-href="{{ route('incident.detail', $inc->id) }}">
                            <td><span class="{{ $priorityClass }}">{{ $priorityLabel }}</span></td>
                            <td>
                                {{ $inc->emergency_type }}
                                @if($inc->report_returned_at)
                                    <span class="tag-resent" title="Sent again after you returned it"><i class="bi bi-arrow-repeat"></i> Sent again</span>
                                @endif
                            </td>
                            <td class="td-location">{{ $inc->location ?: '—' }}</td>
                            <td>{{ $teamSummary($inc) }}</td>
                            <td style="white-space:nowrap;">{{ $sentAt->format('M d, Y g:i A') }}</td>
                            <td onclick="event.stopPropagation()">
                                <div class="review-actions">
                                    @if($inc->report_data)
                                        <a href="{{ route('incident.report-pdf', $inc) }}" target="_blank" class="btn-pdf" title="Open the Final report (PDF)">
                                            <i class="bi bi-file-earmark-pdf"></i> Report
                                        </a>
                                    @endif
                                    <form action="{{ route('incident.confirm-resolved', $inc->id) }}" method="POST" class="resolve-form"
                                          data-type="{{ $inc->emergency_type }}"
                                          data-notifies-citizen="{{ $inc->citizen_id ? '1' : '0' }}">
                                        @csrf
                                        <button type="submit" class="btn-approve" title="Accept the report and mark the incident resolved">
                                            <i class="bi bi-check2-circle"></i> Mark as Resolved
                                        </button>
                                    </form>
                                    <button type="button" class="btn-return" title="Send the report back to the responders"
                                            data-bs-toggle="modal" data-bs-target="#returnModal{{ $inc->id }}">
                                        <i class="bi bi-arrow-return-left"></i> Return
                                    </button>
                                    <a href="{{ route('incident.detail', $inc->id) }}" class="btn-view">View <i class="bi bi-chevron-right"></i></a>
                                </div>
                            </td>
                        </tr>


                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="no-results">
                                    <i class="bi bi-clipboard2-check"></i>
                                    No reports waiting for your review.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Return dialogs — outside the table, like the Decline dialogs above. --}}
            @foreach($forReviewIncidents as $inc)
                @include('partials.return-report-modal', ['incident' => $inc, 'modalId' => 'returnModal' . $inc->id])
            @endforeach
        </div>

        <!-- ══ TAB: RESOLVED (plus declined, and incidents added by MDRRMO) ══ -->
        <div id="tab-resolved" class="tab-panel">

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
                    @foreach(\App\Models\Incident::TYPES as $typeOption)
                        <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                    @endforeach
                </select>

                <select class="filter-select" id="filterBarangayH">
                    <option value="">All Barangays</option>
                    @foreach ($barangayList as $brgy)
                        <option value="{{ strtolower($brgy) }}">{{ $brgy }}</option>
                    @endforeach
                </select>

                <select class="filter-select" id="filterResultH">
                    <option value="">All Results</option>
                    <option value="resolved">Resolved</option>
                    <option value="manual">Added by MDRRMO</option>
                    <option value="declined">Declined</option>
                </select>

                <input type="date" class="filter-date" id="filterDateH" title="Filter by date">

                <div class="search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="search-input" id="searchInputH" placeholder="Search resolved reports...">
                </div>

                <button class="btn-clear" id="btnClearH">Clear Filters</button>

                {{-- An incident MDRRMO handled outside the app (walk-in, phone call, radio…). --}}
                <a href="{{ route('incident.manual.create') }}" class="btn-add-report" title="Add an incident that was handled outside the app">
                    <i class="bi bi-plus-lg"></i> Add Resolved Report
                </a>
            </div>

            <div class="results-count" id="resultsCountH"></div>

            <div class="table-card">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        @forelse($resolvedIncidents as $inc)
                        @php extract($rowData($inc)); @endphp
                        <tr class="incident-row history-row"
                            data-priority="{{ $priorityVal }}"
                            data-type="{{ $inc->emergency_type }}"
                            data-result="{{ $isDeclined ? 'declined' : ($isManual ? 'manual' : 'resolved') }}"
                            data-date="{{ $inc->created_at->format('Y-m-d') }}"
                            data-search="{{ strtolower($inc->emergency_type.' '.$inc->location.' '.$reporterName) }}"
                            data-href="{{ route('incident.detail', $inc->id) }}">
                            <td><span class="{{ $priorityClass }}">{{ $priorityLabel }}</span></td>
                            <td>
                                {{ $inc->emergency_type }}
                                @if($isManual)
                                    <span class="tag-manual" title="Handled outside the app — added by MDRRMO{{ $inc->reported_via ? ' (' . strtolower($inc->reported_via) . ')' : '' }}"><i class="bi bi-pencil-square"></i> Added by MDRRMO</span>
                                @endif
                            </td>
                            <td class="td-location">{{ $inc->location ?: '—' }}</td>
                            <td><span class="{{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td style="white-space:nowrap;">{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                            <td class="td-action" onclick="event.stopPropagation()">
                                <div class="row-actions">
                                    @if($isDeclined)
                                        {{-- Declined reports only (IncidentController::destroy()). --}}
                                        <form method="POST" action="{{ route('incident.destroy', $inc->id) }}" class="delete-form"
                                              data-what="{{ $inc->emergency_type }} report from {{ $reporterName }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-delete" title="Delete this declined report">
                                                <i class="bi bi-trash3"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('incident.detail', $inc->id) }}" class="btn-view">View <i class="bi bi-chevron-right"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
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

    // Opens a tab by name. The tabs were called "review" (now Pending) and
    // "history" (now Resolved) before, so old links still land right.
    function openTab(name) {
        const renamed = { review: 'pending', history: 'resolved', forreview: 'for-review', for_review: 'for-review' };
        const tab = renamed[name] || name;
        const btn = tab && document.querySelector('.tab-btn[data-tab="' + tab + '"]');
        if (btn) switchTab(tab, btn);
        return !!btn;
    }

    // Active tab filters (priority / status / type / barangay / date / search)
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
        // pre-applies the filter on the Active tab — which is already "not
        // resolved yet" by definition — so the combination is exactly "all
        // unresolved critical incidents, one page, no extra clicks."
        const urlParams = new URLSearchParams(window.location.search);
        const priorityParam = urlParams.get('priority');
        if (priorityParam && ['critical', 'high', 'low', 'unset'].includes(priorityParam)) {
            priorityFilter.value = priorityParam;
            // Pending is the first tab, so open Active where this filter lives.
            openTab('active');
        }

        applyFilters();
    })();

    // Pending is the first tab, so after changing something on another tab
    // (a priority on Active, Mark as Resolved on For Review…) the reload
    // would jump back to it. Remember the tab the change came from and
    // reopen it once. (The priority dropdown sends its form with
    // form.submit(), which doesn't fire "submit", so listen for "change"
    // too.)
    (function () {
        const KEY = 'resqpulse_incidents_return_tab';
        const remember = form => {
            const panel = form.closest('.tab-panel');
            if (!panel) return;
            try { sessionStorage.setItem(KEY, panel.id.replace('tab-', '')); } catch (e) {}
        };
        document.querySelectorAll('.tab-panel form:not(.delete-form)').forEach(form => {
            form.addEventListener('submit', () => remember(form));
            form.querySelectorAll('select').forEach(s => s.addEventListener('change', () => remember(form)));
        });

        let tab = null;
        try { tab = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}
        if (tab) openTab(tab);
    })();

    // Every row opens the Incident Details page (the buttons in a row stop
    // the click on their own).
    document.querySelectorAll('tr.incident-row').forEach(row => {
        row.addEventListener('click', () => {
            const href = row.dataset.href;
            if (href) window.location.href = href;
        });
    });

    // Resolved tab filters (priority / type / barangay / result / date / search)
    (function () {
        const priorityFilter = document.getElementById('filterPriorityH');
        if (!priorityFilter) return;

        const typeFilter     = document.getElementById('filterTypeH');
        const barangayFilter = document.getElementById('filterBarangayH');
        const resultFilter   = document.getElementById('filterResultH');
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
            const barangay = barangayFilter.value;
            const result   = resultFilter.value;
            const date     = dateFilter.value;
            const search   = searchInput.value.trim().toLowerCase();
            let visible    = 0;

            rows.forEach(row => {
                // "Resolved" includes the ones MDRRMO added by hand.
                const resultOk = !result
                    || row.dataset.result === result
                    || (result === 'resolved' && row.dataset.result === 'manual');
                const ok = (!priority || row.dataset.priority === priority)
                        && (!type     || row.dataset.type     === type)
                        && (!barangay || row.dataset.search.includes(barangay))
                        && resultOk
                        && (!date     || row.dataset.date     === date)
                        && (!search   || row.dataset.search.includes(search));
                row.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> resolved incidents`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        [priorityFilter, typeFilter, barangayFilter, resultFilter, dateFilter].forEach(s => s.addEventListener('change', applyFilters));
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            [priorityFilter, typeFilter, barangayFilter, resultFilter].forEach(s => s.value = '');
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
    })();

    // Report types can be free text ("Other"), so escape before putting
    // them inside the dialogs' HTML.
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Approve — SweetAlert2 dialog where the admin chooses the priority
    // (Critical / High / Low) before the report goes to the responders.
    // Nothing sets priority automatically: the choice goes into the
    // form's hidden "priority" field, and the server refuses an approval
    // without one.
    const PRIORITY_CHOICES = [['critical', 'Critical'], ['high', 'High'], ['low', 'Low']];

    document.querySelectorAll('.approve-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const type = form.dataset.type || 'report';
            const priorityField = form.querySelector('input[name="priority"]');
            const current = priorityField.value;
            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will also get a notification that their report was approved.'
                : 'This report was submitted by a guest, so there is no account to notify.';

            const choices = PRIORITY_CHOICES.map(([value, label]) => `
                <input class="prio-input" type="radio" name="swalPriority" id="swalPrio-${value}" value="${value}"${current === value ? ' checked' : ''}>
                <label class="prio-choice prio-${value}" for="swalPrio-${value}"><i class="bi bi-check-lg"></i>${label}</label>`).join('');

            Swal.fire({
                title: 'Approve this report?',
                html: `<div class="approve-intro">Choose the priority of this <strong>${escapeHtml(type)}</strong> report. The responders will see it.</div>
                       <div class="prio-choices" role="radiogroup" aria-label="Priority">${choices}</div>
                       <div class="approve-note">${notifyLine}</div>`,
                showCancelButton: true,
                confirmButtonText: 'Approve &amp; Send',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                focusConfirm: false,
                didOpen: (popup) => {
                    popup.querySelectorAll('input[name="swalPriority"]').forEach(input => {
                        input.addEventListener('change', () => Swal.resetValidationMessage());
                    });
                },
                preConfirm: () => {
                    const picked = Swal.getPopup().querySelector('input[name="swalPriority"]:checked');
                    if (!picked) {
                        Swal.showValidationMessage('Choose a priority first.');
                        return false;
                    }
                    return picked.value;
                },
            }).then(result => {
                if (result.isConfirmed) {
                    priorityField.value = result.value;
                    form.submit();
                }
            });
        });
    });

    // Decline confirmation — the reason is picked from the dropdown inside
    // the Bootstrap modal (not free text), same pattern as the Residents
    // Verification rejection modal. Validates a reason was selected,
    // closes the modal (firing a SweetAlert while a Bootstrap modal is
    // still open can cause stacking/focus-trap issues), then asks for one
    // final confirmation before submitting.
    document.querySelectorAll('.decline-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const reasonSelect = form.elements['decline_reason'];
            if (!reasonSelect.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason required',
                    text: 'Please select a reason for declining before continuing.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a3c8f',
                });
                return;
            }

            const type = form.dataset.type || 'report';
            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will get a notification with this reason.'
                : 'This report was submitted by a guest, so there is no account to notify.';

            const parentModalEl = form.closest('.modal');
            const bsModal = parentModalEl ? bootstrap.Modal.getOrCreateInstance(parentModalEl) : null;

            const confirmDecline = () => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Decline this report?',
                    html: `<div style="text-align:left;font-size:.85em;color:#6b7280;">Declining the <strong>${escapeHtml(type)}</strong> report — responders will NOT be dispatched.<br>${notifyLine}</div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, decline',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                }).then(result => {
                    if (result.isConfirmed) form.submit();
                });
            };

            if (bsModal && parentModalEl.classList.contains('show')) {
                parentModalEl.addEventListener('hidden.bs.modal', confirmDecline, { once: true });
                bsModal.hide();
            } else {
                confirmDecline();
            }
        });
    });

    // Delete (declined reports only) — asks first, since it can't be undone.
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Delete this declined report?',
                html: `<div style="font-size:.9em;color:#4b5563;">${escapeHtml(form.dataset.what || 'Declined report')}<br>It's removed for good, with its photos. This can't be undone.</div>`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                focusCancel: true,
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    // ?tab=resolved (e.g. after deleting a declined report) or
    // ?tab=for-review opens that tab.
    (function () {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (tab) openTab(tab);
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
