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

        /* ── SIDEBAR ── */
        .sidebar {
            width: 170px; min-height: 100vh; background: #1a3c8f;
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

        /* ── MAIN ── */
        .main-wrap { margin-left: 170px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 28px 32px; flex: 1; }

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

        /* ── SOS CARD ── */
        .sos-card {
            background: #fff; 
            border: 1.5px solid #fecaca; 
            border-left: 5px solid #dc2626;
            border-radius: 14px; 
            padding: 18px 20px; 
            margin-bottom: 14px;
            display: flex; 
            gap: 16px; 
            align-items: flex-start;
            transition: box-shadow .2s; 
            cursor: pointer;
        }
        .sos-card:hover { 
            box-shadow: 0 6px 20px rgba(220,38,38,.12); 
        }
        .sos-card.status-resolved { 
            border-left-color: #10b981; 
            opacity: .75; 
        }

        .sos-photo {
            width: 92px; height: 92px; border-radius: 10px; object-fit: cover;
            flex-shrink: 0; border: 1px solid #f3f4f6;
        }
        .sos-photo-placeholder {
            width: 92px; height: 92px; border-radius: 10px; flex-shrink: 0;
            background: #f3f4f6; display: flex; align-items: center; justify-content: center;
            color: #9ca3af; font-size: 1.4rem;
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

        .badge-ai { background: #eef2ff; color: #4338ca; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        .sos-meta-row { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 8px; }
        .sos-meta-item { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #4b5563; }
        .sos-meta-item i { color: #9ca3af; }

        .sos-reporter { font-weight: 700; color: #111827; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 2.4rem; display: block; margin-bottom: 12px; color: #d1d5db; }
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
        <a href="{{ route('incident') }}">Incidents</a>
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

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-exclamation-octagon-fill" style="color:#dc2626;"></i> SOS Alerts
            </div>
            <span class="live-pill"><span class="live-dot"></span> {{ $sosAlerts->where('status', 'pending')->count() }} pending</span>
        </div>
        <div class="page-sub">Panic-button alerts from the mobile app — GPS location and photo captured automatically, always critical priority.</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        @forelse($sosAlerts as $sos)
            @php
                $statusClass = 'badge-' . $sos->status;
                $statusLabel = ucfirst($sos->status);
                $reporter = $sos->citizen?->full_name ?? 'Unknown';
                $mobile = $sos->citizen?->mobile;
                $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";
            @endphp
                <div class="sos-card {{ $sos->status === 'resolved' ? 'status-resolved' : '' }}"
                    data-id="{{ $sos->id }}"
                    onclick="window.location.href='{{ route('incident.detail', $sos->id) }}'">

                @if($sos->photo_path)
                    <img src="{{ Storage::url($sos->photo_path) }}" class="sos-photo" alt="SOS photo">
                @else
                    <div class="sos-photo-placeholder"><i class="bi bi-camera-video-off"></i></div>
                @endif

                <div class="sos-body">
                    <div class="sos-top-row">
                        <div class="sos-title">
                            🆘 SOS Emergency
                            <span class="badge-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            @if($sos->ai_detected_type)
                                <span class="badge-ai"><i class="bi bi-stars"></i> {{ $sos->ai_detected_type }}</span>
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
                No SOS alerts. All clear.
            </div>
        @endforelse

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</script>
@include('partials.sos-alert-overlay')
</body>
</html>