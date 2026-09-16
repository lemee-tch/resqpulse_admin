<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports &amp; Analytics — ResQPulse MDRRMO</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
:root{
  --primary:#1A3C8F;
  --primary-dark:#122a66;
  --primary-soft:#EAF0FB;
  --flood:#2F6FED;
  --fire:#F0703A;
  --accident:#E63946;
  --others:#C9CDD6;
  --ink:#1C2433;
  --muted:#6B7385;
  --line:#E7E9F0;
  --up:#1C9D5B;
  --down:#E0473F;
  --bg:#F5F6FA;
}
*{box-sizing:border-box;}
body{
  background:var(--bg);
  font-family:'Inter',sans-serif;
  color:var(--ink);
  margin:0;
}
h1,h2,h3,.brand-title,.metric-value{font-family:'Barlow',sans-serif;}

        /* ── SIDEBAR ── */
        .sidebar {
            width: 200px;
            min-height: 100vh;
            background: #1a3c8f;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 16px 16px;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .sidebar-brand img {
            width: 38px; height: 38px;
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

        .sidebar-nav a:hover {
            color: #fff;
            background: rgba(255,255,255,.08);
        }

        .sidebar-nav a.active {
            color: #fff;
            font-weight: 700;
            border-left-color: #fff;
            background: rgba(255,255,255,.1);
        }

        .sidebar-logout {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.12);
        }

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

        .sidebar-logout a:hover { color: #fff; }

/* ---------- Main ---------- */
.main{margin-left:200px;padding:32px 36px 48px;}
.page-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:26px;}
.page-head h1{font-size:26px;font-weight:800;margin:0;color:var(--ink);}
.page-head .eyebrow{font-size:12.5px;color:var(--muted);font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin-bottom:2px;}

