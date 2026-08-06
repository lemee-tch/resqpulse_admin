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
            width: 170px;
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
.main{margin-left:240px;padding:32px 36px 48px;}
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
.btn-export{
  background:var(--primary);color:#fff;border:none;border-radius:10px;
  padding:9px 18px;font-weight:700;font-size:13.5px;
  display:flex;align-items:center;gap:8px;
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
<aside class="sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Logo">
        <div class="sidebar-brand-text">
            <div class="title">MDRRMO</div>
            <div class="sub">ADMIN PANEL</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('incident') }}">Incidents</a>
        <a href="{{ route('sos-alerts') }}">
            <i class="bi bi-exclamation-octagon-fill"></i> SOS Alerts
            @if(($pendingSosCount ?? 0) > 0)
                <span style="background:#fff;color:#dc2626;font-size:.65rem;font-weight:800;padding:1px 7px;border-radius:20px;margin-left:6px;">{{ $pendingSosCount }}</span>
            @endif
        </a>
        <a href="{{ route('mapview') }}">Map View</a>
        <a href="{{ route('alerts') }}">Alerts &amp; Broadcast</a>
        <a href="{{ route('evacuation') }}">Evacuation Centers</a>
        <a href="{{ route('citizen-verification') }}">Citizen Verification</a>
        <a href="{{ route('reports-analytics') }}" class="active">Reports &amp; Analytics</a>
        <a href="{{ route('users') }}">User</a>
        <a href="#">Settings</a>
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
      <div class="date-pill"><i class="bi bi-calendar3"></i> May 21, 2025 – May 28, 2025 <i class="bi bi-chevron-down" style="font-size:11px;"></i></div>
      <button class="btn-export"><i class="bi bi-download"></i> Export Report</button>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Total Incidents</span>
        <span class="stat-icon" style="background:var(--primary-soft);color:var(--primary);"><i class="bi bi-clipboard2-pulse"></i></span>
      </div>
      <div class="stat-value">128</div>
      <span class="trend up"><i class="bi bi-arrow-up-short"></i>12%<span class="trend-note">&nbsp;from last week</span></span>
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Resolved</span>
        <span class="stat-icon" style="background:#E7F7EE;color:var(--up);"><i class="bi bi-check2-circle"></i></span>
      </div>
      <div class="stat-value">60</div>
      <span class="trend up"><i class="bi bi-arrow-up-short"></i>10%<span class="trend-note">&nbsp;from last week</span></span>
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Response Time (Avg)</span>
        <span class="stat-icon" style="background:#FFF1E8;color:var(--fire);"><i class="bi bi-stopwatch"></i></span>
      </div>
      <div class="stat-value">18m</div>
      <span class="trend up"><i class="bi bi-arrow-up-short"></i>5<span class="trend-note">&nbsp;from last week</span></span>
    </div>
    <div class="card stat-card">
      <div class="stat-top">
        <span class="stat-label">Satisfaction Rate</span>
        <span class="stat-icon" style="background:#EAF2FF;color:var(--flood);"><i class="bi bi-emoji-smile"></i></span>
      </div>
      <div class="stat-value">92%</div>
      <span class="trend up"><i class="bi bi-arrow-up-short"></i>5%<span class="trend-note">&nbsp;from last week</span></span>
    </div>
  </div>

  <!-- Pie chart + Location bar chart -->
  <div class="row-grid">
    <div class="card panel">
      <div class="panel-head">
        <div>
          <div class="panel-title">Incidents by Type</div>
          <div class="panel-sub">Breakdown of 113 classified reports</div>
        </div>
      </div>
      <div class="donut-wrap">
        <div style="width:190px;height:190px;flex-shrink:0;">
          <canvas id="typePie" width="190" height="190"></canvas>
        </div>
        <div class="legend-list">
          <div class="legend-row"><span class="legend-dot" style="background:var(--flood);"></span><span class="legend-name">Flood</span><span class="legend-pct">25%</span></div>
          <div class="legend-row"><span class="legend-dot" style="background:var(--fire);"></span><span class="legend-name">Fire</span><span class="legend-pct">25%</span></div>
          <div class="legend-row"><span class="legend-dot" style="background:var(--accident);"></span><span class="legend-name">Accident</span><span class="legend-pct">25%</span></div>
          <div class="legend-row"><span class="legend-dot" style="background:var(--others);"></span><span class="legend-name">Others</span><span class="legend-pct">25%</span></div>
        </div>
      </div>
    </div>

    <div class="card panel">
      <div class="panel-head">
        <div>
          <div class="panel-title">Incidents by Location</div>
          <div class="panel-sub">Top 5 barangays this period</div>
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
        <div class="panel-sub" id="trendSub">Daily volume, May 22 – May 28</div>
      </div>
      <div class="time-filter">
        <select class="tf-picker" id="tfPickerValue"></select>
        <div class="tf-tabs" id="tfTabs">
          <button type="button" class="tf-tab" data-range="day">Day</button>
          <button type="button" class="tf-tab active" data-range="month">Month</button>
          <button type="button" class="tf-tab" data-range="year">Year</button>
        </div>
      </div>
    </div>
    <canvas id="trendLine" height="90"></canvas>
  </div>

