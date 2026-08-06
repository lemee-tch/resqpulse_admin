<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Responder Verification</title>
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
        .page-sub { font-size: .82rem; color: #6b7280; margin-top: 2px; }

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

        .agency-badge {
            font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
            background: #eef2ff; color: #4338ca;
        }

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
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('responder-verification') }}" class="active">Responder Verification</a>
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
            <div>
                <div class="page-title">Responder Verification</div>
                <div class="page-sub">Review uploaded badge/ID photos and confirm agency credentials before marking accounts verified.</div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">{{ session('success') }}</div>
        @endif

        <div class="table-card">
            <table class="evac-table">
                <thead>
                    <tr>
                        <th>Responder</th>
                        <th>Contact</th>
                        <th>Agency / Badge #</th>
                        <th>Badge / ID Photo</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($responders as $responder)
                    <tr>
                        <td class="td-name">{{ $responder->full_name }}</td>
                        <td>
                            {{ $responder->email }}
                            @if($responder->mobile)
                                <div class="td-sub">{{ $responder->mobile }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="agency-badge">{{ $responder->agency }}</span>
                            <div class="td-sub">
                                #{{ $responder->badge_number }}
                                @if($responder->unit_station)
                                    · {{ $responder->unit_station }}
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($responder->valid_id_path)
                                <img src="{{ Storage::url($responder->valid_id_path) }}"
                                     class="id-thumb"
                                     data-bs-toggle="modal"
                                     data-bs-target="#idModal{{ $responder->id }}"
                                     alt="ID">
                            @else
                                <span class="id-none">No photo uploaded</span>
                            @endif
                        </td>
                        <td>{{ $responder->created_at->format('M d, Y') }}</td>
                        <td>
                            @if($responder->verification_status === 'verified')
                                <span class="badge-verified">Verified</span>
                            @elseif($responder->verification_status === 'rejected')
                                <span class="badge-rejected">Rejected</span>
                            @else
                                <span class="badge-pending">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($responder->verification_status !== 'verified')
                                <form action="{{ route('responder-verification.approve', $responder) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="action-btn approve" title="Approve">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </form>
                            @endif
                            @if($responder->verification_status !== 'rejected')
                                <button class="action-btn reject" title="Reject"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $responder->id }}">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            @endif
                        </td>
                    </tr>

                    <div class="modal fade" id="idModal{{ $responder->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content" style="border-radius:14px;border:none;">
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title-custom">{{ $responder->full_name }}'s Badge/ID</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pt-2">
                                    @if($responder->valid_id_path)
                                        <img src="{{ Storage::url($responder->valid_id_path) }}" class="id-preview-img" alt="Badge/ID">
                                    @endif
                                    <p style="font-size:.8rem;color:#6b7280;margin-top:12px;">
                                        Confirm the badge/ID is genuine and matches
                                        <strong>{{ $responder->agency }}</strong> badge #{{ $responder->badge_number }}
                                        before approving.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="rejectModal{{ $responder->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content" style="border-radius:14px;border:none;">
                                <form action="{{ route('responder-verification.reject', $responder) }}" method="POST">
                                    @csrf
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title-custom">Reject {{ $responder->full_name }}?</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body pt-3">
                                        <p style="font-size:.8rem;color:#6b7280;margin-bottom:12px;">
                                            This permanently removes their account and notifies them by email. This can't be undone.
                                        </p>
                                        <label class="form-label-m">Reason for rejection</label>
                                        <select name="rejection_reason" class="form-control-m" required>
                                            <option value="" disabled selected style="color:#9ca3af;">Select a reason...</option>
                                            <option value="Badge/ID photo is blurry or unreadable">Badge/ID photo is blurry or unreadable</option>
                                            <option value="Badge number could not be verified with the agency">Badge number could not be verified with the agency</option>
                                            <option value="Agency selected does not match the submitted ID">Agency selected does not match the submitted ID</option>
                                            <option value="Suspected fraudulent or edited document">Suspected fraudulent or edited document</option>
                                            <option value="Name on ID does not match registered account name">Name on ID does not match registered account name</option>
                                            <option value="Duplicate or already-registered badge number">Duplicate or already-registered badge number</option>
                                        </select>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="submit" class="btn-reject-confirm">Reject &amp; Remove</button>
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">
                            No responder registrations yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>