<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – SOS Alert</title>
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
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 28px 32px; flex: 1; }

        /* ── PAGE HEADER ── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.5rem; color: #111827; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color .2s; }
        .back-link:hover { color: #0d2e7a; }

        /* ── INCIDENT BANNER — same component/classes as incident-details.
           SOS's own `priority` column is always 'critical' server-side
           (see Api\IncidentController::sos()), so reusing .critical here
           costs nothing and stays visually identical rather than a
           bespoke SOS-only banner style. ── */
        .incident-banner {
            border-radius: 12px; padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; margin-bottom: 14px;
            background: #6b7280;
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

        /* ── SOS-specific: review notice + quick actions (no equivalent
           on the regular Incident Details page). ── */
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
        .btn-call { background: #10b981; color: #fff; }
        .btn-approve-lg { background: #1a3c8f; color: #fff; }

        /* ── THREE COLUMN GRID ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr 220px; gap: 16px; margin-bottom: 20px; }
        .panel-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; }
        .panel-label { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1rem; color: #111827; margin-bottom: 12px; }

        /* Location */
        .location-addr { display: flex; align-items: center; gap: 6px; font-size: .82rem; color: #6b7280; margin-bottom: 10px; }
        #detail-map { width: 100%; height: 160px; border-radius: 8px; overflow: hidden; }
        .btn-gmaps { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 10px; width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 8px; font-size: .8rem; font-weight: 600; color: #374151; background: #fff; cursor: pointer; text-decoration: none; transition: border-color .2s, color .2s; }
        .btn-gmaps:hover { border-color: #1a3c8f; color: #1a3c8f; }

        /* Details */
        .detail-desc { font-size: .83rem; color: #4b5563; line-height: 1.6; margin-bottom: 14px; }
        .detail-sub { font-size: .75rem; font-weight: 700; color: #111827; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .3px; }
        .detail-val { font-size: .82rem; color: #6b7280; margin-bottom: 12px; }

        /* Photos */
        .photos-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .photo-thumb { width: 68px; height: 52px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 2px solid transparent; transition: border-color .2s; }
        .photo-thumb:hover { border-color: #1a3c8f; }
        .photo-empty {
            width: 68px; height: 52px; border-radius: 6px; background: #f9fafb;
            border: 1px dashed #e5e7eb; display: flex; align-items: center; justify-content: center;
            color: #d1d5db; font-size: 1rem;
        }

        /* Action panel */
        .action-panel { display: flex; flex-direction: column; gap: 8px; }
        .btn-note { width: 100%; background: #fff; color: #374151; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 10px 14px; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem; cursor: pointer; transition: border-color .2s; }
        .btn-note:hover { border-color: #1a3c8f; color: #1a3c8f; }
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
        <a href="{{ route('incident') }}">
            Incidents
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
        <a href="{{ route('mapview') }}">Map View</a>
        <a href="{{ route('alerts') }}">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('responder-accounts') }}">Responder Accounts</a>
        <a href="{{ route('reports-analytics') }}">Reports &amp; Analytics</a>
        <a href="{{ route('users') }}">User</a>
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
            // SOS is always critical server-side — default matches that
            // rather than leaving it to chance if an older row somehow
            // has priority unset.
            $priority = $incident->priority ?? 'critical';
            $priorityLabel = ucfirst($priority);
            $reporter = $incident->citizen?->full_name ?? ($incident->citizen_id ? 'Unknown' : 'Guest');
            $mobile = $incident->citizen?->mobile;
            $lat = $incident->latitude  ?? 15.8952;
            $lng = $incident->longitude ?? 120.6263;
            $icon = '🆘';

            $detailPhotos = collect();
            if ($incident->photo_path) {
                $decodedPhotos = json_decode($incident->photo_path, true);
                $detailPhotos = is_array($decodedPhotos) ? collect($decodedPhotos) : collect([$incident->photo_path]);
            }
        @endphp

        <div class="page-header">
            <div class="page-title">SOS Alert #{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }}</div>
            <a href="{{ route('sos-alerts') }}" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to SOS Alerts
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <!-- INCIDENT BANNER (same component as Incident Details) -->
        <div class="incident-banner {{ $priority }}">
            <div class="banner-left">
                <div class="banner-icon">{{ $icon }}</div>
                <div class="banner-type">SOS Emergency</div>
                <span class="badge-priority">{{ $priorityLabel }}</span>
            </div>
            <div class="banner-meta">
                <span><strong>Status</strong> {{ $incident->needs_review ? 'Pending Review' : ucfirst($incident->status) }}</span>
                <span><strong>Reported</strong> {{ $incident->created_at->format('M d, Y g:i A') }}</span>
                <span><strong>Reporter</strong> {{ $reporter }}</span>
            </div>
        </div>

        @if($incident->needs_review)
            <div class="review-banner">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>This SOS came from a guest (not logged in) — only GPS location is guaranteed. Responders are NOT notified until you approve it below.</span>
            </div>
        @endif

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            @if($mobile)
                <a href="tel:{{ $mobile }}" class="btn-quick btn-call">
                    <i class="bi bi-telephone-fill"></i> Call {{ $mobile }}
                </a>
            @endif
            @if($incident->needs_review)
                <form action="{{ route('incident.approve', $incident->id) }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn-quick btn-approve-lg">
                        <i class="bi bi-check-circle-fill"></i> Approve &amp; Notify Responders
                    </button>
                </form>
            @endif
        </div>

        <!-- THREE COLUMN GRID -->
        <div class="detail-grid">

            <!-- LOCATION -->
            <div class="panel-card">
                <div class="panel-label">Location</div>
                <div class="location-addr">
                    <i class="bi bi-geo-alt-fill" style="color:#dc2626;"></i>
                    {{ $incident->location ?: 'Location unavailable' }}
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
                    {{ $reporter }}{{ $mobile ? ' · ' . $mobile : '' }}<br>
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
                                 alt="SOS photo {{ $index + 1 }}">
                        @endforeach
                    </div>
                @else
                    <div class="detail-sub">Photos</div>
                    <div class="photos-row"><div class="photo-empty"><i class="bi bi-camera-video-off"></i></div></div>
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
            </div>

            <!-- RESPONDING TEAM -->
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
                        {{ $incident->needs_review ? 'Approve this SOS to notify responders.' : 'Not yet accepted by a responder.' }}
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
                        <span style="font-size:.85rem; font-weight:700; color:{{ $statusMeta['text'] }};">
                            {{ $incident->needs_review ? 'Pending Review' : $statusMeta['label'] }}
                        </span>
                    </div>

                    @if(!$incident->needs_review && $incident->status !== 'resolved')
                        <div class="dispatch-note">
                            <i class="bi bi-broadcast"></i>
                            <span>Responders were automatically notified when this SOS was triggered. Status updates as they act on it.</span>
                        </div>
                    @endif

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
                    <div class="tl-dot danger"></div>
                    <div class="tl-text">
                        SOS triggered by <strong>{{ $reporter }}</strong>
                        @if($incident->needs_review) — awaiting admin review @endif
                    </div>
                </div>
                @if($incident->needs_review)
                    <div class="tl-row">
                        <div class="tl-time">—</div>
                        <div class="tl-dot warning"></div>
                        <div class="tl-text">Flagged for review — guest report, not yet approved</div>
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
                        <div class="tl-text">SOS <strong>resolved</strong></div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- Photo Modal --}}
@if($detailPhotos->count())
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">
                    SOS Alert #{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }} — Photos ({{ $detailPhotos->count() }})
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
                                     alt="SOS photo {{ $index + 1 }}">
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
</body>
</html>