.head-actions{display:flex;align-items:center;gap:10px;}
.date-pill{
  background:#fff;border:1px solid var(--line);border-radius:10px;
  padding:9px 14px;font-size:13.5px;font-weight:600;color:var(--ink);
  display:flex;align-items:center;gap:8px;cursor:pointer;
}
.date-pill i{color:var(--muted);}
.date-range-form{
  background:#fff;border:1px solid var(--line);border-radius:10px;
  padding:8px 14px;font-size:13.5px;font-weight:600;color:var(--ink);
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.range-preset{
  border:none;background:transparent;font-size:13px;font-weight:700;
  color:var(--primary);font-family:inherit;cursor:pointer;padding:4px 2px;
}
.range-preset:focus{outline:none;}
.date-sep{color:var(--line);font-weight:400;}
.date-input{
  border:none;background:transparent;font-size:13.5px;font-weight:600;
  color:var(--ink);font-family:inherit;padding:5px 3px;cursor:pointer;
}
.date-input:focus{outline:none;}
.btn-apply-range{
  background:var(--primary);color:#fff;border:none;border-radius:8px;
  padding:8px 18px;font-weight:700;font-size:13px;cursor:pointer;
  transition:background .15s;
}
.btn-apply-range:hover{background:var(--primary-dark);}
@media (max-width: 560px){
  .date-range-form{width:100%;}
}
.btn-export{
  background:var(--primary);color:#fff;border:none;border-radius:10px;
  padding:9px 18px;font-weight:700;font-size:13.5px;
  display:flex;align-items:center;gap:8px;text-decoration:none;
  box-shadow:0 6px 14px rgba(26,60,143,.28);
}
.btn-export:hover{background:var(--primary-dark);color:#fff;}

/* ---------- Stat cards ---------- */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px;}
.card{
  background:#fff;border:1px solid var(--line);border-radius:16px;
  box-shadow:0 2px 10px rgba(28,36,51,.04);
}
.stat-card{padding:20px 20px 18px;}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.stat-label{font-size:13px;color:var(--muted);font-weight:600;}
.stat-icon{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:16px;
}
.stat-value{font-size:30px;font-weight:800;line-height:1;margin-bottom:8px;}
.trend{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:700;}
.trend.up{color:var(--up);}
.trend.down{color:var(--down);}
.trend-note{color:var(--muted);font-weight:500;}

/* ---------- Panel headers ---------- */
.panel{padding:22px;}
.panel-head{display:flex;align-items:center;justify-content:between;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.panel-title{font-size:15.5px;font-weight:700;color:var(--ink);}
.panel-sub{font-size:12px;color:var(--muted);font-weight:500;}

.row-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:18px;margin-bottom:18px;}

/* Donut/Pie legend */
.donut-wrap{display:flex;align-items:center;gap:28px;}
.legend-list{flex:1;display:flex;flex-direction:column;gap:12px;}
.legend-row{display:flex;align-items:center;gap:10px;font-size:13.5px;}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.legend-name{flex:1;color:var(--ink);font-weight:600;}
.legend-pending{
  display:inline-block;margin-left:8px;background:#fef3c7;color:#92400e;
  font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;
  vertical-align:middle;
}
.legend-pct{color:var(--muted);font-weight:700;font-size:13px;}

/* Location bar chart card */
.loc-chart-wrap{position:relative;height:260px;}

/* Time filter controls */
.time-filter{display:flex;align-items:center;gap:8px;}
.tf-tabs{display:flex;background:var(--primary-soft);border-radius:10px;padding:3px;gap:2px;}
.tf-tab{
  border:none;background:transparent;border-radius:8px;
  padding:7px 14px;font-size:12.5px;font-weight:700;color:var(--muted);
  cursor:pointer;transition:all .15s;font-family:'Inter',sans-serif;
}
.tf-tab.active{background:var(--primary);color:#fff;box-shadow:0 3px 8px rgba(26,60,143,.25);}
.tf-tab:not(.active):hover{color:var(--primary);}
.tf-picker{
  border:1px solid var(--line);border-radius:8px;background:#fff;
  padding:7px 10px;font-size:12.5px;font-weight:600;color:var(--ink);
  font-family:'Inter',sans-serif;
}
.tf-picker:focus{outline:none;border-color:var(--primary);}

/* Line chart card */
.trend-card .panel-head{margin-bottom:6px;}
canvas{max-width:100%;}

@media (max-width:1100px){
  .stat-grid{grid-template-columns:repeat(2,1fr);}
  .row-grid{grid-template-columns:1fr;}
}
    </style>
</head>
<body>

<!-- ════════════ SIDEBAR ════════════ -->
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
        <a href="{{ route('citizen-verification') }}"><i class="bi bi-person-check-fill"></i> Residents Verification</a>
        <a href="{{ route('responder-accounts') }}"><i class="bi bi-person-badge-fill"></i> Responder Accounts</a>
        <a href="{{ route('reports-analytics') }}" class="active"><i class="bi bi-bar-chart-fill"></i> Reports &amp; Analytics</a>
        <a href="{{ route('audit-log') }}"><i class="bi bi-journal-text"></i> Audit Log</a>
        <a href="{{ route('users') }}"><i class="bi bi-people-fill"></i> User</a>
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

<main class="main">

  <div class="page-head">
    <div>
      <div class="eyebrow">Performance overview</div>
      <h1>Reports &amp; Analytics</h1>
    </div>
    <div class="head-actions">
      <form method="GET" action="{{ route('reports-analytics') }}" class="date-range-form" id="dateRangeForm">
        <select class="range-preset" id="rangePreset" onchange="applyRangePreset(this.value)">
          <option value="">Quick range…</option>
          <option value="today">Today</option>
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
          <option value="thismonth">This month</option>
          <option value="lastmonth">Last month</option>
        </select>
        <span class="date-sep">|</span>
        <i class="bi bi-calendar3" style="color:var(--muted);"></i>
        <input type="date" name="from" id="fromInput" value="{{ $fromDate->toDateString() }}" class="date-input" max="{{ now()->toDateString() }}">
        <span style="color:var(--muted);">–</span>
        <input type="date" name="to" id="toInput" value="{{ $toDate->toDateString() }}" class="date-input" max="{{ now()->toDateString() }}">
        <button type="submit" class="btn-apply-range">Apply</button>
      </form>
      <a href="{{ route('reports-analytics.export', ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()]) }}" class="btn-export">
          <i class="bi bi-download"></i> Export Report
      </a>
    </div>
  </div>

  <div class="panel-sub" style="margin:-14px 0 18px;">
    Stat cards and the two breakdowns below reflect <strong>{{ $fromDate->format('M d, Y') }} – {{ $toDate->format('M d, Y') }}</strong>,
    compared against the {{ $fromDate->diffInDays($toDate) + 1 }}-day period right before it.
    "Incidents over Time" below has its own Week/Month/Year view, independent of this range.
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Total Incidents</span>
        <span class="stat-icon" style="background:var(--primary-soft);color:var(--primary);"><i class="bi bi-clipboard2-pulse"></i></span>
      </div>
      <div class="stat-value">{{ $totalIncidents }}</div>
      <span class="trend {{ $totalTrend >= 0 ? 'up' : 'down' }}">
        <i class="bi bi-arrow-{{ $totalTrend >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($totalTrend) }}%<span class="trend-note">&nbsp;vs previous period</span>
      </span>
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Resolved</span>
        <span class="stat-icon" style="background:#E7F7EE;color:var(--up);"><i class="bi bi-check2-circle"></i></span>
      </div>
      <div class="stat-value">{{ $resolvedIncidents }}</div>
      <span class="trend {{ $resolvedTrend >= 0 ? 'up' : 'down' }}">
        <i class="bi bi-arrow-{{ $resolvedTrend >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($resolvedTrend) }}%<span class="trend-note">&nbsp;vs previous period</span>
      </span>
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Response Time (Avg)</span>
        <span class="stat-icon" style="background:#FFF1E8;color:var(--fire);"><i class="bi bi-stopwatch"></i></span>
      </div>
      <div class="stat-value">{{ $avgResponseMinutes !== null ? $avgResponseMinutes . 'm' : '—' }}</div>
      @if($responseTimeTrend === null)
        <span class="trend" style="color:var(--muted);"><span class="trend-note">Not enough data yet</span></span>
      @else
        {{-- Lower response time is the improvement here, so the arrow
             logic is intentionally flipped from the other cards: a
             DECREASE shows the green "up" (good) styling. --}}
        <span class="trend {{ $responseTimeTrend <= 0 ? 'up' : 'down' }}">
          <i class="bi bi-arrow-{{ $responseTimeTrend <= 0 ? 'down' : 'up' }}-short"></i>{{ abs($responseTimeTrend) }}m<span class="trend-note">&nbsp;vs previous period</span>
        </span>
      @endif
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Resolution Rate</span>
        <span class="stat-icon" style="background:#EAF2FF;color:var(--flood);"><i class="bi bi-check-circle"></i></span>
      </div>
      @if($totalIncidents === 0)
        {{-- resolved ÷ total can't divide by zero, so a 0-incident
             period would otherwise silently show "0%" — indistinguishable
             from "incidents happened but none got resolved," which is a
             very different (and worse) situation. Say plainly that
             there's no data instead of a number that could mean either. --}}
        <div class="stat-value" style="font-size:22px;">No data</div>
        <span class="trend" style="color:var(--muted);"><span class="trend-note">No incidents in this period</span></span>
      @else
        <div class="stat-value">{{ $resolutionRate }}%</div>
        <span class="trend {{ $resolutionRateTrend >= 0 ? 'up' : 'down' }}">
          <i class="bi bi-arrow-{{ $resolutionRateTrend >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($resolutionRateTrend) }} pts<span class="trend-note">&nbsp;vs previous period</span>
        </span>
      @endif
    </div>
  </div>

  <!-- Pie chart + Location bar chart -->
  <div class="row-grid">
    <div class="card panel">
      <div class="panel-head">
        <div>
          <div class="panel-title">Incidents by Type</div>
          <div class="panel-sub">Breakdown of {{ $reportsByType->sum() }} classified reports</div>
        </div>
      </div>
      <div class="donut-wrap">
        <div style="width:190px;height:190px;flex-shrink:0;">
          <canvas id="typePie" width="190" height="190"></canvas>
        </div>
          @php $pieColors = ['#2F6FED','#F0703A','#E63946','#C9CDD6','#8b5cf6','#ec4899','#10b981']; @endphp
          <div class="legend-list">
              @forelse($reportsByType as $type => $count)
                  <div class="legend-row">
                      <span class="legend-dot" style="background:{{ $pieColors[$loop->index % count($pieColors)] }};"></span>
                      <span class="legend-name">
                          {{ $type }}
                          @if(($reportsByTypePending[$type] ?? 0) > 0)
                              <span class="legend-pending">{{ $reportsByTypePending[$type] }} pending</span>
                          @endif
                      </span>
                      <span class="legend-pct">{{ $reportsByType->sum() > 0 ? round($count / $reportsByType->sum() * 100) : 0 }}%</span>
                  </div>
              @empty
                  <div style="color:var(--muted);font-size:13px;">No incidents reported yet.</div>
              @endforelse
          </div>
      </div>
    </div>

    <div class="card panel">
      <div class="panel-head">
        <div>
          <div class="panel-title">Incidents by Location</div>
          <div class="panel-sub">Top {{ $reportsByLocation->count() }} locations this period</div>
        </div>
      </div>
      <div class="loc-chart-wrap">
        <canvas id="locationBar"></canvas>
      </div>
    </div>
  </div>

  <!-- Trend line with Day / Month / Year filter -->
  <div class="card panel trend-card">
    <div class="panel-head">
      <div>
        <div class="panel-title">Incidents over Time</div>
        <div class="panel-sub" id="trendSub"></div>
      </div>
        <div class="time-filter">
          <div class="tf-tabs" id="tfTabs">
            <button type="button" class="tf-tab active" data-range="week">Week</button>
            <button type="button" class="tf-tab" data-range="month">Month</button>
            <button type="button" class="tf-tab" data-range="year">Year</button>
          </div>
        </div>
    </div>
    <canvas id="trendLine" height="90"></canvas>
  </div>

</main>

<script>
// One click instead of hand-picking two dates every time — computes the
// range client-side and auto-submits, so "Apply" is only needed for a
// genuinely custom range typed into the date inputs directly.
function applyRangePreset(preset) {
  if (!preset) return;

  const pad = n => String(n).padStart(2, '0');
  const toStr = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  const today = new Date();
  let from = new Date(today);
  let to = new Date(today);

  if (preset === 'today') {
    // from/to both stay "today"
  } else if (preset === '7' || preset === '30') {
    from.setDate(from.getDate() - (parseInt(preset, 10) - 1));
  } else if (preset === 'thismonth') {
    from = new Date(today.getFullYear(), today.getMonth(), 1);
  } else if (preset === 'lastmonth') {
    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    to = new Date(today.getFullYear(), today.getMonth(), 0);
  }

  document.getElementById('fromInput').value = toStr(from);
  document.getElementById('toInput').value = toStr(to);
  document.getElementById('dateRangeForm').submit();
}

// ── Incidents by Type — Pie Chart ──────────────────────────────────────
const typeLabels = @json($reportsByType->keys());
const typeData = @json($reportsByType->values());
const pieColors = ['#2F6FED','#F0703A','#E63946','#C9CDD6','#8b5cf6','#ec4899','#10b981'];

new Chart(document.getElementById('typePie'), {
  type: 'pie',
  data: {
    labels: typeLabels,
    datasets: [{
      data: typeData,
      backgroundColor: pieColors.slice(0, Math.max(typeLabels.length, 1)),
      borderWidth: 3,
      borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + c.raw } } }
  }
});

// ── Incidents by Location — Bar Chart ──────────────────────────────────
new Chart(document.getElementById('locationBar'), {
  type: 'bar',
  data: {
    labels: @json($reportsByLocation->keys()),
    datasets: [{
      label: 'Incidents',
      data: @json($reportsByLocation->values()),
      backgroundColor: '#1A3C8F',
      borderRadius: 6,
      maxBarThickness: 46
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        // Tooltip title always shows the FULL original location (Chart.js
        // pulls this from data.labels, not from the tick callback below),
        // so truncating the on-axis label loses no information — anyone
        // who needs the full text just hovers.
        callbacks: { label: c => c.raw + (c.raw === 1 ? ' incident' : ' incidents') }
      }
    },
    scales: {
      // stepSize: 1 — this is a count of incidents, which can only ever
      // be a whole number. Without this, Chart.js's auto-scaling showed
      // fractional gridlines (0.2, 0.4, 0.6...) whenever the tallest bar
      // was small, which doesn't mean anything for a count.
      y: {
        beginAtZero: true,
        grid: { color: '#EEF0F6' },
        ticks: { color: '#6B7385', font: { size: 11.5 }, stepSize: 1, precision: 0 }
      },
      x: {
        grid: { display: false },
        ticks: {
          color: '#6B7385',
          font: { size: 11.5 },
          maxRotation: 35,
          minRotation: 0,
          // Some older SOS reports (from before location resolution was
          // wired up — see BarangayLocationService) still have raw
          // coordinates baked into `location`, e.g. "SOS Alert (outside
          // Rosales — Lat 16.07843, Lng 120.57323)". Left un-truncated,
          // one long label like that pushes every other bar's label into
          // an unreadable wrap. Truncate on the axis; full text is still
          // one hover away via the tooltip.
          callback: function (value) {
            const label = this.getLabelForValue(value);
            return label.length > 24 ? label.slice(0, 22) + '…' : label;
          }
        }
      }
    }
  }
});

// ── Incidents over Time — Line Chart with Week / Month / Year filter ────
const trendDatasets = {
  week: {
    sub: 'Daily volume, last 7 days',
    labels: @json($trendWeek->keys()),
    data:   @json($trendWeek->values()),
  },
  month: {
    sub: 'Weekly volume, last 5 weeks',
    labels: @json($trendMonth->keys()),
    data:   @json($trendMonth->values()),
  },
  year: {
    sub: 'Monthly volume, last 12 months',
    labels: @json($trendYear->keys()),
    data:   @json($trendYear->values()),
  }
};

const trendCtx = document.getElementById('trendLine');
const grad = trendCtx.getContext('2d').createLinearGradient(0,0,0,160);
grad.addColorStop(0, 'rgba(26,60,143,0.22)');
grad.addColorStop(1, 'rgba(26,60,143,0)');

let currentRange = 'week';

const trendChart = new Chart(trendCtx, {
  type: 'line',
  data: {
    labels: trendDatasets.week.labels,
    datasets: [{
      data: trendDatasets.week.data,
      borderColor: '#1A3C8F',
      backgroundColor: grad,
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: '#fff',
      pointBorderColor: '#1A3C8F',
      pointBorderWidth: 2,
      pointHoverRadius: 6,
      borderWidth: 2.5
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#EEF0F6' }, ticks: { color: '#6B7385', font: { size: 11.5 } } },
      x: { grid: { display: false }, ticks: { color: '#6B7385', font: { size: 11.5 } } }
    }
  }
});

const tfTabs = document.getElementById('tfTabs');
const trendSub = document.getElementById('trendSub');

function renderTrend(range) {
  const dataset = trendDatasets[range];
  trendChart.data.labels = dataset.labels;
  trendChart.data.datasets[0].data = dataset.data;
  trendChart.update();
  trendSub.textContent = dataset.sub;
}

tfTabs.querySelectorAll('.tf-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    tfTabs.querySelectorAll('.tf-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentRange = btn.dataset.range;
    renderTrend(currentRange);
  });
});

renderTrend(currentRange);
</script>
@include('partials.sos-alert-overlay')</body>
</html>