<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESQPULSE – Add Resolved Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fb; display: flex; min-height: 100vh; }

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
        .content { padding: 32px 36px; flex: 1; min-width: 0; max-width: 1180px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 6px; flex-wrap: wrap; }
        .page-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.5rem; color: #111827; }
        .page-sub { font-size: .85rem; color: #6b7280; margin-bottom: 20px; max-width: 760px; line-height: 1.55; }
        .back-link { font-size: .83rem; font-weight: 600; color: #1a3c8f; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #0d2e7a; }

        /* ── FORM ── */
        .form-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; margin-bottom: 16px; }
        .card-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 22px; min-width: 0; }
        .card-box.span-2 { grid-column: 1 / -1; }
        .card-title { font-family: 'Barlow', sans-serif; font-weight: 800; font-size: 1.02rem; color: #111827; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: #1a3c8f; }
        .card-hint { font-size: .78rem; color: #9ca3af; margin-bottom: 16px; }

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
        .f-error { font-size: .76rem; font-weight: 600; color: #b91c1c; margin-top: 4px; }
        .f-help { font-size: .74rem; color: #9ca3af; margin-top: 4px; }
        .f-count { float: right; font-weight: 500; color: #9ca3af; }

        /* Priority chips (same look as the Approve dialog). */
        .prio-choices { display: flex; gap: 8px; }
        .prio-input { position: absolute; opacity: 0; pointer-events: none; }
        .prio-choice {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
            margin: 0; padding: 9px 6px; border-radius: 10px; border: 2px solid currentColor;
            background: #fff; cursor: pointer; user-select: none;
            font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .92rem;
            transition: background .15s, color .15s;
        }
        .prio-choice i { display: none; }
        .prio-choice.prio-critical { color: #dc2626; }
        .prio-choice.prio-high     { color: #f97316; }
        .prio-choice.prio-low      { color: #3b82f6; }
        .prio-input:checked + .prio-choice { color: #fff; }
        .prio-input:checked + .prio-choice i { display: inline; }
        .prio-input:checked + .prio-choice.prio-critical { background: #dc2626; border-color: #dc2626; }
        .prio-input:checked + .prio-choice.prio-high     { background: #f97316; border-color: #f97316; }
        .prio-input:checked + .prio-choice.prio-low      { background: #3b82f6; border-color: #3b82f6; }
        .prio-input:focus-visible + .prio-choice { outline: 3px solid #1a3c8f; outline-offset: 2px; }

        /* Casualties / team rows */
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

        .photo-drop { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .photo-preview { width: 96px; height: 72px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; display: none; }

        .form-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            background: #10b981; color: #fff; border: none; border-radius: 10px;
            padding: 12px 22px; font-family: 'Barlow', sans-serif; font-weight: 800; font-size: .95rem;
            cursor: pointer; transition: background .2s, box-shadow .2s;
        }
        .btn-save:hover { background: #059669; box-shadow: 0 4px 14px rgba(16,185,129,.3); }
        .btn-save:disabled { background: #a7f3d0; cursor: wait; box-shadow: none; }
        .btn-cancel { font-size: .85rem; font-weight: 700; color: #6b7280; text-decoration: none; padding: 10px 14px; border-radius: 10px; }
        .btn-cancel:hover { background: #eef0f5; color: #374151; }
        .save-note { font-size: .78rem; color: #6b7280; }

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
            .main-wrap { margin-left: 0 !important; padding: 20px 16px 32px !important; padding-bottom: 88px !important; }
            .content { padding: 32px 0; }
            .rows-wrap { overflow-x: auto; }
            .rows-table { min-width: 680px; }
        }
        @media (max-width: 560px) {
            .main-wrap { padding: 16px 12px 28px !important; padding-bottom: 88px !important; }
            .field-row { grid-template-columns: minmax(0, 1fr); }
            .prio-choices { flex-wrap: wrap; }
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
    <a href="{{ route('incident') }}" class="active"><i class="bi bi-clipboard2-pulse"></i><span>Incidents</span>
        @if(($pendingIncidentsCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingIncidentsCount }}</span>
        @endif</a>
    <a href="{{ route('sos-alerts') }}"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span>
        @if(($pendingSosCount ?? 0) > 0)
            <span class="tab-badge">{{ $pendingSosCount }}</span>
        @endif</a>
    <a href="{{ route('mapview') }}"><i class="bi bi-geo-alt-fill"></i><span>Map</span></a>
    <a href="javascript:void(0)" id="mobileMenuBtn"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span></a>
</nav>
@include('partials.sidebar')

@php
    // Saved values come back after a validation error — including the
    // casualty and team rows, which are added with the "+" buttons.
    $casualtyGroups = [
        'dead'    => 'A. Dead / Drowned',
        'injured' => 'B. Injured',
        'missing' => 'C. Missing',
    ];
    $oldCasualties = old('casualties', []);
    $oldTeam = old('team', [['name' => '', 'agency' => '']]);
    $now = now()->format('Y-m-d\TH:i');
    $releasedDefault = auth('web')->user()?->name ? strtoupper(auth('web')->user()->name) . ', MDRRMO Rosales' : 'MDRRMO Rosales';
    $fieldError = fn (string $key) => $errors->has($key) ? 'is-invalid' : '';
@endphp

<!-- ════ MAIN ════ -->
<div class="main-wrap">
    <div class="content">

        <div class="page-header">
            <div class="page-title"><i class="bi bi-journal-plus" style="color:#1a3c8f;"></i> Add Resolved Report</div>
            <a href="{{ route('incident', ['tab' => 'resolved']) }}" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Incidents
            </a>
        </div>
        <div class="page-sub">
            For an incident MDRRMO handled <strong>outside the app</strong> — a walk-in, a phone call, a radio report.
            It's saved straight to the <strong>Resolved</strong> tab with the same MDRRMC Incident Report (PDF) the responders file.
            No one is notified.
        </div>

        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;margin-bottom:16px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Please check the form:</strong>
                <ul style="margin:6px 0 0 18px;">
                    @foreach(array_unique($errors->all()) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('incident.manual.store') }}" enctype="multipart/form-data" id="manualForm" novalidate>
            @csrf

            <div class="form-grid">
                <!-- INCIDENT -->
                <div class="card-box">
                    <div class="card-title"><i class="bi bi-exclamation-diamond-fill"></i> Incident</div>
                    <div class="card-hint">What happened, how serious, and when.</div>

                    <div class="field">
                        <label class="f-label" for="emergency_type">Type of incident <span class="req">*</span></label>
                        <select name="emergency_type" id="emergency_type" class="f-input {{ $fieldError('emergency_type') }}" required>
                            <option value="" disabled {{ old('emergency_type') ? '' : 'selected' }}>Select the type...</option>
                            @foreach(\App\Models\Incident::TYPES as $type)
                                <option value="{{ $type }}" {{ old('emergency_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('emergency_type') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field" id="otherTypeField" style="{{ old('emergency_type') === 'Other' ? '' : 'display:none;' }}">
                        <label class="f-label" for="other_type">What kind of incident? <span class="req">*</span></label>
                        <input type="text" name="other_type" id="other_type" maxlength="60" class="f-input {{ $fieldError('other_type') }}"
                               value="{{ old('other_type') }}" placeholder="e.g. Drowning, Fallen tree, Gas leak">
                        @error('other_type') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <span class="f-label">Priority <span class="req">*</span></span>
                        <div class="prio-choices" role="radiogroup" aria-label="Priority">
                            @foreach(['critical' => 'Critical', 'high' => 'High', 'low' => 'Low'] as $value => $label)
                                <input class="prio-input" type="radio" name="priority" id="prio-{{ $value }}" value="{{ $value }}"
                                       {{ old('priority') === $value ? 'checked' : '' }} required>
                                <label class="prio-choice prio-{{ $value }}" for="prio-{{ $value }}"><i class="bi bi-check-lg"></i>{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('priority') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="occurred_at">When it happened <span class="req">*</span></label>
                            <input type="datetime-local" name="occurred_at" id="occurred_at" max="{{ $now }}"
                                   class="f-input {{ $fieldError('occurred_at') }}" value="{{ old('occurred_at') }}" required>
                            @error('occurred_at') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="resolved_at">When it was resolved <span class="req">*</span></label>
                            <input type="datetime-local" name="resolved_at" id="resolved_at" max="{{ $now }}"
                                   class="f-input {{ $fieldError('resolved_at') }}" value="{{ old('resolved_at', $now) }}" required>
                            @error('resolved_at') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <!-- LOCATION + WHO REPORTED IT -->
                <div class="card-box">
                    <div class="card-title"><i class="bi bi-geo-alt-fill"></i> Location</div>
                    <div class="card-hint">Where in Rosales it happened.</div>

                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="barangay">Barangay <span class="req">*</span></label>
                            <select name="barangay" id="barangay" class="f-input {{ $fieldError('barangay') }}" required>
                                <option value="" disabled {{ old('barangay') ? '' : 'selected' }}>Select the barangay...</option>
                                @foreach($barangays as $brgy)
                                    <option value="{{ $brgy }}" {{ old('barangay') === $brgy ? 'selected' : '' }}>{{ $brgy }}</option>
                                @endforeach
                            </select>
                            @error('barangay') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="street">Street / landmark <span class="opt">(optional)</span></label>
                            <input type="text" name="street" id="street" maxlength="150" class="f-input {{ $fieldError('street') }}"
                                   value="{{ old('street') }}" placeholder="e.g. Rizal St, near the public market">
                            @error('street') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="card-title" style="margin-top:18px;"><i class="bi bi-telephone-inbound-fill"></i> Who reported it</div>
                    <div class="card-hint">How MDRRMO learned about it.</div>

                    <div class="field">
                        <label class="f-label" for="reported_via">Reported via <span class="req">*</span></label>
                        <select name="reported_via" id="reported_via" class="f-input {{ $fieldError('reported_via') }}" required>
                            <option value="" disabled {{ old('reported_via') ? '' : 'selected' }}>Select...</option>
                            @foreach(\App\Models\Incident::REPORTED_VIA as $via)
                                <option value="{{ $via }}" {{ old('reported_via') === $via ? 'selected' : '' }}>{{ $via }}</option>
                            @endforeach
                        </select>
                        @error('reported_via') <div class="f-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="reporter_name">Name <span class="opt">(optional)</span></label>
                            <input type="text" name="reporter_name" id="reporter_name" maxlength="120" class="f-input {{ $fieldError('reporter_name') }}"
                                   value="{{ old('reporter_name') }}" placeholder="Who reported it">
                            @error('reporter_name') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="reporter_mobile">Contact number <span class="opt">(optional)</span></label>
                            <input type="tel" name="reporter_mobile" id="reporter_mobile" maxlength="20" class="f-input {{ $fieldError('reporter_mobile') }}"
                                   value="{{ old('reporter_mobile') }}" placeholder="09XXXXXXXXX">
                            @error('reporter_mobile') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <!-- MDRRMC INCIDENT REPORT -->
                <div class="card-box span-2">
                    <div class="card-title"><i class="bi bi-file-earmark-text-fill"></i> MDRRMC Incident Report</div>
                    <div class="card-hint">The same report the responders file from the app. It's saved as the Final report.</div>

                    <div class="field">
                        <label class="f-label" for="narrative">
                            Details / Narrative / Cause <span class="req">*</span>
                            <span class="f-count" data-count-for="narrative"></span>
                        </label>
                        <textarea name="narrative" id="narrative" maxlength="2000" rows="4" class="f-input {{ $fieldError('narrative') }}"
                                  placeholder="What happened, the underlying circumstances and the cause." required>{{ old('narrative') }}</textarea>
                        @error('narrative') <div class="f-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <span class="f-label">Casualties <span class="opt">(leave empty if none)</span></span>
                        <div class="rows-wrap">
                            @foreach($casualtyGroups as $key => $label)
                                @php $rows = array_values(array_filter((array) ($oldCasualties[$key] ?? []), 'is_array')); @endphp
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
                                                        <option value="M" {{ ($row['sex'] ?? '') === 'M' ? 'selected' : '' }}>M</option>
                                                        <option value="F" {{ ($row['sex'] ?? '') === 'F' ? 'selected' : '' }}>F</option>
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
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="effects">
                                Effects <span class="opt">(optional)</span>
                                <span class="f-count" data-count-for="effects"></span>
                            </label>
                            <textarea name="effects" id="effects" maxlength="1000" rows="3" class="f-input {{ $fieldError('effects') }}"
                                      placeholder="Damage to houses, roads, crops, power…">{{ old('effects') }}</textarea>
                            @error('effects') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="actions_taken">
                                Action/s Taken <span class="req">*</span>
                                <span class="f-count" data-count-for="actions_taken"></span>
                            </label>
                            <textarea name="actions_taken" id="actions_taken" maxlength="1500" rows="3" class="f-input {{ $fieldError('actions_taken') }}"
                                      placeholder="What the team did to resolve it." required>{{ old('actions_taken') }}</textarea>
                            @error('actions_taken') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <div class="group-title" style="margin-top:4px;">
                            <span>Responding Team <span class="opt" style="font-weight:500;color:#9ca3af;">(the first person is the lead)</span></span>
                            <button type="button" class="btn-row-add" id="addTeamRow"><i class="bi bi-plus-lg"></i> Add member</button>
                        </div>
                        <div class="rows-wrap">
                            <table class="rows-table" id="teamTable">
                                <thead>
                                    <tr>
                                        <th style="width:45%;">Name</th>
                                        <th>Agency / Unit</th>
                                        <th style="width:40px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_values(array_filter((array) $oldTeam, 'is_array')) as $i => $member)
                                        <tr>
                                            <td><input type="text" class="f-input" name="team[{{ $i }}][name]" maxlength="120" value="{{ $member['name'] ?? '' }}" placeholder="e.g. Juan Dela Cruz"></td>
                                            <td><input type="text" class="f-input" name="team[{{ $i }}][agency]" maxlength="160" value="{{ $member['agency'] ?? '' }}" placeholder="e.g. BFP Rosales"></td>
                                            <td><button type="button" class="btn-row-remove" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="f-label" for="released_by">Report released by <span class="opt">(Reporter / Designation / Office)</span></label>
                            <input type="text" name="released_by" id="released_by" maxlength="255" class="f-input {{ $fieldError('released_by') }}"
                                   value="{{ old('released_by', $releasedDefault) }}">
                            @error('released_by') <div class="f-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="f-label" for="photo">Photo from the scene <span class="opt">(optional, up to 5 MB)</span></label>
                            <div class="photo-drop">
                                <input type="file" name="photo" id="photo" accept="image/*" class="f-input {{ $fieldError('photo') }}" style="flex:1;min-width:200px;">
                                <img id="photoPreview" class="photo-preview" alt="Selected photo">
                            </div>
                            @error('photo') <div class="f-error">{{ $message }}</div> @enderror
                            <div class="f-help">It's printed on the PDF report.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save" id="btnSave"><i class="bi bi-check2-circle"></i> Save Resolved Report</button>
                <a href="{{ route('incident', ['tab' => 'resolved']) }}" class="btn-cancel">Cancel</a>
                <span class="save-note">Saved as resolved, with the PDF report.</span>
            </div>
        </form>
    </div>
</div>

{{-- Row templates for the "+" buttons. __i__ becomes the row number. --}}
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
<template id="teamRowTpl">
    <tr>
        <td><input type="text" class="f-input" name="team[__i__][name]" maxlength="120" placeholder="e.g. Juan Dela Cruz"></td>
        <td><input type="text" class="f-input" name="team[__i__][agency]" maxlength="160" placeholder="e.g. BFP Rosales"></td>
        <td><button type="button" class="btn-row-remove" title="Remove"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('manualForm');

    // "Other" → type what kind of incident it was.
    const typeSelect = document.getElementById('emergency_type');
    const otherField = document.getElementById('otherTypeField');
    const otherInput = document.getElementById('other_type');
    const syncOther = () => {
        const isOther = typeSelect.value === 'Other';
        otherField.style.display = isOther ? '' : 'none';
        otherInput.required = isOther;
    };
    typeSelect.addEventListener('change', syncOther);
    syncOther();

    // Character counters on the long fields.
    document.querySelectorAll('[data-count-for]').forEach(counter => {
        const field = document.getElementById(counter.dataset.countFor);
        const update = () => { counter.textContent = field.value.length + ' / ' + field.maxLength; };
        field.addEventListener('input', update);
        update();
    });

    // Rows: a counter per table keeps every new row's index unique.
    let nextIndex = 1000;
    const addRow = (tbody, tplId, group) => {
        const html = document.getElementById(tplId).innerHTML
            .replaceAll('__g__', group || '')
            .replaceAll('__i__', String(nextIndex++));
        tbody.insertAdjacentHTML('beforeend', html);
        const row = tbody.lastElementChild;
        row.querySelector('input')?.focus();
        return row;
    };
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
            addRow(table.tBodies[0], 'casualtyRowTpl', group);
            syncEmpty(group);
        });
        syncEmpty(group);
    });

    const teamBody = document.querySelector('#teamTable tbody');
    document.getElementById('addTeamRow').addEventListener('click', () => addRow(teamBody, 'teamRowTpl'));

    form.addEventListener('click', e => {
        const remove = e.target.closest('.btn-row-remove');
        if (!remove) return;
        const row = remove.closest('tr');
        const table = row.closest('table');
        row.remove();
        if (table.dataset.casualtyTable) syncEmpty(table.dataset.casualtyTable);
    });

    // Photo preview.
    const photo = document.getElementById('photo');
    const preview = document.getElementById('photoPreview');
    photo.addEventListener('change', () => {
        const file = photo.files && photo.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    });

    // "Resolved" can't be before "happened".
    const occurred = document.getElementById('occurred_at');
    const resolved = document.getElementById('resolved_at');
    occurred.addEventListener('change', () => { resolved.min = occurred.value || ''; });
    resolved.min = occurred.value || '';

    // The browser checks the required fields first; then one save only.
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        const btn = document.getElementById('btnSave');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
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
