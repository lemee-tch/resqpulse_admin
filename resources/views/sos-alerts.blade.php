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

        /* ── MAIN ── */
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
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

        @php
            $activeSos   = $sosAlerts->where('status', '!=', 'resolved')->values();
            $resolvedSos = $sosAlerts->where('status', '=', 'resolved')->values();
        @endphp

        <!-- TABS -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('active', this)">Active SOS ({{ $activeSos->count() }})</button>
            <button class="tab-btn" onclick="switchTab('history', this)">Resolved History ({{ $resolvedSos->count() }})</button>
        </div>

        <!-- ══ TAB: ACTIVE SOS ══ -->
        <div id="tab-active" class="tab-panel active">
            @forelse($activeSos as $sos)
                @php
                    $statusClass = 'badge-' . $sos->status;
                    $statusLabel = ucfirst($sos->status);
                    $reporter = $sos->citizen?->full_name ?? 'Unknown';
                    $mobile = $sos->citizen?->mobile;
                    $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";

                    // `photo_path` stores a JSON-encoded array of paths (e.g.
                    // '["a.jpg","b.jpg"]') — same pattern as incident.blade.php /
                    // incident-details.blade.php. Some very old rows may still
                    // have a single plain path string instead — handle both.
                    $sosPhoto = null;
                    if ($sos->photo_path) {
                        $decodedSosPhotos = json_decode($sos->photo_path, true);
                        $sosPhoto = is_array($decodedSosPhotos) ? ($decodedSosPhotos[0] ?? null) : $sos->photo_path;
                    }
                @endphp
                <div class="sos-card"
                    data-id="{{ $sos->id }}"
                    onclick="window.location.href='{{ route('incident.detail', $sos->id) }}'">

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
                    $reporter = $sos->citizen?->full_name ?? 'Unknown';
                    $mobile = $sos->citizen?->mobile;
                    $mapsUrl = "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}";

                    // `photo_path` stores a JSON-encoded array of paths — same
                    // decoding as the Active SOS tab above.
                    $sosPhoto = null;
                    if ($sos->photo_path) {
                        $decodedSosPhotos = json_decode($sos->photo_path, true);
                        $sosPhoto = is_array($decodedSosPhotos) ? ($decodedSosPhotos[0] ?? null) : $sos->photo_path;
                    }
                @endphp
                <div class="sos-card status-resolved history-row"
                    data-date="{{ $sos->created_at->format('Y-m-d') }}"
                    data-ai="{{ $sos->ai_detected_type }}"
                    data-search="{{ strtolower($reporter.' '.$sos->location.' '.$sos->ai_detected_type.' '.$sos->ai_analysis) }}"
                    onclick="window.location.href='{{ route('incident.detail', $sos->id) }}'">

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

        // The highlighted alert is always still-active (that's why it fired
        // the overlay), so the Active SOS tab is already showing by default.
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
        if (!dateFilter) return; // nothing resolved yet — no filter bar rendered

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
</body>
</html>