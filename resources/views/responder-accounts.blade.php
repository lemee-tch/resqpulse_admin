<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Responder Accounts</title>
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
        .main-wrap { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.6rem; color: #111827; }
        .page-sub { font-size: .82rem; color: #6b7280; margin-bottom: 24px; }

        /* ── TABLE ── */
        .table-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .acct-table { width: 100%; border-collapse: collapse; }
        .acct-table thead tr { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        .acct-table th { padding: 14px 20px; font-size: .8rem; font-weight: 700; color: #374151; text-align: left; white-space: nowrap; letter-spacing: .3px; }
        .acct-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
        .acct-table tbody tr:last-child { border-bottom: none; }
        .acct-table tbody tr:hover { background: #f9fafb; }
        .acct-table td { padding: 16px 20px; font-size: .83rem; color: #4b5563; vertical-align: middle; }

        .agency-badge { font-size: .78rem; font-weight: 800; padding: 4px 12px; border-radius: 8px; background: #eef2ff; color: #4338ca; font-family: 'Barlow', sans-serif; }
        .td-sub { font-size: .76rem; color: #9ca3af; margin-top: 2px; }

        .badge-active   { background: #d1fae5; color: #065f46; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .badge-inactive { background: #fee2e2; color: #991b1b; font-size: .72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }

        .action-btn {
            background: none; border: none; cursor: pointer;
            color: #9ca3af; font-size: 1rem; padding: 5px 6px;
            border-radius: 6px; transition: color .2s, background .2s;
        }
        .action-btn:hover { color: #1a3c8f; background: #eff2fb; }
        .action-btn.danger:hover { color: #ef4444; background: #fee2e2; }
        .action-btn.success:hover { color: #10b981; background: #d1fae5; }

        /* Modal */
        .modal-title-custom { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.1rem; }
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .form-control-m {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-control-m:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .form-hint { font-size: .74rem; color: #9ca3af; margin-top: 4px; }
        .btn-save {
            background: #1a3c8f; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #152f72; }
        .btn-reset {
            background: #b45309; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-reset:hover { background: #92400e; }

        .info-note {
            display: flex; gap: 10px; align-items: flex-start;
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
            padding: 12px 16px; font-size: .8rem; color: #1e40af; line-height: 1.5;
            margin-bottom: 20px;
        }
        .info-note i { margin-top: 1px; flex-shrink: 0; }
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
        <a href="{{ route('responder-accounts') }}" class="active">Responder Accounts</a>
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
            <div class="page-title">Responder Accounts</div>
        </div>
        <div class="page-sub">One shared login per agency — any number of staff sign into the same account on their own devices.</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <div class="info-note">
            <i class="bi bi-info-circle"></i>
            <span>These accounts are pre-built — there's no self-registration anymore. To hand a login to an agency, reset its password below and share the new credentials directly with the team.</span>
        </div>

        <div class="table-card">
            <table class="acct-table">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Login Email</th>
                        <th>Mobile</th>
                        <th>Unit / Station</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($responders as $r)
                    <tr>
                        <td><span class="agency-badge">{{ $r->agency }}</span></td>
                        <td>
                            {{ $r->email }}
                            <div class="td-sub">Badge/ref: {{ $r->badge_number }}</div>
                        </td>
                        <td>{{ $r->mobile ?? '—' }}</td>
                        <td>{{ $r->unit_station ?? '—' }}</td>
                        <td>
                            @if($r->status === 'active')
                                <span class="badge-active">Active</span>
                            @else
                                <span class="badge-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button class="action-btn" title="Edit contact details"
                                    data-bs-toggle="modal" data-bs-target="#editModal{{ $r->id }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="action-btn" title="Reset password"
                                    data-bs-toggle="modal" data-bs-target="#resetModal{{ $r->id }}">
                                <i class="bi bi-key-fill"></i>
                            </button>
                            <form action="{{ route('responder-accounts.toggle-status', $r) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit"
                                        class="action-btn {{ $r->status === 'active' ? 'danger' : 'success' }}"
                                        title="{{ $r->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                    <i class="bi {{ $r->status === 'active' ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#9ca3af;">
                            No responder accounts yet. Run the ResponderSeeder to create the 5 agency accounts.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

@foreach($responders as $r)
<!-- ════ EDIT MODAL ════ -->
<div class="modal fade" id="editModal{{ $r->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('responder-accounts.update', $r) }}">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Edit {{ $r->agency }} Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-m">Login Email</label>
                            <input type="email" name="email" class="form-control-m" value="{{ $r->email }}" required>
                            <div class="form-hint">This is what the team uses to sign in — changing it doesn't affect the password.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">Mobile (optional)</label>
                            <input type="text" name="mobile" class="form-control-m" value="{{ $r->mobile }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">Unit / Station (optional)</label>
                            <input type="text" name="unit_station" class="form-control-m" value="{{ $r->unit_station }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════ RESET PASSWORD MODAL ════ -->
<div class="modal fade" id="resetModal{{ $r->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('responder-accounts.reset-password', $r) }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Reset {{ $r->agency }} Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <label class="form-label-m">New Password</label>
                    <input type="text" name="password" class="form-control-m" placeholder="At least 6 characters" minlength="6" required>
                    <div class="form-hint">This immediately signs out every device currently using this account — share the new password with the {{ $r->agency }} team afterward.</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn-reset">Reset Password</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.sos-alert-overlay')
</body>
</html>