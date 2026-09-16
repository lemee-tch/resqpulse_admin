<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>RESQPULSE Report</title>
    <style>
        /* dompdf only supports CSS 2.1 + a handful of CSS3 properties —
           no flexbox, no grid, limited box-shadow/border-radius support.
           Kept deliberately plain rather than reusing the admin panel's
           normal CSS, which would silently break in ways only visible
           once you actually open the PDF. */
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; font-size: 11px; }
        .header { border-bottom: 3px solid #1a3c8f; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { font-size: 18px; color: #1a3c8f; margin: 0 0 2px; }
        .header .sub { font-size: 10px; color: #6b7280; }

        .summary { width: 100%; margin-bottom: 18px; }
        .summary td {
            width: 25%; text-align: center; padding: 10px 6px;
            border: 1px solid #e5e7eb;
        }
        .summary .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .summary .value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 3px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #1a3c8f; color: #fff; text-align: left;
            padding: 6px 8px; font-size: 9.5px; text-transform: uppercase;
        }
        table.data td {
            padding: 6px 8px; font-size: 9.5px; border-bottom: 1px solid #e5e7eb;
        }
        table.data tr:nth-child(even) { background: #f9fafb; }

        .footer { margin-top: 16px; font-size: 8.5px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>MDRRMO Rosales — Incident Report</h1>
        <div class="sub">
            {{ $fromDate->format('M d, Y') }} – {{ $toDate->format('M d, Y') }}
            &nbsp;·&nbsp; Generated {{ now()->format('M d, Y g:i A') }}
        </div>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Total Incidents</div>
                <div class="value">{{ $totalIncidents }}</div>
            </td>
            <td>
                <div class="label">Resolved</div>
                <div class="value">{{ $resolvedIncidents }}</div>
            </td>
            <td>
                <div class="label">Resolution Rate</div>
                <div class="value">{{ $resolutionRate }}%</div>
            </td>
            <td>
                <div class="label">Date Range</div>
                <div class="value" style="font-size: 12px;">
                    {{ $fromDate->diffInDays($toDate) + 1 }} days
                </div>
            </td>
        </tr>
    </table>

    @if($incidents->isEmpty())
        <p style="text-align:center; color:#9ca3af; padding: 30px 0;">
            No incidents were reported in this date range.
        </p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Reporter</th>
                    <th>Reported At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidents as $inc)
                    <tr>
                        <td>{{ $inc->emergency_type }}</td>
                        <td>{{ $inc->priority ? ucfirst($inc->priority) : '—' }}</td>
                        <td>{{ $inc->needs_review ? 'Pending Review' : ucfirst($inc->status) }}</td>
                        <td>{{ $inc->location ?: '—' }}</td>
                        <td>{{ $inc->citizen?->full_name ?? ($inc->citizen_id ? 'Unknown' : 'Guest') }}</td>
                        <td>{{ $inc->created_at->format('M d, Y g:i A') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        RESQPULSE — Emergency Reporting &amp; Real-Time Alert System · MDRRMO Rosales, Pangasinan
    </div>

</body>
</html>