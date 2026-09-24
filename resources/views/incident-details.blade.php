<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Incident Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 200px; min-height: 100vh; background: #1a3c8f;
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
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
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
        .admin-notes-box { background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; font-size: .82rem; color: #4b5563; line-height: 1.6; min-height: 60px; }

        /* Resolution (submitted by the responder from the field) */
        .resolution-notes-box { background: #ecfdf5; border: 1.5px solid #a7f3d0; border-left: 3px solid #10b981; border-radius: 8px; padding: 12px 14px; font-size: .82rem; color: #065f46; line-height: 1.6; min-height: 44px; }
        .resolution-empty { font-size: .8rem; color: #9ca3af; font-style: italic; }

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

        @php
            $priority = $incident->priority ?? 'unset';
            $priorityLabel = $incident->priority ? ucfirst($incident->priority) : 'No Priority Set';
            $reporter = $incident->citizen?->full_name ?? ($incident->citizen_id ? 'Unknown' : 'Guest');
            $lat = $incident->latitude  ?? 15.8952;
            $lng = $incident->longitude ?? 120.6263;
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
            <a href="{{ route('incident') }}" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Incidents
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
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
                <span><strong>Status</strong> {{ ucfirst($incident->status) }}</span>
                <span><strong>Reported</strong> {{ $incident->created_at->format('M d, Y g:i A') }}</span>
                <span><strong>Reporter</strong> {{ $reporter }}</span>
            </div>
        </div>

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
                    {{ $reporter }}<br>
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

                @if($incident->status === 'resolved')
                    <div class="detail-sub" style="margin-top:14px;">
                        <i class="bi bi-check-circle-fill" style="color:#10b981;"></i> Resolution (from responder)
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
                    <div style="font-size:.82rem;color:#9ca3af;font-style:italic;">
                        Not yet accepted by a responder.
                    </div>
                @endforelse
            </div>

            <!-- STATUS (read-only) -->
            <div class="panel-card">
                <div class="panel-label">Status</div>
                <div class="action-panel">
                    @php
                        $statusMeta = match($incident->status) {
                            'responding' => ['bg' => '#e0f2fe', 'border' => '#bae6fd', 'text' => '#0369a1', 'icon' => 'bi-lightning-charge-fill', 'label' => 'Responding'],
                            'resolved'   => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46', 'icon' => 'bi-check-circle-fill', 'label' => 'Resolved'],
                            default      => ['bg' => '#fef3c7', 'border' => '#fde68a', 'text' => '#92400e', 'icon' => 'bi-hourglass-split', 'label' => 'Pending'],
                        };
                    @endphp

                    <div style="display:flex; align-items:center; gap:8px; background:{{ $statusMeta['bg'] }}; border:1px solid {{ $statusMeta['border'] }}; border-radius:8px; padding:12px 14px;">
                        <i class="bi {{ $statusMeta['icon'] }}" style="color:{{ $statusMeta['text'] }}; font-size:1.1rem;"></i>
                        <span style="font-size:.85rem; font-weight:700; color:{{ $statusMeta['text'] }};">{{ $statusMeta['label'] }}</span>
                    </div>

                    @if($incident->status !== 'resolved')
                        {{-- Responders were already notified automatically the
                             moment this incident was created (see
                             PushNotificationService::dispatchToRespondersForIncident).
                             Status here is informational only — it's updated by
                             responders themselves, not set manually from admin. --}}
                        <div class="dispatch-note">
                            <i class="bi bi-broadcast"></i>
                            <span>Responders for this emergency type were automatically notified when it was reported. Status updates as they act on it.</span>
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
                        Incident reported by <strong>{{ $reporter }}</strong>
                        — {{ $incident->emergency_type }} at {{ $incident->location }}
                    </div>
                </div>
                @if($incident->priority)
                <div class="tl-row">
                    <div class="tl-time">—</div>
                    <div class="tl-dot {{ $incident->priority === 'critical' ? 'danger' : ($incident->priority === 'high' ? 'warning' : 'info') }}"></div>
                    <div class="tl-text">Priority set to <strong>{{ ucfirst($incident->priority) }}</strong></div>
                </div>
                @endif
                @if(in_array($incident->status, ['acknowledged', 'responding', 'resolved']))
                <div class="tl-row">
                    <div class="tl-time">—</div>
                    <div class="tl-dot warning"></div>
                    <div class="tl-text">Status updated to <strong>{{ ucfirst($incident->status) }}</strong></div>
                </div>
                @endif
                @if($incident->status === 'resolved')
                <div class="tl-row">
                    <div class="tl-time">—</div>
                    <div class="tl-dot success"></div>
                    <div class="tl-text">
                        Incident <strong>resolved</strong>
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
@if($incident->status === 'resolved' && $incident->resolution_photo_path)
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const map = L.map('detail-map', { zoomControl: false, dragging: false, scrollWheelZoom: false })
                 .setView([{{ $lat }}, {{ $lng }}], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([{{ $lat }}, {{ $lng }}]).addTo(map);

    function showPhotoSlide(index) {
        const carouselEl = document.getElementById('photoCarousel');
        if (!carouselEl) return;
        const carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
        carousel.to(index);
    }
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