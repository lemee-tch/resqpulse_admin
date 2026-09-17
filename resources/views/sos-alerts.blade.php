<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – SOS Alerts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        .sidebar {
            width: 200px; min-height: 100vh; background: #1a3c8f;
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

        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
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
        .tab-btn .pill-count {
            background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 800;
            padding: 1px 7px; border-radius: 20px; margin-left: 6px;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── SOS CARD ── */
        .sos-card {
            background: #fff;
            border: 1.5px solid #e5e7eb;
            border-left: 5px solid #dc2626;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 14px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            transition: box-shadow .2s, transform .15s;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .sos-card:hover {
            box-shadow: 0 8px 24px rgba(17,24,39,.1);
            transform: translateY(-1px);
        }

        /* Severity reflects actual status, not a blanket alarm on every
           card — a SOS nobody has touched yet stays the loud red/tinted
           treatment; one already being responded to (or resolved) reads
           calmer, so the cards that still need eyes on them actually
           stand out instead of everything looking equally urgent. */
        .sos-card--pending {
            border-left-color: #dc2626;
            background: linear-gradient(to right, #fff5f5, #fff 70px);
        }
        .sos-card--responding,
        .sos-card--acknowledged {
            border-left-color: #2563eb;
        }
        .sos-card--resolved {
            border-left-color: #10b981;
            opacity: .8;
        }
        .sos-card--review {
            border-left-color: #f59e0b;
        }

        .sos-photo {
            width: 92px; height: 92px; border-radius: 12px; object-fit: cover;
            flex-shrink: 0; border: 1px solid #f3f4f6;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            transition: transform .15s;
        }
        .sos-card:hover .sos-photo { transform: scale(1.03); }
        .sos-photo-placeholder {
            width: 92px; height: 92px; border-radius: 12px; flex-shrink: 0;
            background: #f9fafb; display: flex; align-items: center; justify-content: center;
            color: #d1d5db; font-size: 1.5rem;
            border: 1px dashed #e5e7eb;
        }

        .sos-body { flex: 1; min-width: 0; }
        .sos-top-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
        .sos-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1rem; color: #111827; display: flex; align-items: center; gap: 8px; }
        .sos-time { font-size: .75rem; color: #9ca3af; white-space: nowrap; }

        .badge-status { font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-pending      { background: #fef3c7; color: #92400e; }
        .badge-acknowledged { background: #dbeafe; color: #1e40af; }
        .badge-responding   { background: #e0f2f1; color: #0369a1; }
        .badge-resolved     { background: #d1fae5; color: #065f46; }
        .badge-review       { background: #fef3c7; color: #92400e; }

        .badge-ai { background: #eef2ff; color: #4338ca; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        .sos-meta-row { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 8px; }
        .sos-meta-item { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #4b5563; }
        .sos-meta-item i { color: #9ca3af; }

        .sos-reporter { font-weight: 700; color: #111827; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 2.4rem; display: block; margin-bottom: 12px; color: #d1d5db; }

        .btn-approve {
            background: #10b981; color: #fff; border: none; border-radius: 8px;
            padding: 8px 16px; font-size: .8rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s;
            margin-top: 10px;
        }
        .btn-approve:hover { background: #059669; }
        .review-banner {
            background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;
            font-size:.82rem;color:#92400e;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;
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
        <a href="{{ route('sos-alerts') }}" class="active">
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
            $pendingReviewSos = $sosAlerts->where('needs_review', true)->values();
            $activeSos   = $sosAlerts->where('needs_review', false)->where('status', '!=', 'resolved')->values();
            $resolvedSos = $sosAlerts->where('status', '=', 'resolved')->values();
        @endphp

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-exclamation-octagon-fill" style="color:#dc2626;"></i> SOS Alerts
            </div>
            <span class="live-pill"><span class="live-dot"></span> {{ $activeSos->where('status', 'pending')->count() }} pending</span>
        </div>
        <div class="page-sub">Panic-button alerts from the mobile app — GPS location and photo captured automatically, always critical priority.</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <!-- TABS -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('active', this)">Active SOS ({{ $activeSos->count() }})</button>
            <button class="tab-btn" onclick="switchTab('review', this)">
                Pending Review ({{ $pendingReviewSos->count() }})
                @if($pendingReviewSos->count() > 0)
                    <span class="pill-count">{{ $pendingReviewSos->count() }}</span>
                @endif
            </button>
            <button class="tab-btn" onclick="switchTab('history', this)">Resolved History ({{ $resolvedSos->count() }})</button>
        </div>

        <!-- ══ TAB: ACTIVE SOS ══ -->
        <div id="tab-active" class="tab-panel active">
            @forelse($activeSos as $sos)
                @php
                    $statusClass = 'badge-' . $sos->status;
                    $statusLabel = ucfirst($sos->status);
                    $reporter = $sos->citizen?->full_name ?? ($sos->citizen_id ? 'Unknown' : 'Guest');
                    $mobile = $sos->citizen?->mobile;
                    $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";

                    $sosPhoto = null;
                    if ($sos->photo_path) {
                        $decodedSosPhotos = json_decode($sos->photo_path, true);
                        $sosPhoto = is_array($decodedSosPhotos) ? ($decodedSosPhotos[0] ?? null) : $sos->photo_path;
                    }
                @endphp
                <div class="sos-card sos-card--{{ $sos->status }}"
                    data-id="{{ $sos->id }}"
                    onclick="window.location.href='{{ route('sos.detail', $sos->id) }}'">

                    @if($sosPhoto)
                        <img src="{{ Storage::url($sosPhoto) }}" class="sos-photo" alt="SOS photo">
                    @else
                        <div class="sos-photo-placeholder"><i class="bi bi-camera-video-off"></i></div>
                    @endif

                    <div class="sos-body">
                        <div class="sos-top-row">
                            <div class="sos-title">
                                🆘 SOS Emergency
                                <span class="badge-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if($sos->ai_detected_type)
                                    <span class="badge-ai text-danger">{{ $sos->ai_detected_type }}</span>
                                @endif
                            </div>
                            <div class="sos-time">{{ $sos->created_at->diffForHumans() }} · {{ $sos->created_at->format('M d, g:i A') }}</div>
                        </div>

                        <div class="sos-meta-row">
                            <div class="sos-meta-item">
                                <i class="bi bi-person-fill"></i>
                                <span class="sos-reporter">{{ $reporter }}</span>
                                @if($mobile) <span>· {{ $mobile }}</span> @endif
                            </div>
                            <div class="sos-meta-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <a href="{{ $mapsUrl }}" target="_blank" onclick="event.stopPropagation()" style="color:#1a3c8f;text-decoration:none;font-weight:600;">
                                    {{ $sos->location ?: number_format($sos->latitude, 5) . ', ' . number_format($sos->longitude, 5) }}
                                </a>
                            </div>
                        </div>

                        @if($sos->ai_analysis)
                            <div style="margin-top:8px;font-size:.8rem;color:#6b7280;font-style:italic;">
                                "{{ $sos->ai_analysis }}"
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    No active SOS alerts. All clear.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: PENDING REVIEW (guest SOS) ══ -->
        <div id="tab-review" class="tab-panel">
            <div class="review-banner">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>These SOS alerts came from guests (not logged in) — only their GPS location is guaranteed. Responders are not notified until you approve.</span>
            </div>

            @forelse($pendingReviewSos as $sos)
                @php
                    $reporter = $sos->citizen?->full_name ?? ($sos->citizen_id ? 'Unknown' : 'Guest');
                    $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";

                    $sosPhoto = null;
                    if ($sos->photo_path) {
                        $decodedSosPhotos = json_decode($sos->photo_path, true);
                        $sosPhoto = is_array($decodedSosPhotos) ? ($decodedSosPhotos[0] ?? null) : $sos->photo_path;
                    }
                @endphp
                <div class="sos-card sos-card--review" data-id="{{ $sos->id }}">
                    @if($sosPhoto)
                        <img src="{{ Storage::url($sosPhoto) }}" class="sos-photo" alt="SOS photo" onclick="window.location.href='{{ route('sos.detail', $sos->id) }}'">
                    @else
                        <div class="sos-photo-placeholder" onclick="window.location.href='{{ route('sos.detail', $sos->id) }}'"><i class="bi bi-camera-video-off"></i></div>
                    @endif

                    <div class="sos-body">
                        <div class="sos-top-row">
                            <div class="sos-title">
                                🆘 SOS Emergency
                                <span class="badge-status badge-review">Needs Review</span>
                            </div>
                            <div class="sos-time">{{ $sos->created_at->diffForHumans() }} · {{ $sos->created_at->format('M d, g:i A') }}</div>
                        </div>

                        <div class="sos-meta-row">
                            <div class="sos-meta-item">
                                <i class="bi bi-person-fill"></i>
                                <span class="sos-reporter">{{ $reporter }}</span>
                            </div>
                            <div class="sos-meta-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <a href="{{ $mapsUrl }}" target="_blank" style="color:#1a3c8f;text-decoration:none;font-weight:600;">
                                    {{ $sos->location ?: number_format($sos->latitude, 5) . ', ' . number_format($sos->longitude, 5) }}
                                </a>
                            </div>
                        </div>

                        <form action="{{ route('incident.approve', $sos->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-approve">
                                <i class="bi bi-check-lg"></i> Approve &amp; Dispatch to Responders
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-check2-circle"></i>
                    No guest SOS alerts waiting for review.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: RESOLVED HISTORY ══ -->
        <div id="tab-history" class="tab-panel">

            @if($resolvedSos->count())
            <!-- FILTER BAR -->
            <div class="filter-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="date" class="filter-date" id="filterDateH" title="Filter by date"
                       style="border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;background:#fff;">

                <select class="filter-select" id="filterAiH"
                        style="appearance:none;-webkit-appearance:none;background:#fff url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E&quot;) no-repeat right 12px center;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 34px 9px 14px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;">
                    <option value="">All AI Types</option>
                    @foreach($resolvedSos->pluck('ai_detected_type')->filter()->unique()->sort() as $aiType)
                        <option value="{{ $aiType }}">{{ $aiType }}</option>
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
                @php
                    $reporter = $sos->citizen?->full_name ?? ($sos->citizen_id ? 'Unknown' : 'Guest');
                    $mobile = $sos->citizen?->mobile;
                    $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";

                    $sosPhoto = null;
                    if ($sos->photo_path) {
                        $decodedSosPhotos = json_decode($sos->photo_path, true);
                        $sosPhoto = is_array($decodedSosPhotos) ? ($decodedSosPhotos[0] ?? null) : $sos->photo_path;
                    }
                @endphp
                <div class="sos-card sos-card--resolved history-row"
                    data-date="{{ $sos->created_at->format('Y-m-d') }}"
                    data-ai="{{ $sos->ai_detected_type }}"
                    data-search="{{ strtolower($reporter.' '.$sos->location.' '.$sos->ai_detected_type.' '.$sos->ai_analysis) }}"
                    onclick="window.location.href='{{ route('sos.detail', $sos->id) }}'">

                    @if($sosPhoto)
                        <img src="{{ Storage::url($sosPhoto) }}" class="sos-photo" alt="SOS photo">
                    @else
                        <div class="sos-photo-placeholder"><i class="bi bi-camera-video-off"></i></div>
                    @endif

                    <div class="sos-body">
                        <div class="sos-top-row">
                            <div class="sos-title">
                                🆘 SOS Emergency
                                <span class="badge-status badge-resolved">Resolved</span>
                                @if($sos->ai_detected_type)
                                    <span class="badge-ai text-danger">{{ $sos->ai_detected_type }}</span>
                                @endif
                            </div>
                            <div class="sos-time">{{ $sos->created_at->diffForHumans() }} · {{ $sos->created_at->format('M d, g:i A') }}</div>
                        </div>

                        <div class="sos-meta-row">
                            <div class="sos-meta-item">
                                <i class="bi bi-person-fill"></i>
                                <span class="sos-reporter">{{ $reporter }}</span>
                                @if($mobile) <span>· {{ $mobile }}</span> @endif
                            </div>
                            <div class="sos-meta-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <a href="{{ $mapsUrl }}" target="_blank" onclick="event.stopPropagation()" style="color:#1a3c8f;text-decoration:none;font-weight:600;">
                                    {{ $sos->location ?: number_format($sos->latitude, 5) . ', ' . number_format($sos->longitude, 5) }}
                                </a>
                            </div>
                        </div>

                        @if($sos->ai_analysis)
                            <div style="margin-top:8px;font-size:.8rem;color:#6b7280;font-style:italic;">
                                "{{ $sos->ai_analysis }}"
                            </div>
                        @endif
                    </div>
                </div>
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
        const params = new URLSearchParams(window.location.search);
        const highlightId = params.get('highlight');
        if (!highlightId) return;

        const card = document.querySelector('.sos-card[data-id="' + highlightId + '"]');
        if (card) {
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

    // Resolved History tab filters (date / AI type / search)
    (function () {
        const dateFilter   = document.getElementById('filterDateH');
        if (!dateFilter) return;

        const aiFilter      = document.getElementById('filterAiH');
        const searchInput   = document.getElementById('searchInputH');
        const btnClear      = document.getElementById('btnClearH');
        const cards         = Array.from(document.querySelectorAll('#tab-history .history-row'));
        const resultsCount  = document.getElementById('resultsCountH');
        const noMatch       = document.getElementById('historyNoMatch');
        const totalCount    = cards.length;

        function applyFilters() {
            const date   = dateFilter.value;
            const ai     = aiFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            let visible  = 0;

            cards.forEach(card => {
                const ok = (!date   || card.dataset.date === date)
                        && (!ai     || card.dataset.ai   === ai)
                        && (!search || card.dataset.search.includes(search));
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> resolved SOS alerts`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        dateFilter.addEventListener('change', applyFilters);
        aiFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            aiFilter.value = '';
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
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