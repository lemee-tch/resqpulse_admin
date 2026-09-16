<div id="sosOverlay" class="sos-overlay" style="display:none;">
    <div class="sos-overlay-inner" onclick="goToSosAlert()">
        <div class="sos-ring sos-ring-1"></div>
        <div class="sos-ring sos-ring-2"></div>
        <div class="sos-ring sos-ring-3"></div>
        <div class="sos-core"><i class="bi bi-exclamation-triangle-fill"></i></div>
    </div>

    <div class="sos-info-card">
        <div class="sos-info-header">
            <span class="sos-info-title">SOS Emergency</span>
            <span class="sos-info-count" id="sosCount" style="display:none;"></span>
        </div>
        <div class="sos-info-location" id="sosLocation">Locating…</div>
        <div class="sos-info-meta" id="sosMeta"></div>
        <div class="sos-info-actions">
            <button type="button" class="sos-btn sos-btn-primary" onclick="goToSosAlert()">
                <i class="bi bi-geo-alt-fill"></i> View Alert
            </button>
            <button type="button" class="sos-btn sos-btn-ghost" onclick="dismissSosAlert()">
                Dismiss
            </button>
        </div>
    </div>
</div>

<audio id="sosAlertAudio" src="{{ asset('sounds/sos-alert.mp3') }}" preload="auto" loop></audio>

<style>
/* Full-viewport red-tinted backdrop. pointer-events:none on the wrapper so
   the dashboard underneath stays fully usable — only the pulsing icon and
   the info card below it are actually clickable. */
.sos-overlay {
    position: fixed; inset: 0; z-index: 10000;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px;
    background: radial-gradient(circle at center, rgba(220,38,38,.28) 0%, rgba(220,38,38,.16) 45%, rgba(220,38,38,.08) 75%, rgba(220,38,38,.04) 100%);
    pointer-events: none;
    animation: sosBackdropPulse 2s ease-in-out infinite;
    padding: 20px;
}
@keyframes sosBackdropPulse {
    0%, 100% { background-color: rgba(220,38,38,0); }
    50%      { background-color: rgba(220,38,38,.03); }
}

.sos-overlay-inner {
    position: relative; width: 150px; height: 150px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    pointer-events: auto;
    flex-shrink: 0;
}
.sos-ring {
    position: absolute; border-radius: 50%;
    border: 3px solid rgba(220,38,38,.55);
    animation: sosPulseRing 1.8s ease-out infinite;
}
.sos-ring-1 { width: 150px; height: 150px; }
.sos-ring-2 { width: 150px; height: 150px; animation-delay: .4s; }
.sos-ring-3 { width: 150px; height: 150px; animation-delay: .8s; }
@keyframes sosPulseRing {
    0%   { transform: scale(.5); opacity: 1; }
    100% { transform: scale(1.15); opacity: 0; }
}
.sos-core {
    position: relative; width: 100px; height: 100px; border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #ef4444, #b91c1c);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 32px rgba(220,38,38,.75), 0 10px 26px rgba(0,0,0,.35);
    animation: sosCoreBeat 1s ease-in-out infinite;
}
.sos-core i { color: #fff; font-size: 2.3rem; }
@keyframes sosCoreBeat {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.06); }
}

/* Info card — this is the actual new content: what/where/when, instead
   of a bare "tap to view" label with zero context. */
.sos-info-card {
    pointer-events: auto;
    background: #fff;
    border-radius: 18px;
    padding: 18px 22px;
    width: 100%;
    max-width: 340px;
    box-shadow: 0 16px 40px rgba(0,0,0,.35);
    font-family: 'Barlow', sans-serif;
    text-align: center;
}
.sos-info-header {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-bottom: 6px;
}
.sos-info-title {
    color: #b91c1c; font-weight: 800; font-size: 1.05rem; letter-spacing: .3px;
}
.sos-info-count {
    background: #dc2626; color: #fff; font-size: .7rem; font-weight: 800;
    padding: 2px 9px; border-radius: 20px;
}
.sos-info-location {
    color: #1f2937; font-weight: 700; font-size: .92rem;
    margin-bottom: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sos-info-meta {
    color: #9ca3af; font-size: .78rem; font-weight: 600;
    margin-bottom: 16px;
}
.sos-info-actions {
    display: flex; gap: 10px;
}
.sos-btn {
    flex: 1; border: none; border-radius: 10px; padding: 11px 0;
    font-family: 'Barlow', sans-serif; font-weight: 700; font-size: .85rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: transform .12s ease, opacity .12s ease;
}
.sos-btn:active { transform: scale(.97); }
.sos-btn-primary { background: #dc2626; color: #fff; }
.sos-btn-primary:hover { background: #b91c1c; }
.sos-btn-ghost { background: #f3f4f6; color: #4b5563; }
.sos-btn-ghost:hover { background: #e5e7eb; }
</style>

<script>
(function () {
    const STORAGE_KEY = 'resqpulse_ack_sos_ids';
    const POLL_MS = 6000;
    let currentLatestId = null;

    function getAck() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
    }
    function ack(id) {
        const ids = getAck();
        if (!ids.includes(id)) {
            ids.push(id);
            while (ids.length > 50) ids.shift();
            localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        }
    }

    const overlay = document.getElementById('sosOverlay');
    const audio = document.getElementById('sosAlertAudio');
    const locationEl = document.getElementById('sosLocation');
    const metaEl = document.getElementById('sosMeta');
    const countEl = document.getElementById('sosCount');

    /** "2 min ago" / "just now" — no library, keeps this partial self-contained. */
    function timeAgo(isoString) {
        if (!isoString) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - new Date(isoString).getTime()) / 1000));
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' min ago';
        const hours = Math.floor(minutes / 60);
        return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    }

    function show(latest, pendingCount) {
        currentLatestId = latest.id;

        locationEl.textContent = latest.location || 'Location unavailable';
        metaEl.textContent = timeAgo(latest.created_at);

        if (pendingCount > 1) {
            countEl.textContent = pendingCount + ' pending';
            countEl.style.display = 'inline-block';
        } else {
            countEl.style.display = 'none';
        }

        if (overlay.style.display === 'none') {
            overlay.style.display = 'flex';
            audio.currentTime = 0;
            audio.play().catch(() => {}); // blocked until first user click on the page — normal browser behavior
        }
    }
    function hide() {
        overlay.style.display = 'none';
        audio.pause();
        currentLatestId = null;
    }

    window.goToSosAlert = function () {
        if (currentLatestId) ack(currentLatestId);
        hide();
        window.location.href = "{{ route('sos-alerts') }}?highlight=" + currentLatestId;
    };

    /** Silences the alarm without navigating away — e.g. the admin is
     * already on the phone with the reporter and doesn't need the SOS
     * Alerts page pulled up too. It stays 'pending' server-side and
     * still shows up in the normal SOS Alerts list; this only stops
     * THIS overlay from re-triggering for it on future polls. */
    window.dismissSosAlert = function () {
        if (currentLatestId) ack(currentLatestId);
        hide();
    };

    async function poll() {
        try {
            const res = await fetch("{{ route('sos-alerts.latest') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const latest = data.latest;
            if (latest && !getAck().includes(latest.id)) {
                show(latest, data.pendingCount || 1);
            } else {
                hide();
            }
        } catch (e) { /* silent */ }
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>