<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
        .password-mask { letter-spacing: 2px; color: #9ca3af; font-size: .78rem; }

        /* Action buttons */
        .action-btn {
            background: none; border: none; cursor: pointer;
            color: #9ca3af; font-size: 1rem; padding: 5px 6px;
            border-radius: 6px; transition: color .2s, background .2s;
        }
        .action-btn:hover { color: #1a3c8f; background: #eff2fb; }
        .action-btn.delete:hover { color: #ef4444; background: #fee2e2; }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: .9rem; }

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
        .name-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .name-row .name-field { flex: 1; min-width: 130px; }
        .name-row .name-field.suffix { flex: 0 0 90px; min-width: 90px; }
        .btn-save {
            background: #1a3c8f; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #152f72; }
        .btn-delete-confirm {
            background: #ef4444; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-delete-confirm:hover { background: #dc2626; }
    
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
        <a href="{{ route('citizen-verification') }}"><i class="bi bi-person-check-fill"></i> Citizen Verification</a>
        <a href="{{ route('responder-accounts') }}"><i class="bi bi-person-badge-fill"></i> Responder Accounts</a>
        <a href="{{ route('reports-analytics') }}"><i class="bi bi-bar-chart-fill"></i> Reports &amp; Analytics</a>
        <a href="{{ route('audit-log') }}"><i class="bi bi-journal-text"></i> Audit Log</a>
        <a href="{{ route('users') }}" class="active"><i class="bi bi-people-fill"></i> User</a>
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
            <div class="page-title">User</div>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg"></i> Add User
            </button>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="table-card">
            <table class="evac-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="td-name">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="password-mask">••••••••</span></td>
                        <td>
                            <button class="action-btn" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#editModal{{ $user->id }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="action-btn delete" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal{{ $user->id }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">No admin users yet. Use "Add User" to add the first one.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- ════ ADD USER MODAL ════ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-m">Name</label>
                            <div class="name-row">
                                <div class="name-field">
                                    <input type="text" name="first_name" class="form-control-m" placeholder="First name" required>
                                </div>
                                <div class="name-field">
                                    <input type="text" name="middle_name" class="form-control-m" placeholder="Middle name (optional)">
                                </div>
                                <div class="name-field">
                                    <input type="text" name="last_name" class="form-control-m" placeholder="Last name" required>
                                </div>
                                <div class="name-field suffix">
                                    <input type="text" name="suffix" class="form-control-m" placeholder="Suffix" maxlength="20">
                                </div>
                            </div>
                            <div class="form-hint">Suffix is optional — e.g. Jr., Sr., III.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">Email</label>
                            <input type="email" name="email" class="form-control-m" placeholder="name@rosales.gov.ph" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">Password</label>
                            <input type="password" name="password" class="form-control-m" placeholder="At least 6 characters" minlength="6" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn-save">Save User</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($users as $user)
<!-- ════ EDIT USER MODAL ════ -->
<div class="modal fade" id="editModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Edit {{ $user->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-m">Name</label>
                            <div class="name-row">
                                <div class="name-field">
                                    <input type="text" name="first_name" class="form-control-m" placeholder="First name" value="{{ $user->first_name }}" required>
                                </div>
                                <div class="name-field">
                                    <input type="text" name="middle_name" class="form-control-m" placeholder="Middle name (optional)" value="{{ $user->middle_name }}">
                                </div>
                                <div class="name-field">
                                    <input type="text" name="last_name" class="form-control-m" placeholder="Last name" value="{{ $user->last_name }}" required>
                                </div>
                                <div class="name-field suffix">
                                    <input type="text" name="suffix" class="form-control-m" placeholder="Suffix" maxlength="20" value="{{ $user->suffix }}">
                                </div>
                            </div>
                            <div class="form-hint">Suffix is optional — e.g. Jr., Sr., III.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">Email</label>
                            <input type="email" name="email" class="form-control-m" value="{{ $user->email }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-m">New Password</label>
                            <input type="password" name="password" class="form-control-m" placeholder="Leave blank to keep current password" minlength="6">
                            <div class="form-hint">Only fill this in if you want to change the password.</div>
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

<!-- ════ DELETE USER MODAL ════ -->
<div class="modal fade" id="deleteModal{{ $user->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('users.destroy', $user) }}">
                @csrf @method('DELETE')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title-custom">Remove {{ $user->name }}?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p style="font-size:.85rem;color:#6b7280;">
                        This permanently removes {{ $user->name }}'s admin access. This can't be undone.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn-delete-confirm">Delete</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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