<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Incident Details</title>
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
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12); }
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
        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content { padding: 32px 36px; flex: 1; min-width: 0; }

        /* ── PAGE HEADER ── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.5rem; color: #111827; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color .2s; }
        .back-link:hover { color: #0d2e7a; }

        /* ── INCIDENT BANNER ── */
        .incident-banner {
            border-radius: 12px; padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
            background: #6b7280; /* default: no priority */
        }
        .incident-banner.critical { background: #dc2626; }
        .incident-banner.high     { background: #f97316; }
        .incident-banner.low      { background: #3b82f6; }

        .banner-left { display: flex; align-items: center; gap: 14px; }
        .banner-icon { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; flex-shrink: 0; }
        .banner-type { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.15rem; color: #fff; }
        .badge-priority { background: rgba(255,255,255,.25); color: #fff; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; letter-spacing: .3px; }
        .banner-meta { display: flex; gap: 24px; flex-wrap: wrap; }
        .banner-meta span { font-size: .78rem; color: rgba(255,255,255,.9); font-weight: 500; }
        .banner-meta strong { color: #fff; font-weight: 700; }

        /* ── THREE COLUMN GRID ──
           minmax(0, ...) lets each track shrink below its content's
           natural width instead of forcing the whole grid wider than
           the viewport (the old bare `1fr`/`220px` tracks refused to
           shrink past their min-content, so on laptop-width screens
           the grid overflowed the page and the right-most panel got
           sliced off with no scrollbar). */
        .detail-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 220px); gap: 16px; margin-bottom: 20px; }
        .panel-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; min-width: 0; }
        .panel-label { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1rem; color: #111827; margin-bottom: 12px; }

        /* Location */
        .location-addr { display: flex; align-items: center; gap: 6px; font-size: .82rem; color: #6b7280; margin-bottom: 10px; }
        #detail-map { width: 100%; height: 160px; border-radius: 8px; overflow: hidden; }
        .btn-gmaps { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 10px; width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 8px; font-size: .8rem; font-weight: 600; color: #374151; background: #fff; cursor: pointer; text-decoration: none; transition: border-color .2s, color .2s; }
        .btn-gmaps:hover { border-color: #1a3c8f; color: #1a3c8f; }

        /* Details */
        .detail-desc { font-size: .83rem; color: #4b5563; line-height: 1.6; margin-bottom: 14px; overflow-wrap: break-word; }
        .detail-sub { font-size: .75rem; font-weight: 700; color: #111827; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .3px; }
        .detail-val { font-size: .82rem; color: #6b7280; margin-bottom: 12px; overflow-wrap: break-word; }
        .tag-guest {
            display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 20px;
            background: #fff7ed; color: #c2410c; font-size: .68rem; font-weight: 700;
            letter-spacing: .3px; vertical-align: middle;
        }
        .call-link { color: #1a3c8f; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .call-link:hover { text-decoration: underline; }

        /* Review before dispatch: notice + Approve / Decline (same look as
           the SOS details page). */
        .review-banner {
            display: flex; align-items: flex-start; gap: 10px;
            background: #fef3c7; border: 1px solid #fde68a; color: #92400e;
            border-radius: 10px; padding: 12px 16px; font-size: .82rem; line-height: 1.5;
            margin-bottom: 14px;
        }
        .quick-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .btn-quick {
            display: flex; align-items: center; gap: 8px;
            border: none; border-radius: 10px; padding: 12px 18px;
            font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .88rem;
            cursor: pointer; text-decoration: none; transition: opacity .2s, box-shadow .2s;
        }
        .btn-quick:hover { opacity: .9; box-shadow: 0 4px 14px rgba(0,0,0,.15); }
        .btn-approve-lg { background: #1a3c8f; color: #fff; }
        .btn-decline-lg { background: #fff; color: #dc2626; border: 1.5px solid #dc2626; }
        .btn-decline-lg:hover { background: #dc2626; color: #fff; }
        /* For Review: the responders' Final report is waiting for the admin. */
        .review-banner--report   { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
        .review-banner--returned { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        .review-banner--manual   { background: #eef2ff; border-color: #c7d2fe; color: #3730a3; }
        .quick-actions form { margin: 0; }
        .btn-report-lg  { background: #fff; color: #b91c1c; border: 1.5px solid #fecaca; }
        .btn-report-lg:hover { color: #991b1b; }
        .btn-resolve-lg { background: #10b981; color: #fff; }
        .btn-return-lg  { background: #fff; color: #b45309; border: 1.5px solid #f59e0b; }
        .btn-return-lg:hover { background: #f59e0b; color: #fff; }
        .tag-manual {
            display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 20px;
            background: #e0e7ff; color: #3730a3; font-size: .68rem; font-weight: 700;
            letter-spacing: .3px; vertical-align: middle;
        }
        .map-note { font-size: .72rem; color: #9ca3af; margin-top: 6px; }
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

        /* Approve dialog — the admin chooses the priority (same look as
           the Approve dialog on the Incidents page). */
        .prio-choices { display: flex; gap: 8px; margin: 2px 0 12px; }
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
        .btn-approve-confirm {
            background: #10b981; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-approve-confirm:hover { background: #059669; }
        .btn-approve-confirm:disabled { background: #a7f3d0; cursor: not-allowed; }
        .prio-hint { font-size: .78rem; color: #9ca3af; margin-right: auto; }

        /* Photos */
        .photos-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .photo-thumb { width: 68px; height: 52px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 2px solid transparent; transition: border-color .2s; }
        .photo-thumb:hover { border-color: #1a3c8f; }

        /* Action panel */
        .action-panel { display: flex; flex-direction: column; gap: 8px; }
        .btn-action { width: 100%; border: none; border-radius: 8px; padding: 10px 14px; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem; cursor: pointer; transition: opacity .2s, box-shadow .2s; }
        .btn-action:hover { opacity: .88; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        .btn-responding   { background: #f97316; color: #fff; }
        .btn-resolved     { background: #10b981; color: #fff; }
        .btn-note { width: 100%; background: #fff; color: #374151; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 10px 14px; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem; cursor: pointer; transition: border-color .2s; }
        .btn-note:hover { border-color: #1a3c8f; color: #1a3c8f; }
        .current-status { font-size: .75rem; color: #6b7280; text-align: center; margin-top: 2px; }
        .dispatch-note { display: flex; align-items: flex-start; gap: 8px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 12px; font-size: .74rem; color: #1e40af; line-height: 1.5; margin-bottom: 4px; }
        .dispatch-note i { margin-top: 1px; flex-shrink: 0; }

        /* Timeline */
        .timeline-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1rem; color: #111827; margin-bottom: 14px; }
        .timeline { display: flex; flex-direction: column; gap: 0; }
        .tl-row { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 16px; position: relative; }
        .tl-row:not(:last-child)::before { content: ''; position: absolute; left: 52px; top: 20px; bottom: 0; width: 2px; background: #e5e7eb; }
        .tl-time { font-size: .75rem; color: #9ca3af; font-weight: 600; width: 42px; flex-shrink: 0; padding-top: 2px; }
        .tl-dot { width: 12px; height: 12px; border-radius: 50%; background: #d1d5db; flex-shrink: 0; margin-top: 4px; }
        .tl-dot.success { background: #10b981; }
        .tl-dot.warning { background: #f97316; }
        .tl-dot.danger  { background: #dc2626; }
        .tl-dot.info    { background: #3b82f6; }
        .tl-text { font-size: .82rem; color: #4b5563; line-height: 1.5; }

        /* Admin notes */
        .admin-notes-box { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; font-size: .82rem; color: #4b5563; line-height: 1.6; min-height: 60px; overflow-wrap: break-word; }

        /* Resolution (submitted by the responder from the field) */
        .resolution-notes-box { background: #ecfdf5; border: 1.5px solid #a7f3d0; border-left: 3px solid #10b981; border-radius: 8px; padding: 12px 14px; font-size: .82rem; color: #065f46; line-height: 1.6; min-height: 44px; overflow-wrap: break-word; }
        .resolution-empty { font-size: .8rem; color: #9ca3af; font-style: italic; }
        /* Responder-filed MDRRMC incident report (PDF) */
        .report-pdf-row { display: flex; align-items: center; gap: 10px; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; margin-top: 6px; flex-wrap: wrap; }
        .report-pdf-icon { font-size: 1.6rem; color: #dc2626; line-height: 1; }
        .report-pdf-meta { flex: 1; min-width: 140px; }
        .report-pdf-name { font-size: .82rem; font-weight: 700; color: #111827; }
        .report-pdf-sub { font-size: .72rem; color: #6b7280; }
        .report-pdf-btn { display: inline-flex; align-items: center; gap: 5px; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 6px 11px; font-size: .78rem; font-weight: 600; color: #374151; background: #fff; text-decoration: none; }
        .report-pdf-btn:hover { border-color: #1a3c8f; color: #1a3c8f; }
        .report-type-tag { display: inline-block; margin-left: 6px; font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; vertical-align: middle; }
        .report-type-tag.is-initial { background: #fef3c7; color: #92400e; }
        .report-type-tag.is-final { background: #d1fae5; color: #065f46; }

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

        /* ── Grid: ease from 3 columns down to 2, then 1, before the
           sidebar itself collapses at 900px — this is what actually
           keeps the "Details" / "Responding Team" / "Status" panels
           from being squeezed off-screen on laptop-width windows. */
        @media (max-width: 1150px) {
            .detail-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        }
        @media (max-width: 700px) {
            .detail-grid { grid-template-columns: minmax(0, 1fr); }
        }

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
                padding-bottom: 88px !important;
            }
            /* .main-wrap already supplies the horizontal gutter above —
               drop .content's own left/right padding so the two don't
               stack and eat extra width on a narrow screen. */
            .content { padding: 32px 0; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            img, svg, canvas, iframe { max-width: 100%; }
        }

        @media (max-width: 560px) {
            .main-wrap {
                padding: 16px 12px 28px !important;
                padding-bottom: 88px !important;
            }
            .content { padding: 32px 0; }
            /* Shrink the location preview a bit further on small phones —
               160px is fine at tablet/desktop widths but takes more
               vertical room than it needs once the 3-column grid has
               collapsed to a single column here. */
            #detail-map { height: 130px; }
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

        @php
            $priority = $incident->priority ?? 'unset';
            $priorityLabel = $incident->priority ? ucfirst($incident->priority) : 'No Priority Set';
            // A guest's typed name and number — see Incident::getReporterNameAttribute().
            $reporter = $incident->reporter_name;
            $mobile = $incident->reporter_mobile;
            $isManual = $incident->isManualRecord();
            $isGuestReport = ! $incident->citizen_id && ! $isManual;
            // An incident MDRRMO added by hand has no GPS pin — the map shows
            // the middle of its barangay instead.
            $hasPin = $incident->latitude && $incident->longitude;
            $approxBarangay = null;
            if (! $hasPin) {
                $approxBarangay = app(\App\Services\BarangayLocationService::class)->barangayFromText($incident->location);
            }
            $approxPoint = $approxBarangay ? (\App\Services\BarangayLocationService::allCoordinates()[$approxBarangay] ?? null) : null;
            $lat = $incident->latitude  ?? ($approxPoint[0] ?? 15.8952);
            $lng = $incident->longitude ?? ($approxPoint[1] ?? 120.6263);
            // Which Incidents tab this one is on, for "Back to Incidents".
            $backTab = ['pending' => 'pending', 'active' => 'active', 'for_review' => 'for-review', 'resolved' => 'resolved'][$incident->stage] ?? null;

            // The responders' MDRRMC report (Initial or Final) — see
            // Api\IncidentController::resolve() — or the one MDRRMO typed in
            // for an incident handled outside the app.
            $report = is_array($incident->report_data) ? $incident->report_data : null;
            $reportIsInitial = $report && ($report['report_type'] ?? 'final') === 'initial';
            $reportUpdatedAt = null;
            if ($report && !empty($report['updated_at'])) {
                try {
                    $reportUpdatedAt = \Carbon\Carbon::parse($report['updated_at'])->setTimezone(config('app.timezone'));
                } catch (\Throwable $e) {
                    $reportUpdatedAt = null;
                }
            }
            $isReturned = $incident->status === 'responding' && $incident->report_returned_at && $incident->report_return_reason;
            $typeIcons = [
                'Fire'              => '🔥',
                'Flood'             => '🌊',
                'Earthquake'        => '🏚️',
                'Accident'          => '🚗',
                'Medical Emergency' => '🚑',
                'Landslide'         => '⛰️',
                'Other'             => '⚠️',
            ];
            $icon = $typeIcons[$incident->emergency_type] ?? '⚠️';

            // `photo_path` stores a JSON-encoded array of paths (e.g. '["a.jpg","b.jpg"]').
            // Some very old rows may still have a single plain path string instead — handle both.
            $detailPhotos = collect();
            if ($incident->photo_path) {
                $decodedPhotos = json_decode($incident->photo_path, true);
                $detailPhotos = is_array($decodedPhotos) ? collect($decodedPhotos) : collect([$incident->photo_path]);
            }
        @endphp

        <div class="page-header">
            <div class="page-title">
                Incident Details
            </div>
            <a href="{{ route('incident', $backTab ? ['tab' => $backTab] : []) }}" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Incidents
            </a>
        </div>

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

        <!-- INCIDENT BANNER -->
        <div class="incident-banner {{ $priority }}">
            <div class="banner-left">
                <div class="banner-icon">{{ $icon }}</div>
                <div class="banner-type">{{ $incident->emergency_type }}</div>
                <span class="badge-priority">{{ $priorityLabel }}</span>
            </div>
            <div class="banner-meta">
                <span><strong>Status</strong> {{ $incident->status_label }}</span>
                <span><strong>{{ $isManual ? 'Happened' : 'Reported' }}</strong> {{ $incident->created_at->format('M d, Y g:i A') }}</span>
                <span><strong>Reporter</strong> {{ $reporter }}</span>
            </div>
        </div>

        {{-- Every new report waits for an admin before responders are
             notified (Api\IncidentController::store sets needs_review). --}}
        @if($incident->needs_review)
            <div class="review-banner">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>
                    This report is waiting for your review. Check the photos, description and reporter below.
                    @if($mobile)
                        You can call {{ $reporter }} at <a href="tel:{{ $mobile }}" class="call-link">{{ $mobile }}</a> to confirm.
                    @endif
                    Responders are <strong>not</strong> notified until you approve it and choose its priority.
                </span>
            </div>

            <div class="quick-actions">
                {{-- Opens the Approve dialog, where the admin picks the priority. --}}
                <button type="button" class="btn-quick btn-approve-lg" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bi bi-check-circle-fill"></i> Approve &amp; Send to Responders
                </button>
                <button type="button" class="btn-quick btn-decline-lg" data-bs-toggle="modal" data-bs-target="#declineModal">
                    <i class="bi bi-x-circle-fill"></i> Decline
                </button>
            </div>
        @endif

        {{-- For Review: the responders sent their Final report. The admin
             accepts it (Mark as Resolved) or sends it back with what needs
             fixing — Admin\IncidentController::confirmResolved() / returnReport(). --}}
        @if($incident->isForReview())
            <div class="review-banner review-banner--report">
                <i class="bi bi-clipboard2-check" style="margin-top:1px;"></i>
                <span>
                    The responding team sent their <strong>Final report</strong>@if($incident->report_submitted_at) on {{ $incident->report_submitted_at->format('M d, Y g:i A') }}@endif.
                    Check it — narrative, casualties, actions taken and photo — then mark the incident as resolved,
                    or return the report to the responders with what needs fixing.
                </span>
            </div>

            <div class="quick-actions">
                @if($report)
                    <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="btn-quick btn-report-lg">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Open Final Report
                    </a>
                @endif
                <form action="{{ route('incident.confirm-resolved', $incident->id) }}" method="POST" class="resolve-form"
                      data-type="{{ $incident->emergency_type }}"
                      data-notifies-citizen="{{ $incident->citizen_id ? '1' : '0' }}">
                    @csrf
                    <button type="submit" class="btn-quick btn-resolve-lg">
                        <i class="bi bi-check2-circle"></i> Mark as Resolved
                    </button>
                </form>
                <button type="button" class="btn-quick btn-return-lg" data-bs-toggle="modal" data-bs-target="#returnModal">
                    <i class="bi bi-arrow-return-left"></i> Return to Responders
                </button>
            </div>
        @elseif($isReturned)
            <div class="review-banner review-banner--returned">
                <i class="bi bi-arrow-return-left" style="margin-top:1px;"></i>
                <span>
                    You sent the report back to the responders on {{ $incident->report_returned_at->format('M d, Y g:i A') }}:
                    <strong>{{ rtrim($incident->report_return_reason, '. ') }}</strong>.
                    It comes back to <strong>For Review</strong> when they send it again.
                </span>
            </div>
        @elseif($isManual)
            <div class="review-banner review-banner--manual">
                <i class="bi bi-pencil-square" style="margin-top:1px;"></i>
                <span>
                    This incident was handled outside the app and added by MDRRMO{{ $incident->resolvedBy ? ' (' . $incident->resolvedBy->name . ')' : '' }}
                    @if($incident->reported_via) — reported to MDRRMO via <strong>{{ strtolower($incident->reported_via) }}</strong>@endif.
                </span>
            </div>
        @endif

        <!-- THREE COLUMN GRID -->
        <div class="detail-grid">

            <!-- LOCATION -->
            <div class="panel-card">
                <div class="panel-label">Location</div>
                <div class="location-addr">
                    <i class="bi bi-geo-alt-fill" style="color:#dc2626;"></i>
                    {{ $incident->location }}
                </div>
                <div id="detail-map"></div>
                @unless($hasPin)
                    <div class="map-note">
                        <i class="bi bi-info-circle"></i>
                        {{ $approxBarangay ? "No GPS pin — the map shows the middle of Brgy. {$approxBarangay}." : 'No GPS pin for this incident.' }}
                    </div>
                @endunless
                <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" class="btn-gmaps">
                    <i class="bi bi-map"></i> Open in Google Maps
                </a>
            </div>

            <!-- DETAILS -->
            <div class="panel-card">
                <div class="panel-label">Details</div>
                <p class="detail-desc">{{ $incident->description }}</p>

                <div class="detail-sub">Reported by</div>
                <div class="detail-val">
                    {{ $reporter }}
                    @if($isGuestReport) <span class="tag-guest">Guest</span> @endif
                    @if($isManual) <span class="tag-manual">Added by MDRRMO</span> @endif<br>
                    @if($mobile)
                        <a href="tel:{{ $mobile }}" class="call-link"><i class="bi bi-telephone-fill"></i> {{ $mobile }}</a><br>
                    @endif
                    @if($isManual && $incident->reported_via)
                        Reported via {{ strtolower($incident->reported_via) }}<br>
                    @endif
                    {{ $incident->created_at->format('m/d/y, g:i A') }}
                </div>

                @if($detailPhotos->count())
                    <div class="detail-sub">Photos ({{ $detailPhotos->count() }})</div>
                    <div class="photos-row">
                        @foreach($detailPhotos as $index => $photoPath)
                            <img src="{{ Storage::url($photoPath) }}"
                                 class="photo-thumb"
                                 data-bs-toggle="modal"
                                 data-bs-target="#photoModal"
                                 onclick="showPhotoSlide({{ $index }})"
                                 alt="Incident photo {{ $index + 1 }}">
                        @endforeach
                    </div>
                @endif

                @if($incident->ai_detected_type)
                    <div class="detail-sub" style="margin-top:14px;">AI Photo Analysis</div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <span style="background:#eef2ff;color:#4338ca;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;">
                            <i></i> Likely: {{ $incident->ai_detected_type }}
                        </span>
                        <span style="font-size:.72rem;color:#9ca3af;text-transform:capitalize;">
                            {{ $incident->ai_confidence }} confidence
                        </span>
                    </div>
                    @if($incident->ai_analysis)
                        <div class="detail-val" style="font-style:italic;">{{ $incident->ai_analysis }}</div>
                    @endif
                @endif

                @if($incident->admin_notes)
                    <div class="detail-sub" style="margin-top:14px;">Admin Notes</div>
                    <div class="admin-notes-box">{{ $incident->admin_notes }}</div>
                @endif

                @if(in_array($incident->status, ['resolved', 'for_review'], true) || $report)
                    <div class="detail-sub" style="margin-top:14px;">
                        @if($isManual)
                            <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Resolution (added by MDRRMO)
                        @elseif($incident->status === 'resolved')
                            <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Resolution (from responder)
                        @elseif($incident->isForReview())
                            <i class="bi bi-clipboard2-check" style="color:#7c3aed;"></i> Final report (from responder) — waiting for your review
                        @elseif($isReturned)
                            <i class="bi bi-arrow-return-left" style="color:#c2410c;"></i> Report sent back to the responders
                        @else
                            <i class="bi bi-hourglass-split" style="color:#d97706;"></i> Initial report (from responder)
                        @endif
                    </div>
                    @if($incident->resolution_notes)
                        <div class="resolution-notes-box">{{ $incident->resolution_notes }}</div>
                    @else
                        <div class="resolution-empty">No notes were submitted with the resolution.</div>
                    @endif

                    @if($incident->resolution_photo_path)
                        <div class="detail-sub" style="margin-top:10px;">Resolution Photo</div>
                        <div class="photos-row">
                            <img src="{{ Storage::url($incident->resolution_photo_path) }}"
                                 class="photo-thumb"
                                 data-bs-toggle="modal"
                                 data-bs-target="#resolutionPhotoModal"
                                 alt="Resolution photo">
                        </div>
                    @endif

                    @if($report)
                        <div class="detail-sub" style="margin-top:12px;">Incident Report (PDF)</div>
                        <div class="report-pdf-row">
                            <div class="report-pdf-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                            <div class="report-pdf-meta">
                                <div class="report-pdf-name">
                                    MDRRMC Incident Report
                                    <span class="report-type-tag {{ $reportIsInitial ? 'is-initial' : 'is-final' }}">{{ $reportIsInitial ? 'Initial' : 'Final' }}</span>
                                </div>
                                <div class="report-pdf-sub">
                                    @if($isReturned)
                                        Sent back for changes — the team updates it and submits it as Final again.
                                    @elseif($reportIsInitial)
                                        The responding team can still update it until they submit the Final report.
                                    @elseif($isManual)
                                        Added by MDRRMO
                                    @elseif($incident->isForReview())
                                        Filed by the responding team — waiting for your review
                                    @else
                                        Filed by the responding team{{ !empty($report['reviewed_by']) ? ' · reviewed by ' . $report['reviewed_by'] : '' }}
                                    @endif
                                    @if($reportUpdatedAt)
                                        · {{ ($report['revision'] ?? 1) > 1 ? 'Last updated' : 'Filed' }} {{ $reportUpdatedAt->format('m/d/y, g:i A') }}
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="report-pdf-btn" title="View">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a href="{{ route('incident.report-pdf', [$incident, 'download' => 1]) }}" class="report-pdf-btn" title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    @endif
                @endif
            </div>

            <div class="panel-card">
                <div class="panel-label">
                    Responding Team
                    @if($incident->responders->isNotEmpty())
                        <span style="font-size:.72rem;font-weight:700;color:#1a3c8f;background:#eef2ff;padding:2px 9px;border-radius:20px;margin-left:6px;vertical-align:middle;">
                            {{ $incident->responders->count() }}
                        </span>
                    @endif
                </div>
                @forelse($incident->responders as $teamMember)
                    <div style="display:flex;align-items:center;gap:10px;{{ !$loop->last ? 'margin-bottom:12px;' : '' }}">
                        <div style="width:38px;height:38px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-person-badge-fill" style="color:#1e40af;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.85rem;color:#111827;">{{ $teamMember->full_name }}</div>
                            <div style="font-size:.75rem;color:#6b7280;">
                                {{ $teamMember->agency }} · Badge #{{ $teamMember->badge_number }}
                            </div>
                            @if($teamMember->pivot->accepted_at)
                                <div style="font-size:.72rem;color:#9ca3af;">
                                    Joined {{ \Illuminate\Support\Carbon::parse($teamMember->pivot->accepted_at)->format('M d, g:i A') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    @if($isManual && !empty($report['team']))
                        @foreach($report['team'] as $member)
                            <div style="display:flex;align-items:center;gap:10px;{{ !$loop->last ? 'margin-bottom:12px;' : '' }}">
                                <div style="width:38px;height:38px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-person-badge-fill" style="color:#3730a3;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:.85rem;color:#111827;">{{ $member['name'] ?: '—' }}</div>
                                    <div style="font-size:.75rem;color:#6b7280;">
                                        {{ $member['agency'] ?: '—' }} · {{ $member['role'] ?? '' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div style="font-size:.72rem;color:#9ca3af;margin-top:10px;">Typed in by MDRRMO — no responder accounts were used.</div>
                    @elseif($isManual)
                        <div style="font-size:.82rem;color:#9ca3af;font-style:italic;">
                            No responding team was written down.
                        </div>
                    @else
                        <div style="font-size:.82rem;color:#9ca3af;font-style:italic;">
                            Not yet accepted by a responder.
                        </div>
                    @endif
                @endforelse
            </div>

            <!-- STATUS (read-only) -->
            <div class="panel-card">
                <div class="panel-label">Status</div>
                <div class="action-panel">
                    @php
                        $statusMeta = match(true) {
                            (bool) $incident->needs_review      => ['bg' => '#fef3c7', 'border' => '#fde68a', 'text' => '#92400e', 'icon' => 'bi-hourglass-split'],
                            (bool) $incident->declined_at       => ['bg' => '#fee2e2', 'border' => '#fecaca', 'text' => '#991b1b', 'icon' => 'bi-x-circle-fill'],
                            $incident->status === 'responding'  => ['bg' => '#e0f2fe', 'border' => '#bae6fd', 'text' => '#0369a1', 'icon' => 'bi-lightning-charge-fill'],
                            $incident->status === 'for_review'  => ['bg' => '#f5f3ff', 'border' => '#ddd6fe', 'text' => '#5b21b6', 'icon' => 'bi-clipboard2-check'],
                            $incident->status === 'resolved'    => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46', 'icon' => 'bi-check-circle-fill'],
                            default                             => ['bg' => '#fef3c7', 'border' => '#fde68a', 'text' => '#92400e', 'icon' => 'bi-broadcast'],
                        };
                        $statusMeta['label'] = $incident->status_label;
                    @endphp

                    <div style="display:flex; align-items:center; gap:8px; background:{{ $statusMeta['bg'] }}; border:1px solid {{ $statusMeta['border'] }}; border-radius:8px; padding:12px 14px;">
                        <i class="bi {{ $statusMeta['icon'] }}" style="color:{{ $statusMeta['text'] }}; font-size:1.1rem;"></i>
                        <span style="font-size:.85rem; font-weight:700; color:{{ $statusMeta['text'] }};">{{ $statusMeta['label'] }}</span>
                    </div>

                    {{-- Responders are notified only when an admin approves the
                         report (Admin\IncidentController::approve()). Status is
                         informational — responders update it as they act. --}}
                    @if($incident->needs_review)
                        <div class="dispatch-note">
                            <i class="bi bi-hourglass-split"></i>
                            <span>Waiting for your review. Responders haven't been notified yet.</span>
                        </div>
                    @elseif($incident->declined_at)
                        <div class="dispatch-note">
                            <i class="bi bi-x-circle"></i>
                            <span>Declined{{ $incident->decline_reason ? ': ' . $incident->decline_reason : '' }}. Responders were not notified.</span>
                        </div>
                    @elseif($incident->isForReview())
                        <div class="dispatch-note">
                            <i class="bi bi-clipboard2-check"></i>
                            <span>The responders' Final report is waiting for your review. Mark it as resolved, or return it to them.</span>
                        </div>
                    @elseif($incident->status === 'resolved')
                        <div class="dispatch-note">
                            <i class="bi bi-check2-circle"></i>
                            <span>
                                Resolved{{ $incident->resolved_at ? ' ' . $incident->resolved_at->format('M d, Y g:i A') : '' }}{{ $incident->resolvedBy ? ($isManual ? ' — added by ' : ' — reviewed by ') . $incident->resolvedBy->name : '' }}.
                            </span>
                        </div>
                    @else
                        <div class="dispatch-note">
                            <i class="bi bi-broadcast"></i>
                            <span>Approved and sent to the responders for this emergency type. Status updates as they act on it.</span>
                        </div>
                    @endif

                    {{-- Add Note (unrelated to status — stays available regardless) --}}
                    <button class="btn-note" data-bs-toggle="modal" data-bs-target="#noteModal">
                        <i class="bi bi-pencil-square me-1"></i> Add Note
                    </button>
                </div>
            </div>

        </div>

        <!-- TIMELINE -->
        <div class="panel-card">
            <div class="timeline-title">Timeline / Activity</div>
            <div class="timeline">
                <div class="tl-row">
                    <div class="tl-time">{{ $incident->created_at->format('g:i A') }}</div>
                    <div class="tl-dot info"></div>
                    <div class="tl-text">
                        @if($isManual)
                            Incident happened — {{ $incident->emergency_type }} at {{ $incident->location }}
                            @if($incident->reported_via) (reported to MDRRMO via {{ strtolower($incident->reported_via) }}) @endif
                        @else
                            Incident reported by <strong>{{ $reporter }}</strong>
                            — {{ $incident->emergency_type }} at {{ $incident->location }}
                        @endif
                    </div>
                </div>
                @if($incident->priority)
                <div class="tl-row">
                    <div class="tl-time">—</div>
                    <div class="tl-dot {{ $incident->priority === 'critical' ? 'danger' : ($incident->priority === 'high' ? 'warning' : 'info') }}"></div>
                    <div class="tl-text">Priority set to <strong>{{ ucfirst($incident->priority) }}</strong></div>
                </div>
                @endif
                @if(! $isManual && $incident->responders->isNotEmpty())
                <div class="tl-row">
                    <div class="tl-time">{{ $incident->responders->min(fn ($r) => $r->pivot->accepted_at) ? \Illuminate\Support\Carbon::parse($incident->responders->min(fn ($r) => $r->pivot->accepted_at))->format('g:i A') : '—' }}</div>
                    <div class="tl-dot warning"></div>
                    <div class="tl-text">Accepted by the responders — status <strong>Responding</strong></div>
                </div>
                @endif
                @if($incident->report_returned_at)
                <div class="tl-row">
                    <div class="tl-time">{{ $incident->report_returned_at->format('g:i A') }}</div>
                    <div class="tl-dot danger"></div>
                    <div class="tl-text">
                        Final report <strong>sent back</strong> to the responders
                        @if($incident->report_return_reason) — "{{ \Illuminate\Support\Str::limit($incident->report_return_reason, 80) }}" @endif
                    </div>
                </div>
                @endif
                @if($incident->report_submitted_at)
                <div class="tl-row">
                    <div class="tl-time">{{ $incident->report_submitted_at->format('g:i A') }}</div>
                    <div class="tl-dot" style="background:#7c3aed;"></div>
                    <div class="tl-text">Final report sent — <strong>For Review</strong></div>
                </div>
                @endif
                @if($incident->status === 'resolved')
                <div class="tl-row">
                    <div class="tl-time">{{ $incident->resolved_at ? $incident->resolved_at->format('g:i A') : '—' }}</div>
                    <div class="tl-dot success"></div>
                    <div class="tl-text">
                        Incident <strong>resolved</strong>
                        @if($incident->resolvedBy) {{ $isManual ? '— added by' : '— reviewed by' }} {{ $incident->resolvedBy->name }} @endif
                        @if($incident->resolution_notes) — "{{ \Illuminate\Support\Str::limit($incident->resolution_notes, 60) }}" @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- Photo Modal (carousel — supports 3 or many photos, plus legacy single photo) --}}
@if($detailPhotos->count())
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                    Incident Photos ({{ $detailPhotos->count() }})
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="photoCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner">
                        @foreach($detailPhotos as $index => $photoPath)
                            <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                                <img src="{{ Storage::url($photoPath) }}"
                                     style="width:100%;max-height:480px;object-fit:contain;border-radius:10px;border:1px solid #e5e7eb;"
                                     alt="Incident photo {{ $index + 1 }}">
                            </div>
                        @endforeach
                    </div>
                    @if($detailPhotos->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" style="filter:invert(1) grayscale(100);"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" style="filter:invert(1) grayscale(100);"></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Resolution Photo Modal — separate from the citizen report's photo carousel above --}}
@if($incident->resolution_photo_path)
<div class="modal fade" id="resolutionPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                    Resolution Photo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <img src="{{ Storage::url($incident->resolution_photo_path) }}"
                     style="width:100%;max-height:480px;object-fit:contain;border-radius:10px;border:1px solid #e5e7eb;"
                     alt="Resolution photo">
            </div>
        </div>
    </div>
</div>
@endif

@if($incident->needs_review)
{{-- Approve Modal — the admin chooses the priority, then the report is
     sent to the responders. Nothing sets priority automatically. --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form action="{{ route('incident.approve', $incident->id) }}" method="POST" id="approveForm">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Approve and send to responders?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <label class="form-label-m">Priority of this {{ $incident->emergency_type }} report</label>
                    <div class="prio-choices" role="radiogroup" aria-label="Priority">
                        @foreach(['critical' => 'Critical', 'high' => 'High', 'low' => 'Low'] as $value => $label)
                            <input class="prio-input" type="radio" name="priority" id="prio-{{ $value }}" value="{{ $value }}"
                                   required {{ old('priority', $incident->priority) === $value ? 'checked' : '' }}>
                            <label class="prio-choice prio-{{ $value }}" for="prio-{{ $value }}"><i class="bi bi-check-lg"></i>{{ $label }}</label>
                        @endforeach
                    </div>
                    <p style="font-size:.8rem;color:#6b7280;margin:0;">
                        The responders will see this priority.
                        {{ $incident->citizen_id ? 'The reporter will also get a notification that their report was approved.' : 'This report was sent by a guest, so there is no account to notify.' }}
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <span class="prio-hint" id="prioHint">Choose a priority first.</span>
                    <button type="submit" class="btn-approve-confirm" id="approveSubmit">Approve &amp; Send</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Decline Modal — opened by the Decline button under the banner. --}}
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form action="{{ route('incident.decline', $incident->id) }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Decline this report?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">
                        Responders will NOT be dispatched.
                        {{ $incident->citizen_id ? 'The reporter will get a notification with this reason.' : 'This report was sent by a guest, so there is no account to notify.' }}
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
@endif

@if($incident->isForReview())
    @include('partials.return-report-modal', ['incident' => $incident, 'modalId' => 'returnModal'])
@endif

{{-- Add Note Modal --}}
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('incident.note', $incident->id) }}">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;">Add Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea name="admin_notes" rows="4" placeholder="Type your note here..."
                        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 13px;font-size:.85rem;font-family:'Inter',sans-serif;resize:none;background:#fafafa;">{{ $incident->admin_notes }}</textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit"
                        style="background:#1a3c8f;color:#fff;border:none;border-radius:8px;padding:9px 22px;font-family:'Barlow',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;">
                        Save Note
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function initDetailMap() {
        const map = new google.maps.Map(document.getElementById('detail-map'), {
            center: { lat: {{ $lat }}, lng: {{ $lng }} },
            zoom: 15,
            disableDefaultUI: true,
            gestureHandling: 'none',
            keyboardShortcuts: false,
            clickableIcons: false,
        });
        @if($hasPin || $approxPoint)
        new google.maps.Marker({
            position: { lat: {{ $lat }}, lng: {{ $lng }} },
            map,
            @if(! $hasPin) opacity: 0.6, title: 'Barangay center (approximate)', @endif
        });
        @endif
    }

    function showPhotoSlide(index) {
        const carouselEl = document.getElementById('photoCarousel');
        if (!carouselEl) return;
        const carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
        carousel.to(index);
    }

    // Approve dialog: "Approve & Send" stays off until a priority is picked.
    (function () {
        const form = document.getElementById('approveForm');
        if (!form) return;
        const submit = document.getElementById('approveSubmit');
        const hint = document.getElementById('prioHint');
        const sync = () => {
            const picked = !!form.querySelector('input[name="priority"]:checked');
            submit.disabled = !picked;
            hint.style.visibility = picked ? 'hidden' : 'visible';
        };
        form.addEventListener('change', sync);
        sync();
    })();
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&callback=initDetailMap&v=weekly" async defer></script>
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