<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – SOS Alerts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
             display: flex;
             align-items: center;
             gap: 10px;
             padding: 18px 16px 16px;
             border-bottom: 1px solid rgba(255,255,255,.12);
            }
        .sidebar-brand img {
             width: 38px;
             height: 38px;
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

        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
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
        .sidebar-logout a:hover {
            color: #fff;
        }

        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .page-title {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-sub {
            font-size: .82rem;
            color: #6b7280;
            margin-bottom: 20px;
            }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fee2e2;
            color: #991b1b;
            font-size: .75rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #dc2626;
            animation: pulse-dot 1s infinite;
        }
        @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .3; } }

        @keyframes sosFlicker {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,.55); border-color:#dc2626; background:#fff5f5; }
            50%      { box-shadow: 0 0 0 14px rgba(220,38,38,0); border-color:#fecaca; background:#fff; }
        }
        .sos-card.is-new-alert {
            animation: sosFlicker 1s ease-in-out infinite;
        }

        /* ── TABS ── */
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

        /* ── SOS CARD (partials/sos-card.blade.php) ──
           A plain row: the type, then who / number / where / time, then
           (when there is one) the team or the decline reason. All the
           buttons sit together on the right; on phones they go under the
           text. A small dot says where the alert stands. */
        .sos-card {
            display: flex; align-items: center; gap: 20px;
            background: #fff;
            border: 1px solid #e8eaf0;
            border-radius: 12px;
            padding: 14px 16px 14px 18px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }
        .sos-card:hover { border-color: #cfd6e4; box-shadow: 0 4px 14px rgba(17,24,39,.06); }

        .sos-info { flex: 1; min-width: 0; }
        .sos-line1 { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; }
        .sos-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; background: #9ca3af; }
        /* Where it stands: waiting for review = amber; sent, nobody accepted
           yet = red; responding = blue; For Review = purple; resolved =
           green; declined = grey. */
        .sos-card--review     .sos-dot { background: #f59e0b; box-shadow: 0 0 0 3px #fef3c7; }
        .sos-card--pending    .sos-dot { background: #dc2626; box-shadow: 0 0 0 3px #fee2e2; }
        .sos-card--responding .sos-dot,
        .sos-card--acknowledged .sos-dot { background: #2563eb; box-shadow: 0 0 0 3px #dbeafe; }
        .sos-card--for_review .sos-dot { background: #7c3aed; box-shadow: 0 0 0 3px #ede9fe; }
        .sos-card--resolved   .sos-dot { background: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
        .sos-card--declined   .sos-dot { background: #9ca3af; box-shadow: 0 0 0 3px #f3f4f6; }

        .sos-title {
            font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 1.02rem; line-height: 1.3;
            color: #111827; margin: 0;
        }
        .sos-state { font-size: .74rem; font-weight: 600; color: #6b7280; }
        .sos-card--pending .sos-state { color: #b91c1c; }
        .sos-card--responding .sos-state { color: #1d4ed8; }
        .sos-flag {
            font-size: .7rem; font-weight: 700; color: #c2410c; background: #fff7ed;
            padding: 1px 8px; border-radius: 20px;
        }

        .sos-line2 { margin: 5px 0 0 19px; font-size: .82rem; line-height: 1.55; color: #6b7280; overflow-wrap: anywhere; }
        .sos-line2 .sos-sep { margin: 0 6px; color: #d1d5db; }
        .sos-who { color: #111827; font-weight: 600; }
        .sos-guest {
            margin-left: 6px; font-size: .68rem; font-weight: 700; letter-spacing: .3px;
            color: #c2410c; background: #fff7ed; padding: 1px 7px; border-radius: 20px; vertical-align: 1px;
        }
        .sos-tel { color: #1a3c8f; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .sos-tel i { margin-right: 4px; font-size: .8em; }
        .sos-tel:hover { text-decoration: underline; }
        .sos-outside { color: #b91c1c; font-weight: 700; margin-right: 4px; }
        .sos-place { color: #374151; text-decoration: none; }
        a.sos-place:hover { color: #1a3c8f; text-decoration: underline; }
        .sos-time { font-size: .78rem; color: #6b7280; white-space: nowrap; }
        .sos-time.waiting { color: #b45309; font-weight: 600; }
        .sos-time.late { color: #dc2626; font-weight: 700; }
        .sos-time::before { content: "·"; margin-right: 10px; color: #d1d5db; font-weight: 400; }
        .sos-line3 { margin: 3px 0 0 19px; font-size: .8rem; color: #6b7280; }
        .sos-line3.warn { color: #b45309; font-weight: 600; }

        /* All the buttons, together. */
        .sos-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .sos-actions form { margin: 0; display: flex; }
        .act {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;
            height: 36px; padding: 0 14px; border-radius: 9px;
            background: #fff; color: #374151; border: 1px solid #d9dee8;
            font-size: .8rem; font-weight: 600; text-decoration: none; cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .act i { font-size: .9rem; }
        .act:hover, .act:focus-visible { background: #f5f7fb; border-color: #c3cad9; color: #111827; outline: none; }
        .act--primary { background: #16a34a; border-color: #16a34a; color: #fff; }
        .act--primary:hover, .act--primary:focus-visible { background: #15803d; border-color: #15803d; color: #fff; }
        .act--danger { color: #dc2626; border-color: #f5c2c2; }
        .act--danger:hover, .act--danger:focus-visible { background: #fef2f2; border-color: #ef4444; color: #b91c1c; }
        .act--warn { color: #b45309; border-color: #f3d9a4; }
        .act--warn:hover, .act--warn:focus-visible { background: #fffbeb; border-color: #f59e0b; color: #92400e; }

        @media (max-width: 760px) {
            .sos-card { flex-direction: column; align-items: stretch; gap: 12px; padding: 14px; }
            .sos-line2, .sos-line3 { margin-left: 0; }
            .sos-time::before { display: none; } /* it may start a new line */
            /* The main action first, full width; the others share a row. */
            .sos-actions { flex-wrap: wrap; }
            .sos-actions > a, .sos-actions > button, .sos-actions > form { flex: 1 1 0; }
            .sos-actions form .act, .sos-actions .act { width: 100%; height: 40px; }
            .sos-actions > form:has(.act--primary) { flex-basis: 100%; order: -1; }
        }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 2.4rem; display: block; margin-bottom: 12px; color: #d1d5db; }

        /* One quiet line of help above a tab's list. */
        .review-banner { font-size: .8rem; color: #6b7280; margin: -4px 0 14px; display: flex; gap: 8px; align-items: flex-start; }
        .review-banner i { color: #9ca3af; }
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
        .decline-who { font-size: .85rem; color: #111827; margin-bottom: 6px; }
        .decline-outside {
            display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px;
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 10px;
            font-size: .8rem; color: #991b1b;
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
            /* .main-wrap already has the side padding here — don't add more. */
            .content { padding: 4px 0 24px; }
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
    <a href="{{ route('sos-alerts') }}" class="active"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
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
            $pendingReviewSos = $sosAlerts->where('needs_review', true)->values();
            // Active excludes anything still pending review AND anything
            // declined — a declined SOS was never dispatched, so it
            // doesn't belong alongside genuinely active ones. Declined
            // alerts have their own tab; Resolved History is only the
            // ones responders actually resolved.
            // For Review: the responders sent their Final report — the
            // admin marks it resolved or sends it back (same as Incidents).
            $activeSos    = $sosAlerts->filter(fn ($s) => $s->stage === 'active')->values();
            $forReviewSos = $sosAlerts->filter(fn ($s) => $s->stage === 'for_review')->values();
            $resolvedSos  = $sosAlerts->filter(fn ($s) => $s->status === 'resolved' && ! $s->declined_at)->values();
            $declinedSos  = $sosAlerts->filter(fn ($s) => (bool) $s->declined_at)->sortByDesc('declined_at')->values();

            // The cards say who resolved or declined it — loaded once for all.
            $sosAlerts->loadMissing(['resolvedBy', 'reviewedBy']);
        @endphp

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-exclamation-octagon-fill" style="color:#dc2626;"></i> SOS Alerts
            </div>
            <span class="live-pill"><span class="live-dot"></span> {{ $pendingReviewSos->count() }} waiting for review</span>
        </div>
        <div class="page-sub">Panic-button alerts from the mobile app — GPS location and photo captured automatically, always critical priority.</div>

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

        <!-- TABS — same order as Incidents: Pending → Active → For Review →
             Resolved, plus Declined. Every new SOS lands in Pending for the
             admin to verify before responders are notified. -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending', this)">Pending ({{ $pendingReviewSos->count() }})</button>
            <button class="tab-btn" data-tab="active" onclick="switchTab('active', this)">Active ({{ $activeSos->count() }})</button>
            <button class="tab-btn" data-tab="for-review" onclick="switchTab('for-review', this)">For Review ({{ $forReviewSos->count() }})</button>
            <button class="tab-btn" data-tab="resolved" onclick="switchTab('resolved', this)">Resolved ({{ $resolvedSos->count() }})</button>
            <button class="tab-btn" data-tab="declined" onclick="switchTab('declined', this)">Declined ({{ $declinedSos->count() }})</button>
        </div>

        <!-- ══ TAB: ACTIVE SOS (approved and sent to responders) ══ -->
        <div id="tab-active" class="tab-panel">
            @forelse($activeSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'active'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    No active SOS alerts. All clear.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: FOR REVIEW (the responders' Final report, waiting for the admin) ══ -->
        <div id="tab-for-review" class="tab-panel">
            <div class="review-banner review-banner--report">
                <i class="bi bi-clipboard2-check" style="margin-top:1px;"></i>
                <span>The team sent their Final report. Check it, then mark it resolved or return it with what needs fixing.</span>
            </div>

            @forelse($forReviewSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'for-review'])
                @include('partials.return-report-modal', ['incident' => $sos, 'modalId' => 'returnModal' . $sos->id])
            @empty
                <div class="empty-state">
                    <i class="bi bi-clipboard2-check"></i>
                    No SOS reports waiting for your review.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: PENDING (every new SOS, waiting for review before dispatch) ══ -->
        <div id="tab-pending" class="tab-panel active">
            <div class="review-banner">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>Call to confirm the emergency, then approve. Responders are notified only after you approve. View has the photo, the map and the team.</span>
            </div>

            @forelse($pendingReviewSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'pending'])

                {{-- Decline: pick a reason (the card's Decline button opens this). --}}
                <div class="modal fade" id="declineModal{{ $sos->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-radius:14px;border:none;">
                            <form action="{{ route('incident.decline', $sos->id) }}" method="POST" class="decline-form"
                                  data-notifies-citizen="{{ $sos->citizen_id ? '1' : '0' }}">
                                @csrf
                                <div class="modal-header border-0 pb-0">
                                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Decline this SOS alert?</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    <div class="decline-who">
                                        SOS from <strong>{{ $sos->reporter_name }}</strong>{{ $sos->citizen_id ? '' : ' (guest)' }}, sent {{ $sos->created_at->format('M d, g:i A') }}.
                                    </div>
                                    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">
                                        Responders will NOT be dispatched.
                                        {{ $sos->citizen_id ? 'The reporter will get a notification with this reason.' : 'This SOS was submitted by a guest, so there is no account to notify.' }}
                                    </p>
                                    @if(str_contains((string) $sos->location, 'Outside Rosales'))
                                        <div class="decline-outside">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <span>The GPS pin is outside Rosales. If MDRRMO Rosales can't respond there, choose "Location is outside Rosales / MDRRMO service area".</span>
                                        </div>
                                    @endif
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
                                    <button type="submit" class="btn-decline-confirm">Decline Alert</button>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-check2-circle"></i>
                    No SOS alerts waiting for review.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: RESOLVED ══ -->
        <div id="tab-resolved" class="tab-panel">

            @if($resolvedSos->count())
            <!-- FILTER BAR -->
            <div class="filter-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="date" class="filter-date" id="filterDateH" title="Filter by date"
                       style="border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;background:#fff;">

                {{-- The SOS type on the card (it was the AI's reading of the photo). --}}
                <select class="filter-select" id="filterTypeH"
                        style="appearance:none;-webkit-appearance:none;background:#fff url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E&quot;) no-repeat right 12px center;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 34px 9px 14px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;">
                    <option value="">All Types</option>
                    @foreach($resolvedSos->pluck('sos_emergency_type')->filter()->unique()->sort() as $sosType)
                        <option value="{{ $sosType }}">{{ $sosType }}</option>
                    @endforeach
                </select>

                <div style="position:relative;flex:1;min-width:180px;max-width:280px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem;"></i>
                    <input type="text" id="searchInputH" placeholder="Search resolved SOS..."
                           style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px 9px 34px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;background:#fff;">
                </div>

                <button id="btnClearH"
                        style="background:#fff;color:#6b7280;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 18px;font-size:.82rem;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
                    Clear Filters
                </button>
            </div>
            <div class="results-count" id="resultsCountH" style="font-size:.8rem;color:#6b7280;margin-bottom:14px;"></div>
            @endif

            @forelse($resolvedSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'resolved'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    No resolved SOS alerts yet.
                </div>
            @endforelse

            @if($resolvedSos->count())
            <div id="historyNoMatch" class="empty-state" style="display:none;">
                <i class="bi bi-search"></i>
                No resolved SOS alerts match these filters.
            </div>
            @endif
        </div>

        <!-- ══ TAB: DECLINED ══ -->
        <div id="tab-declined" class="tab-panel">

            @if($declinedSos->count())
            <!-- FILTER BAR -->
            <div class="filter-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="date" class="filter-date" id="filterDateD" title="Filter by date reported"
                       style="border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;background:#fff;">

                <select class="filter-select" id="filterReasonD"
                        style="appearance:none;-webkit-appearance:none;background:#fff url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E&quot;) no-repeat right 12px center;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 34px 9px 14px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;max-width:320px;">
                    <option value="">All Reasons</option>
                    @foreach($declinedSos->pluck('decline_reason')->filter()->unique()->sort() as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                    @endforeach
                </select>

                <div style="position:relative;flex:1;min-width:180px;max-width:280px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem;"></i>
                    <input type="text" id="searchInputD" placeholder="Search declined SOS..."
                           style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px 9px 34px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;background:#fff;">
                </div>

                <button id="btnClearD"
                        style="background:#fff;color:#6b7280;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 18px;font-size:.82rem;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
                    Clear Filters
                </button>
            </div>
            <div class="results-count" id="resultsCountD" style="font-size:.8rem;color:#6b7280;margin-bottom:14px;"></div>
            @endif

            @forelse($declinedSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'declined'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-slash-circle"></i>
                    No declined SOS alerts.
                </div>
            @endforelse

            @if($declinedSos->count())
            <div id="declinedNoMatch" class="empty-state" style="display:none;">
                <i class="bi bi-search"></i>
                No declined SOS alerts match these filters.
            </div>
            @endif
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

    // ?tab=declined (e.g. after deleting a declined alert) opens that tab.
    (function () {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (tab) openTab(tab);
    })();

    // Pending is the first tab, so after Mark as Resolved / Return on the
    // For Review tab the reload would jump back to it. Remember the tab the
    // change came from and reopen it once.
    (function () {
        const KEY = 'resqpulse_sos_return_tab';
        document.querySelectorAll('.tab-panel form:not(.delete-form)').forEach(form => {
            form.addEventListener('submit', () => {
                const panel = form.closest('.tab-panel');
                if (!panel) return;
                try { sessionStorage.setItem(KEY, panel.id.replace('tab-', '')); } catch (e) {}
            });
        });
        let tab = null;
        try { tab = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}
        if (tab) openTab(tab);
    })();

    // Clicking a card opens the SOS details, like its View button —
    // except on the card's own buttons, links and forms, or while text is
    // being selected.
    document.querySelectorAll('.sos-card[data-href]').forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('a, button, form, input, select, textarea, label')) return;
            if (String(window.getSelection ? window.getSelection() : '').trim() !== '') return;
            window.location.href = card.dataset.href;
        });
    });

    // Delete (declined alerts only) — asks first, since it can't be undone.
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const what = document.createElement('div');
            what.textContent = form.dataset.what || 'declined SOS alert';
            Swal.fire({
                icon: 'warning',
                title: 'Delete this declined SOS alert?',
                html: `<div style="font-size:.9em;color:#4b5563;">${what.innerHTML}<br>It's removed for good, with its photo. This can't be undone.</div>`,
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

    (function () {
        const params = new URLSearchParams(window.location.search);
        const highlightId = params.get('highlight');
        if (!highlightId) return;

        const card = document.querySelector('.sos-card[data-id="' + highlightId + '"]');
        if (card) {
            // Open the tab the alert is in (usually Pending Review, the
            // first tab) so the highlighted card is actually visible.
            const panel = card.closest('.tab-panel');
            const tabBtn = panel && document.querySelector('.tab-btn[data-tab="' + panel.id.replace('tab-', '') + '"]');
            if (tabBtn && !panel.classList.contains('active')) switchTab(panel.id.replace('tab-', ''), tabBtn);

            card.classList.add('is-new-alert');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => card.classList.remove('is-new-alert'), 12000);
        }

        try {
            const KEY = 'resqpulse_ack_sos_ids';
            const ids = JSON.parse(localStorage.getItem(KEY) || '[]');
            const idNum = parseInt(highlightId, 10);
            if (!ids.includes(idNum)) {
                ids.push(idNum);
                localStorage.setItem(KEY, JSON.stringify(ids));
            }
        } catch (e) {}
    })();

    // Resolved History tab filters (date / type / search)
    (function () {
        const dateFilter   = document.getElementById('filterDateH');
        if (!dateFilter) return;

        const typeFilter    = document.getElementById('filterTypeH');
        const searchInput   = document.getElementById('searchInputH');
        const btnClear      = document.getElementById('btnClearH');
        const cards         = Array.from(document.querySelectorAll('#tab-resolved .history-row'));
        const resultsCount  = document.getElementById('resultsCountH');
        const noMatch       = document.getElementById('historyNoMatch');
        const totalCount    = cards.length;

        function applyFilters() {
            const date   = dateFilter.value;
            const type   = typeFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            let visible  = 0;

            cards.forEach(card => {
                const ok = (!date   || card.dataset.date === date)
                        && (!type   || card.dataset.type === type)
                        && (!search || card.dataset.search.includes(search));
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> resolved SOS alerts`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        dateFilter.addEventListener('change', applyFilters);
        typeFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            typeFilter.value = '';
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
    })();

    // Declined tab filters (date reported / reason / search)
    (function () {
        const dateFilter = document.getElementById('filterDateD');
        if (!dateFilter) return;

        const reasonFilter = document.getElementById('filterReasonD');
        const searchInput  = document.getElementById('searchInputD');
        const btnClear     = document.getElementById('btnClearD');
        const cards        = Array.from(document.querySelectorAll('#tab-declined .declined-row'));
        const resultsCount = document.getElementById('resultsCountD');
        const noMatch      = document.getElementById('declinedNoMatch');
        const totalCount   = cards.length;

        function applyFilters() {
            const date   = dateFilter.value;
            const reason = reasonFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            let visible  = 0;

            cards.forEach(card => {
                const ok = (!date   || card.dataset.date === date)
                        && (!reason || card.dataset.reason === reason)
                        && (!search || card.dataset.search.includes(search));
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> declined SOS alerts`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        dateFilter.addEventListener('change', applyFilters);
        reasonFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            reasonFilter.value = '';
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
    })();

    // Decline confirmation — the reason is picked from the dropdown inside
    // the Bootstrap modal (not free text), same pattern as the Residents
    // Verification rejection modal. Validates a reason was selected,
    // closes the modal (firing a SweetAlert while a Bootstrap modal is
    // still open can cause stacking/focus-trap issues), then asks for one
    // final confirmation before submitting.
    document.querySelectorAll('.decline-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const reasonSelect = form.querySelector('select[name="decline_reason"]');
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

            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will get a notification with this reason.'
                : 'This SOS was submitted by a guest, so there is no account to notify.';

            const parentModalEl = form.closest('.modal');
            const bsModal = parentModalEl ? bootstrap.Modal.getOrCreateInstance(parentModalEl) : null;

            const confirmDecline = () => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Decline this SOS alert?',
                    html: `<div style="text-align:left;font-size:.85em;color:#6b7280;">Declining this SOS alert — responders will NOT be dispatched.<br>${notifyLine}</div>`,
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