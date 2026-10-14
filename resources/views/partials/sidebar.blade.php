{{--
    The admin sidebar, shared by every admin page: @include('partials.sidebar').

    It used to be copied into each page (16 copies). Now a change here
    shows on every page. The current page is highlighted from its route
    name, and "User Management" opens by itself when one of its pages is
    open. The page's own .sidebar / .sidebar-nav styles still apply;
    only the dropdown's styles live here.

    Uses $pendingIncidentsCount / $pendingSosCount from
    AppServiceProvider's view composer (the red counts).
--}}
@php
    $navActive = fn (string ...$routes) => request()->routeIs(...$routes) ? 'active' : '';
    $userManagementOpen = request()->routeIs('citizen-verification', 'users', 'responder-accounts');
    $countBadge = 'background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;';
@endphp

<style>
    /* Long menus (dropdown open on a short screen) scroll inside the sidebar. */
    .sidebar { max-height: 100vh; }
    .sidebar-nav { overflow-y: auto; min-height: 0; }

    /* User Management dropdown — looks like the other menu items. */
    .nav-group { margin: 0; }
    .nav-group > summary {
        list-style: none; cursor: pointer; user-select: none;
        display: flex; align-items: center; gap: 10px;
        margin: 2px 10px; padding: 10px 20px; border-radius: 10px;
        font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75);
        transition: color .2s, background .2s;
    }
    .nav-group > summary::-webkit-details-marker { display: none; }
    .nav-group > summary:hover { color: #fff; background: rgba(255,255,255,.08); }
    .nav-group > summary:focus-visible { outline: 2px solid #fff; outline-offset: -2px; }
    .nav-group > summary i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
    .nav-group > summary .nav-caret { margin-left: auto; font-size: .75rem; width: auto; transition: transform .2s; }
    .nav-group[open] > summary .nav-caret { transform: rotate(180deg); }
    .nav-group > summary.active { color: #fff; font-weight: 700; background: rgba(255,255,255,.16); }

    .sidebar-nav .nav-sub a { padding: 8px 20px 8px 48px; font-size: .8rem; margin: 1px 10px; }
    .sidebar-nav .nav-sub a::before { content: '\2022'; opacity: .8; }
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Logo">
        <div class="sidebar-brand-text">
            <div class="title">MDRRMO</div>
            <div class="sub">ADMIN PANEL</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="{{ $navActive('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="{{ route('incident') }}" class="{{ $navActive('incident', 'incident.detail', 'incident.report-pdf', 'incident.manual.create') }}">
            <i class="bi bi-clipboard2-pulse"></i> Incidents
            @if(($pendingIncidentsCount ?? 0) > 0)
                <span style="{{ $countBadge }}">{{ $pendingIncidentsCount }}</span>
            @endif
        </a>
        <a href="{{ route('sos-alerts') }}" class="{{ $navActive('sos-alerts', 'sos.detail') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="{{ $countBadge }}">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}" class="{{ $navActive('mapview') }}"><i class="bi bi-geo-alt-fill"></i> Map View</a>
        <a href="{{ route('alerts') }}" class="{{ $navActive('alerts') }}"><i class="bi bi-megaphone-fill"></i> Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}" class="{{ $navActive('evacuation', 'evacuation.log') }}"><i class="bi bi-house-heart-fill"></i> Evacuation Centers</a>
        <a href="{{ route('reports-analytics') }}" class="{{ $navActive('reports-analytics') }}"><i class="bi bi-bar-chart-fill"></i> Reports &amp; Analytics</a>
        <a href="{{ route('audit-log') }}" class="{{ $navActive('audit-log') }}"><i class="bi bi-journal-text"></i> Audit Log</a>

        <details class="nav-group" {{ $userManagementOpen ? 'open' : '' }}>
            <summary class="{{ $userManagementOpen ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i> User Management
                <i class="bi bi-chevron-down nav-caret"></i>
            </summary>
            <div class="nav-sub">
                <a href="{{ route('citizen-verification') }}" class="{{ $navActive('citizen-verification') }}">Residents Verification</a>
                <a href="{{ route('users') }}" class="{{ $navActive('users') }}">LGU Staff</a>
                <a href="{{ route('responder-accounts') }}" class="{{ $navActive('responder-accounts') }}">Response Team</a>
            </div>
        </details>
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