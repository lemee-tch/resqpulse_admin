<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>MDRRMC Incident Report #{{ $incident->id }}</title>
    <style>
        /* dompdf = CSS 2.1 only (no flexbox/grid) — the layout below is
           plain tables, mirroring the official MDRRMC Incident Report form. */
        @page { margin: 28px 34px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .head { width: 100%; margin-bottom: 4px; }
        .head td { text-align: center; }
        .head .muni { font-size: 11px; }
        .head .council { font-size: 11px; font-weight: bold; }
        .title { text-align: center; font-size: 13px; font-weight: bold; margin: 8px 0 8px; }

        .box { border: 1.5px solid #000; }
        .box td { border: 1px solid #000; padding: 3px 6px; }
        .lbl { background: #d9d9d9; font-weight: bold; width: 17%; }
        .lbl2 { background: #d9d9d9; font-weight: bold; }
        .sp { height: 6px; }

        .sect { background: #d9d9d9; font-weight: bold; padding: 3px 6px; border: 1px solid #000; }
        .body { border: 1px solid #000; border-top: 0; padding: 6px; min-height: 60px; line-height: 1.45; }

        table.cas { width: 92%; margin: 2px auto 10px; }
        table.cas th, table.cas td { border: 1px solid #000; padding: 3px 4px; font-size: 9.5px; }
        table.cas th { text-align: center; }
        .casl { font-weight: bold; text-decoration: underline; margin: 8px 0 2px 26px; }

        .chk { font-family: DejaVu Sans, sans-serif; }
        .photo { text-align: center; padding: 14px 0; border: 1px solid #000; border-top: 0; }
        .photo img { max-width: 360px; max-height: 420px; }
        .foot { margin-top: 8px; font-size: 8px; color: #666; text-align: center; }
    </style>
</head>
<body>
@php
    $r = $report;
    $type = $r['report_type'] ?? 'final';
    $mark = fn($on) => $on ? '&#10003;' : '&nbsp;';
    $rows = function ($list, $min) {
        $list = array_values(array_filter((array) $list, fn($x) => is_array($x) && trim(implode('', array_map('strval', $x))) !== ''));
        while (count($list) < $min) { $list[] = []; }
        return $list;
    };
@endphp

<table class="head">
    <tr>
        <td style="width:15%;">@if($logoLeft)<img src="{{ $logoLeft }}" style="width:62px;height:62px;">@endif</td>
        <td>
            <div class="muni">Municipality of Rosales</div>
            <div class="council">Municipal Disaster Risk Reduction and Management Council</div>
        </td>
        <td style="width:15%;">@if($logoRight)<img src="{{ $logoRight }}" style="width:62px;height:62px;">@endif</td>
    </tr>
</table>
<div class="title">MDRRMC INCIDENT REPORT</div>

<table class="box">
    <tr>
        <td class="lbl">Report Source:<br><span style="font-weight:normal;">(Office/Unit)</span></td>
        <td style="width:33%;">{{ $r['report_source'] ?? 'MDRRMO Rosales, Pangasinan' }}</td>
        <td class="lbl2" style="width:50%;">Validated by:<br><span style="font-weight:normal;">{{ $r['validated_by'] ?? '' }}</span></td>
    </tr>
    <tr>
        <td class="lbl">Report Date/Time:</td>
        <td colspan="2">{{ $r['report_datetime'] ?? '' }}</td>
    </tr>
</table>
<div class="sp"></div>

<table class="box">
    <tr>
        <td class="lbl" style="width:17%;">Incident Type:</td>
        <td style="width:33%;">{{ $r['incident_type'] ?? '' }}</td>
        <td colspan="2" style="width:50%;padding:0;">
            <div style="padding:3px 6px;border-bottom:1px solid #000;"><span class="chk">{!! $mark($type === 'initial') !!}</span>&nbsp; Initial Report</div>
            <div style="padding:3px 6px;border-bottom:1px solid #000;"><span class="chk">{!! $mark($type === 'progress') !!}</span>&nbsp; Progress Report No. {{ $type === 'progress' ? ($r['progress_no'] ?? '') : '' }}</div>
            <div style="padding:3px 6px;"><span class="chk">{!! $mark($type === 'final') !!}</span>&nbsp; Final Report</div>
        </td>
    </tr>
    <tr>
        <td class="lbl" rowspan="3">Location:</td>
        <td rowspan="3">{{ $r['location'] ?? '' }}</td>
        <td class="lbl2" style="width:17%;">Incident Date:</td>
        <td>{{ $r['incident_date'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="lbl2">Day:</td>
        <td>{{ $r['incident_day'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="lbl2">Time:</td>
        <td>{{ $r['incident_time'] ?? '' }}</td>
    </tr>
</table>

<div class="sect" style="margin-top:0;">Details/Narrative/Underlying Circumstances/Cause/s:</div>
<div class="body" style="min-height:90px;">{!! nl2br(e($r['narrative'] ?? '')) !!}</div>

<div class="sect" style="margin-top:8px;">CASUALTIES:</div>
<div class="body" style="padding:2px 0 0;">
    @foreach (['dead' => 'A. Dead/Drowned', 'injured' => 'B. Injured', 'missing' => 'C. Missing'] as $key => $label)
        <div class="casl">{{ $label }}</div>
        <table class="cas">
            <tr>
                <th style="width:26%;">Name</th><th style="width:7%;">Age</th><th style="width:7%;">Sex</th>
                <th style="width:20%;">Address</th><th style="width:16%;">Cause</th>
                <th>Remarks<br>(Disposition of Casualty)</th>
            </tr>
            @php
                $raw  = $r['casualties'][$key] ?? [];
                $none = $rows($raw, 0) === [];
                $list = $rows($raw, 3);
            @endphp
            @foreach ($list as $i => $c)
                @if ($none && $i === 0)
                <tr><td colspan="6" style="text-align:center;">None</td></tr>
                @else
                <tr>
                    <td style="font-weight:bold;">{{ strtoupper($c['name'] ?? '') }}&nbsp;</td>
                    <td style="text-align:center;">{{ $c['age'] ?? '' }}</td>
                    <td style="text-align:center;">{{ strtoupper($c['sex'] ?? '') }}</td>
                    <td>{{ $c['address'] ?? '' }}</td>
                    <td>{{ $c['cause'] ?? '' }}</td>
                    <td>{{ $c['remarks'] ?? '' }}</td>
                </tr>
                @endif
            @endforeach
        </table>
    @endforeach
</div>

<div style="page-break-inside:avoid;">
    <div class="sect" style="margin-top:8px;">EFFECTS:</div>
    <div class="body" style="min-height:50px;">{!! nl2br(e($r['effects'] ?? '')) !!}</div>

    <div class="sect" style="margin-top:8px;">Action/s Taken:</div>
    <div class="body" style="min-height:60px;">{!! nl2br(e($r['actions_taken'] ?? '')) !!}</div>

    <div class="sect" style="margin-top:8px;">Report Released by: <span style="font-weight:normal;">(Reporter/Designation/ Office/Agency)</span></div>
    <div class="body" style="min-height:14px;">{{ $r['released_by'] ?? '' }}</div>
</div>

@if($photoSrc)
    <div class="photo"><img src="{{ $photoSrc }}"></div>
@endif

<div class="foot">Generated by ResQPulse · Incident #{{ $incident->id }} · {{ now()->format('M d, Y g:i A') }}</div>
</body>
</html>