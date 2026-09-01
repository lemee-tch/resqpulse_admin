<div id="sosOverlay" class="sos-overlay" style="display:none;">
    <div class="sos-overlay-inner" onclick="goToSosAlert()">
        <div class="sos-ring sos-ring-1"></div>
        <div class="sos-ring sos-ring-2"></div>
        <div class="sos-ring sos-ring-3"></div>
        <div class="sos-core"><i class="bi bi-exclamation-triangle-fill"></i></div>
    </div>
    <div class="sos-overlay-label" onclick="goToSosAlert()">SOS Emergency — showing location (tap to view)</div>
</div>

<audio id="sosAlertAudio" src="{{ asset('sounds/sos-alert.mp3') }}" preload="auto" loop></audio>

<style>
/* Full-viewport red-tinted backdrop. pointer-events:none on the wrapper so
   the dashboard underneath stays fully usable — only the pulsing icon and
   the label below it are actually clickable. */
.sos-overlay {
    position: fixed; inset: 0; z-index: 10000;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px;
    background: radial-gradient(circle at center, rgba(220,38,38,.28) 0%, rgba(220,38,38,.16) 45%, rgba(220,38,38,.08) 75%, rgba(220,38,38,.04) 100%);
    pointer-events: none;
    animation: sosBackdropPulse 2s ease-in-out infinite;
}
@keyframes sosBackdropPulse {
    0%, 100% { background-color: rgba(220,38,38,0); }
    50%      { background-color: rgba(220,38,38,.03); }
}

.sos-overlay-inner {
    position: relative; width: 180px; height: 180px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    pointer-events: auto;
}
.sos-ring {
    position: absolute; border-radius: 50%;
    border: 3px solid rgba(220,38,38,.55);
    animation: sosPulseRing 1.8s ease-out infinite;
}
.sos-ring-1 { width: 180px; height: 180px; }
.sos-ring-2 { width: 180px; height: 180px; animation-delay: .4s; }
.sos-ring-3 { width: 180px; height: 180px; animation-delay: .8s; }
@keyframes sosPulseRing {
    0%   { transform: scale(.5); opacity: 1; }
    100% { transform: scale(1.15); opacity: 0; }
}
.sos-core {
    position: relative; width: 120px; height: 120px; border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #ef4444, #b91c1c);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 32px rgba(220,38,38,.75), 0 10px 26px rgba(0,0,0,.35);
    animation: sosCoreBeat 1s ease-in-out infinite;
}
.sos-core i { color: #fff; font-size: 2.8rem; }
@keyframes sosCoreBeat {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.06); }
}
.sos-overlay-label {
    background: #dc2626; color: #fff; font-family:'Barlow',sans-serif;
    font-weight: 700; font-size: .95rem; padding: 9px 20px;
    border-radius: 30px; box-shadow: 0 6px 18px rgba(0,0,0,.3);
    white-space: nowrap;
    cursor: pointer;
    pointer-events: auto;
}
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

    function show(id) {
        currentLatestId = id;
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

    async function poll() {
        try {
            const res = await fetch("{{ route('sos-alerts.latest') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const latest = data.latest;
            if (latest && !getAck().includes(latest.id)) {
                show(latest.id);
            } else {
                hide();
            }
        } catch (e) { /* silent */ }
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>