v<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Evacuation Centers</title>
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
            display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500;
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
            display: flex; align-items: center; gap: 8px; font-size: .82rem;
            color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s;
        }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }

        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-title {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: 1.6rem; color: #111827;
        }
        .btn-add {
            background: #1a3c8f; color: #fff; border: none;
            border-radius: 8px; padding: 9px 18px;
            font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem;
            cursor: pointer; display: flex; align-items: center; gap: 6px;
            transition: background .2s, box-shadow .2s;
        }
        .btn-add:hover { background: #152f72; box-shadow: 0 4px 14px rgba(26,60,143,.3); }

        /* ── TABLE ── */
        .table-card {
            background: #fff; border-radius: 14px;
            border: 1px solid #e5e7eb; overflow: hidden;
        }
        .evac-table { width: 100%; border-collapse: collapse; }
        .evac-table thead tr { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        .evac-table th {
            padding: 14px 20px; font-size: .8rem; font-weight: 700;
            color: #374151; text-align: left; white-space: nowrap; letter-spacing: .3px;
        }
        .evac-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
        .evac-table tbody tr:last-child { border-bottom: none; }
        .evac-table tbody tr:hover { background: #f9fafb; }
        .evac-table td {
            padding: 16px 20px; font-size: .83rem;
            color: #4b5563; vertical-align: middle;
        }
        .td-name { font-weight: 600; color: #111827; }

        /* Occupancy bar */
        .occ-wrap { display: flex; align-items: center; gap: 10px; }
        .occ-bar {
            flex: 1; height: 6px; background: #e5e7eb;
            border-radius: 99px; overflow: hidden; min-width: 60px;
        }
        .occ-fill { height: 100%; border-radius: 99px; transition: width .4s; }
        .occ-low    { background: #10b981; }
        .occ-mid    { background: #f59e0b; }
        .occ-high   { background: #ef4444; }

        /* Status badge */
        .badge-open   { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .badge-full   { background: #fee2e2; color: #991b1b; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .badge-closed { background: #f3f4f6; color: #6b7280; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }

        /* Action buttons */
        .action-btn {
            background: none; border: none; cursor: pointer;
            color: #9ca3af; font-size: 1rem; padding: 5px 6px;
            border-radius: 6px; transition: color .2s, background .2s;
        }
        .action-btn:hover { color: #1a3c8f; background: #eff2fb; }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }

        /* Modal */
        .modal-title-custom {
            font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.1rem;
        }
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .form-control-m, select.form-control-m {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-control-m:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .btn-save {
            background: #1a3c8f; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #152f72; }
        .btn-save:disabled { background: #9ca3af; cursor: not-allowed; }

        .geocode-status {
            font-size: .76rem; color: #6b7280; margin-top: 6px;
            display: flex; align-items: center; gap: 6px;
        }
        .geocode-status.resolved { color: #065f46; }
        .geocode-status .spinner-border-sm { width: .85rem; height: .85rem; }
    </style>
</head>
<body>

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
        <a href="{{ route('alerts') }}">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}" class="active">Evacuation Centers</a>
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

<div class="main-wrap">
    <div class="content">

        <div class="page-header">
            <div class="page-title">Evacuation Centers</div>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg"></i> Add Center
            </button>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <strong>Couldn't save:</strong>
                <ul style="margin:6px 0 0;padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-card">
            <table class="evac-table">
                <thead>
                    <tr>
                        <th>Center Name</th>
                        <th>Barangay</th>
                        <th>Distance from HQ</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centers as $c)
                    @php
                        $badge = 'badge-' . $c->status;
                        $label = ucfirst($c->status);
                        $dist  = $c->distance_from_hq;
                    @endphp
                    <tr>
                        <td class="td-name">{{ $c->name }}</td>
                        <td>{{ $c->barangay }}</td>
                        <td>{{ $dist !== null ? $dist . ' km' : '—' }}</td>
                        <td><span class="{{ $badge }}">{{ $label }}</span></td>
                        <td>
                            <button class="action-btn" title="View details" data-bs-toggle="modal" data-bs-target="#viewModal{{ $c->id }}">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                No evacuation centers added yet. Use "Add Center" to add the first one.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- View-location modals — kept outside <table> since a modal/form
            isn't valid inside <tbody> (browsers "foster parent" it, breaking
            the DOM), same pattern as citizen-verification.blade.php --}}
        @foreach($centers as $c)
        <div class="modal fade" id="viewModal{{ $c->id }}" tabindex="-1"
            data-lat="{{ $c->latitude }}" data-lng="{{ $c->longitude }}"
            data-map-id="viewMap{{ $c->id }}" data-name="{{ $c->name }}">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:14px;border:none;">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title-custom">{{ $c->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <div id="viewMap{{ $c->id }}" style="width:100%;height:220px;border-radius:10px;margin-bottom:14px;background:#eef1f6;"></div>

                        <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem;color:#374151;">
                            <div><i class="bi bi-geo-alt-fill" style="color:#1a3c8f;width:16px;"></i> Brgy. {{ $c->barangay }}</div>
                            <div><i class="bi bi-signpost-split" style="color:#1a3c8f;width:16px;"></i>
                                {{ $c->distance_from_hq !== null ? $c->distance_from_hq . ' km from MDRRMO HQ' : 'Distance unavailable' }}
                            </div>
                            <div><span class="badge-{{ $c->status }}">{{ ucfirst($c->status) }}</span></div>
                        </div>

                        <a href="https://www.google.com/maps?q={{ $c->latitude }},{{ $c->longitude }}" target="_blank"
                        style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px;width:100%;
                                border:1.5px solid #d1d5db;border-radius:8px;padding:9px;font-size:.82rem;font-weight:600;
                                color:#374151;text-decoration:none;">
                            <i class="bi bi-map"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

    </div>
</div>

<!-- ════ ADD CENTER MODAL ════ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('evacuation.store') }}" id="addCenterForm">
                @csrf
                <input type="hidden" name="latitude" id="centerLat" value="15.8952">
                <input type="hidden" name="longitude" id="centerLng" value="120.6263">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Add Evacuation Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-m">Center Name</label>
                            <input type="text" name="name" class="form-control-m" placeholder="e.g. Rosales Central School" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label-m">Barangay</label>
                            <select name="barangay" id="barangaySelect" class="form-control-m" required>
                                <option value="" disabled selected>Select a barangay...</option>
                                @foreach ([
                                    'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                                    'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                                    'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                                    'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                                    'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                                    'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                                    'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                                    'Zone IV (Poblacion)','Zone V (Poblacion)',
                                ] as $brgy)
                                    <option value="{{ $brgy }}">{{ $brgy }}</option>
                                @endforeach
                            </select>
                            <div class="geocode-status" id="geocodeStatus">
                                <i class="bi bi-geo-alt"></i> Select a barangay to auto-locate the center on the map.
                            </div>
                        </div>

                        <div class="col-6">
                            <label class="form-label-m">Status</label>
                            <select name="status" class="form-control-m" required>
                                <option value="open">Open</option>
                                <option value="full">Full</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn-save">Save Center</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Static, verified barangay centroids — computed from official PSGC
    // boundary polygons, same table used by the citizen app's report form
    // (report_incident.dart). No live geocoding: every barangay listed
    // in the dropdown above has a known-accurate coordinate here, so
    // there's no "couldn't pinpoint" fallback path anymore.
    const BARANGAY_COORDINATES = {
        'Acop': { lat: 15.867531, lng: 120.652154 },
        'Bakitbakit': { lat: 15.878026, lng: 120.650273 },
        'Balingcanaway': { lat: 15.892046, lng: 120.651996 },
        'Cabalaoangan Norte': { lat: 15.884509, lng: 120.626134 },
        'Cabalaoangan Sur': { lat: 15.876132, lng: 120.633595 },
        'Calanutan': { lat: 15.864204, lng: 120.633487 },
        'Camangaan': { lat: 15.849665, lng: 120.635914 },
        'Capitan Tomas': { lat: 15.908782, lng: 120.644112 },
        'Carmay East': { lat: 15.914305, lng: 120.637743 },
        'Carmay West': { lat: 15.911984, lng: 120.625233 },
        'Carmen East': { lat: 15.891492, lng: 120.601517 },
        'Carmen West': { lat: 15.888648, lng: 120.594245 },
        'Casanicolasan': { lat: 15.924801, lng: 120.642405 },
        'Coliling': { lat: 15.854561, lng: 120.620069 },
        'Don Antonio Village': { lat: 15.899285, lng: 120.621318 },
        'Guiling': { lat: 15.849206, lng: 120.621911 },
        'Palakipak': { lat: 15.862663, lng: 120.617815 },
        'Pangaoan': { lat: 15.838011, lng: 120.641362 },
        'Rabago': { lat: 15.856993, lng: 120.634730 },
        'Rizal': { lat: 15.923404, lng: 120.630220 },
        'Salvacion': { lat: 15.846088, lng: 120.659706 },
        'San Angel': { lat: 15.860160, lng: 120.656112 },
        'San Antonio': { lat: 15.853902, lng: 120.658100 },
        'San Bartolome': { lat: 15.875859, lng: 120.611195 },
        'San Isidro': { lat: 15.838424, lng: 120.623979 },
        'San Luis': { lat: 15.842845, lng: 120.650298 },
        'San Pedro East': { lat: 15.896897, lng: 120.645530 },
        'San Pedro West': { lat: 15.896184, lng: 120.636969 },
        'San Vicente': { lat: 15.847728, lng: 120.671759 },
        'Station District': { lat: 15.892017, lng: 120.620915 },
        'Tomana East': { lat: 15.895439, lng: 120.613376 },
        'Tomana West': { lat: 15.892375, lng: 120.608154 },
        'Zone I (Poblacion)': { lat: 15.888483, lng: 120.623073 },
        'Zone II (Poblacion)': { lat: 15.897089, lng: 120.629352 },
        'Zone III (Poblacion)': { lat: 15.904627, lng: 120.621300 },
        'Zone IV (Poblacion)': { lat: 15.904928, lng: 120.629206 },
        'Zone V (Poblacion)': { lat: 15.890244, lng: 120.629699 },
    };

    const latInput = document.getElementById('centerLat');
    const lngInput = document.getElementById('centerLng');
    const statusEl = document.getElementById('geocodeStatus');
    const barangaySelect = document.getElementById('barangaySelect');

    barangaySelect.addEventListener('change', () => {
        const barangay = barangaySelect.value;
        const coords = BARANGAY_COORDINATES[barangay];

        if (!coords) {
            // Should never happen — every option in the dropdown exists
            // in the table above — but guard against it anyway.
            statusEl.classList.remove('resolved');
            statusEl.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Unrecognized barangay.`;
            return;
        }

        latInput.value = coords.lat;
        lngInput.value = coords.lng;
        statusEl.classList.add('resolved');
        statusEl.innerHTML = `<i class="bi bi-geo-alt-fill"></i> Pinned at Brgy. ${barangay} (${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)})`;
    });

    // Lazy-init each view-location map only when its modal actually opens —
    // initializing Leaflet inside a display:none element gives a blank/grey
    // map, and invalidateSize() after a short delay fixes the tile sizing
    // once the modal transition finishes.
    document.querySelectorAll('[id^="viewModal"]').forEach(modalEl => {
        let map = null;
        modalEl.addEventListener('shown.bs.modal', function () {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            const mapId = this.dataset.mapId;
            const name = this.dataset.name;

            if (!lat || !lng) return;

            if (!map) {
                map = L.map(mapId).setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
                }).addTo(map);
                L.marker([lat, lng]).addTo(map).bindPopup(name).openPopup();
            }
            setTimeout(() => map.invalidateSize(), 200);
        });
    });
</script>
@include('partials.sos-alert-overlay')
</body>
</html>