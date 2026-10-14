<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – SOS Alerts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
             display: flex;
             align-items: center;
             gap: 10px;
             padding: 18px 16px 16px;
             border-bottom: 1px solid rgba(255,255,255,.12);
            }
        .sidebar-brand img {
             width: 38px;
             height: 38px;
             border-radius: 50%;
             object-fit: cover;
             border: 2px solid rgba(255,255,255,.3);
            }
        .sidebar-brand-text .title {
             font-family: 'Barlow', sans-serif;
             font-weight: 800;
             font-size: .85rem;
             color: #fff;
             letter-spacing: .5px;
             line-height: 1.1;
            }
        .sidebar-brand-text .sub {
             font-size: .65rem;
             color: rgba(255,255,255,.6);
             letter-spacing: .5px;
            }
        .sidebar-nav {
            flex: 1;
            padding: 18px 0;
        }
        .sidebar-nav a {
             display: block;
             padding: 10px 20px;
             font-size: .82rem;
             font-weight: 500;
             color: rgba(255,255,255,.75);
             text-decoration: none;
             border-left: 3px solid transparent;
             transition: all .2s;
        }

        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-weight: 500;
            transition: color .2s;
        }
        .sidebar-logout a:hover {
            color: #fff;
        }

        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; }
        .content { padding: 32px 36px; flex: 1; }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .page-title {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-sub {
            font-size: .82rem;
            color: #6b7280;
            margin-bottom: 20px;
            }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fee2e2;
            color: #991b1b;
            font-size: .75rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #dc2626;
            animation: pulse-dot 1s infinite;
        }
        @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .3; } }

        @keyframes sosFlicker {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,.55); border-color:#dc2626; background:#fff5f5; }
            50%      { box-shadow: 0 0 0 14px rgba(220,38,38,0); border-color:#fecaca; background:#fff; }
        }
        .sos-card.is-new-alert {
            animation: sosFlicker 1s ease-in-out infinite;
        }

        /* ── TABS ── */
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
        /* Phones: the tabs scroll sideways instead of pushing the page wider. */
        .tab-bar { overflow-x: auto; scrollbar-width: none; }
        .tab-bar::-webkit-scrollbar { display: none; }
        .tab-btn { white-space: nowrap; flex-shrink: 0; }
        .main-wrap, .content { min-width: 0; }
        @media (max-width: 560px) { .tab-btn { padding: 10px 12px; } }

        /* ── SOS CARD (partials/sos-card.blade.php) ──
           Photo │ type, who, where, team │ time + View.
           The buttons for the tab (Approve / Decline, Mark as Resolved /
           Return, Delete) run along the bottom. On phones: photo + text,
           then the buttons, then the time and View. */
        .sos-card {
            display: grid;
            grid-template-columns: 104px minmax(0, 1fr) auto;
            grid-template-areas: "thumb main side";
            column-gap: 18px;
            align-items: start;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-left: 5px solid #dc2626;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 14px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            transition: box-shadow .2s, transform .15s;
        }
        .sos-card.has-foot { grid-template-areas: "thumb main side" "thumb foot foot"; }
        .sos-card:hover { box-shadow: 0 8px 24px rgba(17,24,39,.1); transform: translateY(-1px); }

        /* Left edge: what the alert needs. Waiting for review = amber (same
           as the banner); sent but nobody accepted yet = red; responding =
           blue; For Review = purple; resolved = green; declined = dark red. */
        .sos-card--review     { border-left-color: #f59e0b; }
        .sos-card--pending    { border-left-color: #dc2626; background: linear-gradient(to right, #fff5f5, #fff 110px); }
        .sos-card--responding,
        .sos-card--acknowledged { border-left-color: #2563eb; }
        .sos-card--for_review { border-left-color: #7c3aed; }
        .sos-card--resolved   { border-left-color: #10b981; }
        .sos-card--declined   { border-left-color: #991b1b; background: #fcfcfd; }

        /* Photo */
        .sos-thumb {
            grid-area: thumb;
            position: relative; display: block;
            width: 104px; height: 104px;
            border-radius: 12px; overflow: hidden;
            background: #f3f4f6; border: 1px solid #eef0f4;
            text-decoration: none;
        }
        .sos-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .2s; }
        .sos-card:hover .sos-thumb img { transform: scale(1.04); }
        .sos-thumb .no-photo {
            position: absolute; inset: 0; display: none;
            flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            padding: 6px; text-align: center;
            color: #9ca3af; font-size: .7rem; font-weight: 600; line-height: 1.2;
        }
        .sos-thumb .no-photo i { font-size: 1.5rem; color: #c4c9d4; }
        .sos-thumb.is-empty, .sos-thumb.is-broken { background: #f9fafb; border-style: dashed; border-color: #e5e7eb; }
        .sos-thumb.is-empty .no-photo, .sos-thumb.is-broken .no-photo { display: flex; }
        .photo-count {
            position: absolute; right: 6px; bottom: 6px;
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(17,24,39,.75); color: #fff;
            font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;
        }

        /* Type, status, facts */
        .sos-main { grid-area: main; min-width: 0; }
        .sos-head { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; margin-bottom: 10px; }
        .sos-title {
            font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.06rem; line-height: 1.25;
            color: #111827; margin: 0; display: inline-flex; align-items: center; gap: 7px;
        }
        .sos-title i { color: #dc2626; font-size: .95rem; }

        .badge-status { font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        .badge-pending      { background: #fef3c7; color: #92400e; }   /* Active: Awaiting Responder */
        .badge-acknowledged { background: #dbeafe; color: #1e40af; }
        .badge-responding   { background: #e0f2fe; color: #0369a1; }
        .badge-resolved     { background: #d1fae5; color: #065f46; }
        .badge-review       { background: #fef3c7; color: #92400e; }
        .badge-declined     { background: #fee2e2; color: #991b1b; }
        .badge-forreview    { background: #ede9fe; color: #5b21b6; }
        .badge-ai {
            display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
            background: #eef2ff; color: #4338ca; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
        }

        /* Each fact takes the room it needs and wraps to the next line when
           the card is narrow. */
        .sos-facts { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 7px 26px; }
        .sos-facts li { display: flex; align-items: flex-start; gap: 8px; min-width: 0; max-width: 100%; font-size: .82rem; line-height: 1.45; color: #374151; }
        .sos-facts li > i { width: 16px; flex-shrink: 0; margin-top: 1px; text-align: center; font-size: .88rem; color: #9ca3af; }
        .sos-facts li > span { min-width: 0; overflow-wrap: anywhere; }
        .sos-facts li.wide { flex-basis: 100%; }
        .sos-facts .muted { color: #9ca3af; }
        .sos-facts .warn-text { color: #b45309; font-weight: 600; }
        .ai-quote { color: #6b7280; font-style: italic; }

        .sos-reporter { font-weight: 700; color: #111827; }
        .tag-guest, .tag-resident {
            display: inline-block; margin-left: 4px; padding: 1px 8px; border-radius: 20px;
            font-size: .68rem; font-weight: 700; letter-spacing: .3px; vertical-align: 1px;
        }
        .tag-guest    { background: #fff7ed; color: #c2410c; }
        .tag-resident { background: #ecfdf5; color: #047857; }
        .tag-outside {
            display: inline-flex; align-items: center; gap: 4px; margin-right: 6px; white-space: nowrap;
            padding: 1px 8px; border-radius: 20px; border: 1px solid #fecaca;
            background: #fef2f2; color: #b91c1c; font-size: .68rem; font-weight: 700; vertical-align: 1px;
        }
        .call-pill {
            display: inline-flex; align-items: center; gap: 5px; margin-left: 6px; white-space: nowrap;
            padding: 2px 10px; border-radius: 20px; background: #eef2ff; color: #1a3c8f;
            font-size: .78rem; font-weight: 700; text-decoration: none; transition: background .2s, color .2s;
        }
        .call-pill:hover { background: #1a3c8f; color: #fff; }
        .call-link { margin-left: 4px; color: #1a3c8f; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .call-link:hover { text-decoration: underline; }
        .place-link { color: #1a3c8f; font-weight: 600; text-decoration: none; }
        .place-link:hover { text-decoration: underline; }

        /* Time + View */
        .sos-side { grid-area: side; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; text-align: right; }
        .sos-when .ago { font-size: .8rem; font-weight: 700; color: #374151; white-space: nowrap; }
        .sos-when .ago.waiting { color: #b45309; }
        .sos-when .ago.late { color: #dc2626; }
        .sos-when .at { margin-top: 2px; font-size: .72rem; color: #9ca3af; white-space: nowrap; }
        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;
            background: #fff; color: #1a3c8f; border: 1.5px solid #c7d2fe; border-radius: 8px;
            padding: 7px 16px; font-size: .8rem; font-weight: 700; text-decoration: none;
            transition: background .2s, color .2s, border-color .2s;
        }
        .btn-view i { font-size: .95rem; }
        .btn-view:hover, .btn-view:focus-visible { background: #1a3c8f; border-color: #1a3c8f; color: #fff; outline: none; }

        /* Buttons along the bottom */
        .sos-foot {
            grid-area: foot; min-width: 0;
            margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e5e7eb;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px;
        }
        .foot-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .foot-actions form { margin: 0; display: flex; }
        .sos-foot .dispatch-hint { margin: 0; }
        .sos-foot .declined-reason-note { flex: 1; min-width: 0; margin: 0; display: flex; align-items: center; gap: 8px; }
        .sos-foot form.delete-form { margin: 0; display: flex; }

        @media (max-width: 720px) {
            .sos-card {
                grid-template-columns: 76px minmax(0, 1fr);
                grid-template-areas: "thumb main" "side side";
                column-gap: 14px; padding: 14px;
            }
            .sos-card.has-foot { grid-template-areas: "thumb main" "foot foot" "side side"; }
            .sos-thumb { width: 76px; height: 76px; }
            .sos-facts { flex-direction: column; }
            .sos-side {
                flex-direction: row; align-items: center; justify-content: space-between; text-align: left;
                margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f2f6;
            }
            .sos-foot { flex-direction: column; align-items: stretch; }
            .foot-actions { flex-direction: column; }
            .foot-actions > *, .foot-actions form { width: 100%; }
            .foot-actions button, .foot-actions a { width: 100%; justify-content: center; padding-top: 10px; padding-bottom: 10px; }
            .sos-foot .declined-reason-note { flex: none; }
            .sos-foot form.delete-form .btn-delete { width: 100%; justify-content: center; padding-top: 10px; padding-bottom: 10px; }
        }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 2.4rem; display: block; margin-bottom: 12px; color: #d1d5db; }

        .btn-approve {
            background: #10b981; color: #fff; border: none; border-radius: 8px;
            padding: 8px 16px; font-size: .8rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s;
        }
        .btn-approve:hover { background: #059669; }
        .btn-decline {
            background: #fff; color: #dc2626; border: 1.5px solid #dc2626; border-radius: 8px;
            padding: 8px 16px; font-size: .8rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s, color .2s;
        }
        .btn-decline:hover { background: #dc2626; color: #fff; }
        .review-banner {
            background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;
            font-size:.82rem;color:#92400e;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;
        }
        .review-banner--report { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
        /* Under Approve: which team(s) the quick Approve sends the SOS to. */
        .dispatch-hint { font-size: .76rem; color: #6b7280; margin-top: 8px; line-height: 1.45; }
        .dispatch-hint i { margin-right: 2px; }
        .dispatch-hint strong { color: #374151; }
        .dispatch-hint a { color: #1a3c8f; font-weight: 600; text-decoration: none; margin-left: 4px; }
        .dispatch-hint a:hover { text-decoration: underline; }
        /* For Review actions: the report PDF, Return. */
        .btn-pdf {
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
            background: #fff; color: #b91c1c; border: 1.5px solid #fecaca; border-radius: 8px;
            padding: 7px 14px; font-size: .8rem; font-weight: 700; text-decoration: none;
            transition: background .2s, border-color .2s;
        }
        .btn-pdf:hover { background: #fef2f2; border-color: #f87171; color: #991b1b; }
        .btn-return {
            background: #fff; color: #b45309; border: 1.5px solid #f59e0b; border-radius: 8px;
            padding: 8px 16px; font-size: .8rem; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s, color .2s;
        }
        .btn-return:hover { background: #f59e0b; color: #fff; }
        .tag-returned {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 1px 8px; border-radius: 20px; font-size: .66rem; font-weight: 700;
            background: #fff7ed; color: #c2410c;
        }
        .tag-resent {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 1px 8px; border-radius: 20px; font-size: .66rem; font-weight: 700;
            background: #f5f3ff; color: #6d28d9;
        }
        /* Decline-reason modal — same dropdown pattern as the Residents
           Verification rejection modal. */
        .form-label-m { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; display: block; }
        .form-control-m {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #374151; background: #fafafa; transition: border-color .2s;
        }
        .form-control-m:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .btn-decline-confirm {
            background: #dc2626; color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-family: 'Barlow', sans-serif; font-weight: 700;
            font-size: .88rem; cursor: pointer; transition: background .2s;
        }
        .btn-decline-confirm:hover { background: #b91c1c; }
        .decline-who { font-size: .85rem; color: #111827; margin-bottom: 6px; }
        .decline-outside {
            display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px;
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 10px;
            font-size: .8rem; color: #991b1b;
        }
        .declined-reason-note {
            margin-top: 8px; font-size: .8rem; color: #991b1b; font-style: italic;
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 10px;
        }
        .btn-delete {
            background: #fff; color: #b91c1c; border: 1.5px solid #fecaca; border-radius: 8px;
            padding: 6px 14px; font-size: .8rem; font-weight: 700; cursor: pointer; white-space: nowrap;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s, color .2s, border-color .2s;
        }
        .btn-delete:hover { background: #dc2626; border-color: #dc2626; color: #fff; }

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
            /* .main-wrap already has the side padding here — don't add more. */
            .content { padding: 4px 0 24px; }
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

<!-- ════ SIDEBAR ════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="bottom-tab-bar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}" class="active"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
</nav>
@include('partials.sidebar')

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        @php
            $pendingReviewSos = $sosAlerts->where('needs_review', true)->values();
            // Active excludes anything still pending review AND anything
            // declined — a declined SOS was never dispatched, so it
            // doesn't belong alongside genuinely active ones. Declined
            // alerts have their own tab; Resolved History is only the
            // ones responders actually resolved.
            // For Review: the responders sent their Final report — the
            // admin marks it resolved or sends it back (same as Incidents).
            $activeSos    = $sosAlerts->filter(fn ($s) => $s->stage === 'active')->values();
            $forReviewSos = $sosAlerts->filter(fn ($s) => $s->stage === 'for_review')->values();
            $resolvedSos  = $sosAlerts->filter(fn ($s) => $s->status === 'resolved' && ! $s->declined_at)->values();
            $declinedSos  = $sosAlerts->filter(fn ($s) => (bool) $s->declined_at)->sortByDesc('declined_at')->values();

            // The cards say who resolved or declined it — loaded once for all.
            $sosAlerts->loadMissing(['resolvedBy', 'reviewedBy']);
        @endphp

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-exclamation-octagon-fill" style="color:#dc2626;"></i> SOS Alerts
            </div>
            <span class="live-pill"><span class="live-dot"></span> {{ $pendingReviewSos->count() }} waiting for review</span>
        </div>
        <div class="page-sub">Panic-button alerts from the mobile app — GPS location and photo captured automatically, always critical priority.</div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
            </div>
        @endif

        <!-- TABS — same order as Incidents: Pending → Active → For Review →
             Resolved, plus Declined. Every new SOS lands in Pending for the
             admin to verify before responders are notified. -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending', this)">Pending ({{ $pendingReviewSos->count() }})</button>
            <button class="tab-btn" data-tab="active" onclick="switchTab('active', this)">Active ({{ $activeSos->count() }})</button>
            <button class="tab-btn" data-tab="for-review" onclick="switchTab('for-review', this)">For Review ({{ $forReviewSos->count() }})</button>
            <button class="tab-btn" data-tab="resolved" onclick="switchTab('resolved', this)">Resolved ({{ $resolvedSos->count() }})</button>
            <button class="tab-btn" data-tab="declined" onclick="switchTab('declined', this)">Declined ({{ $declinedSos->count() }})</button>
        </div>

        <!-- ══ TAB: ACTIVE SOS (approved and sent to responders) ══ -->
        <div id="tab-active" class="tab-panel">
            @forelse($activeSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'active'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    No active SOS alerts. All clear.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: FOR REVIEW (the responders' Final report, waiting for the admin) ══ -->
        <div id="tab-for-review" class="tab-panel">
            <div class="review-banner review-banner--report">
                <i class="bi bi-clipboard2-check" style="margin-top:1px;"></i>
                <span>The responding team sent their <strong>Final report</strong> for these SOS alerts. Open the report and check it, then <strong>Mark as Resolved</strong> — or <strong>Return</strong> it to the responders with what needs fixing. A returned report goes back to Active until they send it again.</span>
            </div>

            @forelse($forReviewSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'for-review'])
                @include('partials.return-report-modal', ['incident' => $sos, 'modalId' => 'returnModal' . $sos->id])
            @empty
                <div class="empty-state">
                    <i class="bi bi-clipboard2-check"></i>
                    No SOS reports waiting for your review.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: PENDING (every new SOS, waiting for review before dispatch) ══ -->
        <div id="tab-pending" class="tab-panel active">
            <div class="review-banner">
                <i class="bi bi-info-circle" style="margin-top:1px;"></i>
                <span>Every SOS alert waits here for your review. Call the number shown to confirm the emergency, then approve it to notify the responders. Click <strong>View</strong> to see the photos and the map, choose the teams, or add notes for them. Responders are not notified until you approve.</span>
            </div>

            @forelse($pendingReviewSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'pending'])

                {{-- Decline: pick a reason (the card's Decline button opens this). --}}
                <div class="modal fade" id="declineModal{{ $sos->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-radius:14px;border:none;">
                            <form action="{{ route('incident.decline', $sos->id) }}" method="POST" class="decline-form"
                                  data-notifies-citizen="{{ $sos->citizen_id ? '1' : '0' }}">
                                @csrf
                                <div class="modal-header border-0 pb-0">
                                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Decline this SOS alert?</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    <div class="decline-who">
                                        SOS from <strong>{{ $sos->reporter_name }}</strong>{{ $sos->citizen_id ? '' : ' (guest)' }}, sent {{ $sos->created_at->format('M d, g:i A') }}.
                                    </div>
                                    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">
                                        Responders will NOT be dispatched.
                                        {{ $sos->citizen_id ? 'The reporter will get a notification with this reason.' : 'This SOS was submitted by a guest, so there is no account to notify.' }}
                                    </p>
                                    @if(str_contains((string) $sos->location, 'Outside Rosales'))
                                        <div class="decline-outside">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <span>The GPS pin is outside Rosales. If MDRRMO Rosales can't respond there, choose "Location is outside Rosales / MDRRMO service area".</span>
                                        </div>
                                    @endif
                                    <label class="form-label-m">Reason for declining</label>
                                    <select name="decline_reason" class="form-control-m" required>
                                        <option value="" disabled selected>Select a reason...</option>
                                        <option value="Duplicate of another report already received">Duplicate of another report already received</option>
                                        <option value="Spam, prank, or test submission">Spam, prank, or test submission</option>
                                        <option value="Insufficient details to dispatch responders">Insufficient details to dispatch responders</option>
                                        <option value="Location is outside Rosales / MDRRMO service area">Location is outside Rosales / MDRRMO service area</option>
                                        <option value="Unable to verify — no credible evidence of an emergency">Unable to verify — no credible evidence of an emergency</option>
                                        <option value="Reported by mistake (accidental submission)">Reported by mistake (accidental submission)</option>
                                    </select>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="submit" class="btn-decline-confirm">Decline Alert</button>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-check2-circle"></i>
                    No SOS alerts waiting for review.
                </div>
            @endforelse
        </div>

        <!-- ══ TAB: RESOLVED ══ -->
        <div id="tab-resolved" class="tab-panel">

            @if($resolvedSos->count())
            <!-- FILTER BAR -->
            <div class="filter-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="date" class="filter-date" id="filterDateH" title="Filter by date"
                       style="border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;background:#fff;">

                <select class="filter-select" id="filterAiH"
                        style="appearance:none;-webkit-appearance:none;background:#fff url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E&quot;) no-repeat right 12px center;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 34px 9px 14px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;">
                    <option value="">All AI Types</option>
                    @foreach($resolvedSos->pluck('ai_detected_type')->filter()->unique()->sort() as $aiType)
                        <option value="{{ $aiType }}">{{ $aiType }}</option>
                    @endforeach
                </select>

                <div style="position:relative;flex:1;min-width:180px;max-width:280px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem;"></i>
                    <input type="text" id="searchInputH" placeholder="Search resolved SOS..."
                           style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px 9px 34px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;background:#fff;">
                </div>

                <button id="btnClearH"
                        style="background:#fff;color:#6b7280;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 18px;font-size:.82rem;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
                    Clear Filters
                </button>
            </div>
            <div class="results-count" id="resultsCountH" style="font-size:.8rem;color:#6b7280;margin-bottom:14px;"></div>
            @endif

            @forelse($resolvedSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'resolved'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    No resolved SOS alerts yet.
                </div>
            @endforelse

            @if($resolvedSos->count())
            <div id="historyNoMatch" class="empty-state" style="display:none;">
                <i class="bi bi-search"></i>
                No resolved SOS alerts match these filters.
            </div>
            @endif
        </div>

        <!-- ══ TAB: DECLINED ══ -->
        <div id="tab-declined" class="tab-panel">

            @if($declinedSos->count())
            <!-- FILTER BAR -->
            <div class="filter-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="date" class="filter-date" id="filterDateD" title="Filter by date reported"
                       style="border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;background:#fff;">

                <select class="filter-select" id="filterReasonD"
                        style="appearance:none;-webkit-appearance:none;background:#fff url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E&quot;) no-repeat right 12px center;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 34px 9px 14px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;font-weight:500;cursor:pointer;max-width:320px;">
                    <option value="">All Reasons</option>
                    @foreach($declinedSos->pluck('decline_reason')->filter()->unique()->sort() as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                    @endforeach
                </select>

                <div style="position:relative;flex:1;min-width:180px;max-width:280px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem;"></i>
                    <input type="text" id="searchInputD" placeholder="Search declined SOS..."
                           style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 14px 9px 34px;font-size:.82rem;color:#374151;font-family:'Inter',sans-serif;background:#fff;">
                </div>

                <button id="btnClearD"
                        style="background:#fff;color:#6b7280;border:1.5px solid #d1d5db;border-radius:8px;padding:9px 18px;font-size:.82rem;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
                    Clear Filters
                </button>
            </div>
            <div class="results-count" id="resultsCountD" style="font-size:.8rem;color:#6b7280;margin-bottom:14px;"></div>
            @endif

            @forelse($declinedSos as $sos)
                @include('partials.sos-card', ['sos' => $sos, 'tab' => 'declined'])
            @empty
                <div class="empty-state">
                    <i class="bi bi-slash-circle"></i>
                    No declined SOS alerts.
                </div>
            @endforelse

            @if($declinedSos->count())
            <div id="declinedNoMatch" class="empty-state" style="display:none;">
                <i class="bi bi-search"></i>
                No declined SOS alerts match these filters.
            </div>
            @endif
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab switching
    function switchTab(id, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + id).classList.add('active');
        btn.classList.add('active');
    }

    // Opens a tab by name. The tabs were called "review" (now Pending) and
    // "history" (now Resolved) before, so old links still land right.
    function openTab(name) {
        const renamed = { review: 'pending', history: 'resolved', forreview: 'for-review', for_review: 'for-review' };
        const tab = renamed[name] || name;
        const btn = tab && document.querySelector('.tab-btn[data-tab="' + tab + '"]');
        if (btn) switchTab(tab, btn);
        return !!btn;
    }

    // ?tab=declined (e.g. after deleting a declined alert) opens that tab.
    (function () {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (tab) openTab(tab);
    })();

    // Pending is the first tab, so after Mark as Resolved / Return on the
    // For Review tab the reload would jump back to it. Remember the tab the
    // change came from and reopen it once.
    (function () {
        const KEY = 'resqpulse_sos_return_tab';
        document.querySelectorAll('.tab-panel form:not(.delete-form)').forEach(form => {
            form.addEventListener('submit', () => {
                const panel = form.closest('.tab-panel');
                if (!panel) return;
                try { sessionStorage.setItem(KEY, panel.id.replace('tab-', '')); } catch (e) {}
            });
        });
        let tab = null;
        try { tab = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}
        if (tab) openTab(tab);
    })();

    // Clicking a card opens the SOS details, like its View button —
    // except on the card's own buttons, links and forms, or while text is
    // being selected.
    document.querySelectorAll('.sos-card[data-href]').forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('a, button, form, input, select, textarea, label')) return;
            if (String(window.getSelection ? window.getSelection() : '').trim() !== '') return;
            window.location.href = card.dataset.href;
        });
    });

    // Delete (declined alerts only) — asks first, since it can't be undone.
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const what = document.createElement('div');
            what.textContent = form.dataset.what || 'declined SOS alert';
            Swal.fire({
                icon: 'warning',
                title: 'Delete this declined SOS alert?',
                html: `<div style="font-size:.9em;color:#4b5563;">${what.innerHTML}<br>It's removed for good, with its photo. This can't be undone.</div>`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                focusCancel: true,
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    (function () {
        const params = new URLSearchParams(window.location.search);
        const highlightId = params.get('highlight');
        if (!highlightId) return;

        const card = document.querySelector('.sos-card[data-id="' + highlightId + '"]');
        if (card) {
            // Open the tab the alert is in (usually Pending Review, the
            // first tab) so the highlighted card is actually visible.
            const panel = card.closest('.tab-panel');
            const tabBtn = panel && document.querySelector('.tab-btn[data-tab="' + panel.id.replace('tab-', '') + '"]');
            if (tabBtn && !panel.classList.contains('active')) switchTab(panel.id.replace('tab-', ''), tabBtn);

            card.classList.add('is-new-alert');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => card.classList.remove('is-new-alert'), 12000);
        }

        try {
            const KEY = 'resqpulse_ack_sos_ids';
            const ids = JSON.parse(localStorage.getItem(KEY) || '[]');
            const idNum = parseInt(highlightId, 10);
            if (!ids.includes(idNum)) {
                ids.push(idNum);
                localStorage.setItem(KEY, JSON.stringify(ids));
            }
        } catch (e) {}
    })();

    // Resolved History tab filters (date / AI type / search)
    (function () {
        const dateFilter   = document.getElementById('filterDateH');
        if (!dateFilter) return;

        const aiFilter      = document.getElementById('filterAiH');
        const searchInput   = document.getElementById('searchInputH');
        const btnClear      = document.getElementById('btnClearH');
        const cards         = Array.from(document.querySelectorAll('#tab-resolved .history-row'));
        const resultsCount  = document.getElementById('resultsCountH');
        const noMatch       = document.getElementById('historyNoMatch');
        const totalCount    = cards.length;

        function applyFilters() {
            const date   = dateFilter.value;
            const ai     = aiFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            let visible  = 0;

            cards.forEach(card => {
                const ok = (!date   || card.dataset.date === date)
                        && (!ai     || card.dataset.ai   === ai)
                        && (!search || card.dataset.search.includes(search));
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> resolved SOS alerts`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        dateFilter.addEventListener('change', applyFilters);
        aiFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            aiFilter.value = '';
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
    })();

    // Declined tab filters (date reported / reason / search)
    (function () {
        const dateFilter = document.getElementById('filterDateD');
        if (!dateFilter) return;

        const reasonFilter = document.getElementById('filterReasonD');
        const searchInput  = document.getElementById('searchInputD');
        const btnClear     = document.getElementById('btnClearD');
        const cards        = Array.from(document.querySelectorAll('#tab-declined .declined-row'));
        const resultsCount = document.getElementById('resultsCountD');
        const noMatch      = document.getElementById('declinedNoMatch');
        const totalCount   = cards.length;

        function applyFilters() {
            const date   = dateFilter.value;
            const reason = reasonFilter.value;
            const search = searchInput.value.trim().toLowerCase();
            let visible  = 0;

            cards.forEach(card => {
                const ok = (!date   || card.dataset.date === date)
                        && (!reason || card.dataset.reason === reason)
                        && (!search || card.dataset.search.includes(search));
                card.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });

            resultsCount.innerHTML = `Showing <strong>${visible}</strong> of <strong>${totalCount}</strong> declined SOS alerts`;
            noMatch.style.display = (visible === 0 && totalCount > 0) ? 'block' : 'none';
        }

        dateFilter.addEventListener('change', applyFilters);
        reasonFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);
        btnClear.addEventListener('click', () => {
            reasonFilter.value = '';
            dateFilter.value = searchInput.value = '';
            applyFilters();
        });

        applyFilters();
    })();

    // Decline confirmation — the reason is picked from the dropdown inside
    // the Bootstrap modal (not free text), same pattern as the Residents
    // Verification rejection modal. Validates a reason was selected,
    // closes the modal (firing a SweetAlert while a Bootstrap modal is
    // still open can cause stacking/focus-trap issues), then asks for one
    // final confirmation before submitting.
    document.querySelectorAll('.decline-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const reasonSelect = form.querySelector('select[name="decline_reason"]');
            if (!reasonSelect.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason required',
                    text: 'Please select a reason for declining before continuing.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1a3c8f',
                });
                return;
            }

            const notifiesCitizen = form.dataset.notifiesCitizen === '1';
            const notifyLine = notifiesCitizen
                ? 'The reporter will get a notification with this reason.'
                : 'This SOS was submitted by a guest, so there is no account to notify.';

            const parentModalEl = form.closest('.modal');
            const bsModal = parentModalEl ? bootstrap.Modal.getOrCreateInstance(parentModalEl) : null;

            const confirmDecline = () => {
                Swal.fire({
                    icon: 'warning',
                    title: 'Decline this SOS alert?',
                    html: `<div style="text-align:left;font-size:.85em;color:#6b7280;">Declining this SOS alert — responders will NOT be dispatched.<br>${notifyLine}</div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, decline',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                }).then(result => {
                    if (result.isConfirmed) form.submit();
                });
            };

            if (bsModal && parentModalEl.classList.contains('show')) {
                parentModalEl.addEventListener('hidden.bs.modal', confirmDecline, { once: true });
                bsModal.hide();
            } else {
                confirmDecline();
            }
        });
    });
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