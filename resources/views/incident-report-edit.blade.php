{{--
    For Review → "Review & Edit" (item 49): the responding team's Initial
    report as a form, so MDRRMO can correct it before approving it as
    resolved (Admin\IncidentController::editReport() / updateReport()).

      read-only  what the team can't get wrong here: the report type, when
                 it was sent, the team, the photo, the incident's type and
                 date/time
      editable   location, narrative, casualties, effects, actions taken,
                 report released by

    "Save Changes" keeps it on For Review; "Save & Approve as Resolved"
    also resolves it (asks first). The corrections go into the report and
    its PDF, and the team sees them in the app (team_report.dart).
--}}
@php
    $isSos   = $incident->isSos();
    $onIt    = $incident->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->values();
    $lead    = $onIt->first();
    $sentAt  = $incident->report_submitted_at ?? $incident->updated_at;
    $ago     = fn ($at) => $at->gt(now()->subMinute()) ? 'just now' : $at->diffForHumans();
    $isFinal = $kind === 'Final report';

    // After a validation error the form shows what the admin typed —
    // including casualty rows they added or removed.
    $posted     = old('edit_form') !== null;
    $casualties = $posted ? (array) old('casualties', []) : (array) ($report['casualties'] ?? []);
    $casualtyGroups = [
        'dead'    => 'A. Dead / Drowned',
        'injured' => 'B. Injured',
        'missing' => 'C. Missing',
    ];
    $value = fn (string $key) => $posted ? old($key, '') : (string) ($report[$key] ?? '');
    $fieldError = fn (string $key) => $errors->has($key) ? 'is-invalid' : '';

    $when = trim(implode(' ', array_filter([
        $report['incident_date'] ?? null,
        ! empty($report['incident_day']) ? '(' . $report['incident_day'] . ')' : null,
        $report['incident_time'] ?? null,
    ])));

    // Corrected before (this or another admin).
    $editedAt = null;
    if (! empty($report['edited_at'])) {
        try {
            $editedAt = \Carbon\Carbon::parse($report['edited_at'])->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            $editedAt = null;
        }
    }
    $editedParts = array_values(array_filter((array) ($report['edited_parts'] ?? []), 'is_string'));
    $photoUrl = $incident->resolution_photo_path ? Storage::url($incident->resolution_photo_path) : null;
    $notified = $incident->citizen_id ? 'The responding team and the resident who reported it are notified.' : 'The responding team is notified.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Review {{ $kind }}</title>
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
        .content { padding: 28px 36px 0; flex: 1; min-width: 0; max-width: 1180px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 6px; flex-wrap: wrap; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.5rem; color: #111827; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .page-title i { color: #6d28d9; }
        .page-sub { font-size: .86rem; color: #4b5563; margin-bottom: 18px; max-width: 820px; line-height: 1.55; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #0d2e7a; }
        .type-chip { font-family: 'Inter', sans-serif; font-size: .7rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; background: #fef3c7; color: #92400e; }
        .type-chip.final { background: #ede9fe; color: #5b21b6; }

        .notice { display: flex; gap: 9px; align-items: flex-start; border-radius: 10px; padding: 10px 14px; font-size: .82rem; line-height: 1.5; margin-bottom: 16px; }
        .notice i { margin-top: 1px; }
        .notice-edited { background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; }

        /* ── FORM ── */
        .form-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; margin-bottom: 16px; }
        .card-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 22px; min-width: 0; }
        .card-box.span-2 { grid-column: 1 / -1; }
        .card-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.02rem; color: #111827; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: #1a3c8f; }
        .card-hint { font-size: .78rem; color: #9ca3af; margin-bottom: 14px; }

        .kv { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 9px 14px; font-size: .85rem; }
        .kv dt { color: #6b7280; font-weight: 500; }
        .kv dd { margin: 0; color: #111827; font-weight: 500; overflow-wrap: anywhere; }
        .kv .muted { color: #9ca3af; }
        .small-note { font-size: .76rem; color: #6b7280; }
        .member { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .member + .member { margin-top: 4px; }
        .role-tag { font-size: .62rem; font-weight: 800; padding: 1px 7px; border-radius: 8px; background: #e0e7ff; color: #3730a3; }
        .role-tag.backup { background: #f3f4f6; color: #4b5563; }
        .scene-photo { display: inline-block; width: 120px; height: 90px; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .scene-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pdf-link { display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 700; color: #b91c1c; text-decoration: none; border: 1.5px solid #fecaca; border-radius: 8px; padding: 6px 12px; background: #fff; }
        .pdf-link:hover { background: #fef2f2; color: #991b1b; }

        .field { margin-bottom: 14px; }
        .field:last-child { margin-bottom: 0; }
        .field-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
        .f-label { display: block; font-size: .8rem; font-weight: 700; color: #374151; margin-bottom: 5px; }
        .f-label .req { color: #dc2626; }
        .f-label .opt { font-weight: 500; color: #9ca3af; }
        .f-input {
            width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
            padding: 9px 12px; font-size: .85rem; font-family: 'Inter', sans-serif;
            color: #111827; background: #fafafa; transition: border-color .2s, background .2s;
        }
        .f-input:focus { outline: none; border-color: #1a3c8f; background: #fff; }
        textarea.f-input { resize: vertical; min-height: 92px; line-height: 1.5; }
        .f-input.is-invalid { border-color: #dc2626; background: #fef2f2; }
        .f-input.is-changed { border-color: #a78bfa; background: #faf5ff; }
        .f-error { font-size: .76rem; font-weight: 600; color: #b91c1c; margin-top: 4px; }
        .f-help { font-size: .74rem; color: #9ca3af; margin-top: 4px; }
        .f-count { float: right; font-weight: 500; color: #9ca3af; }

        /* Casualty rows */
        .group-title { font-size: .8rem; font-weight: 800; color: #111827; margin: 16px 0 6px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .group-title:first-child { margin-top: 4px; }
        .rows-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .rows-table th { font-size: .7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .3px; padding: 0 4px; text-align: left; }
        .rows-table td { padding: 0 4px; vertical-align: top; }
        .rows-table .f-input { padding: 7px 9px; font-size: .8rem; }
        .rows-empty { font-size: .78rem; color: #9ca3af; font-style: italic; padding: 2px 4px 6px; }
        .btn-row-add {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fff; color: #1a3c8f; border: 1.5px dashed #a5b4fc; border-radius: 8px;
            padding: 5px 12px; font-size: .76rem; font-weight: 700; cursor: pointer;
        }
        .btn-row-add:hover { background: #eef2ff; }
        .btn-row-remove {
            width: 32px; height: 34px; border-radius: 8px; border: 1.5px solid #fecaca;
            background: #fff; color: #b91c1c; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-row-remove:hover { background: #fef2f2; }

        /* The buttons stay in view at the bottom while scrolling. */
        .form-actions {
            position: sticky; bottom: 0; z-index: 5;
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            margin: 4px -36px 0; padding: 14px 36px 18px;
            background: rgba(244, 246, 251, .96); border-top: 1px solid #e5e7eb;
            backdrop-filter: blur(4px);
        }
        .btn-x {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border-radius: 10px; padding: 11px 20px; font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .95rem;
            cursor: pointer; border: 1.5px solid transparent; transition: background .2s, box-shadow .2s, color .2s;
        }
        .btn-x:disabled { opacity: .6; cursor: wait; }
        .btn-approve { background: #10b981; color: #fff; }
        .btn-approve:hover:not(:disabled) { background: #059669; box-shadow: 0 4px 14px rgba(16,185,129,.3); }
        .btn-save { background: #fff; color: #1a3c8f; border-color: #1a3c8f; }
        .btn-save:hover:not(:disabled) { background: #eef2ff; }
        .btn-cancel { font-size: .85rem; font-weight: 700; color: #6b7280; text-decoration: none; padding: 10px 14px; border-radius: 10px; }
        .btn-cancel:hover { background: #e9ecf3; color: #374151; }
        .save-note { font-size: .78rem; color: #6b7280; line-height: 1.45; flex: 1; min-width: 220px; }
        .changed-count { font-weight: 700; color: #6d28d9; }
        .btn-x .lbl-short { display: none; }
        /* The notices in the corner (partials/report-alert) sit above the
           action bar instead of covering its buttons. */
        body .report-alerts { bottom: 96px; }

        /* ── RESPONSIVE ── */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 150; }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 1000px) {
            .form-grid { grid-template-columns: minmax(0, 1fr); }
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
            .main-wrap { margin-left: 0 !important; padding: 20px 16px 0 !important; padding-bottom: 64px !important; }
            .content { padding: 12px 0 0; }
            .form-actions { margin: 4px -16px 0; padding: 12px 16px 14px; bottom: calc(58px + env(safe-area-inset-bottom, 0px)); }
            body .report-alerts { bottom: calc(160px + env(safe-area-inset-bottom, 0px)); }
            .rows-wrap { overflow-x: auto; }
            .rows-table { min-width: 680px; }
        }
        @media (max-width: 560px) {
            .main-wrap { padding: 16px 12px 0 !important; padding-bottom: 64px !important; }
            /* One row of short buttons, so the bar stays small. */
            .form-actions { margin: 4px -12px 0; padding: 10px 12px 10px; gap: 8px; }
            .field-row { grid-template-columns: minmax(0, 1fr); }
            .kv { grid-template-columns: minmax(0, 1fr); gap: 2px 0; }
            .kv dd { margin-bottom: 8px; }
            .btn-x { flex: 1; padding: 10px 10px; font-size: .9rem; white-space: nowrap; }
            .btn-x .lbl-long { display: none; }
            .btn-x .lbl-short { display: inline; }
            .btn-cancel { padding: 10px 6px; }
            .save-note { order: 3; flex-basis: 100%; min-width: 0; }
            .save-note .note-rest { display: none; }
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

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        <div class="page-header">
            <div class="page-title">
                <i class="bi bi-clipboard2-check"></i> Review {{ $kind }}
                <span class="type-chip {{ $isFinal ? 'final' : '' }}">{{ $isFinal ? 'Final' : 'Initial' }}</span>
            </div>
            <a href="{{ $details }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to the {{ $isSos ? 'SOS alert' : 'incident' }}</a>
        </div>
        <div class="page-sub">
            <strong>{{ $incident->display_type }}</strong> — sent by
            <strong>{{ $lead ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') : 'the responding team' }}</strong>
            {{ $ago($sentAt) }}{{ $incident->report_returned_at ? ', again after you returned it' : '' }}.
            Read it and correct anything that's wrong, then approve it as resolved. Your corrections go into the report and
            its PDF, and the team sees them in the app.
        </div>

        @if($editedAt)
            <div class="notice notice-edited">
                <i class="bi bi-pencil-square"></i>
                <span>
                    Corrected by <strong>{{ $report['edited_by'] ?? 'MDRRMO' }}</strong> {{ $editedAt->format('M d, g:i A') }}{{ $editedParts ? ' — ' . implode(', ', $editedParts) : '' }}.
                </span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Please check the report:</strong>
                <ul style="margin:6px 0 0 18px;">
                    @foreach(array_unique($errors->all()) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('incident.report.update', $incident) }}" id="reviewForm" novalidate
              data-kind="{{ $kind }}" data-type="{{ $isSos ? 'SOS' : $incident->emergency_type }}" data-notified="{{ $notified }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_form" value="1">
            <input type="hidden" name="approve" id="approveInput" value="0">

            <div class="form-grid">
                <!-- FROM THE TEAM (read-only) -->
                <div class="card-box">
                    <div class="card-title"><i class="bi bi-people-fill"></i> From the team</div>
                    <div class="card-hint">As the team sent it — these can't be changed here.</div>
                    <dl class="kv">
                        <dt>Report</dt>
                        <dd>
                            {{ $kind }}{{ ($report['revision'] ?? 1) > 1 ? ' · sent ' . $report['revision'] . ' times' : '' }}
                        </dd>

                        <dt>Sent</dt>
                        <dd>{{ $sentAt->format('M d, Y g:i A') }} <span class="small-note">({{ $ago($sentAt) }})</span></dd>

                        <dt>Team</dt>
                        <dd>
                            @forelse($team as $member)
                                <div class="member">
                                    <span>{{ $member['name'] ?? '—' }}{{ ! empty($member['agency']) ? ' — ' . $member['agency'] : '' }}</span>
                                    <span class="role-tag {{ ($member['role'] ?? '') === 'Lead' ? '' : 'backup' }}">{{ $member['role'] ?? '' }}</span>
                                </div>
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </dd>

                        <dt>Photo</dt>
                        <dd>
                            @if($photoUrl)
                                <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="scene-photo" title="Open the photo">
                                    <img src="{{ $photoUrl }}" alt="Photo from the scene">
                                </a>
                            @else
                                <span class="muted">No photo</span>
                            @endif
                        </dd>

                        <dt>PDF</dt>
                        <dd>
                            <a href="{{ route('incident.report-pdf', $incident) }}" target="_blank" class="pdf-link">
                                <i class="bi bi-file-earmark-pdf"></i> Open the PDF
                            </a>
                            <div class="small-note" style="margin-top:4px;">As it is now — saved changes are added to it.</div>
                        </dd>
                    </dl>
                </div>

                <!-- INCIDENT -->
                <div class="card-box">
                    <div class="card-title"><i class="bi bi-exclamation-diamond-fill"></i> Incident</div>
                    <div class="card-hint">Filled in from the {{ $isSos ? 'SOS alert' : 'report' }} itself.</div>
                    <dl class="kv" style="margin-bottom:16px;">
                        <dt>Type</dt>
                        <dd>{{ $report['incident_type'] ?? $incident->display_type }}</dd>

                        <dt>Date / time</dt>
                        <dd>{{ $when !== '' ? $when : $incident->created_at->format('F j, Y g:i A') }}</dd>
                    </dl>

                    <div class="field">
                        <label class="f-label" for="location">Location <span class="req">*</span></label>
                        <input type="text" name="location" id="location" maxlength="255" required
                               class="f-input {{ $fieldError('location') }}" value="{{ $value('location') }}"
                               data-part="Location" data-original="{{ $report['location'] ?? '' }}">
                        @error('location') <div class="f-error">{{ $message }}</div> @enderror
                        <div class="f-help">As it's printed on the report.</div>
                    </div>
                </div>

                <!-- MDRRMC INCIDENT REPORT -->
                <div class="card-box span-2">
                    <div class="card-title"><i class="bi bi-file-earmark-text-fill"></i> MDRRMC Incident Report</div>
                    <div class="card-hint">What the team wrote. Correct anything that's wrong or missing.</div>

                    <div class="field">
                        <label class="f-label" for="narrative">
                            Details / Narrative / Cause <span class="req">*</span>
                            <span class="f-count" data-count-for="narrative"></span>
                        </label>
                        <textarea name="narrative" id="narrative" maxlength="2000" rows="5" required
                                  class="f-input {{ $fieldError('narrative') }}"
                                  data-part="Narrative" data-original="{{ $report['narrative'] ?? '' }}">{{ $value('narrative') }}</textarea>
                        @error('narrative') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <span class="f-label" id="casualtiesLabel">Casualties <span class="opt">(leave empty if none)</span></span>
                        <div class="rows-wrap" id="casualtyLists" data-original='@json($report['casualties'] ?? [])'>
                            @foreach($casualtyGroups as $key => $label)
                                @php $rows = array_values(array_filter((array) ($casualties[$key] ?? []), 'is_array')); @endphp
                                <div class="group-title">
                                    <span>{{ $label }}</span>
                                    <button type="button" class="btn-row-add" data-add-casualty="{{ $key }}"><i class="bi bi-plus-lg"></i> Add person</button>
                                </div>
                                <table class="rows-table" data-casualty-table="{{ $key }}">
                                    <thead>
                                        <tr>
                                            <th style="width:22%;">Name</th>
                                            <th style="width:7%;">Age</th>
                                            <th style="width:9%;">Sex</th>
                                            <th style="width:21%;">Address</th>
                                            <th style="width:16%;">Cause</th>
                                            <th>Remarks</th>
                                            <th style="width:40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $i => $row)
                                            <tr>
                                                <td><input type="text" class="f-input" name="casualties[{{ $key }}][{{ $i }}][name]" maxlength="120" value="{{ $row['name'] ?? '' }}"></td>
                                                <td><input type="text" class="f-input" name="casualties[{{ $key }}][{{ $i }}][age]" maxlength="10" value="{{ $row['age'] ?? '' }}"></td>
                                                <td>
                                                    <select class="f-input" name="casualties[{{ $key }}][{{ $i }}][sex]">
                                                        <option value="" {{ ($row['sex'] ?? '') === '' ? 'selected' : '' }}>—</option>
                                                        <option value="M" {{ strtoupper($row['sex'] ?? '') === 'M' ? 'selected' : '' }}>M</option>
                                                        <option value="F" {{ strtoupper($row['sex'] ?? '') === 'F' ? 'selected' : '' }}>F</option>
                                                    </select>
                                                </td>
                                                <td><input type="text" class="f-input" name="casualties[{{ $key }}][{{ $i }}][address]" maxlength="160" value="{{ $row['address'] ?? '' }}"></td>
                                                <td><input type="text" class="f-input" name="casualties[{{ $key }}][{{ $i }}][cause]" maxlength="120" value="{{ $row['cause'] ?? '' }}"></td>
                                                <td><input type="text" class="f-input" name="casualties[{{ $key }}][{{ $i }}][remarks]" maxlength="200" value="{{ $row['remarks'] ?? '' }}"></td>
                                                <td><button type="button" class="btn-row-remove" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="rows-empty" data-empty-for="{{ $key }}" style="{{ $rows ? 'display:none;' : '' }}">None</div>
                            @endforeach
                        </div>
                        @error('casualties.*') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="effects">
                                Effects <span class="opt">(optional)</span>
                                <span class="f-count" data-count-for="effects"></span>
                            </label>
                            <textarea name="effects" id="effects" maxlength="1000" rows="3" class="f-input {{ $fieldError('effects') }}"
                                      data-part="Effects" data-original="{{ $report['effects'] ?? '' }}"
                                      placeholder="Damage to houses, roads, crops, power…">{{ $value('effects') }}</textarea>
                            @error('effects') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="actions_taken">
                                Action/s Taken <span class="opt">(optional)</span>
                                <span class="f-count" data-count-for="actions_taken"></span>
                            </label>
                            <textarea name="actions_taken" id="actions_taken" maxlength="1500" rows="3" class="f-input {{ $fieldError('actions_taken') }}"
                                      data-part="Actions taken" data-original="{{ $report['actions_taken'] ?? '' }}"
                                      placeholder="What the team did on scene.">{{ $value('actions_taken') }}</textarea>
                            @error('actions_taken') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="f-label" for="released_by">Report released by <span class="opt">(Reporter / Designation / Office)</span></label>
                        <input type="text" name="released_by" id="released_by" maxlength="255" class="f-input {{ $fieldError('released_by') }}"
                               data-part="Released by" data-original="{{ $report['released_by'] ?? '' }}" value="{{ $value('released_by') }}">
                        @error('released_by') <div class="f-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-x btn-approve" data-approve="1"><i class="bi bi-check2-circle"></i> <span class="lbl-long">Save &amp; Approve as Resolved</span><span class="lbl-short">Save &amp; Approve</span></button>
                <button type="submit" class="btn-x btn-save" data-approve="0"><i class="bi bi-floppy"></i> <span class="lbl-long">Save Changes</span><span class="lbl-short">Save</span></button>
                <a href="{{ $details }}" class="btn-cancel">Cancel</a>
                <span class="save-note"><span class="changed-count" id="changedCount" hidden></span> <span class="note-rest">Approving resolves the incident and adds your name to the PDF as the reviewer. {{ $notified }}</span></span>
            </div>
        </form>
    </div>
</div>

{{-- Row template for "Add person". __g__ / __i__ become the list and the row number. --}}
<template id="casualtyRowTpl">
    <tr>
        <td><input type="text" class="f-input" name="casualties[__g__][__i__][name]" maxlength="120"></td>
        <td><input type="text" class="f-input" name="casualties[__g__][__i__][age]" maxlength="10"></td>
        <td>
            <select class="f-input" name="casualties[__g__][__i__][sex]">
                <option value="">—</option>
                <option value="M">M</option>
                <option value="F">F</option>
            </select>
        </td>
        <td><input type="text" class="f-input" name="casualties[__g__][__i__][address]" maxlength="160"></td>
        <td><input type="text" class="f-input" name="casualties[__g__][__i__][cause]" maxlength="120"></td>
        <td><input type="text" class="f-input" name="casualties[__g__][__i__][remarks]" maxlength="200"></td>
        <td><button type="button" class="btn-row-remove" title="Remove"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('reviewForm');
    const approveInput = document.getElementById('approveInput');
    const changedCount = document.getElementById('changedCount');
    if (!form) return;

    // Character counters on the long fields.
    document.querySelectorAll('[data-count-for]').forEach(counter => {
        const field = document.getElementById(counter.dataset.countFor);
        const update = () => { counter.textContent = field.value.length + ' / ' + field.maxLength; };
        field.addEventListener('input', update);
        update();
    });

    // Casualty rows: a counter keeps every new row's index unique.
    let nextIndex = 1000;
    const syncEmpty = (group) => {
        const table = document.querySelector('[data-casualty-table="' + group + '"]');
        const empty = document.querySelector('[data-empty-for="' + group + '"]');
        const hasRows = table.tBodies[0].rows.length > 0;
        table.style.display = hasRows ? '' : 'none';
        empty.style.display = hasRows ? 'none' : '';
    };
    document.querySelectorAll('[data-add-casualty]').forEach(btn => {
        const group = btn.dataset.addCasualty;
        btn.addEventListener('click', () => {
            const table = document.querySelector('[data-casualty-table="' + group + '"]');
            const html = document.getElementById('casualtyRowTpl').innerHTML
                .replaceAll('__g__', group)
                .replaceAll('__i__', String(nextIndex++));
            table.tBodies[0].insertAdjacentHTML('beforeend', html);
            table.tBodies[0].lastElementChild.querySelector('input')?.focus();
            syncEmpty(group);
            markDirty();
        });
        syncEmpty(group);
    });
    form.addEventListener('click', e => {
        const remove = e.target.closest('.btn-row-remove');
        if (!remove) return;
        const row = remove.closest('tr');
        const table = row.closest('table');
        row.remove();
        if (table.dataset.casualtyTable) syncEmpty(table.dataset.casualtyTable);
        markDirty();
    });

    // What was changed: the corrected fields are tinted and named next to
    // the buttons, and leaving the page with unsaved changes asks first.
    const groups = ['dead', 'injured', 'missing'];
    const lists = document.getElementById('casualtyLists');
    const casualtiesLabel = document.getElementById('casualtiesLabel');
    const rowText = values => values.map(v => String(v ?? '').trim()).join('\u0001');
    const filled = text => text.replace(/\u0001/g, '') !== '';
    function casualtiesNow() {
        return groups.map(g => Array.from(document.querySelector('[data-casualty-table="' + g + '"]').tBodies[0].rows)
            .map(row => rowText(Array.from(row.querySelectorAll('input, select')).map(f => f.value)))
            .filter(filled).join('\u0002')).join('\u0003');
    }
    let casualtiesBefore = '';
    try {
        const original = JSON.parse(lists.dataset.original || '{}') || {};
        casualtiesBefore = groups.map(g => (Array.isArray(original[g]) ? original[g] : [])
            .map(r => rowText([r.name, r.age, String(r.sex || '').toUpperCase(), r.address, r.cause, r.remarks]))
            .filter(filled).join('\u0002')).join('\u0003');
    } catch (e) { casualtiesBefore = casualtiesNow(); }

    let dirty = {{ $posted ? 'true' : 'false' }};
    let sending = false;
    function markDirty() { dirty = true; }
    function tint() {
        const parts = [];
        form.querySelectorAll('[data-original]').forEach(field => {
            if (field === lists) return;
            const changed = field.value.trim() !== field.dataset.original.trim();
            field.classList.toggle('is-changed', changed);
            if (changed) parts.push(field.dataset.part);
        });
        const casualtiesChanged = casualtiesNow() !== casualtiesBefore;
        casualtiesLabel.style.color = casualtiesChanged ? '#6d28d9' : '';
        // In the report's order: after Location and Narrative.
        if (casualtiesChanged) parts.splice(parts.filter(p => p === 'Location' || p === 'Narrative').length, 0, 'Casualties');
        changedCount.hidden = parts.length === 0;
        changedCount.textContent = 'Changed: ' + parts.join(', ') + '.';
    }
    form.addEventListener('input', () => { markDirty(); tint(); });
    form.addEventListener('change', () => { markDirty(); tint(); });
    form.addEventListener('click', e => { if (e.target.closest('.btn-row-remove, .btn-row-add')) setTimeout(tint, 0); });
    tint();
    window.addEventListener('beforeunload', e => {
        if (dirty && !sending) { e.preventDefault(); e.returnValue = ''; }
    });

    // Enter in a one-line field doesn't send the form (it would press
    // "Save & Approve", the first button).
    form.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
    });

    // Which button was pressed.
    form.querySelectorAll('button[data-approve]').forEach(btn => {
        btn.addEventListener('click', () => { approveInput.value = btn.dataset.approve; });
    });

    function send() {
        sending = true;
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; });
        const pressed = form.querySelector('button[data-approve="' + approveInput.value + '"]');
        if (pressed) pressed.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + (approveInput.value === '1' ? 'Approving…' : 'Saving…');
        form.submit();
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        if (approveInput.value !== '1') { send(); return; }

        // Approving resolves the incident: one last check.
        const type = document.createElement('span');
        type.textContent = form.dataset.type || 'this';
        const corrected = changedCount.hidden ? '' : ', with your corrections in the report,';
        const confirmText = '<div style="font-size:.9em;color:#4b5563;line-height:1.5;">The <strong>' + type.innerHTML
            + '</strong> incident moves to Resolved' + corrected + ' and your name goes on the PDF as the reviewer. '
            + form.dataset.notified + '</div>';
        if (window.Swal) {
            Swal.fire({
                icon: 'question',
                title: 'Approve as resolved?',
                html: confirmText,
                showCancelButton: true,
                confirmButtonText: 'Approve as Resolved',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
            }).then(r => { if (r.isConfirmed) send(); else approveInput.value = '0'; });
        } else if (window.confirm('Approve this report and mark the incident resolved?')) {
            send();
        }
    });
})();
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
