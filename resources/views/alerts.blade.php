<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Alerts & Broadcast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 200px; min-height: 100vh;
            background: #1a3c8f;
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 16px 16px;    
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .sidebar-brand img {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid rgba(255,255,255,.3);
        }
        .sidebar-brand-text .title {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: .85rem; color: #fff; letter-spacing: .5px; line-height: 1.1;
        }
        .sidebar-brand-text .sub { font-size: .65rem; color: rgba(255,255,255,.6); letter-spacing: .5px; }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a {
            display: block; padding: 10px 20px;
            font-size: .82rem; font-weight: 500;
            color: rgba(255,255,255,.75); text-decoration: none;
            border-left: 3px solid transparent; transition: all .2s;
        }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active {
            color: #fff; font-weight: 700;
            border-left-color: #fff; background: rgba(255,255,255,.1);
        }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a {
            display: flex; align-items: center; gap: 8px;
            font-size: .82rem; color: rgba(255,255,255,.75);
            text-decoration: none; font-weight: 500; transition: color .2s;
        }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 28px 32px; flex: 1; }

        .page-title {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: 1.5rem; color: #111827; margin-bottom: 18px;
        }

        /* ── TABS ── */
        .tab-bar {
            display: flex; gap: 0;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 24px;
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

        /* Tab panels */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── SECTION TITLE ── */
        .section-title {
            font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: 1rem; color: #111827; margin-bottom: 14px;
        }

        /* ── ALERT CARDS ── */
        .alert-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
            transition: box-shadow .2s;
        }
        .alert-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }

        .alert-icon {
            width: 44px; height: 44px; flex-shrink: 0;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }

        .alert-body { flex: 1; }
        .alert-title {
            font-weight: 700; font-size: .9rem;
            color: #111827; margin-bottom: 3px;
        }
        .alert-desc { font-size: .8rem; color: #6b7280; line-height: 1.5; margin-bottom: 4px; }
        .alert-time { font-size: .73rem; color: #9ca3af; }

        .empty-state {
            text-align: center; padding: 40px; color: #9ca3af; font-size: .85rem;
        }

        /* ── QUICK BROADCAST ── */
        .broadcast-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 22px;
            margin-top: 24px;
        }
        .broadcast-title {
            font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: 1rem; color: #111827; margin-bottom: 14px;
        }
        .form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 180px; }
        .form-label-s { font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: 5px; display: block; }
        .form-input-s {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-input-s:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        select.form-input-s { cursor: pointer; }

        .custom-field { margin-top: 8px; display: none; }

        .broadcast-textarea {
            width: 100%;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: .85rem;
            font-family: 'Inter', sans-serif;
            color: #374151;
            resize: vertical;
            height: 90px;
            transition: border-color .2s;
            background: #fafafa;
            margin-bottom: 14px;
        }
        .broadcast-textarea:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .broadcast-textarea::placeholder { color: #9ca3af; }

        .btn-send {
            background: #1a3c8f; color: #fff;
            border: none; border-radius: 8px;
            padding: 10px 24px;
            font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer;
            white-space: nowrap;
            transition: background .2s, box-shadow .2s;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-send:hover { background: #152f72; box-shadow: 0 4px 14px rgba(26,60,143,.3); }
        .btn-send:disabled { background: #9ca3af; cursor: not-allowed; box-shadow: none; }

        .push-note {
            font-size: .76rem; color: #6b7280; margin-top: 10px;
            display: flex; align-items: center; gap: 6px;
        }

        /* ── HISTORY TABLE ── */
        .history-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .history-table th {
            padding: 12px 16px; font-weight: 700; color: #374151;
            background: #f9fafb; border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        .history-table td {
            padding: 13px 16px; color: #4b5563;
            border-bottom: 1px solid #f3f4f6; vertical-align: middle;
        }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:hover td { background: #f9fafb; }

        .badge-sent { background: #d1fae5; color: #065f46; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-audience { font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; color: #1f2937; }

        /* Toast */
        .toast-wrap {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        }
    </style>
</head>
<body>

@php
    // Audience the broadcast is targeted at — replaces the old
    // "Alerts / Updates" category. Determines both who receives the
    // push notification (see Admin\AlertController::store) and how
    // it's displayed here.
    $audienceMeta = [
        'Citizens'   => ['emoji' => '👥', 'bg' => '#dbeafe', 'label' => 'Citizens'],
        'Responders' => ['emoji' => '🚨', 'bg' => '#ffedd5', 'label' => 'Responders'],
        'Both'       => ['emoji' => '📢', 'bg' => '#ede9fe', 'label' => 'Both'],
    ];
@endphp

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
        <a href="{{ route('sos-alerts') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}">Map View</a>
        <a href="{{ route('alerts') }}" class="active">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('responder-verification') }}">Responder Verification</a>
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
        <div class="page-title">Alerts &amp; Broadcast</div>

        <!-- TABS -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('active', this)">Active Alerts</button>
            <button class="tab-btn" onclick="switchTab('history', this)">Broadcast History</button>
        </div>

        <!-- ══ TAB: ACTIVE ALERTS ══ -->
        <div id="tab-active" class="tab-panel active">
            <div class="section-title">Recent Broadcasts</div>

           @forelse($recentAlerts->take(5) as $alert)
                @php $meta = $audienceMeta[$alert->type] ?? $audienceMeta['Citizens']; @endphp
                <div class="alert-card">
                    <div class="alert-icon" style="background: {{ $meta['bg'] }};">
                        {{ $meta['emoji'] }}
                    </div>
                    <div class="alert-body">
                        <div class="alert-title">{{ $alert->title }}</div>
                        @if($alert->subtitle)
                            <div class="alert-desc" style="font-weight:600;color:#374151;">{{ $alert->subtitle }}</div>
                        @endif
                        <div class="alert-desc">{{ $alert->body }}</div>
                        <div class="alert-time">{{ $alert->created_at->format('M d, Y g:i A') }} · Sent to {{ $meta['label'] }}</div>
                    </div>
                </div>
            @empty
                <div class="empty-state">No broadcasts sent yet. Use the form below to send your first one.</div>
            @endforelse

            <!-- Quick Broadcast -->
            <div class="broadcast-card">
                <div class="broadcast-title">Quick Broadcast</div>

                <form method="POST" action="{{ route('alerts.store') }}" id="broadcastForm">
                    @csrf

                    @php
                        // Same barangay list used across the citizen-facing app
                        // (register.dart / report_incident.dart) so location
                        // options here stay consistent with citizen records.
                        $barangays = [
                            'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                            'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                            'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                            'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                            'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                            'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                            'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                            'Zone IV (Poblacion)','Zone V (Poblacion)',
                        ];

                        $titlePresets = [
                            'Typhoon Warning',
                            'Heavy Rainfall Advisory',
                            'Flood Warning',
                            'Earthquake Advisory',
                            'Fire Warning',
                            'Landslide Warning',
                            'Heat Index Advisory',
                            'Water Service Interruption',
                            'Power Interruption Notice',
                            'Road Closure Advisory',
                            'Evacuation Order',
                            'All Clear / Warning Lifted',
                        ];

                        // value => message template. Keys are just labels shown
                        // in the picker; selecting one fills the textarea below,
                        // which stays fully editable before sending.
                        $messageTemplates = [
                            'Typhoon / heavy rain' => 'Residents are advised to stay indoors, secure loose objects, and avoid unnecessary travel until the weather advisory is lifted.',
                            'Flood warning' => 'Flooding is expected in low-lying areas. Residents in affected barangays are advised to move to higher ground or the nearest evacuation center.',
                            'Earthquake advisory' => 'A recent earthquake has been recorded in the area. Please check your home and surroundings for damage, and report any injuries or structural damage to MDRRMO.',
                            'Fire warning' => 'A fire incident has been reported. Residents in the vicinity are advised to evacuate calmly and avoid the affected area to allow responders clear access.',
                            'Evacuation order' => 'Please proceed to the nearest designated evacuation center immediately and bring your emergency go-bag. Follow instructions from barangay officials and responders.',
                            'Water interruption' => 'Water service interruption is scheduled today from [start time] to [end time] due to maintenance. Please store enough water in advance.',
                            'Power interruption' => 'A power interruption is scheduled today from [start time] to [end time] due to maintenance work in your area.',
                            'All clear' => 'This is to inform all residents that the previous warning has been lifted. It is now safe to resume normal activities. Stay alert for further advisories.',
                        ];
                    @endphp

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label-s">Alert Title</label>
                            <select class="form-input-s" id="titleSelect" name="title" required
                                    onchange="toggleCustom(this, document.getElementById('titleCustom'), 'title')">
                                <option value="" disabled selected>Select a title...</option>
                                @foreach($titlePresets as $preset)
                                    <option value="{{ $preset }}">{{ $preset }}</option>
                                @endforeach
                                <option value="__custom__">Other (type custom title)</option>
                            </select>
                            <input type="text" id="titleCustom" class="form-input-s custom-field"
                                   placeholder="Enter custom alert title">
                        </div>

                        <div class="form-group">
                            <label class="form-label-s">Send To</label>
                            <select name="type" class="form-input-s" required>
                                <option value="Citizens">Citizens</option>
                                <option value="Responders">Responders</option>
                                <option value="Both">Both</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label-s">Location / Barangay</label>
                            <select class="form-input-s" id="locationSelect" name="subtitle"
                                    onchange="toggleCustom(this, document.getElementById('locationCustom'), 'subtitle')">
                                <option value="" selected>-- Optional --</option>
                                <option value="Rosales, Pangasinan">Rosales, Pangasinan (Municipality-wide)</option>
                                @foreach($barangays as $brgy)
                                    <option value="Brgy. {{ $brgy }}, Rosales">Brgy. {{ $brgy }}</option>
                                @endforeach
                                <option value="__custom__">Other (type custom location)</option>
                            </select>
                            <input type="text" id="locationCustom" class="form-input-s custom-field"
                                   placeholder="Enter custom location">
                        </div>
                    </div>

                    <label class="form-label-s">Quick Message Template (optional)</label>
                    <select class="form-input-s" style="margin-bottom:10px;"
                            onchange="applyMessageTemplate(this.value)">
                        <option value="" selected>-- Select a template to auto-fill the message, then edit as needed --</option>
                        @foreach($messageTemplates as $label => $template)
                            <option value="{{ $template }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <label class="form-label-s">Message</label>
                    <textarea id="messageBody" name="body" class="broadcast-textarea" placeholder="Type your message here, or pick a template above..." required></textarea>

                    <button type="submit" class="btn-send">
                        <i class="bi bi-broadcast"></i> Send Broadcast
                    </button>


                    <div class="push-note">
                        <i class="bi bi-info-circle"></i>
                        This sends a real push notification to whichever audience you select above, and adds it to their Alerts feed.
                    </div>
                </form>
            </div>
        </div>

        <!-- ══ TAB: BROADCAST HISTORY ══ -->
        <div id="tab-history" class="tab-panel">
            <div class="section-title">Broadcast History</div>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Sent To</th>
                            <th>Sent By</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($alerts as $alert)
                        @php $meta = $audienceMeta[$alert->type] ?? $audienceMeta['Citizens']; @endphp
                        <tr>
                            <td style="font-weight:600;color:#111827;">{{ $alert->title }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($alert->body, 60) }}</td>
                            <td>
                                <span class="badge-audience" style="background: {{ $meta['bg'] }};">
                                    {{ $meta['emoji'] }} {{ $meta['label'] }}
                                </span>
                            </td>
                            <td>{{ $alert->user->name ?? 'MDRRMO Admin' }}</td>
                            <td>{{ $alert->created_at->format('M d, Y g:i A') }}</td>
                            <td><span class="badge-sent">Sent</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="empty-state">No broadcast history yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div>

<!-- Toast -->
<div class="toast-wrap">
    <div id="toastMsg" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="toastText">{{ session('success') }}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
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

    // Swaps a preset <select> for a free-text <input> (and back) when the
    // person picks "Other (custom)". Only whichever field is visible carries
    // the real field name, so the form still submits a single clean value.
    function toggleCustom(selectEl, customEl, fieldName) {
        if (selectEl.value === '__custom__') {
            selectEl.removeAttribute('name');
            customEl.name = fieldName;
            customEl.style.display = 'block';
            customEl.required = fieldName === 'title'; // location stays optional
            customEl.value = '';
            customEl.focus();
        } else {
            selectEl.name = fieldName;
            customEl.removeAttribute('name');
            customEl.style.display = 'none';
            customEl.required = false;
        }
    }

    // Fills the message textarea with the chosen template. Left fully
    // editable afterward — nothing here locks the field.
    function applyMessageTemplate(template) {
        if (!template) return;
        document.getElementById('messageBody').value = template;
    }

    @if(session('success'))
        document.addEventListener('DOMContentLoaded', function () {
            const toast = new bootstrap.Toast(document.getElementById('toastMsg'));
            toast.show();
        });
    @endif
</script>
<script>
    const broadcastForm = document.getElementById('broadcastForm');

    broadcastForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const audience = this.querySelector('select[name="type"]').value;
        const audienceLabel = audience === 'Both'
            ? 'citizens and responders'
            : audience.toLowerCase();

        Swal.fire({
            icon: 'question',
            title: 'Send this broadcast?',
            text: `This will send a real push notification to ${audienceLabel}.`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#1a3c8f',
            cancelButtonColor: '#6b7280',
        }).then((result) => {
            if (result.isConfirmed) {
                broadcastForm.submit();
            }
        });
    });
</script>
</body>
</html>