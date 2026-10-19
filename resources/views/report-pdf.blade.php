<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Incident Summary Report</title>
    {{--
        "Export Report" on Reports & Analytics (AuthController::exportReport()
        → exportReportAsPdf()). That method always loaded this view, but the
        file didn't exist, so every export ended in a server error.

        Gets: $incidents (with citizen), $fromDate, $toDate, $totalIncidents,
        $resolvedIncidents, $resolutionRate, $exportedBy, $listedIncidents
        (the rows for the incident list — at most
        AuthController::PDF_LIST_LIMIT of them; the totals and breakdowns
        always count every incident in $incidents) and $barangayLabels
        (incident id => barangay, for "By barangay").

        dompdf only understands CSS 2.1 — no flexbox/grid — so the layout is
        plain tables, same as incident-report-pdf.blade.php. dompdf keeps the
        whole document in memory, so each list row is kept simple, and the
        built-in Helvetica font is used (nothing to embed, so the file stays
        small; ñ, — and · still print).
    --}}
    <style>
        @page { margin: 26px 30px 40px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #111; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .head td { text-align: center; vertical-align: middle; }
        .head .muni { font-size: 10px; }
        .head .council { font-size: 10.5px; font-weight: bold; }
        .head .office { font-size: 9px; color: #444; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0 2px; letter-spacing: .5px; }
        .period { text-align: center; font-size: 9.5px; margin-bottom: 2px; }
        .meta { text-align: center; font-size: 8px; color: #555; margin-bottom: 10px; }

        .stats td { border: 1px solid #999; text-align: center; padding: 6px 4px; width: 20%; }
        .stats .num { font-size: 15px; font-weight: bold; color: #1a3c8f; }
        .stats .lbl { font-size: 8px; color: #444; text-transform: uppercase; letter-spacing: .3px; }

        .sect { font-size: 10.5px; font-weight: bold; margin: 14px 0 5px; padding-bottom: 2px; border-bottom: 1.5px solid #1a3c8f; color: #1a3c8f; }

        .cols > tbody > tr > td { width: 50%; }
        .cols > tbody > tr > td.gap { width: 2%; }

        .breakdown th, .breakdown td { border: 1px solid #bbb; padding: 3px 6px; }
        .breakdown th { background: #e8edf7; text-align: left; font-size: 8.5px; }
        .breakdown td.n, .breakdown th.n { text-align: right; width: 18%; }

        .list thead { display: table-header-group; }
        .list tr { page-break-inside: avoid; }
        .list th, .list td { border: 1px solid #bbb; padding: 3px 4px; }
        .list th { background: #e8edf7; text-align: left; font-size: 8px; }
        .list td { font-size: 8px; }
        .list tr.alt td { background: #f7f8fb; }
        .muted { color: #777; }
        .empty { text-align: center; padding: 14px; color: #777; border: 1px solid #bbb; }
        .note { font-size: 8px; color: #555; background: #f3f4f6; border: 1px solid #ddd; padding: 4px 6px; margin-bottom: 5px; }

        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7.5px; color: #777; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
@php
    // ── Logos (same files as the MDRRMC Incident Report) ──
    $logo = function (string $file) {
        $path = public_path('images/' . $file);
        if (! is_file($path)) {
            return null;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    };
    $logoLeft  = $logo('logo_mdrrmo.png');
    $logoRight = $logo('logo_rosales.png');

    // ── Status, type and barangay of each incident ──
    // Approved with no one accepted yet is "Active · Awaiting Responder",
    // not pending (Incident::progressLabel()).
    $statusOf = fn ($inc) => $inc->needs_review ? 'Pending Review' : $inc->progressLabel();

    // Same grouping as the "Reports by Type" chart on Reports & Analytics:
    // SOS stays SOS, otherwise the AI-detected type if any, and anything
    // that isn't a known type (free text from "Other") counts as Other.
    $knownTypes = ['Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'Other'];
    $typeOf = function ($inc) use ($knownTypes) {
        if ($inc->emergency_type === 'SOS Emergency') return 'SOS Emergency';
        $type = $inc->ai_detected_type ?: $inc->emergency_type;
        if ($type === 'Vehicular Accident') return 'Accident';
        return in_array($type, $knownTypes, true) ? $type : 'Other';
    };

    // Barangay from the location text, e.g. "SOS Alert — Rizal St, Brgy.
    // Carmay East, Rosales, Pangasinan". Longest names first so
    // "Zone II (Poblacion)" isn't read as "Zone I (Poblacion)".
    // The controller passes $barangayLabels (incident id => barangay), the
    // same grouping as the "Incidents by Location" chart. The text match
    // below is only a fallback for when it isn't passed.
    $barangayLabels = $barangayLabels ?? [];
    $barangayNames = \App\Services\BarangayLocationService::names();
    usort($barangayNames, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $barangayOf = function ($inc) use ($barangayNames, $barangayLabels) {
        if (isset($barangayLabels[$inc->id])) return $barangayLabels[$inc->id];
        $text = (string) $inc->location;
        if (stripos($text, 'Outside Rosales') !== false) return 'Outside Rosales';
        foreach ($barangayNames as $name) {
            if ($name !== '' && stripos($text, $name) !== false) return $name;
        }
        return 'Not identified';
    };

    // Who sent it — a guest's typed name when there's no account.
    $reporterOf = function ($inc) {
        return $inc->reporter_name
            ?: ($inc->citizen?->full_name ?? ($inc->citizen_id ? 'Unknown' : 'Guest'));
    };

    $count = fn ($fn) => $incidents->groupBy($fn)->map->count()->sortDesc();
    $byType     = $count($typeOf);
    $byStatus   = $count($statusOf);
    $byBarangay = $count($barangayOf);

    $sosCount      = $incidents->where('emergency_type', 'SOS Emergency')->count();
    $declinedCount = $incidents->filter(fn ($i) => (bool) $i->declined_at)->count();
    $pct = fn ($n) => $totalIncidents > 0 ? round($n / $totalIncidents * 100) . '%' : '0%';

    // The incident list — capped by the controller for very long periods.
    $listed = $listedIncidents ?? $incidents;
    $listIsCut = $listed->count() < $incidents->count();
@endphp

<div class="footer">
    <table>
        <tr>
            <td>ResQPulse · MDRRMO Rosales, Pangasinan · Generated {{ now()->format('M d, Y g:i A') }}</td>
            <td style="text-align:right;">Page <span class="page"></span></td>
        </tr>
    </table>
</div>

<table class="head">
    <tr>
        <td style="width:15%;">@if($logoLeft)<img src="{{ $logoLeft }}" style="width:58px;height:58px;">@endif</td>
        <td>
            <div class="muni">Republic of the Philippines · Province of Pangasinan</div>
            <div class="muni">Municipality of Rosales</div>
            <div class="council">Municipal Disaster Risk Reduction and Management Council</div>
            <div class="office">MDRRMO Rosales</div>
        </td>
        <td style="width:15%;">@if($logoRight)<img src="{{ $logoRight }}" style="width:58px;height:58px;">@endif</td>
    </tr>
</table>

<div class="title">INCIDENT SUMMARY REPORT</div>
<div class="period">
    {{ $fromDate->format('F d, Y') }} – {{ $toDate->format('F d, Y') }}
</div>
<div class="meta">Prepared by {{ $exportedBy }} · {{ now()->format('F d, Y g:i A') }}</div>

<table class="stats">
    <tr>
        <td><div class="num">{{ $totalIncidents }}</div><div class="lbl">Total incidents</div></td>
        <td><div class="num">{{ $resolvedIncidents }}</div><div class="lbl">Resolved</div></td>
        <td><div class="num">{{ $resolutionRate }}%</div><div class="lbl">Resolution rate</div></td>
        <td><div class="num">{{ $sosCount }}</div><div class="lbl">SOS alerts</div></td>
        <td><div class="num">{{ $declinedCount }}</div><div class="lbl">Declined</div></td>
    </tr>
</table>

<table class="cols" style="margin-top:4px;">
    <tr>
        <td>
            <div class="sect">By type</div>
            <table class="breakdown">
                <tr><th>Type</th><th class="n">Count</th><th class="n">Share</th></tr>
                @forelse($byType as $label => $n)
                    <tr><td>{{ $label }}</td><td class="n">{{ $n }}</td><td class="n">{{ $pct($n) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No incidents.</td></tr>
                @endforelse
            </table>
        </td>
        <td class="gap"></td>
        <td>
            <div class="sect">By status</div>
            <table class="breakdown">
                <tr><th>Status</th><th class="n">Count</th><th class="n">Share</th></tr>
                @forelse($byStatus as $label => $n)
                    <tr><td>{{ $label }}</td><td class="n">{{ $n }}</td><td class="n">{{ $pct($n) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No incidents.</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<div class="sect">By barangay</div>
<table class="breakdown">
    <tr><th>Barangay</th><th class="n">Count</th><th class="n">Share</th></tr>
    @forelse($byBarangay as $label => $n)
        <tr><td>{{ $label }}</td><td class="n">{{ $n }}</td><td class="n">{{ $pct($n) }}</td></tr>
    @empty
        <tr><td colspan="3" class="muted">No incidents.</td></tr>
    @endforelse
</table>

<div class="sect">Incident list</div>
@if($incidents->isEmpty())
    <div class="empty">No incidents were reported in this period.</div>
@else
    @if($listIsCut)
        <div class="note">
            Showing the latest {{ number_format($listed->count()) }} of {{ number_format($incidents->count()) }} incidents.
            The totals and breakdowns above count all of them. To list every incident, export a shorter date range.
        </div>
    @endif
    <table class="list">
        <thead>
            <tr>
                <th style="width:15%;">Date &amp; time</th>
                <th style="width:17%;">Type</th>
                <th>Location</th>
                <th style="width:17%;">Reporter</th>
                <th style="width:8%;">Priority</th>
                <th style="width:11%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($listed as $i => $inc)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $inc->display_type }}</td>
                    <td>{{ $inc->location ?: '—' }}</td>
                    <td>{{ $reporterOf($inc) }}</td>
                    <td>{{ $inc->priority ? ucfirst($inc->priority) : '—' }}</td>
                    <td>{{ $statusOf($inc) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>