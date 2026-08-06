<div id="sosOverlay" class="sos-overlay" style="display:none;">
    <div class="sos-overlay-inner" onclick="goToSosAlert()">
        <div class="sos-ring sos-ring-1"></div>
        <div class="sos-ring sos-ring-2"></div>
        <div class="sos-ring sos-ring-3"></div>
        <div class="sos-core"><i class="bi bi-exclamation-triangle-fill"></i></div>
    </div>
    <div class="sos-overlay-label">SOS Emergency — tap to view</div>
</div>

<audio id="sosAlertAudio" src="{{ asset('sounds/sos-alert.mp3') }}" preload="auto" loop></audio>

<style>
.sos-overlay {
    position: fixed; right: 30px; bottom: 30px; z-index: 10000;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.sos-overlay-inner {
    position: relative; width: 140px; height: 140px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
}
.sos-ring {
    position: absolute; border-radius: 50%;
    border: 3px solid rgba(220,38,38,.55);
    animation: sosPulseRing 1.8s ease-out infinite;
}
.sos-ring-1 { width: 140px; height: 140px; }
.sos-ring-2 { width: 140px; height: 140px; animation-delay: .4s; }
.sos-ring-3 { width: 140px; height: 140px; animation-delay: .8s; }
@keyframes sosPulseRing {
    0%   { transform: scale(.5); opacity: 1; }
    100% { transform: scale(1.15); opacity: 0; }
}
.sos-core {
    position: relative; width: 90px; height: 90px; border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #ef4444, #b91c1c);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 24px rgba(220,38,38,.7), 0 8px 20px rgba(0,0,0,.3);
    animation: sosCoreBeat 1s ease-in-out infinite;
}
.sos-core i { color: #fff; font-size: 2.1rem; }
@keyframes sosCoreBeat {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.06); }
}
.sos-overlay-label {
    background: #dc2626; color: #fff; font-family:'Barlow',sans-serif;
    font-weight: 700; font-size: .78rem; padding: 6px 14px;
    border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,.25);
    white-space: nowrap;
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