<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Responder Accounts</title>
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
        <a href="{{ route('citizen-verification') }}"><i class="bi bi-person-check-fill"></i> Residents Verification</a>
        <a href="{{ route('responder-accounts') }}" class="active"><i class="bi bi-person-badge-fill"></i> Responder Accounts</a>
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
            <div class="page-title">Responder Accounts</div>
        </div>
        <div class="page-sub">One shared login per agency — any number of staff sign into the same account on their own devices.</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        @error('otp')
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ $message }}
            </div>
        @enderror

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
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($responders as $r)
                    <tr>
                        <td><span class="agency-badge">{{ $r->agency }}</span></td>
                        <td>{{ $r->email }}</td>
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
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:40px; color:#9ca3af;">
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
                            <div style="display:flex;gap:8px;align-items:flex-start;">
                                <input type="email" name="email" id="editEmail{{ $r->id }}" class="form-control-m"
                                       value="{{ $r->email }}" required style="flex:1;">
                                <button type="button" id="getCodeBtn{{ $r->id }}" onclick="sendEmailOtp({{ $r->id }})"
                                        style="white-space:nowrap;background:#1a3c8f;color:#fff;border:none;
                                               border-radius:8px;padding:0 14px;font-size:.82rem;font-weight:700;cursor:pointer;">
                                    Get Code
                                </button>
                            </div>
                            <div class="form-hint">This is what the team uses to sign in — changing it doesn't affect the password.</div>
                            <div class="form-hint" id="otpStatus{{ $r->id }}" style="display:none;"></div>
                        </div>
                        <div class="col-12" id="otpFieldWrap{{ $r->id }}" style="display:none;">
                            <label class="form-label-m">Verification Code</label>
                            <input type="text" name="otp" id="otpInput{{ $r->id }}" class="form-control-m"
                                   placeholder="6-digit code" maxlength="6" inputmode="numeric">
                            <div class="form-hint">Check the inbox for the new email above — the code expires in 10 minutes.</div>
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
            <form method="POST" action="{{ route('responder-accounts.reset-password', $r) }}" class="reset-password-form" data-agency="{{ $r->agency }}">
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

<script>
/**
 * Guards against a mistyped agency login email — there's no self-service
 * recovery if this gets fat-fingered (see the "Reset Password" flow's
 * own doc comment: forgotten *passwords* are recoverable, but nobody
 * catches a typo'd email until the whole team can't log in). Unlike a
 * simple retype-to-confirm, this actually proves the new address is
 * real and reachable: "Get Code" emails a 6-digit OTP to it (see
 * Admin\ResponderAccountController::sendEmailOtp), and the email only
 * takes effect once that code is typed back in and update() confirms
 * it server-side. The button is always visible — clicking it re-sends
 * a code for whatever's currently in the field, changed or not.
 */
function sendEmailOtp(id) {
    var input = document.getElementById('editEmail' + id);
    var btn = document.getElementById('getCodeBtn' + id);
    var statusEl = document.getElementById('otpStatus' + id);
    var otpWrap = document.getElementById('otpFieldWrap' + id);
    var otpInput = document.getElementById('otpInput' + id);

    btn.disabled = true;
    btn.textContent = 'Sending...';

    fetch('{{ url('/responder-accounts') }}/' + id + '/send-email-otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ email: input.value.trim() }),
    })
        .then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        })
        .then(function (result) {
            statusEl.style.display = 'block';
            if (result.ok) {
                statusEl.style.color = '#065f46';
                statusEl.textContent = result.data.message;
                otpWrap.style.display = 'block';
                otpInput.required = true;
            } else {
                statusEl.style.color = '#dc2626';
                var firstError = result.data.errors
                    ? Object.values(result.data.errors)[0][0]
                    : (result.data.message || 'Could not send code.');
                statusEl.textContent = firstError;
            }
        })
        .catch(function () {
            statusEl.style.display = 'block';
            statusEl.style.color = '#dc2626';
            statusEl.textContent = 'Network error — could not send code.';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = 'Get Code';
        });
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

// Reset Password — the modal already collects the new password; close
// it first, THEN show the SweetAlert (firing Swal while a Bootstrap
// modal is still open can cause stacking/focus-trap issues — same
// reasoning as citizen-verification.blade.php's reject flow), and do
// one final confirm before every device on this account gets signed out.
document.querySelectorAll('.reset-password-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const agency = form.dataset.agency;
        const parentModalEl = form.closest('.modal');
        const bsModal = parentModalEl ? bootstrap.Modal.getOrCreateInstance(parentModalEl) : null;

        const confirmReset = () => {
            Swal.fire({
                ...swalTheme,
                icon: 'warning',
                title: `Reset the ${agency} password?`,
                text: 'This immediately signs out every device currently using this account.',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reset Password',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        };

        if (bsModal && parentModalEl.classList.contains('show')) {
            parentModalEl.addEventListener('hidden.bs.modal', confirmReset, { once: true });
            bsModal.hide();
        } else {
            confirmReset();
        }
    });
});
</script>
</body>
</html>