<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Residents Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            margin-bottom: 6px;
        }
        .page-title {
            font-family: 'Barlow', sans-serif; font-weight: 800;
            font-size: 1.6rem; color: #111827;
        }
        .page-sub { font-size: .82rem; color: #6b7280; margin-bottom: 20px; }

        /* ── TABS (same pattern as Incidents / SOS Alerts) ── */
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
        .td-sub { font-size: .76rem; color: #9ca3af; }

        /* ID thumbnail */
        .id-thumb {
            width: 56px; height: 38px; object-fit: cover;
            border-radius: 6px; border: 1px solid #e5e7eb; cursor: pointer;
        }
        .id-none { font-size: .76rem; color: #d1d5db; font-style: italic; }

        /* Status badge */
        .badge-pending  { background: #fef3c7; color: #92400e; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .badge-verified { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .badge-rejected { background: #fee2e2; color: #991b1b; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }

        /* Action buttons */
        .action-btn {
            background: none; border: none; cursor: pointer;
            color: #9ca3af; font-size: 1rem; padding: 5px 6px;
            border-radius: 6px; transition: color .2s, background .2s;
        }
        .action-btn:hover { color: #1a3c8f; background: #eff2fb; }
        .action-btn.approve:hover { color: #10b981; background: #d1fae5; }
        .action-btn.reject:hover { color: #ef4444; background: #fee2e2; }

        .empty-state { text-align: center; padding: 40px; color: #9ca3af; font-size: .85rem; }

        /* Modal */
        .modal-title-custom { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.1rem; }
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .form-control-m {
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
        .btn-reject-confirm {
            background: #ef4444; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-reject-confirm:hover { background: #dc2626; }
        .id-preview-img { width: 100%; border-radius: 10px; border: 1px solid #e5e7eb; }
    
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="bottom-tab-bar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn" class="active"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
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
        <a href="{{ route('sos-alerts') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i> Map View</a>
        <a href="{{ route('alerts') }}"><i class="bi bi-megaphone-fill"></i> Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}"><i class="bi bi-house-heart-fill"></i> Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}" class="active"><i class="bi bi-person-check-fill"></i> Residents Verification</a>
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

<div class="main-wrap">
    <div class="content">

        <div class="page-header">
            <div class="page-title">Residents Verification</div>
        </div>
        <div class="page-sub">Review uploaded IDs and confirm residency before marking accounts verified.</div>

        @php
            // Rejected citizens are deleted immediately in the controller
            // (CitizenVerificationController::reject), so in practice this
            // only ever splits into "still needs review" vs "verified" —
            // but the badge/status logic stays generic in case that
            // behavior changes later.
            $pendingCitizens  = $citizens->where('verification_status', '!=', 'verified')->values();
            $verifiedCitizens = $citizens->where('verification_status', '=', 'verified')->values();
        @endphp

        <!-- TABS -->
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('pending', this)">Pending Review ({{ $pendingCitizens->count() }})</button>
            <button class="tab-btn" onclick="switchTab('verified', this)">Verified ({{ $verifiedCitizens->count() }})</button>
        </div>

        @php
            // Shared row markup, rendered once per tab via a Blade component-ish closure.
            $renderTable = function ($rows, $emptyMessage) {
                return view('partials.citizen-verification-table', [
                    'citizens' => $rows,
                    'emptyMessage' => $emptyMessage,
                ])->render();
            };
        @endphp

        <!-- ══ TAB: PENDING REVIEW ══ -->
        <div id="tab-pending" class="tab-panel active">
            <div class="table-card">
                <table class="evac-table">
                    <thead>
                        <tr>
                            <th>Citizen</th>
                            <th>Contact</th>
                            <th>Municipality / Barangay</th>
                            <th>Valid ID</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingCitizens as $citizen)
                        <tr>
                            <td class="td-name">{{ $citizen->full_name }}</td>
                            <td>
                                {{ $citizen->email }}
                                @if($citizen->mobile)
                                    <div class="td-sub">{{ $citizen->mobile }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $citizen->municipality ?? '—' }}
                                @if($citizen->barangay)
                                    <div class="td-sub">Brgy. {{ $citizen->barangay }}</div>
                                @endif
                            </td>
                            <td>
                                @if($citizen->valid_id_path)
                                    <img src="{{ Storage::url($citizen->valid_id_path) }}"
                                         class="id-thumb"
                                         data-bs-toggle="modal"
                                         data-bs-target="#idModal{{ $citizen->id }}"
                                         alt="ID">
                                @else
                                    <span class="id-none">No ID uploaded</span>
                                @endif
                            </td>
                            <td>{{ $citizen->created_at->format('M d, Y') }}</td>
                            <td>
                                @if($citizen->verification_status === 'rejected')
                                    <span class="badge-rejected">Rejected</span>
                                @else
                                    <span class="badge-pending">Pending</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('citizen-verification.approve', $citizen) }}" method="POST" style="display:inline;" class="approve-form">
                                    @csrf
                                    <button type="submit" class="action-btn approve" title="Approve">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </form>
                                <button class="action-btn reject" title="Reject"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $citizen->id }}">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="empty-state">
                                No citizens waiting for review right now.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ TAB: VERIFIED ══ -->
        <div id="tab-verified" class="tab-panel">
            <div class="table-card">
                <table class="evac-table">
                    <thead>
                        <tr>
                            <th>Citizen</th>
                            <th>Contact</th>
                            <th>Municipality / Barangay</th>
                            <th>Valid ID</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($verifiedCitizens as $citizen)
                        <tr>
                            <td class="td-name">{{ $citizen->full_name }}</td>
                            <td>
                                {{ $citizen->email }}
                                @if($citizen->mobile)
                                    <div class="td-sub">{{ $citizen->mobile }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $citizen->municipality ?? '—' }}
                                @if($citizen->barangay)
                                    <div class="td-sub">Brgy. {{ $citizen->barangay }}</div>
                                @endif
                            </td>
                            <td>
                                @if($citizen->valid_id_path)
                                    <img src="{{ Storage::url($citizen->valid_id_path) }}"
                                         class="id-thumb"
                                         data-bs-toggle="modal"
                                         data-bs-target="#idModal{{ $citizen->id }}"
                                         alt="ID">
                                @else
                                    <span class="id-none">No ID uploaded</span>
                                @endif
                            </td>
                            <td>{{ $citizen->created_at->format('M d, Y') }}</td>
                            <td><span class="badge-verified">Verified</span></td>
                            <td>
                                <button class="action-btn reject" title="Reject"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $citizen->id }}">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="empty-state">
                                No verified citizens yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modals live OUTSIDE the tables on purpose — see the original
             note: a <form> placed directly inside <tbody> is invalid HTML
             and browsers "foster parent" it, breaking the form's internal
             structure. Loop over ALL citizens once here so both tabs'
             rows can open the right modal by id. --}}
        @foreach($citizens as $citizen)
            <div class="modal fade" id="idModal{{ $citizen->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:14px;border:none;">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title-custom">{{ $citizen->full_name }}'s ID</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-2">
                            @if($citizen->valid_id_path)
                                <img src="{{ Storage::url($citizen->valid_id_path) }}" class="id-preview-img" alt="Valid ID">
                            @endif
                            <p style="font-size:.8rem;color:#6b7280;margin-top:12px;">
                                Confirm the ID is genuine and shows an address within
                                <strong>{{ $citizen->municipality }}</strong> before approving.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="rejectModal{{ $citizen->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:14px;border:none;">
                        <form action="{{ route('citizen-verification.reject', $citizen) }}" method="POST" class="reject-form">
                            @csrf
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title-custom">Reject {{ $citizen->full_name }}'s ID</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body pt-3">
                                <label class="form-label-m">Reason for rejection</label>
                                <select name="rejection_reason" class="form-control-m">
                                    <option value="" disabled selected style="color:#9ca3af;">Select a reason...</option>
                                    <option value="Address on ID does not match registered locality">Address on ID does not match registered locality</option>
                                    <option value="ID photo/text is blurry or unreadable">ID photo/text is blurry or unreadable</option>
                                    <option value="Submitted document is expired">Submitted document is expired</option>
                                    <option value="Invalid ID type (Document not accepted)">Invalid ID type (Document not accepted)</option>
                                    <option value="Suspected fraudulent or edited document">Suspected fraudulent or edited document</option>
                                    <option value="Name on ID does not match registered account name">Name on ID does not match registered account name</option>
                                </select>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="submit" class="btn-reject-confirm">Reject</button>
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab switching — same pattern as incident.blade.php / sos-alerts.blade.php
    function switchTab(id, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + id).classList.add('active');
        btn.classList.add('active');
    }

    const swalTheme = {
        confirmButtonColor: '#1a3c8f',
        cancelButtonColor: '#6b7280',
        buttonsStyling: true,
        customClass: {
            popup: 'rounded-4',
            confirmButton: 'fw-bold',
            cancelButton: 'fw-bold',
        },
    };

    // Approve — simple yes/no, no reason needed
    document.querySelectorAll('.approve-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                ...swalTheme,
                icon: 'question',
                title: 'Approve this citizen?',
                text: 'This confirms their identity and unlocks full access to the app.',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    // Reject — the Bootstrap modal already collects a reason via <select>;
    // close that modal first, THEN show the SweetAlert (firing Swal while a
    // Bootstrap modal is still open can cause stacking/focus-trap issues),
    // and do one final destructive-action confirm before the account is
    // deleted and tokens revoked.
    document.querySelectorAll('.reject-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const reasonSelect = form.querySelector('select[name="rejection_reason"]');
            if (!reasonSelect.value) {
                Swal.fire({
                    ...swalTheme,
                    icon: 'warning',
                    title: 'Reason required',
                    text: 'Please select a reason for rejection before continuing.',
                    confirmButtonText: 'OK',
                });
                return;
            }

            const parentModalEl = form.closest('.modal');
            const bsModal = parentModalEl ? bootstrap.Modal.getOrCreateInstance(parentModalEl) : null;

            const confirmReject = () => {
                Swal.fire({
                    ...swalTheme,
                    icon: 'warning',
                    title: 'Reject this account?',
                    html: 'This will <strong>permanently delete</strong> this citizen\'s account and revoke their login immediately. This cannot be undone.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reject & Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                }).then(result => {
                    if (result.isConfirmed) form.submit();
                });
            };

            if (bsModal && parentModalEl.classList.contains('show')) {
                // Wait for the modal to finish closing before opening Swal
                parentModalEl.addEventListener('hidden.bs.modal', confirmReject, { once: true });
                bsModal.hide();
            } else {
                confirmReject();
            }
        });
    });

    @if(session('success'))
        Swal.fire({
            ...swalTheme,
            icon: 'success',
            title: 'Done',
            text: @json(session('success')),
            timer: 3000,
            showConfirmButton: false,
        });
    @endif
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