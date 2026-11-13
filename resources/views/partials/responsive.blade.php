{{--
    Phones and tablets (item 70) — one place for the small-screen layout of
    every admin page. Included by partials/sidebar, so every page with the
    menu has it. Before, each page had its own copy of the phone menu (the
    bottom tab bar, the dark overlay, its script and ~100 lines of CSS).

    Up to 1099px wide (phones, tablets, small laptop windows):
      - the menu slides in from the left (More, in the tab bar at the
        bottom) and the page uses the full width;
      - no page is wider than the screen;
      - a table that doesn't fit shows each row as one block, with the
        column names on the left, so no column is hidden off to the side.
    Up to 640px (phones):
      - every table shows its rows as blocks;
      - tabs and filters wrap into a grid, so all of them can be seen.

    A page opts a table out of the phone layout with class="no-stack";
    tables inside a form or with fields in them keep their own layout.
--}}
@php
    $tabSos = ($isSos ?? false) || request()->routeIs('sos-alerts', 'sos.detail');
    $tabIncidents = ! $tabSos && request()->routeIs('incident', 'incident.*', 'incidents.overview', 'critical');
    $tabMore = ! $tabSos && ! $tabIncidents && ! request()->routeIs('dashboard', 'mapview');
@endphp

<style>
    /* Every page: the content may shrink, so a wide table can't push the
       page wider than the screen (before, the phone zoomed the page out). */
    .main-wrap, .main, .content { min-width: 0; }

    /* Menu items: icon and text (was in every page). */
    .sidebar-nav a { display: flex; align-items: center; gap: 10px; margin: 2px 10px; border-radius: 10px; border-left: none !important; }
    .sidebar-nav a i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
    .sidebar-nav a.active { border-left: none !important; background: rgba(255,255,255,.16) !important; }

    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 150; }
    .sidebar-overlay.show { display: block; }

    /* ── Bottom tab bar ── */
    .bottom-tab-bar {
        display: none; position: fixed; left: 0; right: 0; bottom: 0; z-index: 250;
        background: #fff; border-top: 1px solid #e5e7eb; box-shadow: 0 -2px 14px rgba(0,0,0,.08);
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .bottom-tab-bar > a, .bottom-tab-bar > button {
        flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 2px; min-height: 56px; padding: 8px 2px 7px; background: none; border: 0; cursor: pointer;
        color: #6b7280; text-decoration: none; font: 700 .68rem/1.1 'Inter', sans-serif; letter-spacing: .2px;
    }
    .bottom-tab-bar i { font-size: 1.2rem; }
    .bottom-tab-bar .active { color: #1a3c8f; }
    .bottom-tab-bar > :focus-visible { outline: 2px solid #1a3c8f; outline-offset: -4px; border-radius: 10px; }
    .bottom-tab-bar .tab-badge {
        position: absolute; top: 4px; right: calc(50% - 22px);
        background: #dc2626; color: #fff; font-size: .6rem; font-weight: 800; line-height: 1;
        padding: 2px 5px; border-radius: 20px;
    }

    /* ── Phones and tablets ── */
    @media (max-width: 1099.98px) {
        /* The open menu is above the tab bar, so Log out at its bottom
           isn't hidden behind it; More stays visible to close it. */
        .sidebar {
            transform: translateX(-100%) !important; transition: transform .25s ease; z-index: 300 !important;
            width: 260px !important; min-width: 0 !important; max-width: 85vw !important;
            height: 100vh; height: 100dvh; max-height: none; padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .sidebar.open { transform: none !important; box-shadow: 4px 0 24px rgba(0,0,0,.3); }
        /* The corner notices wait while the menu is open. */
        .sidebar.open ~ .report-alerts { visibility: hidden; }
        .sidebar-nav a { white-space: normal !important; }
        .bottom-tab-bar { display: flex; }

        /* The full width, with room at the bottom for the tab bar. */
        .main-wrap { margin-left: 0 !important; padding: 0 0 calc(64px + env(safe-area-inset-bottom, 0px)) !important; }
        .main { margin-left: 0 !important; padding: 20px 20px calc(84px + env(safe-area-inset-bottom, 0px)) !important; }
        .content { padding: 20px 20px 16px !important; }
        .topbar { padding-left: 20px !important; padding-right: 20px !important; }

        img, svg, canvas, iframe, video { max-width: 100%; }

        /* Tables use the width they have and wrap; a table that's still too
           wide scrolls inside its box, not the page. */
        .main-wrap table, .main table { width: 100% !important; white-space: normal !important; }
        .main-wrap table:not(.no-stack), .main table:not(.no-stack) { display: table !important; }
        .rt-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* The corner notices sit above the tab bar (a page can lift them
           higher, like Review report does above its buttons). */
        .report-alerts { bottom: calc(70px + env(safe-area-inset-bottom, 0px)); }
    }

    /* ── Rows as blocks: every table on phones, and on tablets a table
       that doesn't fit (the script below adds .rt-cards). The column name
       on the left, the value on the right; the buttons under them. ── */
    @media (max-width: 1099.98px) {
        table.rt-cards, table.rt-cards > tbody, table.rt-cards > tbody > tr, table.rt-cards > tbody > tr > td {
            display: block !important; width: 100% !important;
        }
        table.rt-cards > thead { display: none !important; }
        table.rt-cards > tbody > tr { padding: 10px 14px; border-bottom: 1px solid #eef0f4; }
        table.rt-cards > tbody > tr:last-child { border-bottom: 0; }
        table.rt-cards > tbody > tr > td {
            position: relative; min-height: 26px; height: auto !important; max-width: none !important;
            padding: 5px 0 5px 38% !important; border: 0 !important; text-align: left !important;
            white-space: normal !important; overflow-wrap: anywhere;
        }
        table.rt-cards > tbody > tr > td::before {
            content: attr(data-label); position: absolute; left: 0; top: 6px; width: 34%;
            font-size: .7rem; font-weight: 700; line-height: 1.3; color: #6b7280;
            text-transform: uppercase; letter-spacing: .3px; white-space: normal;
        }
        table.rt-cards > tbody > tr > td.rt-nolabel { padding-left: 0 !important; }
        table.rt-cards > tbody > tr > td.rt-nolabel::before { content: none; }
        table.rt-cards > tbody > tr > td.rt-action { padding-top: 8px !important; }
        table.rt-cards > tbody > tr > td.rt-full { padding: 8px 0 !important; text-align: center !important; }
        table.rt-cards > tbody > tr > td.rt-full::before { content: none; }
    }

    /* ── Phones ── */
    @media (max-width: 640px) {
        .content { padding: 16px 12px 12px !important; }
        .main { padding: 16px 12px calc(80px + env(safe-area-inset-bottom, 0px)) !important; }
        .topbar { padding-left: 12px !important; padding-right: 12px !important; }

        /* Tabs: all of them in a grid, the open one highlighted. */
        .tab-bar {
            display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px;
            border-bottom: 0 !important; overflow: visible !important;
        }
        .tab-bar > .tab-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            margin: 0 !important; padding: 9px 8px !important; min-height: 42px;
            border: 1.5px solid #e5e7eb !important; border-radius: 10px; background: #fff;
            white-space: normal !important; text-align: center; line-height: 1.25;
        }
        .tab-bar > .tab-btn.active { border-color: #1a3c8f !important; background: #eef2ff; color: #1a3c8f; }

        /* Filters: two to a row, the search across. */
        .filter-bar {
            display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-flow: row dense;
            gap: 8px !important; align-items: stretch !important;
        }
        .filter-bar > * { width: 100% !important; max-width: none !important; min-width: 0 !important; margin: 0 !important; }
        .filter-bar > .search-wrap, .filter-bar > .btn-add-report, .filter-bar > :only-child { grid-column: 1 / -1; }
        .filter-bar .filter-select, .filter-bar .filter-date, .filter-bar .search-input { width: 100% !important; max-width: none !important; }
        .filter-bar > .btn-add-report { justify-content: center; }
    }
</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="bottom-tab-bar" aria-label="Main">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}" class="{{ $tabIncidents ? 'active' : '' }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif
    </a>
    <a href="{{ route('sos-alerts') }}" class="{{ $tabSos ? 'active' : '' }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif
    </a>
    <a href="{{ route('mapview') }}" class="{{ request()->routeIs('mapview') ? 'active' : '' }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <button type="button" id="mobileMenuBtn" class="{{ $tabMore ? 'active' : '' }}" aria-controls="sidebar" aria-expanded="false">
        <i class="bi bi-grid-3x3-gap-fill"></i><span>More</span>
    </button>
</nav>

<script>
(function () {
    // More: opens and closes the menu. The dark overlay, Esc or a menu
    // link closes it.
    var btn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (btn && sidebar && overlay) {
        var setOpen = function (open) {
            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('show', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        btn.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
        overlay.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () { setOpen(false); });
        });
    }

    // Tables: each cell gets its column's name (data-label), shown next to
    // the value when the rows become blocks. Rows added later (filters,
    // live updates) get them too.
    function stackTable(table) {
        if (table.classList.contains('no-stack') || table.closest('.no-stack')) return;
        // Rows you type in (Review report, Add Resolved Report) keep their
        // own layout.
        if (table.closest('form') || table.querySelector('input:not([type=hidden]), select, textarea')) return;
        var head = table.tHead && table.tHead.rows[0];
        if (!head) return;

        var labels = [];
        Array.prototype.forEach.call(head.cells, function (th) {
            var text = th.textContent.replace(/\s+/g, ' ').trim();
            for (var i = 0; i < (th.colSpan || 1); i++) labels.push(text);
        });

        var label = function (row) {
            var col = 0;
            Array.prototype.forEach.call(row.cells, function (td) {
                var span = td.colSpan || 1;
                if (span >= labels.length && labels.length > 1) {
                    td.classList.add('rt-full'); // "No incidents yet." and the like
                } else if (!td.hasAttribute('data-label')) {
                    var name = labels[col] || '';
                    td.setAttribute('data-label', name);
                    // Buttons (an Action column) go under the values without
                    // a name. Audit Log's Action column is a value: it keeps it.
                    if ((name === '' || /^actions?$/i.test(name)) && td.querySelector('a, button, form')) {
                        td.classList.add('rt-nolabel', 'rt-action');
                    } else if (name === '') {
                        td.classList.add('rt-nolabel');
                    }
                }
                col += span;
            });
        };

        table.classList.add('rt-stack');
        stacked.push(table);
        var box = table.parentElement;
        if (box) {
            box.classList.add('rt-scroll');
            // A tab opened, the screen turned: check again (only when the
            // box's width changed, so a table turning into blocks — which
            // changes its height — doesn't check itself over and over).
            if (window.ResizeObserver) {
                var lastWidth = -1;
                new ResizeObserver(function () {
                    if (box.clientWidth !== lastWidth) { lastWidth = box.clientWidth; fit(table); }
                }).observe(box);
            }
        }
        Array.prototype.forEach.call(table.tBodies, function (body) {
            Array.prototype.forEach.call(body.rows, label);
            new MutationObserver(function (changes) {
                changes.forEach(function (change) {
                    change.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.tagName === 'TR') label(node);
                    });
                });
            }).observe(body, { childList: true });
        });
    }

    // Rows as blocks: always on phones; on tablets when the table is wider
    // than its box (it would hide columns); never on bigger screens.
    var stacked = [];
    var phone = window.matchMedia('(max-width: 640px)');
    var small = window.matchMedia('(max-width: 1099.98px)');
    function fit(table) {
        if (!small.matches) { table.classList.remove('rt-cards'); return; }
        if (phone.matches) { table.classList.add('rt-cards'); return; }
        var box = table.parentElement;
        if (!box || box.clientWidth === 0) return; // in a closed tab
        var css = getComputedStyle(box);
        var room = box.clientWidth - parseFloat(css.paddingLeft) - parseFloat(css.paddingRight);
        table.classList.remove('rt-cards');
        if (table.offsetWidth > room + 1) table.classList.add('rt-cards');
    }
    var queued = false;
    window.addEventListener('resize', function () {
        if (queued) return;
        queued = true;
        requestAnimationFrame(function () { queued = false; stacked.forEach(fit); });
    });

    var stackAll = function () {
        document.querySelectorAll('.main-wrap table, main.main table').forEach(stackTable);
        stacked.forEach(fit);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', stackAll);
    else stackAll();
})();
</script>
