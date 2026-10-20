{{--
    A notice in the corner of every admin page, with a short sound, when
    something comes in that MDRRMO has to look at (items 46 and 49).
    partials/sidebar brings it, so every page with the menu has it.

    It asks IncidentController::latestReports() every 15 seconds:
      new     red — a resident's new report (Report Emergency) waiting for
              approval on Pending; from the last day. It used to ring only
              on the Dashboard (the bell). SOS alerts have their own siren
              (partials/sos-alert-overlay).
      review  purple — a team's report waiting on For Review; from the
              last day
      open    amber — an Initial report a team saved before item 49 (not
              sent to MDRRMO yet); from the last 3 hours
    A notice stays, on every page, until the admin opens the incident (its
    page marks it as seen: data-report-seen, see incident-details) or
    closes it (×). It also goes when it's no longer new: the report was
    approved or declined, the team sent a newer one…

    Up to 3 show at a time (2 on a short screen, 1 on a phone). With 2 or
    more, a bar above them says how many and has Hide (they shrink to one
    small button until the next report comes in) and Close all.

    The sound plays once per report, when it comes in. On a first visit in
    a browser, the reports already there show without a sound. What was
    seen is kept in this browser (localStorage), like the SOS alert's.

    It also keeps the purple For Review counts in the menu up to date, and
    shows how many notices are open in the tab's title: "(2) RESQPULSE – …".
--}}
<div class="report-alerts" id="reportAlerts" role="region" aria-label="New reports" aria-live="polite" aria-relevant="additions"></div>
<audio id="reportAlertAudio" src="{{ asset('sounds/notification.mp3') }}" preload="auto"></audio>