</main>

<script>
// ── Incidents by Type — Pie Chart ──────────────────────────────────────
const typeCtx = document.getElementById('typePie');
new Chart(typeCtx, {
  type: 'pie',
  data: {
    labels: ['Flood','Fire','Accident','Others'],
    datasets: [{
      data: [25,25,25,25],
      backgroundColor: ['#2F6FED','#F0703A','#E63946','#C9CDD6'],
      borderWidth: 3,
      borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + c.raw + '%' } } }
  }
});

// ── Incidents by Location — Bar Chart ──────────────────────────────────
const locCtx = document.getElementById('locationBar');
new Chart(locCtx, {
  type: 'bar',
  data: {
    labels: ['Poblacion', 'San Vicente', 'Malico', 'Others'],
    datasets: [{
      label: 'Incidents',
      data: [45, 25, 15, 28],
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
      tooltip: { callbacks: { label: c => c.raw + ' incidents' } }
    },
    scales: {
      y: { beginAtZero: true, grid: { color: '#EEF0F6' }, ticks: { color: '#6B7385', font: { size: 11.5 } } },
      x: { grid: { display: false }, ticks: { color: '#6B7385', font: { size: 11.5 } } }
    }
  }
});

// ── Incidents over Time — Line Chart with Day / Month / Year filter ────

// Demo datasets. Replace with real data from the Incident model —
// e.g. group by hour/day/month depending on the selected range.
const trendDatasets = {
  day: {
    label: (val) => `Hourly volume, ${val}`,
    labels: ['12am','2am','4am','6am','8am','10am','12pm','2pm','4pm','6pm','8pm','10pm'],
    data:   [1, 0, 1, 2, 4, 6, 8, 7, 9, 6, 3, 2],
  },
  month: {
    label: (val) => `Daily volume, ${val}`,
    labels: ['Wk 1','Wk 2','Wk 3','Wk 4','Wk 5'],
    data:   [22, 30, 18, 26, 12],
  },
  year: {
    label: (val) => `Monthly volume, ${val}`,
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    data:   [40, 35, 52, 48, 60, 55, 62, 58, 45, 50, 47, 53],
  }
};

const trendCtx = document.getElementById('trendLine');
const grad = trendCtx.getContext('2d').createLinearGradient(0,0,0,160);
grad.addColorStop(0, 'rgba(26,60,143,0.22)');
grad.addColorStop(1, 'rgba(26,60,143,0)');

let currentRange = 'month';

const trendChart = new Chart(trendCtx, {
  type: 'line',
  data: {
    labels: trendDatasets.month.labels,
    datasets: [{
      data: trendDatasets.month.data,
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

// ── Picker options per range (Day → specific dates, Month → months, Year → years) ──
const pickerOptions = {
  day:   ['May 28, 2025', 'May 27, 2025', 'May 26, 2025', 'May 25, 2025'],
  month: ['May 2025', 'April 2025', 'March 2025', 'February 2025'],
  year:  ['2025', '2024', '2023'],
};

const tfPicker = document.getElementById('tfPickerValue');
const tfTabs = document.getElementById('tfTabs');
const trendSub = document.getElementById('trendSub');

function populatePicker(range) {
  tfPicker.innerHTML = '';
  pickerOptions[range].forEach(opt => {
    const el = document.createElement('option');
    el.value = opt;
    el.textContent = opt;
    tfPicker.appendChild(el);
  });
}

function renderTrend(range) {
  const dataset = trendDatasets[range];
  const pickerVal = tfPicker.value || pickerOptions[range][0];

  trendChart.data.labels = dataset.labels;
  trendChart.data.datasets[0].data = dataset.data;
  trendChart.update();

  trendSub.textContent = dataset.label(pickerVal);
}

tfTabs.querySelectorAll('.tf-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    tfTabs.querySelectorAll('.tf-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentRange = btn.dataset.range;
    populatePicker(currentRange);
    renderTrend(currentRange);
  });
});

tfPicker.addEventListener('change', () => renderTrend(currentRange));

// Initial state
populatePicker(currentRange);
renderTrend(currentRange);
</script>
@include('partials.sos-alert-overlay')
</body>
</html>