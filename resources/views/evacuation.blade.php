<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Evacuation Centers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 170px; min-height: 100vh; background: #1a3c8f;
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
        .main-wrap { margin-left: 170px; flex: 1; display: flex; flex-direction: column; }
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
                        <td>
                        </td>
                        <td>{{ $dist !== null ? $dist . 'km' : '—' }}</td>
                        <td><span class="{{ $badge }}">{{ $label }}</span></td>
                        <td>
                            <button class="action-btn" title="View details"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                No evacuation centers added yet. Use "Add Center" to add the first one.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

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
                                    'Poblacion','Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
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
<script>
    // ── Barangay → coordinates, via Nominatim (OpenStreetMap), same pattern
    // used elsewhere in the app for citizen registration / incident location.
    // Cached in-memory per barangay, with a Rosales town-center fallback if
    // the lookup fails or returns nothing.
    const ROSALES_FALLBACK = { lat: 15.8952, lng: 120.6263 };
    const geocodeCache = {};

    const latInput = document.getElementById('centerLat');
    const lngInput = document.getElementById('centerLng');
    const statusEl = document.getElementById('geocodeStatus');
    const barangaySelect = document.getElementById('barangaySelect');

    async function geocodeBarangay(barangay) {
        if (geocodeCache[barangay]) return geocodeCache[barangay];

        const query = encodeURIComponent(`${barangay}, Rosales, Pangasinan, Philippines`);
        const url = `https://nominatim.openstreetmap.org/search?q=${query}&format=json&limit=1`;

        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();

        if (Array.isArray(data) && data.length > 0) {
            const coords = { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
            geocodeCache[barangay] = coords;
            return coords;
        }

        throw new Error('No results');
    }

    barangaySelect.addEventListener('change', async () => {
        const barangay = barangaySelect.value;
        if (!barangay) return;

        statusEl.classList.remove('resolved');
        statusEl.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Locating ${barangay}...`;

        try {
            const coords = await geocodeBarangay(barangay);
            latInput.value = coords.lat;
            lngInput.value = coords.lng;
            statusEl.classList.add('resolved');
            statusEl.innerHTML = `<i class="bi bi-geo-alt-fill"></i> Located at ${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)}`;
        } catch (e) {
            latInput.value = ROSALES_FALLBACK.lat;
            lngInput.value = ROSALES_FALLBACK.lng;
            statusEl.classList.remove('resolved');
            statusEl.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Couldn't pinpoint ${barangay} — using Rosales town center. You can fine-tune this later.`;
        }
    });
</script>
@include('partials.sos-alert-overlay')
</body>
</html>