<style>
.report-alerts {
    position: fixed; right: 20px; bottom: 20px; z-index: 1090;
    width: 344px; max-width: calc(100vw - 32px);
    display: flex; flex-direction: column-reverse; align-items: stretch; gap: 8px; /* newest nearest the corner */
    pointer-events: none;
    font-family: 'Inter', sans-serif; text-align: left;
}
.report-alert {
    --ra-accent: #7c3aed; --ra-tint: #ede9fe; --ra-ink: #5b21b6;
    pointer-events: auto; position: relative;
    display: flex; gap: 11px; align-items: flex-start;
    background: #fff; border: 1px solid #e5e7eb; border-left: 5px solid var(--ra-accent);
    border-radius: 12px; padding: 11px 12px 11px 11px;
    box-shadow: 0 12px 30px rgba(17, 24, 39, .16), 0 2px 6px rgba(17, 24, 39, .06);
    color: #111827; cursor: pointer;
    animation: raIn .28s ease-out;
}
.report-alert--open { --ra-accent: #f59e0b; --ra-tint: #fef3c7; --ra-ink: #92400e; }
.report-alert--new  { --ra-accent: #dc2626; --ra-tint: #fee2e2; --ra-ink: #991b1b; }
.ra-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    background: var(--ra-tint); color: var(--ra-ink);
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.ra-body { flex: 1; min-width: 0; }
.ra-head { display: flex; align-items: baseline; gap: 6px; padding-right: 22px; white-space: nowrap; }
.ra-title { font-size: .66rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--ra-ink); }
.ra-time { font-size: .7rem; color: #9ca3af; font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.ra-what { font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 1rem; line-height: 1.25; margin-top: 1px; overflow-wrap: anywhere; }
.ra-where { font-size: .78rem; color: #4b5563; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ra-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 7px; }
.ra-from { font-size: .73rem; color: #6b7280; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ra-open {
    flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px;
    background: var(--ra-accent); color: #fff; border: 1.5px solid var(--ra-accent); border-radius: 7px;
    padding: 3px 10px; font-size: .74rem; font-weight: 700; text-decoration: none; white-space: nowrap;
}
.ra-open:hover { color: #fff; filter: brightness(.93); }
.report-alert--open .ra-open { background: #fff; color: var(--ra-ink); }
.report-alert--open .ra-open:hover { background: #fffbeb; color: var(--ra-ink); filter: none; }
.ra-close {
    position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border-radius: 7px;
    background: none; border: none; color: #9ca3af; font-size: 1.15rem; line-height: 1;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.ra-close:hover, .ra-close:focus-visible { background: #f3f4f6; color: #374151; outline: none; }

/* With 2 or more: how many, Hide, Close all. Hidden: one small button. */
.ra-bar, .ra-pill {
    pointer-events: auto; align-self: flex-end;
    display: inline-flex; align-items: center; gap: 4px;
    background: #111827; color: #fff; border-radius: 20px; padding: 5px 6px 5px 13px;
    font-size: .75rem; font-weight: 600; white-space: nowrap;
    box-shadow: 0 8px 20px rgba(17, 24, 39, .22);
}
.ra-bar span { margin-right: 6px; }
.ra-bar button {
    background: rgba(255, 255, 255, .14); color: #fff; border: none; border-radius: 14px;
    padding: 3px 10px; font-size: .72rem; font-weight: 700; cursor: pointer;
}
.ra-bar button:hover, .ra-bar button:focus-visible { background: rgba(255, 255, 255, .28); outline: none; }
.ra-pill { border: none; cursor: pointer; padding: 8px 14px 8px 10px; gap: 8px; font-size: .78rem; animation: raIn .2s ease-out; }
.ra-pill .ra-dot {
    width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    background: #7c3aed; font-size: .72rem;
}
.ra-pill.open-only .ra-dot { background: #f59e0b; }
.ra-pill.has-new .ra-dot { background: #dc2626; }
.ra-pill:hover, .ra-pill:focus-visible { background: #1f2937; outline: none; }

@keyframes raIn {
    from { opacity: 0; transform: translateY(10px) scale(.98); }
    to   { opacity: 1; transform: none; }
}
@media (prefers-reduced-motion: reduce) { .report-alert, .ra-pill { animation: none; } }
/* Above the phone's bottom tab bar. */
@media (max-width: 900px) {
    .report-alerts { bottom: calc(70px + env(safe-area-inset-bottom, 0px)); }
}
@media (max-width: 560px) {
    .report-alerts { left: 12px; right: 12px; width: auto; max-width: none; }
}
</style>

<script>
(function () {
    const POLL_MS  = 15000;
    // How many show at a time: fewer on a short screen or a phone.
    const showMax = () => window.innerWidth <= 560 ? 1 : (window.innerHeight < 820 ? 2 : 3);
    const SEEN_KEY   = 'resqpulse_reports_seen';      // opened or closed
    const RUNG_KEY   = 'resqpulse_reports_rung';      // the sound already played for it
    const LAST_KEY   = 'resqpulse_reports_polled_at'; // the last time a page here asked
    const HIDDEN_KEY = 'resqpulse_reports_hidden';    // Hide was pressed

    const box   = document.getElementById('reportAlerts');
    const audio = document.getElementById('reportAlertAudio');
    if (!box || !window.fetch) return;

    const baseTitle = document.title;
    let reports = [];      // what the server said last time
    let clockOffset = 0;   // server clock − this computer's clock, in ms

    // Kept in this browser; in this page only if the browser won't keep it
    // (a private window…), so closing a notice still works.
    const memory = {};
    function read(key) {
        try {
            const value = localStorage.getItem(key);
            if (value !== null) return value;
        } catch (e) {}
        return memory[key] ?? null;
    }
    function write(key, value) {
        memory[key] = value;
        try {
            if (value === null) localStorage.removeItem(key);
            else localStorage.setItem(key, value);
        } catch (e) {}
    }
    function load(key) {
        try {
            const list = JSON.parse(read(key) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) { return []; }
    }
    function remember(key, ids) {
        const list = load(key);
        ids.forEach(id => { if (!list.includes(id)) list.push(id); });
        write(key, JSON.stringify(list.slice(-150)));
    }

    function escapeHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function timeAgo(at) {
        const seconds = Math.max(0, Math.floor((Date.now() + clockOffset) / 1000 - at));
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' min ago';
        const hours = Math.floor(minutes / 60);
        return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    }
    const plural = n => n === 1 ? '1 new report' : n + ' new reports';

    // ── The sound: browsers only play it after a click or key press on
    // the page, so it's unlocked silently on the first one. ──
    let unlocked = false;
    function unlock() {
        if (unlocked || !audio) return;
        audio.muted = true;
        audio.play().then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            unlocked = true;
        }).catch(() => { audio.muted = false; });
    }
    ['click', 'keydown', 'touchstart'].forEach(type => document.addEventListener(type, unlock, { passive: true }));
    function ring() {
        if (!audio) return;
        audio.muted = false;
        audio.volume = 0.8;
        audio.currentTime = 0;
        audio.play().catch(() => {});
    }

    // ── The notices: new (a resident's report), review (a team's report on
    // For Review), open (an Initial report saved before item 49) ──
    const ICONS = { new: 'bi-megaphone-fill', review: 'bi-clipboard2-check-fill', open: 'bi-file-earmark-text-fill' };
    const kindOf = r => (ICONS[r.kind] ? r.kind : 'review');
    function card(r) {
        const el = document.createElement('div');
        el.className = 'report-alert report-alert--' + kindOf(r);
        el.dataset.key = r.key;
        el.innerHTML =
            '<div class="ra-icon"><i class="bi ' + ICONS[kindOf(r)] + '"></i></div>' +
            '<div class="ra-body">' +
                '<div class="ra-head"><span class="ra-title">' + escapeHtml(r.title) + '</span><span class="ra-time">· ' + timeAgo(r.at) + '</span></div>' +
                '<div class="ra-what">' + escapeHtml(r.what) + '</div>' +
                '<div class="ra-where" title="' + escapeHtml(r.where) + '">' + escapeHtml(r.where) + '</div>' +
                '<div class="ra-foot">' +
                    '<span class="ra-from">From ' + escapeHtml(r.from) + '</span>' +
                    '<a class="ra-open" href="' + escapeHtml(r.url) + '">' + escapeHtml(r.action) + ' <i class="bi bi-arrow-right"></i></a>' +
                '</div>' +
            '</div>' +
            '<button type="button" class="ra-close" aria-label="Close" title="Close">&times;</button>';

        // The whole notice opens the incident; × only closes it.
        el.addEventListener('click', e => {
            remember(SEEN_KEY, [r.key]);
            if (e.target.closest('.ra-close')) {
                render();
                return;
            }
            const link = e.target.closest('a');
            if (link && (e.ctrlKey || e.metaKey || e.shiftKey)) {
                render(); // opening in a new tab: let the browser do it
                return;
            }
            e.preventDefault();
            window.location.href = r.url;
        });
        return el;
    }

    // Made once and kept, so they don't flash at every check.
    let barEl = null;
    let pillEl = null;
    function bar(count) {
        if (!barEl) {
            barEl = document.createElement('div');
            barEl.className = 'ra-bar';
            barEl.innerHTML = '<span></span>' +
                '<button type="button" data-do="hide">Hide</button>' +
                '<button type="button" data-do="close">Close all</button>';
            barEl.addEventListener('click', e => {
                const what = e.target.closest('button')?.dataset.do;
                if (what === 'hide') write(HIDDEN_KEY, '1');
                if (what === 'close') remember(SEEN_KEY, reports.map(r => r.key));
                if (what) render();
            });
        }
        barEl.querySelector('span').textContent = plural(count);
        return barEl;
    }
    function pill(open) {
        if (!pillEl) {
            pillEl = document.createElement('button');
            pillEl.type = 'button';
            pillEl.title = 'Show the new reports';
            pillEl.innerHTML = '<span class="ra-dot"><i class="bi bi-bell-fill"></i></span><span class="ra-count"></span><i class="bi bi-chevron-up"></i>';
            pillEl.addEventListener('click', () => { write(HIDDEN_KEY, null); render(); });
        }
        // The dot: red with a resident's new report, purple with a report to
        // review, amber otherwise.
        const kinds = open.map(kindOf);
        pillEl.className = 'ra-pill' + (kinds.includes('new') ? ' has-new' : (kinds.includes('review') ? '' : ' open-only'));
        pillEl.querySelector('.ra-count').textContent = plural(open.length);
        return pillEl;
    }
    function putLast(el) {
        if (box.lastElementChild !== el) box.appendChild(el);
    }

    function render() {
        const seen = load(SEEN_KEY);
        const open = reports.filter(r => !seen.includes(r.key));
        const hidden = read(HIDDEN_KEY) === '1' && open.length > 0;

        const shown = hidden ? [] : open.slice(0, showMax());
        const keep = shown.map(r => r.key);
        box.querySelectorAll('.report-alert').forEach(el => {
            if (!keep.includes(el.dataset.key)) el.remove();
        });
        // Newest first in the page = nearest the corner (column-reverse).
        shown.forEach((r, i) => {
            let el = box.querySelector('.report-alert[data-key="' + r.key + '"]');
            if (el) {
                el.querySelector('.ra-time').textContent = '· ' + timeAgo(r.at);
            } else {
                el = card(r);
            }
            if (box.children[i] !== el) box.insertBefore(el, box.children[i] || null);
        });

        if (hidden) {
            barEl?.remove();
            putLast(pill(open));
        } else {
            pillEl?.remove();
            if (open.length > 1) putLast(bar(open.length));
            else barEl?.remove();
        }

        document.title = open.length ? '(' + open.length + ') ' + baseTitle : baseTitle;
    }

    // ── The purple For Review counts in the menu (partials/sidebar) ──
    function updateCounts(forReview) {
        if (!forReview) return;
        document.querySelectorAll('[data-review-count]').forEach(el => {
            const n = Number(forReview[el.dataset.reviewCount] || 0);
            el.hidden = n === 0;
            el.querySelector('span').textContent = n;
            el.title = n === 1 ? '1 report waiting for your review' : n + ' reports waiting for your review';
        });
    }
    document.querySelectorAll('[data-review-count]').forEach(el => {
        el.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = el.dataset.href;
        });
    });

    async function poll() {
        try {
            const res = await fetch("{{ route('notifications.reports') }}", {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!Array.isArray(data.reports)) return;

            reports = data.reports;
            clockOffset = data.now * 1000 - Date.now();
            updateCounts(data.forReview);

            // The sound: once per report, only for one that came in since a
            // page in this browser last asked (not the ones already there
            // on a first visit). A new one also brings hidden notices back.
            const seen = load(SEEN_KEY);
            const rung = load(RUNG_KEY);
            const fresh = reports.filter(r => !seen.includes(r.key) && !rung.includes(r.key));
            const lastAsked = Number(read(LAST_KEY) || 0);
            if (fresh.length) {
                remember(RUNG_KEY, fresh.map(r => r.key));
                if (lastAsked && fresh.some(r => r.at >= lastAsked)) {
                    write(HIDDEN_KEY, null);
                    ring();
                }
            }
            write(LAST_KEY, String(data.now));

            render();
        } catch (e) { /* the next poll tries again */ }
    }

    function start() {
        // The incident page that's open shows its report: it counts as seen.
        const here = Array.from(document.querySelectorAll('[data-report-seen]')).map(el => el.dataset.reportSeen);
        if (here.length) remember(SEEN_KEY, here);

        poll();
        setInterval(poll, POLL_MS);
    }

    // Closed, opened or hidden in another tab: the same here.
    window.addEventListener('storage', e => {
        if (e.key === SEEN_KEY || e.key === HIDDEN_KEY) render();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
