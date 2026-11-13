<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Evacuation Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 250px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12); }
        .sidebar-brand img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.3); }
        .sidebar-brand-text .title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .85rem; color: #fff; letter-spacing: .5px; line-height: 1.1; }
        .sidebar-brand-text .sub { font-size: .65rem; color: rgba(255,255,255,.6); letter-spacing: .5px; }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a { display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75); text-decoration: none; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s; }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 10px; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.6rem; color: #111827; display: flex; align-items: center; gap: 10px; }
        .page-sub { font-size: .84rem; color: #6b7280; margin-bottom: 22px; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #0d2e7a; }

        .btn-add { background: #1a3c8f; color: #fff; border: none; border-radius: 8px; padding: 9px 18px; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background .2s, box-shadow .2s; }
        .btn-add:hover { background: #152f72; box-shadow: 0 4px 14px rgba(26,60,143,.3); }

        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-select {
            font-size: .82rem; font-weight: 600; color: #374151;
            border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 8px 12px; background: #fff; cursor: pointer;
        }
        .filter-select:hover { border-color: #1a3c8f; }
        .results-count { font-size: .82rem; color: #6b7280; margin-bottom: 10px; }

        .table-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table thead tr { background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .log-table th { padding: 14px 18px; font-size: .78rem; font-weight: 700; color: #374151; text-align: left; white-space: nowrap; letter-spacing: .3px; }
        .log-table tbody tr { border-bottom: 1px solid #f3f4f6; }
        .log-table tbody tr:last-child { border-bottom: none; }
        .log-table td { padding: 12px 18px; font-size: .82rem; color: #4b5563; vertical-align: middle; }
        .td-name { font-weight: 600; color: #111827; }

        .badge-gender-male   { background: #dbeafe; color: #1e40af; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-gender-female { background: #fce7f3; color: #9d174d; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }

        .modal-title-custom { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.1rem; }
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .form-control-m, select.form-control-m {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-control-m:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .btn-save { background: #1a3c8f; color: #fff; border: none; border-radius: 8px; padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .88rem; cursor: pointer; transition: background .2s; }
        .btn-save:hover { background: #152f72; }

        @media (max-width: 560px) {
            .filter-bar { flex-direction: column !important; align-items: stretch !important; }
            .filter-bar > * { width: 100% !important; max-width: 100% !important; }
        }

    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
@include('partials.sidebar')

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        @php
            $barangayList = [
                'Acop','Bakitbakit','Balingcanaway','Cabalaoangan Norte','Cabalaoangan Sur',
                'Calanutan','Camangaan','Capitan Tomas','Carmay East','Carmay West',
                'Carmen East','Carmen West','Casanicolasan','Coliling','Don Antonio Village',
                'Guiling','Palakipak','Pangaoan','Rabago','Rizal','Salvacion','San Angel',
                'San Antonio','San Bartolome','San Isidro','San Luis','San Pedro East',
                'San Pedro West','San Vicente','Station District','Tomana East','Tomana West',
                'Zone I (Poblacion)','Zone II (Poblacion)','Zone III (Poblacion)',
                'Zone IV (Poblacion)','Zone V (Poblacion)',
            ];
        @endphp

        <div class="page-header">
            <div class="page-title"><i class="bi bi-clipboard2-pulse" style="color:#1a3c8f;"></i> Evacuation Log — {{ $center->name }}</div>
            <a href="{{ route('evacuation') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Evacuation Centers</a>
        </div>
        <div class="page-sub">
            Evacuees logged by MSWD responders in the field, one row per person. Arrival only — this log doesn't track when someone leaves. Entries are created from the responder app, not here — this page is for review only.
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

        <div class="page-header" style="margin-bottom:16px;">
            <div class="filter-bar" style="margin-bottom:0;">
                <select class="filter-select" id="filterBarangay">
                    <option value="">All Barangays</option>
                    @foreach($barangayList as $brgy)
                        <option value="{{ $brgy }}">{{ $brgy }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="results-count" id="resultsCount"></div>

        <div class="table-card">
            @if($evacuees->isEmpty())
                <div class="empty-state">No evacuees logged at this center yet.</div>
            @else
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th>Logged</th>
                        </tr>
                    </thead>
                    <tbody id="logBody">
                        @foreach($evacuees as $e)
                            <tr data-barangay="{{ $e->barangay }}">
                                <td class="td-name">{{ $e->full_name }}</td>
                                <td>
                                    <span class="{{ $e->gender === 'Male' ? 'badge-gender-male' : 'badge-gender-female' }}">
                                        {{ $e->gender }}
                                    </span>
                                </td>
                                <td>{{ $e->age }}</td>
                                <td>{{ $e->barangay }}</td>
                                <td>{{ $e->contact_number ?: '—' }}</td>
                                <td>{{ $e->created_at->format('M d, g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Exact-match barangay filter — unlike the Incidents page's substring
    // match, barangay here is always picked from the same fixed dropdown
    // when an entry is logged, never free-typed, so exact matching is
    // reliable and simpler.
    (function () {
        const barangayFilter = document.getElementById('filterBarangay');
        const rows = Array.from(document.querySelectorAll('#logBody tr'));
        const resultsCount = document.getElementById('resultsCount');
        const totalCount = rows.length;

        if (!barangayFilter || !resultsCount) return;

        function applyFilter() {
            const barangay = barangayFilter.value;
            let visible = 0;

            rows.forEach(row => {
                const ok = !barangay || row.dataset.barangay === barangay;
                row.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> evacuees`;
        }

        barangayFilter.addEventListener('change', applyFilter);
        applyFilter();
    })();
</script>
@include('partials.sos-alert-overlay')

</body>
</html>