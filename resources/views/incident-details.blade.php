{{--
    Incident Details — and SOS Details (sos-details.blade.php shows this same
    page with $isSos = true).

    Laid out like the panel's prototype ("Incident Detail — the admin
    workspace"): what happened on the left, what the admin does on the
    right.

      left   Incident Summary & Reporter · Location · Description &
             Attachments · Responders' Report · Activity Timeline
      right  Administrative Actions (changes with the stage: Approve or
             Decline / Assign response team, once approved / change the
             assignment / review the team's Initial report — correct it,
             approve it as resolved or return it (item 49) / resolved /
             declined) · Response Assignment · Internal Note

    The lists (Incidents, SOS Alerts) only have View for Pending: approving,
    declining and assigning the team are done here.
--}}
@php
    $isSos = $isSos ?? $incident->isSos();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – {{ $isSos ? 'SOS Alert' : 'Incident Details' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; color: #111827; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 250px; min-height: 100vh; background: #1a3c8f;
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 18px 16px 16px; border-bottom: 1px solid rgba(255,255,255,.12); }
        .sidebar-brand img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.3); }
        .sidebar-brand-text .title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .85rem; color: #fff; letter-spacing: .5px; line-height: 1.1; }
        .sidebar-brand-text .sub { font-size: .65rem; color: rgba(255,255,255,.6); }
        .sidebar-nav { flex: 1; padding: 18px 0; }
        .sidebar-nav a { display: block; padding: 10px 20px; font-size: .82rem; font-weight: 500; color: rgba(255,255,255,.75); text-decoration: none; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav a.active { color: #fff; font-weight: 700; border-left-color: #fff; background: rgba(255,255,255,.1); }
        .sidebar-logout { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-logout a { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: rgba(255,255,255,.75); text-decoration: none; font-weight: 500; transition: color .2s; }
        .sidebar-logout a:hover { color: #fff; }

        /* ── MAIN ── */
        .main-wrap { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .content { padding: 28px 36px 40px; flex: 1; min-width: 0; }

        .page-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .page-kicker { font-size: .78rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #0d2e7a; }

        /* ── HEADER: what it is, where it is in the process, who sent it ── */
        .incident-head {
            background: #fff; border: 1px solid #e5e7eb; border-left: 6px solid #9ca3af;
            border-radius: 14px; padding: 18px 22px; margin-bottom: 18px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .incident-head.prio-critical { border-left-color: #dc2626; }
        .incident-head.prio-high     { border-left-color: #f97316; }
        .incident-head.prio-low      { border-left-color: #3b82f6; }
        .head-left { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .head-icon { width: 50px; height: 50px; border-radius: 12px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 1.55rem; flex-shrink: 0; }
        .head-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.55rem; line-height: 1.15; color: #111827; }
        .head-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 7px; }
        .head-meta { text-align: right; font-size: .78rem; color: #6b7280; line-height: 1.5; }
        .head-meta strong { display: block; font-size: .92rem; color: #111827; font-weight: 700; }

        .chip { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; border: 1px solid transparent; white-space: nowrap; }
        .chip-pending  { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .chip-active   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .chip-review   { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .chip-resolved { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .chip-declined { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .chip-source   { background: #fff; color: #4b5563; border-color: #d1d5db; }
        /* The team's latest report (items 46, 49) — goes to the report card.
           Purple while it waits for your review; amber for an Initial report
           a team saved before item 49 and hasn't sent to MDRRMO yet. */
        .chip-report-review { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
        .chip-report-open   { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        a.chip { text-decoration: none; }
        a.chip:hover { filter: brightness(.96); }
        .chip-prio-critical { background: #dc2626; color: #fff; }
        .chip-prio-high     { background: #f97316; color: #fff; }
        .chip-prio-low      { background: #3b82f6; color: #fff; }
        .chip-prio-unset    { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }

        /* ── WORKSPACE: details left, actions right ── */
        .workspace { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(300px, 1fr); gap: 18px; align-items: start; }
        .ws-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .card-x { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; min-width: 0; }
        .card-title {
            font-size: .74rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #374151;
            margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px;
        }
        .card-title .title-note { font-size: .72rem; font-weight: 600; letter-spacing: 0; text-transform: none; color: #9ca3af; }
        .card-x.card-actions { border-top: 4px solid #1a3c8f; }

        /* Label / value rows */
        .kv { display: grid; grid-template-columns: 118px minmax(0, 1fr); gap: 10px 14px; font-size: .86rem; }
        .kv dt { color: #6b7280; font-weight: 500; }
        .kv dd { margin: 0; color: #111827; font-weight: 500; overflow-wrap: anywhere; }
        .kv .muted { color: #9ca3af; font-weight: 500; }
        .ok   { color: #047857; font-weight: 700; }
        .warn { color: #b45309; font-weight: 700; }
        .bad  { color: #b91c1c; font-weight: 700; }
        .call-link { color: #1a3c8f; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .call-link:hover { text-decoration: underline; }
        .small-note { font-size: .78rem; color: #6b7280; line-height: 1.5; }

        /* Location */
        #detail-map { width: 100%; height: 210px; border-radius: 10px; overflow: hidden; background: #eef1f6; margin-top: 14px; }
        .map-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .btn-soft {
            display: inline-flex; align-items: center; gap: 6px; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 7px 12px; font-size: .8rem; font-weight: 600; color: #374151; background: #fff; text-decoration: none; cursor: pointer;
        }
        .btn-soft:hover { border-color: #1a3c8f; color: #1a3c8f; }

        /* Description & photos (each photo shows Loading… / the photo / Couldn't load · Retry) */
        .desc-text { font-size: .9rem; line-height: 1.65; color: #1f2937; overflow-wrap: anywhere; white-space: pre-line; }
        .sub-label { font-size: .74rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin: 16px 0 8px; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
        .photo-tile {
            position: relative; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden;
            background: #f3f4f6; border: 1.5px dashed #d1d5db; cursor: pointer; padding: 0;
        }
        .photo-tile img { width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .25s; display: block; }
        .photo-tile.is-loaded { border-style: solid; border-color: #e5e7eb; }
        .photo-tile.is-loaded img { opacity: 1; }
        .photo-tile.is-loaded:hover { box-shadow: 0 0 0 3px #c7d2fe; }
        .photo-state {
            position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; font-size: .76rem; color: #6b7280; text-align: center; padding: 6px;
        }
        .photo-tile.is-loaded .photo-state { display: none; }
        .photo-state .when-failed { display: none; }
        .photo-tile.is-failed .photo-state .when-loading { display: none; }
        .photo-tile.is-failed .photo-state .when-failed { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .photo-retry { background: none; border: none; color: #1a3c8f; font-weight: 700; text-decoration: underline; font-size: .76rem; cursor: pointer; }
        .ai-note { margin-top: 12px; display: flex; gap: 8px; align-items: flex-start; background: #eef2ff; color: #3730a3; border-radius: 8px; padding: 9px 12px; font-size: .8rem; line-height: 1.5; }

        /* Responders' report */
        .report-box { border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .report-box .pdf-icon { font-size: 1.7rem; color: #dc2626; line-height: 1; }
        .report-box .meta { flex: 1; min-width: 160px; }
        .report-box .name { font-size: .88rem; font-weight: 700; color: #111827; }
        .report-box .sub { font-size: .76rem; color: #6b7280; margin-top: 2px; }
        .tag { display: inline-block; margin-left: 6px; font-size: .66rem; font-weight: 800; padding: 2px 8px; border-radius: 10px; vertical-align: middle; }
        .tag-initial { background: #fef3c7; color: #92400e; }
        .tag-final   { background: #d1fae5; color: #065f46; }
        /* The team's latest report stands out until it's approved. */
        .o-report { scroll-margin-top: 16px; }
        .o-report.is-new-review { border-color: #c4b5fd; box-shadow: inset 4px 0 0 #7c3aed; }
        .o-report.is-new-open   { border-color: #fcd34d; box-shadow: inset 4px 0 0 #f59e0b; }
        .new-pill { font-size: .66rem; font-weight: 800; letter-spacing: .04em; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; }
        .new-pill.review { background: #ede9fe; color: #5b21b6; }
        .new-pill.open   { background: #fef3c7; color: #92400e; }
        /* MDRRMO corrected the report (item 49). */
        .edited-note { margin-top: 10px; display: flex; gap: 7px; align-items: flex-start; font-size: .78rem; line-height: 1.45; color: #5b21b6; background: #f5f3ff; border-radius: 8px; padding: 8px 11px; }
        .narrative { margin-top: 12px; background: #f9fafb; border-left: 3px solid #10b981; border-radius: 8px; padding: 10px 12px; font-size: .85rem; line-height: 1.6; color: #1f2937; white-space: pre-line; overflow-wrap: anywhere; }
        .count-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .count-pill { font-size: .75rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; background: #f3f4f6; color: #374151; }
        .count-pill.has { background: #fee2e2; color: #991b1b; }
        .res-photo { width: 120px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; cursor: pointer; margin-top: 12px; }

        /* Notices inside cards */
        .notice { display: flex; gap: 9px; align-items: flex-start; border-radius: 10px; padding: 10px 12px; font-size: .8rem; line-height: 1.5; margin-bottom: 14px; }
        .notice i { margin-top: 1px; }
        .notice-info     { background: #eff6ff; color: #1e40af; }
        .notice-pending  { background: #fffbeb; color: #92400e; }
        .notice-review   { background: #f5f3ff; color: #5b21b6; }
        .notice-initial  { background: #fef3c7; color: #78350f; }
        .notice-returned { background: #fff7ed; color: #9a3412; }
        .notice-ok       { background: #ecfdf5; color: #065f46; }
        .notice-bad      { background: #fef2f2; color: #991b1b; }

        /* ── ADMINISTRATIVE ACTIONS ── */
        .field { margin-bottom: 15px; }
        .field-label { display: block; font-size: .8rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
        .field-label .req { color: #dc2626; font-weight: 700; }
        .field-label .opt { color: #9ca3af; font-weight: 500; }
        .field-help { font-size: .74rem; color: #9ca3af; margin-top: 5px; line-height: 1.45; }
        .f-input {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px; padding: 8px 11px;
            font-size: .85rem; font-family: 'Inter', sans-serif; color: #111827; background: #fafafa; resize: vertical;
        }
        .f-input:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        .fixed-value { display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; font-weight: 700; color: #b91c1c; background: #fef2f2; border-radius: 8px; padding: 7px 11px; }
        /* A report's priority once it's approved: shown, not changed. */
        .fixed-value.fixed-high  { color: #c2410c; background: #fff7ed; }
        .fixed-value.fixed-low   { color: #1d4ed8; background: #eff6ff; }
        .fixed-value.fixed-unset { color: #6b7280; background: #f3f4f6; }
        .fixed-value .fixed-note { font-weight: 500; opacity: .8; }

        /* Priority — pick one */
        .choice-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .choice-input { position: absolute; opacity: 0; pointer-events: none; }
        .prio-choice {
            flex: 1; min-width: 72px; display: flex; align-items: center; justify-content: center; gap: 5px;
            margin: 0; padding: 8px 6px; border-radius: 9px; border: 2px solid currentColor; background: #fff;
            cursor: pointer; user-select: none; font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .9rem;
            transition: background .15s, color .15s;
        }
        .prio-choice i { display: none; }
        .prio-choice.prio-critical { color: #dc2626; }
        .prio-choice.prio-high     { color: #f97316; }
        .prio-choice.prio-low      { color: #3b82f6; }
        .choice-input:checked + .prio-choice { color: #fff; }
        .choice-input:checked + .prio-choice i { display: inline; }
        .choice-input:checked + .prio-choice.prio-critical { background: #dc2626; border-color: #dc2626; }
        .choice-input:checked + .prio-choice.prio-high     { background: #f97316; border-color: #f97316; }
        .choice-input:checked + .prio-choice.prio-low      { background: #3b82f6; border-color: #3b82f6; }
        .choice-input:focus-visible + label { outline: 3px solid #1a3c8f; outline-offset: 2px; }

        /* Response team — pick one or more */
        .team-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
        .team-choice {
            position: relative;
            display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 10px;
            border: 1.5px solid #d1d5db; border-radius: 9px; background: #fff; cursor: pointer; user-select: none;
            transition: border-color .15s, background .15s;
        }
        .team-choice .tick {
            width: 18px; height: 18px; border-radius: 5px; border: 1.5px solid #9ca3af; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem;
        }
        .team-choice .code { font-weight: 800; font-size: .85rem; color: #111827; }
        .team-choice .full { display: block; font-size: .68rem; color: #6b7280; line-height: 1.2; }
        .choice-input:checked + .team-choice { border-color: #1a3c8f; background: #eef2ff; }
        .choice-input:checked + .team-choice .tick { background: #1a3c8f; border-color: #1a3c8f; }
        .team-choice .new-tag {
            display: none; position: absolute; top: -9px; right: 8px;
            font-size: .6rem; font-weight: 800; color: #047857; background: #d1fae5; border: 1px solid #a7f3d0;
            border-radius: 8px; padding: 1px 6px; white-space: nowrap;
        }
        .team-choice.is-new .new-tag { display: inline-block; }

        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .btn-x {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            border-radius: 9px; padding: 10px 16px; font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .92rem;
            cursor: pointer; text-decoration: none; border: 1.5px solid transparent; transition: background .15s, box-shadow .15s, opacity .15s;
        }
        .btn-x:disabled { opacity: .45; cursor: not-allowed; }
        .btn-primary-x { background: #1a3c8f; color: #fff; flex: 1; }
        .btn-primary-x:hover:not(:disabled) { background: #0d2e7a; color: #fff; box-shadow: 0 4px 12px rgba(26,60,143,.25); }
        .btn-green-x { background: #10b981; color: #fff; }
        .btn-green-x:hover { background: #059669; color: #fff; }
        .btn-outline-x { background: #fff; color: #1a3c8f; border-color: #1a3c8f; }
        .btn-outline-x:hover { background: #eef2ff; color: #1a3c8f; }
        .btn-danger-x { background: #fff; color: #dc2626; border-color: #dc2626; }
        .btn-danger-x:hover { background: #dc2626; color: #fff; }
        .btn-amber-x { background: #fff; color: #b45309; border-color: #f59e0b; }
        .btn-amber-x:hover { background: #f59e0b; color: #fff; }
        .btn-block { width: 100%; }
        .stack { display: flex; flex-direction: column; gap: 8px; }
        .stack form { margin: 0; }
        .action-help { font-size: .75rem; color: #6b7280; line-height: 1.5; margin-top: 10px; }
        .action-help i { color: #9ca3af; }
        .why-disabled { font-size: .74rem; color: #b45309; margin-top: 6px; }

        /* Response assignment — who's on it */
        .team-member { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .team-member + .team-member { margin-top: 6px; }
        .role-tag { font-size: .62rem; font-weight: 800; padding: 1px 7px; border-radius: 8px; background: #e0e7ff; color: #3730a3; }
        .role-tag.backup { background: #f3f4f6; color: #4b5563; }

        /* Internal note */
        .note-box { background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: .84rem; line-height: 1.55; color: #374151; white-space: pre-line; overflow-wrap: anywhere; }

        /* ── ACTIVITY TIMELINE ── */
        .tl { list-style: none; margin: 0; padding: 2px 0 0 0; }
        .tl li { position: relative; padding: 0 0 16px 26px; }
        .tl li:last-child { padding-bottom: 0; }
        .tl li::before { content: ''; position: absolute; left: 7px; top: 16px; bottom: -2px; width: 2px; background: #e5e7eb; }
        .tl li:last-child::before { display: none; }
        .tl .dot {
            position: absolute; left: 0; top: 2px; width: 16px; height: 16px; border-radius: 50%;
            background: #fff; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; font-size: .6rem; color: #fff;
        }
        .tl li.done .dot    { background: #10b981; border-color: #10b981; }
        .tl li.current .dot { background: #fff; border-color: #f59e0b; box-shadow: 0 0 0 3px #fef3c7; }
        .tl li.bad .dot     { background: #dc2626; border-color: #dc2626; }
        .tl li.back .dot    { background: #f97316; border-color: #f97316; }
        .tl .tl-label { font-size: .86rem; font-weight: 700; color: #111827; }
        .tl .tl-time { font-weight: 500; color: #6b7280; font-size: .8rem; margin-left: 4px; }
        .tl .tl-sub { font-size: .8rem; color: #6b7280; margin-top: 1px; line-height: 1.45; overflow-wrap: anywhere; }
        .tl li.todo .tl-label { color: #9ca3af; font-weight: 600; }

        /* Modal forms (decline) */
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
        .decline-outside {
            display: flex; gap: 8px; align-items: flex-start;
            background: #fef2f2; color: #991b1b; border-radius: 8px;
            padding: 8px 10px; font-size: .78rem; line-height: 1.45; margin-bottom: 12px;
        }

        /* ── RESPONSIVE (mobile / tablet) ── */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 150; }
        .sidebar-overlay.show { display: block; }

        /* One column (tablets and phones): the summary first, then what to
           do, then the rest — instead of the actions at the very bottom. */
        @media (max-width: 1150px) {
            .workspace { display: flex; flex-direction: column; gap: 16px; }
            .ws-col { display: contents; }
            .o-summary { order: 1; } .o-actions { order: 2; } .o-location { order: 3; }
            .o-description { order: 4; } .o-report { order: 5; } .o-assignment { order: 6; }
            .o-timeline { order: 7; } .o-note { order: 8; }
        }
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%) !important;
                transition: transform .25s ease;
                z-index: 200;
                width: 230px !important; min-width: 230px !important; max-width: 230px !important;
            }
            .sidebar.open { transform: translateX(0) !important; box-shadow: 4px 0 24px rgba(0,0,0,.3); }
            .sidebar-nav a { white-space: normal !important; }
            .main-wrap { margin-left: 0 !important; padding: 20px 16px 32px !important; padding-bottom: 88px !important; }
            .content { padding: 8px 0 24px; }
            img, svg, canvas, iframe { max-width: 100%; }
        }
        @media (max-width: 560px) {
            .main-wrap { padding: 16px 12px 28px !important; padding-bottom: 88px !important; }
            .incident-head { padding: 14px 16px; }
            .head-title { font-size: 1.3rem; }
            .head-meta { text-align: left; }
            .kv { grid-template-columns: minmax(0, 1fr); gap: 2px 0; }
            .kv dd { margin-bottom: 8px; }
            .team-grid { grid-template-columns: minmax(0, 1fr); }
            #detail-map { height: 170px; }
        }

        /* ── App-style nav polish ── */
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; margin: 2px 10px; border-radius: 10px; border-left: none !important; }
        .sidebar-nav a i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
        .sidebar-nav a.active { border-left: none !important; background: rgba(255,255,255,.16) !important; }

        /* ── Bottom app tab bar (mobile only) ── */
        .bottom-tab-bar {
            display: none; position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff; border-top: 1px solid #e5e7eb; box-shadow: 0 -2px 14px rgba(0,0,0,.08);
            z-index: 250; padding-bottom: env(safe-area-inset-bottom, 0);
        }
        .bottom-tab-bar a {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 2px; padding: 8px 2px 7px; color: #8a93a6; text-decoration: none;
            font-size: .62rem; font-weight: 700; letter-spacing: .2px; position: relative;
        }
        .bottom-tab-bar a i { font-size: 1.15rem; }
        .bottom-tab-bar a.active { color: #1a3c8f; }
        .bottom-tab-bar .tab-badge {
            position: absolute; top: 3px; right: calc(50% - 20px);
            background: #dc2626; color: #fff; font-size: .58rem; font-weight: 800; line-height: 1;
            padding: 2px 5px; border-radius: 20px;
        }
        @media (max-width: 900px) { .bottom-tab-bar { display: flex; } }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="bottom-tab-bar">
    <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i><span>Home</span></a>
    <a href="{{ route('incident') }}" class="{{ $isSos ? '' : 'active' }}"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}" class="{{ $isSos ? 'active' : '' }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
</nav>
@include('partials.sidebar')

@php
    $stage      = $incident->stage;                 // pending | active | for_review | resolved
    $isDeclined = (bool) $incident->declined_at;
    $isManual   = $incident->isManualRecord();
    $isGuest    = ! $incident->citizen_id && ! $isManual;
    $reporter   = $incident->reporter_name;         // a guest's typed name too
    $mobile     = $incident->reporter_mobile;
    $priority   = $incident->priority;

    $typeIcons = [
        'Fire' => '🔥', 'Flood' => '🌊', 'Earthquake' => '🏚️', 'Accident' => '🚗',
        'Medical Emergency' => '🚑', 'Landslide' => '⛰️', 'Other' => '⚠️',
    ];
    $icon = $isSos ? '🆘' : ($typeIcons[$incident->emergency_type] ?? '⚠️');

    // Where it is in MDRRMO's process (same as the tabs).
    [$stageText, $stageClass] = match (true) {
        (bool) $incident->needs_review => ['Pending', 'chip-pending'],
        $isDeclined                    => ['Declined', 'chip-declined'],
        $stage === 'for_review'        => ['For Review', 'chip-review'],
        $stage === 'resolved'          => ['Resolved', 'chip-resolved'],
        $incident->awaitingTeam()      => ['Active · Needs Team', 'chip-pending'],
        default                        => ['Active · ' . $incident->status_label, 'chip-active'],
    };

    // Which tab "Back" opens.
    $backTab = ($isDeclined && $isSos) ? 'declined'
        : (['pending' => 'pending', 'active' => 'active', 'for_review' => 'for-review', 'resolved' => 'resolved'][$stage] ?? null);
    $backUrl = $isSos
        ? route('sos-alerts', $backTab ? ['tab' => $backTab] : [])
        : route('incident', $backTab ? ['tab' => $backTab] : []);

    // Resident account status. A blocked one (item 54) says so first.
    $account = null;
    if ($incident->citizen?->isBlocked()) {
        $account = ['Blocked by MDRRMO ' . $incident->citizen->blocked_at->format('M d, Y') . ' — ' . ($incident->citizen->block_reason ?: 'no reason given'), 'bad'];
    } elseif ($incident->citizen) {
        $account = match ($incident->citizen->verification_status) {
            'verified' => ['Verified ✓', 'ok'],
            'rejected' => ['ID rejected', 'bad'],
            default    => ['Not verified yet (ID under review)', 'warn'],
        };
    }

    // Location: barangay, GPS pin, map.
    $barangay = app(\App\Services\BarangayLocationService::class)->barangayFromText($incident->location);
    $hasPin = $incident->latitude && $incident->longitude;
    $approxPoint = (! $hasPin && $barangay) ? (\App\Services\BarangayLocationService::allCoordinates()[$barangay] ?? null) : null;
    $lat = $incident->latitude  ?? ($approxPoint[0] ?? 15.8952);
    $lng = $incident->longitude ?? ($approxPoint[1] ?? 120.6263);

    // Photos the reporter sent: a JSON list (very old rows hold one plain path).
    $photos = collect();
    if ($incident->photo_path) {
        $decoded = json_decode($incident->photo_path, true);
        $photos = is_array($decoded) ? collect($decoded) : collect([$incident->photo_path]);
    }
    $photos = $photos->filter(fn ($p) => is_string($p) && $p !== '')->values();

    // The responders' MDRRMC report — the Initial report (a Final one if it
    // was sent before item 49) — or the one MDRRMO typed in for an incident
    // handled outside the app.
    $report = is_array($incident->report_data) ? $incident->report_data : null;
    $reportIsInitial = $report && ($report['report_type'] ?? 'final') === 'initial';
    $reportKind = $reportIsInitial || ! $report ? 'Initial report' : 'Final report';
    $parseTime = function ($value) {
        if (empty($value)) return null;
        try {
            return \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            return null;
        }
    };
    $reportUpdatedAt = $report ? $parseTime($report['updated_at'] ?? null) : null;
    // MDRRMO corrected it on For Review (item 49).
    $reportEditedAt = $report ? $parseTime($report['edited_at'] ?? null) : null;
    $editedParts = $report ? array_values(array_filter((array) ($report['edited_parts'] ?? []), 'is_string')) : [];
    $casualtyCounts = [];
    foreach (['dead' => 'Dead', 'injured' => 'Injured', 'missing' => 'Missing'] as $key => $label) {
        $casualtyCounts[$label] = count(array_filter((array) ($report['casualties'][$key] ?? []), 'is_array'));
    }
    $isReturned = $incident->status === 'responding' && $incident->report_returned_at && $incident->report_return_reason;
    // The team's latest report (Incident::teamReport(), item 46): said at
    // the top and in Administrative Actions. Opening this page counts as
    // seeing it for the "report received" notice (partials/report-alert,
    // see data-report-seen below).
    $teamReport = $incident->teamReport();

    // Response team. Approving sends it to the team(s) the admin ticks —
    // the usual ones for the type start ticked (item 53); on Active the
    // admin can notify more teams. $needsTeam: approved while approving
    // didn't send it anywhere (item 36) — not sent yet.
    $needsTeam    = $incident->awaitingTeam();
    $teamOnIt     = $incident->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->values();
    $assigned     = $incident->assignedTeamCodes();
    $toAll        = $incident->sentToAllTeams();
    $suggested    = $incident->defaultTeams();
    $checkedTeams = (array) old('teams', []);
    $approveTeams = (array) old('teams', $suggested);
    $usualFor     = $isSos ? ($incident->sos_emergency_type ?: 'an SOS') : $incident->emergency_type;
    $teamList = function (array $teams) {
        if (count($teams) <= 1) return $teams[0] ?? '';
        return implode(', ', array_slice($teams, 0, -1)) . ' and ' . end($teams);
    };
    $sentTo        = $toAll ? 'all responders' : ($assigned ? $teamList($assigned) : 'the responders');
    $firstAccepted = $teamOnIt->first()?->pivot?->accepted_at;
    $ago           = fn ($at) => $at->gt(now()->subMinute()) ? 'just now' : $at->diffForHumans();

    // The last time the admin notified specific teams, from the Audit Log
    // line it writes (IncidentController::updateAssignment()): who, and
    // whether it was "no one has accepted yet" or asking them to help.
    $lastNotify = null;
    if (! $incident->needs_review && ! $incident->declined_at) {
        $lastNotify = \App\Models\AuditLog::where('auditable_type', \App\Models\Incident::class)
            ->where('auditable_id', $incident->id)
            ->where('action', 'updated')
            ->where('description', 'like', 'Notified %')
            ->latest('id')->first();
    }
    $notifiedAt    = $lastNotify?->created_at;
    $notifiedTeams = $lastNotify ? (array) ($lastNotify->new_values['notified'] ?? $lastNotify->new_values['teams'] ?? []) : [];
    $notifiedHelp  = $lastNotify && ($lastNotify->new_values['kind'] ?? null) === 'help';

    // Responders who declined it ("not me") — it's off their list until
    // the admin notifies their team.
    $noOneYet   = $stage === 'active' && ! $needsTeam && $teamOnIt->isEmpty();
    $declinedBy = $noOneYet ? $incident->declinedBy()->orderBy('incident_declines.declined_at')->get() : collect();
@endphp

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        <div class="page-top">
            <span class="page-kicker">{{ $isSos ? 'SOS Alert Details' : 'Incident Details' }}</span>
            <a href="{{ $backUrl }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $isSos ? 'SOS Alerts' : 'Incidents' }}</a>
        </div>

        {{-- Opening this page counts as seeing its notice (partials/report-alert):
             the team's report, or a resident's new report waiting here. --}}
        @if($teamReport)
            <span hidden data-report-seen="{{ $teamReport['key'] }}"></span>
        @elseif($incident->needs_review && ! $isSos && ! $isDeclined)
            <span hidden data-report-seen="new-{{ $incident->id }}"></span>
        @endif

        <!-- HEADER -->
        <div class="incident-head prio-{{ $priority ?: 'unset' }}">
            <div class="head-left">
                <div class="head-icon">{{ $icon }}</div>
                <div style="min-width:0;">
                    <h1 class="head-title">{{ $incident->display_type ?: 'Incident' }}</h1>
                    <div class="head-badges">
                        <span class="chip {{ $stageClass }}">{{ $stageText }}</span>
                        @if($teamReport)
                            <a href="#report" class="chip {{ $teamReport['review'] ? 'chip-report-review' : 'chip-report-open' }}" title="Sent {{ $teamReport['at']->format('M d, g:i A') }} — go to the report">
                                <i class="bi {{ $teamReport['review'] ? 'bi-clipboard2-check-fill' : 'bi-file-earmark-text-fill' }}"></i>
                                {{ $teamReport['type'] === 'final' ? 'Final report' : 'Initial report' }} · {{ $ago($teamReport['at']) }}
                            </a>
                        @endif
                        <span class="chip chip-source">Source: {{ $incident->source_label }}</span>
                        @if($priority)
                            <span class="chip chip-prio-{{ $priority }}">{{ ucfirst($priority) }} priority</span>
                        @else
                            <span class="chip chip-prio-unset">No priority yet</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="head-meta">
                <strong>{{ $incident->created_at->format('M d, Y — g:i A') }}</strong>
                {{ $isManual ? 'when it happened' : 'reported ' . $incident->created_at->diffForHumans() }}
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
            </div>
        @endif

        <div class="workspace">

            <!-- ═══════════ LEFT: what happened ═══════════ -->
            <div class="ws-col">

                <!-- INCIDENT SUMMARY & REPORTER -->
                <section class="card-x o-summary">
                    <div class="card-title">Incident Summary &amp; Reporter</div>
                    <dl class="kv">
                        <dt>Type</dt>
                        <dd>{{ $icon }} {{ $incident->display_type }}</dd>

                        <dt>{{ $isManual ? 'Happened' : 'Date / time' }}</dt>
                        <dd>{{ $incident->created_at->format('M d, Y — g:i A') }}</dd>

                        <dt>Reporter</dt>
                        <dd>
                            {{ $reporter }}
                            @if($mobile)
                                · <a href="tel:{{ $mobile }}" class="call-link"><i class="bi bi-telephone-fill"></i> {{ $mobile }}</a>
                            @endif
                        </dd>

                        <dt>Account</dt>
                        <dd>
                            @if($account)
                                <span class="{{ $account[1] }}">{{ $account[0] }}</span>
                            @elseif($isManual)
                                <span class="muted">None — added by MDRRMO{{ $incident->resolvedBy ? ' (' . $incident->resolvedBy->name . ')' : '' }}</span>
                            @elseif($incident->citizen_id)
                                <span class="muted">Account removed</span>
                            @else
                                <span class="warn">Guest — no account</span>
                                <div class="small-note">The name and number were typed in before sending. Call to confirm.</div>
                            @endif
                        </dd>

                        <dt>Source</dt>
                        <dd>
                            @if($isManual)
                                Handled outside the app — reported to MDRRMO via {{ strtolower($incident->reported_via ?: 'other') }}
                            @elseif($isSos)
                                SOS button in the residents app
                            @else
                                Report Emergency in the residents app
                            @endif
                        </dd>

                        <dt>Priority</dt>
                        <dd>
                            @if($priority)
                                <span class="chip chip-prio-{{ $priority }}">{{ ucfirst($priority) }}</span>
                                @if($isSos) <span class="small-note">every SOS is Critical</span> @endif
                            @else
                                <span class="muted">Not set — you choose it when you approve the report.</span>
                            @endif
                        </dd>
                    </dl>
                </section>

                <!-- LOCATION -->
                <section class="card-x o-location">
                    <div class="card-title">Location</div>
                    <dl class="kv">
                        <dt>Barangay</dt>
                        <dd>{!! $barangay ? e($barangay) : '<span class="muted">Not identified</span>' !!}</dd>

                        <dt>Address</dt>
                        <dd>{{ $incident->location ?: '—' }}</dd>

                        <dt>GPS</dt>
                        <dd>
                            @if($hasPin)
                                {{ number_format((float) $incident->latitude, 5) }}, {{ number_format((float) $incident->longitude, 5) }}
                            @else
                                <span class="muted">No GPS pin{{ $approxPoint ? " — the map shows the middle of Brgy. {$barangay}" : '' }}</span>
                            @endif
                        </dd>
                    </dl>
                    <div id="detail-map" aria-label="Map of the incident location"></div>
                    <div class="map-actions">
                        <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="btn-soft">
                            <i class="bi bi-map"></i> Open in Google Maps
                        </a>
                        @if($hasPin)
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="btn-soft">
                                <i class="bi bi-sign-turn-right"></i> Directions
                            </a>
                        @endif
                    </div>
                </section>

                <!-- DESCRIPTION & ATTACHMENTS -->
                <section class="card-x o-description">
                    <div class="card-title">
                        Description &amp; Attachments
                        @if($photos->count())
                            <span class="title-note">{{ $photos->count() }} {{ \Illuminate\Support\Str::plural('photo', $photos->count()) }}</span>
                        @endif
                    </div>
                    <div class="desc-text">{{ $incident->description ?: 'No description was sent.' }}</div>

                    @if($photos->count())
                        <div class="sub-label">Photos from the reporter</div>
                        <div class="photo-grid">
                            @foreach($photos as $index => $photoPath)
                                <button type="button" class="photo-tile" data-index="{{ $index }}" aria-label="Open photo {{ $index + 1 }}">
                                    <img src="{{ Storage::url($photoPath) }}" alt="Photo {{ $index + 1 }}" loading="lazy">
                                    <span class="photo-state">
                                        <span class="when-loading"><span class="spinner-border spinner-border-sm text-secondary"></span> Loading…</span>
                                        <span class="when-failed">
                                            <i class="bi bi-image" style="font-size:1.2rem;color:#9ca3af;"></i>
                                            Couldn't load the image.
                                            <span class="photo-retry" role="button" tabindex="0">Retry</span>
                                        </span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(! $isManual)
                        <div class="sub-label">Photos from the reporter</div>
                        <div class="small-note">No photos were sent.</div>
                    @endif

                    @if($incident->ai_detected_type)
                        <div class="ai-note">
                            <i class="bi bi-stars"></i>
                            <span>
                                The photo looks like <strong>{{ $incident->ai_detected_type }}</strong>@if($incident->ai_confidence) ({{ $incident->ai_confidence }} confidence)@endif.
                                @if($incident->ai_analysis) <em>{{ $incident->ai_analysis }}</em> @endif
                                Only a hint — check the photo yourself.
                            </span>
                        </div>
                    @endif
                </section>

                <!-- RESPONDERS' REPORT -->
                @if($report || in_array($incident->status, ['for_review', 'resolved'], true))
                    <section class="card-x o-report{{ $teamReport ? ' is-new-' . ($teamReport['review'] ? 'review' : 'open') : '' }}" id="report">
                        <div class="card-title">
                            {{ $isManual ? 'MDRRMC Incident Report' : "Responders' Report" }}
                            @if($teamReport)
                                <span class="new-pill {{ $teamReport['review'] ? 'review' : 'open' }}">{{ $teamReport['review'] ? 'Waiting for your review' : ($teamReport['revision'] > 1 ? 'Updated' : 'Saved') }} · {{ $ago($teamReport['at']) }}</span>
                            @elseif($reportUpdatedAt)
                                <span class="title-note">{{ ($report['revision'] ?? 1) > 1 ? 'Last updated' : 'Filed' }} {{ $reportUpdatedAt->format('M d, g:i A') }}</span>
                            @endif
                        </div>

                        @if($isReturned)
                            <div class="notice notice-returned">
                                <i class="bi bi-arrow-return-left"></i>
                                <span>You sent this report back on {{ $incident->report_returned_at->format('M d, g:i A') }}: <strong>{{ rtrim($incident->report_return_reason, '. ') }}</strong>. It comes back to For Review when they send it again.</span>
                            </div>
                        @endif

                        @if($report)
                            <div class="report-box">
                                <div class="pdf-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                <div class="meta">
                                    <div class="name">
                                        MDRRMC Incident Report
                                        <span class="tag {{ $reportIsInitial ? 'tag-initial' : 'tag-final' }}">{{ $reportIsInitial ? 'Initial' : 'Final' }}</span>
                                    </div>
                                    <div class="sub">
                                        @if($isReturned)
                                            Sent back — the team fixes it and sends it again.
                                        @elseif($incident->isForReview())
                                            Waiting for your review — correct it if needed, then approve it.
                                        @elseif($isManual)
                                            Added by MDRRMO.
                                        @elseif($incident->status === 'resolved')
                                            Approved{{ ! empty($report['reviewed_by']) ? ' by ' . $report['reviewed_by'] : '' }}.
                                        @elseif($reportIsInitial)
                                            Saved by the team — it reaches For Review when they send it from the app.
                                        @else
                                            {{ $reportKind }}.
                                        @endif
                                    </div>
                                </div>
                                @if($incident->isForReview())
                                    <a href="{{ route('incident.report.edit', $incident) }}" class="btn-soft"><i class="bi bi-pencil-square"></i> Review &amp; Edit</a>
                                @endif
                                <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="btn-soft"><i class="bi bi-eye"></i> View</a>
                                <a href="{{ route('incident.report-pdf', [$incident, 'download' => 1]) }}" class="btn-soft" title="Download"><i class="bi bi-download"></i></a>
                            </div>

                            <div class="count-row">
                                @foreach($casualtyCounts as $label => $count)
                                    <span class="count-pill {{ $count ? 'has' : '' }}">{{ $label }}: {{ $count }}</span>
                                @endforeach
                            </div>

                            @if($reportEditedAt)
                                <div class="edited-note">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>Corrected by <strong>{{ $report['edited_by'] ?? 'MDRRMO' }}</strong> {{ $reportEditedAt->format('M d, g:i A') }}{{ $editedParts ? ' — ' . implode(', ', $editedParts) : '' }}.</span>
                                </div>
                            @endif
                        @endif
                    </section>
                @endif

                <!-- ACTIVITY TIMELINE -->
                <section class="card-x o-timeline">
                    <div class="card-title">Activity Timeline</div>
                    <ol class="tl">
                        @if($isManual)
                            <li class="done">
                                <span class="dot"><i class="bi bi-check"></i></span>
                                <div class="tl-label">Happened <span class="tl-time">{{ $incident->created_at->format('M d, g:i A') }}</span></div>
                                <div class="tl-sub">Reported to MDRRMO via {{ strtolower($incident->reported_via ?: 'other') }}{{ $incident->guest_name ? ' by ' . $incident->guest_name : '' }}.</div>
                            </li>
                            <li class="done">
                                <span class="dot"><i class="bi bi-check"></i></span>
                                <div class="tl-label">Resolved <span class="tl-time">{{ $incident->resolved_at?->format('M d, g:i A') }}</span></div>
                                <div class="tl-sub">Handled outside the app{{ ! empty($report['team']) ? ' by ' . collect($report['team'])->pluck('name')->filter()->join(', ') : '' }}.</div>
                            </li>
                            <li class="done">
                                <span class="dot"><i class="bi bi-check"></i></span>
                                <div class="tl-label">Added to ResQPulse <span class="tl-time">{{ $report['recorded_at'] ?? '' }}</span></div>
                                <div class="tl-sub">By {{ $incident->resolvedBy?->name ?? 'MDRRMO' }}{{ $report ? ', with its MDRRMC Incident Report' : '' }}.</div>
                            </li>
                        @else
                            {{-- 1. Submitted --}}
                            <li class="done">
                                <span class="dot"><i class="bi bi-check"></i></span>
                                <div class="tl-label">Submitted <span class="tl-time">{{ $incident->created_at->format('M d, g:i A') }}</span></div>
                                <div class="tl-sub">{{ $isSos ? 'SOS' : 'Report' }} from {{ $reporter === 'Guest' ? 'a guest' : $reporter . ($isGuest ? ' (guest)' : '') }}.</div>
                            </li>

                            {{-- 2. Reviewed --}}
                            @if($incident->needs_review)
                                <li class="current">
                                    <span class="dot"></span>
                                    <div class="tl-label">Reviewed — waiting for you</div>
                                    <div class="tl-sub">Approve it or decline it.</div>
                                </li>
                            @elseif($isDeclined)
                                <li class="bad">
                                    <span class="dot"><i class="bi bi-x"></i></span>
                                    <div class="tl-label">Declined <span class="tl-time">{{ $incident->declined_at->format('M d, g:i A') }}</span></div>
                                    <div class="tl-sub">{{ $incident->reviewedBy ? 'By ' . $incident->reviewedBy->name . ' — ' : '' }}{{ $incident->decline_reason }}</div>
                                </li>
                            @else
                                <li class="done">
                                    <span class="dot"><i class="bi bi-check"></i></span>
                                    <div class="tl-label">Approved <span class="tl-time">{{ $incident->reviewed_at?->format('M d, g:i A') }}</span></div>
                                    <div class="tl-sub">{{ $incident->reviewedBy ? 'By ' . $incident->reviewedBy->name : 'Approved by MDRRMO' }}{{ $priority ? ' · ' . ucfirst($priority) . ' priority' : '' }}.</div>
                                </li>
                            @endif

                            @unless($isDeclined)
                                @if($incident->needs_review)
                                    <li class="todo"><span class="dot"></span><div class="tl-label">Assigned · Accepted · Report · Resolved — not yet</div></li>
                                @elseif($needsTeam)
                                    {{-- 3. Approved, not sent to anyone yet (item 36). --}}
                                    <li class="current">
                                        <span class="dot"></span>
                                        <div class="tl-label">Assigned — waiting for you</div>
                                        <div class="tl-sub">Send it to the responders in Administrative Actions.</div>
                                    </li>
                                    <li class="todo"><span class="dot"></span><div class="tl-label">Accepted · Report · Resolved — not yet</div></li>
                                @else
                                    {{-- 3. Assigned — when the team was set. A team changed after a
                                         responder accepted is mentioned, so the times stay in order. --}}
                                    @php
                                        $changedLater  = $incident->assigned_at && $firstAccepted
                                            && $incident->assigned_at->gt(\Illuminate\Support\Carbon::parse($firstAccepted));
                                        $assignedAt    = $changedLater ? $incident->reviewed_at : ($incident->assigned_at ?? $incident->reviewed_at);
                                        $teamChangedAt = $changedLater ? $incident->assigned_at : null;
                                    @endphp
                                    <li class="done">
                                        <span class="dot"><i class="bi bi-check"></i></span>
                                        <div class="tl-label">Assigned <span class="tl-time">{{ $assignedAt?->format('M d, g:i A') }}</span></div>
                                        <div class="tl-sub">
                                            {{ $assigned ? 'Sent to ' . $sentTo . '.' : 'Sent to the responders (before teams were assigned).' }}
                                            @if($teamChangedAt) Team changed {{ $teamChangedAt->format('M d, g:i A') }}. @endif
                                            @if($notifiedAt)
                                                {{ $notifiedHelp ? 'Asked ' . $teamList($notifiedTeams) . ' to help' : 'Notified ' . $teamList($notifiedTeams) . ' again' }} {{ $notifiedAt->format('M d, g:i A') }}.
                                            @endif
                                        </div>
                                    </li>

                                    {{-- 4. Accepted --}}
                                    @if($teamOnIt->isNotEmpty())
                                        <li class="done">
                                            <span class="dot"><i class="bi bi-check"></i></span>
                                            <div class="tl-label">Accepted <span class="tl-time">{{ $firstAccepted ? \Illuminate\Support\Carbon::parse($firstAccepted)->format('M d, g:i A') : '' }}</span></div>
                                            <div class="tl-sub">{{ $teamOnIt->pluck('full_name')->join(', ') }} — responding.</div>
                                        </li>
                                    @else
                                        <li class="current">
                                            <span class="dot"></span>
                                            <div class="tl-label">Accepted — waiting for the team</div>
                                            <div class="tl-sub">No responder has accepted it yet.</div>
                                        </li>
                                    @endif

                                    {{-- 5. Report --}}
                                    @if($incident->report_returned_at)
                                        <li class="back">
                                            <span class="dot"><i class="bi bi-arrow-return-left"></i></span>
                                            <div class="tl-label">Report sent back <span class="tl-time">{{ $incident->report_returned_at->format('M d, g:i A') }}</span></div>
                                            <div class="tl-sub">{{ $incident->report_return_reason }}</div>
                                        </li>
                                    @endif
                                    @if($incident->report_submitted_at || $incident->status === 'resolved')
                                        <li class="done">
                                            <span class="dot"><i class="bi bi-check"></i></span>
                                            <div class="tl-label">{{ $reportKind }} sent <span class="tl-time">{{ $incident->report_submitted_at?->format('M d, g:i A') }}</span></div>
                                            <div class="tl-sub">
                                                {{ $incident->isForReview() ? 'Waiting for your review.' : 'Reviewed by MDRRMO.' }}
                                                @if($reportEditedAt) Corrected by {{ $report['edited_by'] ?? 'MDRRMO' }} {{ $reportEditedAt->format('M d, g:i A') }}. @endif
                                            </div>
                                        </li>
                                    @elseif($report && $reportIsInitial)
                                        <li class="current">
                                            <span class="dot"></span>
                                            @if($isReturned && ! $teamReport)
                                                <div class="tl-label">Initial report — waiting for the team to send it again</div>
                                                <div class="tl-sub">They fix what you sent it back for, then send it to you again.</div>
                                            @else
                                                <div class="tl-label">Initial report {{ ($report['revision'] ?? 1) > 1 ? 'updated' : 'saved' }} <span class="tl-time">{{ $reportUpdatedAt?->format('M d, g:i A') }}</span></div>
                                                <div class="tl-sub">Not sent to MDRRMO yet — it reaches For Review when the team sends it from the app.</div>
                                            @endif
                                        </li>
                                    @elseif($teamOnIt->isNotEmpty())
                                        <li class="todo"><span class="dot"></span><div class="tl-label">Initial report — not yet</div></li>
                                    @endif

                                    {{-- 6. Resolved --}}
                                    @if($incident->status === 'resolved')
                                        <li class="done">
                                            <span class="dot"><i class="bi bi-check"></i></span>
                                            <div class="tl-label">Resolved <span class="tl-time">{{ $incident->resolved_at?->format('M d, g:i A') }}</span></div>
                                            <div class="tl-sub">{{ $incident->resolvedBy ? 'Report approved by ' . $incident->resolvedBy->name . '.' : 'Closed.' }}</div>
                                        </li>
                                    @elseif($incident->isForReview())
                                        <li class="current">
                                            <span class="dot"></span>
                                            <div class="tl-label">Resolved — waiting for your approval</div>
                                        </li>
                                    @else
                                        <li class="todo"><span class="dot"></span><div class="tl-label">Resolved — not yet</div></li>
                                    @endif
                                @endif
                            @endunless
                        @endif
                    </ol>
                </section>
            </div>

            <!-- ═══════════ RIGHT: what the admin does ═══════════ -->
            <div class="ws-col">

                <!-- ADMINISTRATIVE ACTIONS -->
                <section class="card-x card-actions o-actions" id="actions">
                    <div class="card-title">Administrative Actions</div>

                    @if($incident->needs_review)
                        {{-- ── PENDING: choose the response team, then Approve & Send
                             (item 53, as in item 26) — or Decline. Only the ticked
                             teams are notified; on Active the admin can notify
                             more teams. ── --}}
                        <div class="notice notice-pending">
                            <i class="bi bi-hourglass-split"></i>
                            <span>
                                Check the details, photos and location first.
                                @if($mobile) Call <a href="tel:{{ $mobile }}" class="call-link">{{ $mobile }}</a> to confirm. @endif
                                Then choose the response team — only that team is notified when you approve it.
                            </span>
                        </div>

                        <form action="{{ route('incident.approve', $incident->id) }}" method="POST" id="approveForm">
                            @csrf

                            <div class="field">
                                <span class="field-label">Priority level @unless($isSos)<span class="req">(required)</span>@endunless</span>
                                @if($isSos)
                                    <span class="fixed-value"><i class="bi bi-exclamation-octagon-fill"></i> Critical — every SOS</span>
                                @else
                                    <div class="choice-row" role="radiogroup" aria-label="Priority">
                                        @foreach(['critical' => 'Critical', 'high' => 'High', 'low' => 'Low'] as $value => $label)
                                            <input class="choice-input" type="radio" name="priority" id="prio-{{ $value }}" value="{{ $value }}"
                                                   {{ old('priority', $priority) === $value ? 'checked' : '' }}>
                                            <label class="prio-choice prio-{{ $value }}" for="prio-{{ $value }}"><i class="bi bi-check-lg"></i>{{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <div class="field-help">It can't be changed after you approve the report.</div>
                                @endif
                            </div>

                            <div class="field">
                                <span class="field-label">Send to <span class="req">(required)</span></span>
                                @include('partials.team-choices', ['checked' => $approveTeams, 'previous' => null])
                                <div class="field-help">
                                    Usually needed for {{ $usualFor }}: {{ $teamList($suggested) }} — ticked for you.
                                    Only the ticked teams are notified and can accept it. You can notify more teams later, while it's Active.
                                </div>
                            </div>

                            <div class="field">
                                <label class="field-label" for="approve_notes">Notes for the team <span class="opt">(optional)</span></label>
                                <textarea name="assignment_notes" id="approve_notes" rows="2" maxlength="500" class="f-input"
                                          placeholder="e.g. Proceed via the market road.">{{ old('assignment_notes') }}</textarea>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" class="btn-x btn-primary-x" id="approveSubmit">
                                    <i class="bi bi-check2-circle"></i> Approve &amp; Send
                                </button>
                                <button type="button" class="btn-x btn-danger-x" data-bs-toggle="modal" data-bs-target="#declineModal">
                                    <i class="bi bi-x-circle"></i> Decline
                                </button>
                            </div>
                            <div class="why-disabled" id="approveHint" hidden></div>
                            <div class="action-help">
                                <i class="bi bi-info-circle"></i>
                                <strong>Approve &amp; Send</strong> → the {{ $isSos ? 'SOS' : 'report' }} moves to <strong>Active</strong> and the ticked teams are notified right away, with your notes.
                                {{ $incident->citizen_id ? 'The resident is told it was approved.' : '' }}
                            </div>
                        </form>

                    @elseif($isDeclined)
                        {{-- ── DECLINED ── --}}
                        <div class="notice notice-bad">
                            <i class="bi bi-x-circle-fill"></i>
                            <span>
                                Declined {{ $incident->declined_at->format('M d, Y g:i A') }}{{ $incident->reviewedBy ? ' by ' . $incident->reviewedBy->name : '' }}:
                                <strong>{{ $incident->decline_reason ?: 'no reason given' }}</strong>. The responders were never notified.
                            </span>
                        </div>
                        <form method="POST" action="{{ route('incident.destroy', $incident->id) }}" id="deleteForm"
                              data-what="{{ $incident->display_type }} from {{ $reporter }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-x btn-danger-x btn-block"><i class="bi bi-trash3"></i> Delete this {{ $isSos ? 'SOS alert' : 'report' }}</button>
                        </form>
                        <div class="action-help"><i class="bi bi-info-circle"></i> Deleting removes it for good, with its photos. The Audit Log keeps a line about it.</div>

                    @elseif($incident->isForReview())
                        {{-- ── FOR REVIEW: the team's Initial report (item 49) — correct
                             it if needed, then approve it as resolved, or return it. ── --}}
                        <div class="notice notice-review">
                            <i class="bi bi-clipboard2-check"></i>
                            <span>
                                @if($report)
                                    <strong>{{ $teamOnIt->first()?->full_name ?? 'The team' }}</strong> sent the <strong>{{ $reportKind }}</strong>{{ $teamReport && $teamReport['again'] ? ' again' : '' }}
                                    {{ $teamReport ? $ago($teamReport['at']) : '' }}{{ $incident->report_submitted_at ? ' (' . $incident->report_submitted_at->format('M d, g:i A') . ')' : '' }}.
                                    Check the narrative, casualties, actions taken and photo — correct anything that's wrong — then approve it.
                                @else
                                    <strong>{{ $teamOnIt->first()?->full_name ?? 'The team' }}</strong> marked it done {{ $teamReport ? $ago($teamReport['at']) : '' }} without a report (an older version of the app).
                                @endif
                                @if($reportEditedAt)
                                    <br>You corrected it {{ $ago($reportEditedAt) }}{{ $editedParts ? ': ' . implode(', ', $editedParts) : '' }}.
                                @endif
                            </span>
                        </div>
                        <div class="stack">
                            @if($report)
                                <a href="{{ route('incident.report.edit', $incident) }}" class="btn-x btn-outline-x btn-block">
                                    <i class="bi bi-pencil-square"></i> Review &amp; Edit Report
                                </a>
                            @endif
                            <form action="{{ route('incident.confirm-resolved', $incident->id) }}" method="POST" class="resolve-form"
                                  data-type="{{ $isSos ? 'SOS' : $incident->emergency_type }}"
                                  data-notifies-citizen="{{ $incident->citizen_id ? '1' : '0' }}">
                                @csrf
                                <button type="submit" class="btn-x btn-green-x btn-block"><i class="bi bi-check2-circle"></i> Approve as Resolved</button>
                            </form>
                            <button type="button" class="btn-x btn-amber-x btn-block" data-bs-toggle="modal" data-bs-target="#returnModal">
                                <i class="bi bi-arrow-return-left"></i> Return to Responders
                            </button>
                        </div>
                        <div class="action-help">
                            <i class="bi bi-info-circle"></i>
                            @if($report) <strong>Review &amp; Edit</strong> opens the report as a form, to correct it before approving. @endif
                            <strong>Approve as Resolved</strong> closes the incident and adds "Reviewed by" with your name to the PDF.
                            <strong>Return</strong> sends the report back to the team with what to fix — it goes back to Active.
                        </div>

                    @elseif($incident->status === 'resolved')
                        {{-- ── RESOLVED ── --}}
                        <div class="notice notice-ok">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>
                                Resolved{{ $incident->resolved_at ? ' ' . $incident->resolved_at->format('M d, Y g:i A') : '' }}{{ $incident->resolvedBy ? ($isManual ? ' — added by ' : ' — report approved by ') . $incident->resolvedBy->name : '' }}.
                                Nothing left to do.
                            </span>
                        </div>
                        @if($report)
                            <div class="stack">
                                <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="btn-x btn-outline-x btn-block"><i class="bi bi-file-earmark-pdf"></i> Open Report</a>
                                <a href="{{ route('incident.report-pdf', [$incident, 'download' => 1]) }}" class="btn-x btn-outline-x btn-block"><i class="bi bi-download"></i> Download PDF</a>
                            </div>
                        @endif

                    @else
                        {{-- ── ACTIVE: it went to the team(s) chosen when it was
                             approved. The admin can notify specific teams: "no one has
                             accepted yet", or asking a team to help once
                             someone is on it. ($needsTeam: approved while
                             approving didn't send it anywhere — send it.) ── --}}
                        @if($needsTeam)
                            <div class="notice notice-pending">
                                <i class="bi bi-people-fill"></i>
                                <span>
                                    Approved{{ $incident->reviewedBy ? ' by ' . $incident->reviewedBy->name : '' }}{{ $incident->reviewed_at ? ' ' . $incident->reviewed_at->format('M d, g:i A') : '' }}, but not sent yet.
                                    <strong>Send it to the responders</strong> — no one is notified until you do.
                                </span>
                            </div>
                        @elseif($isReturned)
                            <div class="notice notice-returned">
                                <i class="bi bi-arrow-return-left"></i>
                                <span>
                                    Report sent back: <strong>{{ rtrim($incident->report_return_reason, '. ') }}</strong>.
                                    @if($teamReport)
                                        {{ $teamOnIt->first()?->full_name ?? 'The team' }} updated it {{ $ago($teamReport['at']) }} but hasn't sent it yet — it comes back to For Review when they do.
                                    @else
                                        Waiting for the team to send it again.
                                    @endif
                                    @if($notifiedHelp && $notifiedAt) Asked {{ $teamList($notifiedTeams) }} to help {{ $ago($notifiedAt) }}. @endif
                                </span>
                            </div>
                        @elseif($teamOnIt->isEmpty())
                            <div class="notice notice-pending">
                                <i class="bi bi-broadcast"></i>
                                <span>
                                    Sent to <strong>{{ $sentTo }}</strong>{{ $incident->assigned_at ? ' ' . $ago($incident->assigned_at) : '' }}.
                                    @if($notifiedAt) Notified {{ $teamList($notifiedTeams) }} again {{ $ago($notifiedAt) }}. @endif
                                    @if($declinedBy->isNotEmpty())
                                        {{ $declinedBy->map(fn ($r) => "{$r->full_name} ({$r->agency})")->join(', ', ' and ') }} declined it.
                                    @endif
                                    <strong>No one has accepted yet.</strong>
                                </span>
                            </div>
                        @elseif($teamReport)
                            {{-- An Initial report the team saved before item 49, when the
                                 incident stayed Active until a Final report came. It goes
                                 to For Review when they send it from the app. --}}
                            <div class="notice notice-initial">
                                <i class="bi bi-file-earmark-text-fill"></i>
                                <span>
                                    <strong>{{ $teamOnIt->first()->full_name }}</strong> {{ $teamReport['revision'] > 1 ? 'updated an' : 'saved an' }} <strong>Initial report</strong> {{ $ago($teamReport['at']) }}, but hasn't sent it to MDRRMO yet.
                                    When they send it from the app, it moves to <strong>For Review</strong> for you to approve.
                                    @if($notifiedHelp && $notifiedAt) Asked {{ $teamList($notifiedTeams) }} to help {{ $ago($notifiedAt) }}. @endif
                                </span>
                            </div>
                            @if($report)
                                <div class="stack" style="margin-bottom:14px;">
                                    <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="btn-x btn-amber-x btn-block">
                                        <i class="bi bi-file-earmark-pdf"></i> Open Initial Report
                                    </a>
                                </div>
                            @endif
                        @else
                            <div class="notice notice-info">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <span>
                                    {{ $teamOnIt->first()->full_name }} {{ $teamOnIt->count() > 1 ? 'and ' . ($teamOnIt->count() - 1) . ' more are' : 'is' }} responding. You'll see their report here when they send it.
                                    @if($notifiedHelp && $notifiedAt) Asked {{ $teamList($notifiedTeams) }} to help {{ $ago($notifiedAt) }}. @endif
                                </span>
                            </div>
                        @endif

                        <form action="{{ route('incident.assignment', $incident->id) }}" method="POST" id="assignmentForm">
                            @csrf
                            @method('PATCH')

                            {{-- The priority is chosen when the report is approved (Pending)
                                 and can't be changed after that. --}}
                            <div class="field">
                                <span class="field-label">Priority level</span>
                                @if($isSos)
                                    <span class="fixed-value"><i class="bi bi-exclamation-octagon-fill"></i> Critical — every SOS</span>
                                @else
                                    <span class="fixed-value fixed-{{ $priority ?: 'unset' }}">
                                        <i class="bi bi-lock-fill"></i> {{ $priority ? ucfirst($priority) : 'Not set' }}
                                    </span>
                                @endif
                            </div>

                            @if($needsTeam)
                                <button type="submit" class="btn-x btn-primary-x btn-block" id="assignmentSubmit">
                                    <i class="bi bi-send-check"></i> Send to All Responders
                                </button>
                                <div class="why-disabled" id="assignmentHint" hidden></div>
                                <div class="action-help">
                                    <i class="bi bi-info-circle"></i>
                                    Every response team gets a notification and any of them can accept it.
                                    {{ $incident->citizen_id ? 'The resident is told it was approved and sent.' : '' }}
                                </div>
                            @else
                                <div class="field">
                                    <span class="field-label">Notify specific responders</span>
                                    @include('partials.team-choices', ['checked' => $checkedTeams, 'previous' => null])
                                    <div class="field-help">
                                        @if($teamOnIt->isEmpty())
                                            Tick the teams to notify again.
                                            @if($toAll || $incident->assigned_teams === null) Every team can already see it and accept it. @else A team it wasn't sent to is added and can accept it. @endif
                                            Usually needed for {{ $isSos ? ($incident->sos_emergency_type ?: 'an SOS') : $incident->emergency_type }}: {{ $teamList($suggested) }}.
                                            @if($declinedBy->isNotEmpty()) Anyone on a ticked team who declined gets it back in their list. @endif
                                        @else
                                            Tick a team to ask it to help — it's notified and can join the responders on it.
                                        @endif
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="assignment_notes">Notes for the team <span class="opt">(optional)</span></label>
                                    <textarea name="assignment_notes" id="assignment_notes" rows="2" maxlength="500" class="f-input"
                                              placeholder="e.g. Proceed via the market road.">{{ old('assignment_notes', $incident->assignment_notes) }}</textarea>
                                </div>

                                <button type="submit" class="btn-x btn-primary-x btn-block" id="assignmentSubmit">
                                    <i class="bi bi-bell-fill"></i> Notify Selected
                                </button>
                                <div class="why-disabled" id="assignmentHint" hidden></div>
                                <div class="action-help">
                                    <i class="bi bi-info-circle"></i>
                                    @if($teamOnIt->isEmpty())
                                        The ticked teams get the alert again, marked "no one has accepted yet".
                                    @else
                                        The ticked teams get "MDRRMO needs your team" and can accept it as backup.
                                    @endif
                                </div>
                            @endif
                        </form>
                    @endif
                </section>

                <!-- RESPONSE ASSIGNMENT -->
                <section class="card-x o-assignment">
                    <div class="card-title">Response Assignment</div>
                    <dl class="kv">
                        <dt>Team</dt>
                        <dd>
                            @if($isManual)
                                @php $typedTeam = collect($report['team'] ?? [])->filter(fn ($m) => ! empty($m['name']) || ! empty($m['agency'])); @endphp
                                @forelse($typedTeam as $member)
                                    <div class="team-member">
                                        <span>{{ $member['name'] ?: '—' }}{{ ! empty($member['agency']) ? ' — ' . $member['agency'] : '' }}</span>
                                        <span class="role-tag {{ ($member['role'] ?? '') === 'Lead' ? '' : 'backup' }}">{{ $member['role'] ?? '' }}</span>
                                    </div>
                                @empty
                                    <span class="muted">Not written down</span>
                                @endforelse
                            @elseif($incident->needs_review)
                                <span class="muted">Chosen when you approve it</span>
                            @elseif($isDeclined)
                                <span class="muted">—</span>
                            @elseif($needsTeam)
                                <span class="warn">Not sent yet</span>
                            @elseif($toAll)
                                All responders <span class="muted">({{ $teamList($assigned) }})</span>
                            @elseif($assigned)
                                {{ $teamList($assigned) }}
                            @else
                                <span class="muted">All teams (approved before teams were assigned)</span>
                            @endif
                        </dd>

                        @unless($isManual)
                            <dt>Accepted by</dt>
                            <dd>
                                @forelse($teamOnIt as $member)
                                    <div class="team-member">
                                        <span>{{ $member->full_name }}{{ $member->agency ? ' · ' . $member->agency : '' }}</span>
                                        <span class="role-tag {{ $loop->first ? '' : 'backup' }}">{{ $loop->first ? 'Lead' : 'Backup' }}</span>
                                        @if($member->pivot->accepted_at)
                                            <span class="small-note">{{ \Illuminate\Support\Carbon::parse($member->pivot->accepted_at)->format('g:i A') }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </dd>

                            <dt>Notes</dt>
                            <dd>{!! $incident->assignment_notes ? e($incident->assignment_notes) : '<span class="muted">—</span>' !!}</dd>
                        @endunless

                        <dt>Status</dt>
                        <dd>
                            @if($incident->needs_review)
                                <span class="warn">Waiting for approval</span>
                            @else
                                <span class="chip {{ $stageClass }}">{{ $incident->status_label }}</span>
                            @endif
                        </dd>
                    </dl>
                </section>

                <!-- INTERNAL NOTE -->
                <section class="card-x o-note">
                    <div class="card-title">
                        Internal Note
                        <span class="title-note">admins only</span>
                    </div>
                    @if($incident->admin_notes)
                        <div class="note-box">{{ $incident->admin_notes }}</div>
                    @else
                        <div class="small-note">No note yet.</div>
                    @endif
                    <button type="button" class="btn-soft" style="margin-top:10px;" data-bs-toggle="modal" data-bs-target="#noteModal">
                        <i class="bi bi-pencil-square"></i> {{ $incident->admin_notes ? 'Edit note' : 'Add note' }}
                    </button>
                </section>
            </div>
        </div>
    </div>
</div>

{{-- Photo viewer --}}
@if($photos->count())
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">Photos ({{ $photos->count() }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="photoCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner">
                        @foreach($photos as $index => $photoPath)
                            <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                                <img src="{{ Storage::url($photoPath) }}"
                                     style="width:100%;max-height:520px;object-fit:contain;border-radius:10px;border:1px solid #e5e7eb;background:#f9fafb;"
                                     alt="Photo {{ $index + 1 }}">
                            </div>
                        @endforeach
                    </div>
                    @if($photos->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" style="filter:invert(1) grayscale(100);"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" style="filter:invert(1) grayscale(100);"></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Photo from the responders' report --}}
@if($incident->resolution_photo_path)
<div class="modal fade" id="resolutionPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1rem;">Photo from the scene</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <img src="{{ Storage::url($incident->resolution_photo_path) }}"
                     style="width:100%;max-height:520px;object-fit:contain;border-radius:10px;border:1px solid #e5e7eb;"
                     alt="Photo from the scene">
            </div>
        </div>
    </div>
</div>
@endif

@if($incident->needs_review)
{{-- Decline — opened from Administrative Actions. --}}
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form action="{{ route('incident.decline', $incident->id) }}" method="POST" id="declineForm">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Decline this {{ $isSos ? 'SOS alert' : 'report' }}?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">
                        The responders will NOT be notified.
                        {{ $incident->citizen_id ? 'The reporter gets a notification with this reason.' : 'It was sent by a guest, so there is no account to notify.' }}
                    </p>
                    @if(str_contains((string) $incident->location, 'Outside Rosales'))
                        <div class="decline-outside">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>The GPS pin is outside Rosales. If MDRRMO Rosales can't respond there, choose "Location is outside Rosales / MDRRMO service area".</span>
                        </div>
                    @endif
                    <label class="form-label-m" for="declineReason">Reason for declining</label>
                    <select name="decline_reason" id="declineReason" class="form-control-m" required>
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
                    <button type="submit" class="btn-decline-confirm">Decline</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($incident->isForReview())
    @include('partials.return-report-modal', ['incident' => $incident, 'modalId' => 'returnModal'])
@endif

{{-- Internal note --}}
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form method="POST" action="{{ route('incident.note', $incident->id) }}">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;">Internal note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size:.8rem;color:#6b7280;margin-bottom:8px;">Only admins see this. The responders and the reporter don't.</p>
                    <textarea name="admin_notes" rows="4" maxlength="2000" placeholder="Type your note here..."
                        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:10px 13px;font-size:.85rem;font-family:'Inter',sans-serif;resize:vertical;background:#fafafa;">{{ $incident->admin_notes }}</textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit"
                        style="background:#1a3c8f;color:#fff;border:none;border-radius:8px;padding:9px 22px;font-family:'Barlow',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;">
                        Save Note
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Map: the GPS pin, or the middle of the barangay (faded) when there's no pin.
    function initDetailMap() {
        const el = document.getElementById('detail-map');
        if (!el || !window.google) return;
        const position = { lat: {{ (float) $lat }}, lng: {{ (float) $lng }} };
        const map = new google.maps.Map(el, {
            center: position,
            zoom: {{ $hasPin ? 16 : 14 }},
            disableDefaultUI: true,
            zoomControl: true,
            gestureHandling: 'cooperative',
            clickableIcons: false,
        });
        @if($hasPin || $approxPoint)
        new google.maps.Marker({
            position,
            map,
            @if(! $hasPin) opacity: 0.6, title: 'Middle of the barangay (no GPS pin)', @endif
        });
        @endif
    }

    // Photos: "Loading…" until each one arrives; "Couldn't load · Retry" if it fails.
    document.querySelectorAll('.photo-tile').forEach(tile => {
        const img = tile.querySelector('img');
        const original = img.getAttribute('src');
        const loaded = () => { tile.classList.add('is-loaded'); tile.classList.remove('is-failed'); };
        const failed = () => { tile.classList.add('is-failed'); tile.classList.remove('is-loaded'); };
        img.addEventListener('load', loaded);
        img.addEventListener('error', failed);
        if (img.complete) { img.naturalWidth > 0 ? loaded() : failed(); }

        const retry = tile.querySelector('.photo-retry');
        const doRetry = (e) => {
            e.stopPropagation();
            tile.classList.remove('is-failed');
            img.src = original + (original.includes('?') ? '&' : '?') + 'retry=' + Date.now();
        };
        retry.addEventListener('click', doRetry);
        retry.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') doRetry(e); });

        tile.addEventListener('click', () => {
            if (!tile.classList.contains('is-loaded')) return;
            const modalEl = document.getElementById('photoModal');
            const carouselEl = document.getElementById('photoCarousel');
            if (!modalEl || !carouselEl) return;
            bootstrap.Carousel.getOrCreateInstance(carouselEl).to(parseInt(tile.dataset.index, 10) || 0);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    // Approve & Send: stays off until a priority is chosen (reports) and a
    // team is ticked. Notify Selected: stays off until a team is ticked.
    // (Send to All Responders has no teams to tick.)
    function wireAssignmentForm(formId, submitId, hintId, needsPriority, needsTeam) {
        const form = document.getElementById(formId);
        if (!form) return;
        const submit = document.getElementById(submitId);
        const hint = document.getElementById(hintId);
        const sync = () => {
            const missing = [];
            if (needsPriority && form.querySelector('input[name="priority"]') && !form.querySelector('input[name="priority"]:checked')) {
                missing.push('a priority');
            }
            if (needsTeam && form.querySelector('input[name="teams[]"]') && !form.querySelector('input[name="teams[]"]:checked')) {
                missing.push('at least one team');
            }
            submit.disabled = missing.length > 0;
            hint.hidden = missing.length === 0;
            hint.textContent = missing.length ? 'Choose ' + missing.join(' and ') + ' first.' : '';

            form.querySelectorAll('.team-choice[data-previous]').forEach(label => {
                const input = document.getElementById(label.getAttribute('for'));
                label.classList.toggle('is-new', input.checked && label.dataset.previous === '0');
            });
        };
        form.addEventListener('change', sync);
        form.addEventListener('submit', e => {
            if (submit.disabled) { e.preventDefault(); return; }
            submit.disabled = true;
        });
        sync();
    }
    wireAssignmentForm('approveForm', 'approveSubmit', 'approveHint', {{ $isSos ? 'false' : 'true' }}, true);
    wireAssignmentForm('assignmentForm', 'assignmentSubmit', 'assignmentHint', false, true);

    // Decline: a reason is required, then one last confirmation.
    (function () {
        const form = document.getElementById('declineForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const reason = form.elements['decline_reason'];
            if (!reason.value) {
                reason.focus();
                reason.style.borderColor = '#dc2626';
                return;
            }
            const modalEl = form.closest('.modal');
            const confirmIt = () => Swal.fire({
                icon: 'warning',
                title: 'Decline this {{ $isSos ? 'SOS alert' : 'report' }}?',
                html: '<div style="font-size:.9em;color:#4b5563;">The responders will NOT be notified.</div>',
                showCancelButton: true,
                confirmButtonText: 'Yes, decline',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
            }).then(r => { if (r.isConfirmed) form.submit(); });
            if (modalEl && modalEl.classList.contains('show')) {
                modalEl.addEventListener('hidden.bs.modal', confirmIt, { once: true });
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            } else {
                confirmIt();
            }
        });
    })();

    // Delete (declined only) — asks first, since it can't be undone.
    (function () {
        const form = document.getElementById('deleteForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const what = document.createElement('div');
            what.textContent = form.dataset.what || '';
            Swal.fire({
                icon: 'warning',
                title: 'Delete it for good?',
                html: `<div style="font-size:.9em;color:#4b5563;">${what.innerHTML}<br>It's removed with its photos. This can't be undone.</div>`,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                focusCancel: true,
            }).then(r => { if (r.isConfirmed) form.submit(); });
        });
    })();
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&callback=initDetailMap&v=weekly" async defer></script>
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
