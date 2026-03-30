<?php require_once 'config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_NAME; ?> — trusted care delivery engineered for global reliability. Patient, doctor and admin portals with live system health monitoring.">
    <title><?php echo SITE_NAME; ?> | Connected Care Network</title>
    <style>
        :root {
            --bg-deep: #08131f;
            --bg-mid: #10253a;
            --text: #ebf3ff;
            --muted: #9db0c7;
            --surface: rgba(255,255,255,0.07);
            --surface-border: rgba(255,255,255,0.16);
            --ok: #38d39f;
            --warn: #ffcb66;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 500px at 10% -10%, rgba(0,179,134,0.28), transparent 60%),
                radial-gradient(900px 450px at 100% 0%, rgba(255,127,80,0.18), transparent 60%),
                linear-gradient(160deg, var(--bg-deep), var(--bg-mid));
            background-attachment: fixed;
        }
        .page { max-width: 1180px; margin: 0 auto; padding: 2rem 1rem 4rem; }

        /* ── Top bar ── */
        .topbar {
            display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem;
            align-items: center; margin-bottom: 2rem; animation: reveal 700ms ease-out;
        }
        .brand { font-family: 'Fraunces', serif; font-size: 1.25rem; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 0.45rem;
            border: 1px solid var(--surface-border); border-radius: 999px;
            padding: 0.35rem 0.85rem; background: var(--surface); font-size: 0.88rem;
        }
        .dot {
            width: 8px; height: 8px; border-radius: 999px; background: var(--warn);
            animation: pulse-warn 1.6s infinite;
        }
        .dot.ok { background: var(--ok); animation: pulse-ok 1.6s infinite; }

        /* ── Hero ── */
        .hero {
            border: 1px solid var(--surface-border); border-radius: 22px;
            background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            padding: 2.2rem; box-shadow: 0 22px 60px rgba(0,0,0,0.25);
            animation: reveal 950ms ease-out;
        }
        .hero h1 {
            margin: 0 0 0.7rem;
            font-family: 'Fraunces', serif;
            font-size: clamp(1.9rem, 4vw, 3rem); line-height: 1.12;
        }
        .hero > p { margin: 0; color: var(--muted); max-width: 780px; font-size: 1.02rem; line-height: 1.7; }

        /* ── Actions ── */
        .actions { margin-top: 1.6rem; display: flex; flex-wrap: wrap; gap: 0.7rem; }
        .btn {
            text-decoration: none; color: #08131f; background: #fff;
            border-radius: 12px; padding: 0.72rem 1.1rem; font-weight: 700; font-size: 0.95rem;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0,0,0,0.22); }
        .btn.primary   { background: linear-gradient(90deg,#00d49f,#46f0c4); }
        .btn.secondary { background: linear-gradient(90deg,#c8d8ff,#a5b8ff); }
        .btn.doctor    { background: linear-gradient(90deg,#a8ffba,#68f08a); }
        .btn.admin     { background: linear-gradient(90deg,#ffd17f,#ff9e68); }
        .btn.accent    { background: linear-gradient(90deg,#d9b4fe,#a78bfa); }

        /* ── Metrics strip ── */
        .metrics {
            margin-top: 1.4rem;
            display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 0.8rem;
        }
        .metric {
            border: 1px solid var(--surface-border); border-radius: 14px;
            background: var(--surface); padding: 0.9rem 1rem;
            animation: reveal 1050ms ease-out;
        }
        .metric .label { font-size: 0.82rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
        .metric .value { font-size: 1.08rem; font-weight: 800; margin-top: 0.3rem; }

        /* ── Two-column grid below metrics ── */
        .grid { margin-top: 1.2rem; display: grid; grid-template-columns: 2fr 1fr; gap: 0.9rem; }
        .panel { border: 1px solid var(--surface-border); border-radius: 16px; background: var(--surface); padding: 1.1rem 1.3rem; }
        .panel h2 { margin: 0 0 0.7rem; font-size: 1.05rem; }
        .feature-list { margin: 0; padding-left: 1.1rem; color: var(--muted); line-height: 1.75; font-size: 0.97rem; }
        .time-row {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px dashed rgba(255,255,255,0.13); padding: 0.48rem 0; font-size: 0.93rem;
        }
        .time-row:last-child { border-bottom: 0; }
        .time-row span { color: var(--muted); }

        /* ── Footer note ── */
        .foot { margin-top: 1.1rem; color: var(--muted); font-size: 0.93rem; }
        .foot strong { color: var(--text); }

        /* ── Keyframes ── */
        @keyframes pulse-warn {
            0%,100% { box-shadow: 0 0 0 0 rgba(255,203,102,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(255,203,102,0); }
        }
        @keyframes pulse-ok {
            0%,100% { box-shadow: 0 0 0 0 rgba(56,211,159,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(56,211,159,0); }
        }
        @keyframes reveal {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .metrics { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .grid    { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .hero { padding: 1.3rem; }
            .actions { flex-direction: column; }
            .btn { text-align: center; }
        }
    </style>
</head>
<body>
<main class="page">

    <!-- Top bar -->
    <header class="topbar">
        <div class="brand"><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="status-pill" id="statusPill" role="status" aria-live="polite">
            <span class="dot" id="statusDot"></span>
            <span id="statusText">Checking live service status&hellip;</span>
        </div>
    </header>

    <!-- Hero card -->
    <section class="hero">
        <h1>Trusted care delivery, engineered for global reliability.</h1>
        <p>
            A unified care platform for patients, doctors, and administrators &mdash; with live system health
            monitoring, bilingual readiness, and always-on access designed for regional and cross-border
            healthcare operations.
        </p>

        <nav class="actions" aria-label="Portal access">
            <a href="patient/login.php"    class="btn primary">Patient Sign In</a>
            <a href="patient/register.php" class="btn secondary">Create Patient Account</a>
            <a href="doctor/login.php"     class="btn doctor">Doctor Sign In</a>
            <a href="admin/login.php"      class="btn admin">Admin Sign In</a>
            <a href="accreditation.php"    class="btn accent">Accreditation Centre</a>
        </nav>

        <!-- Live metrics strip -->
        <div class="metrics" role="list">
            <article class="metric" role="listitem">
                <div class="label">Platform Status</div>
                <div class="value" id="metricStatus">Syncing&hellip;</div>
            </article>
            <article class="metric" role="listitem">
                <div class="label">Languages</div>
                <div class="value">English &amp; Français</div>
            </article>
            <article class="metric" role="listitem">
                <div class="label">Payment Hotline</div>
                <div class="value"><?php echo htmlspecialchars(PAYMENT_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
            </article>
            <article class="metric" role="listitem">
                <div class="label">Customer Service</div>
                <div class="value"><?php echo htmlspecialchars(CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
            </article>
        </div>

        <!-- Info panels -->
        <div class="grid">
            <section class="panel">
                <h2>Worldwide Reliability Profile</h2>
                <ul class="feature-list">
                    <li>Live database heartbeat through a dedicated API health endpoint.</li>
                    <li>Role-based access controls for patient, doctor, and admin portals.</li>
                    <li>Cross-timezone operational visibility for distributed care teams.</li>
                    <li>Structured accreditation workflow supporting compliance programs.</li>
                    <li>Secure, escaped output and server-side error logging throughout.</li>
                    <li>Pharmacy inventory, lab reports, AI suggestions, and audit logs built in.</li>
                </ul>
            </section>
            <aside class="panel">
                <h2>Global Clock</h2>
                <div class="time-row"><span>Douala (WAT)</span><strong id="timeDouala">--:--</strong></div>
                <div class="time-row"><span>UTC</span><strong id="timeUtc">--:--</strong></div>
                <div class="time-row"><span>New York (ET)</span><strong id="timeNy">--:--</strong></div>
                <div class="time-row"><span>London (GMT/BST)</span><strong id="timeLondon">--:--</strong></div>
                <div class="time-row"><span>Tokyo (JST)</span><strong id="timeTokyo">--:--</strong></div>
            </aside>
        </div>

        <p class="foot">
            Health endpoint: <strong>/api/health.php</strong> &nbsp;&middot;&nbsp;
            Last checked: <span id="healthTimestamp">pending&hellip;</span>
        </p>
    </section>

</main>

<script>
(function () {
    'use strict';

    /* ── Live health check ── */
    async function loadHealth() {
        const dot        = document.getElementById('statusDot');
        const text       = document.getElementById('statusText');
        const metricEl   = document.getElementById('metricStatus');
        const tsEl       = document.getElementById('healthTimestamp');

        try {
            const res     = await fetch('api/health.php', { cache: 'no-store' });
            const payload = await res.json();
            const ok      = payload.status === 'operational';

            dot.classList.toggle('ok', ok);
            text.textContent      = ok ? 'All core services operational'
                                       : 'Service degraded — monitoring in progress';
            metricEl.textContent  = ok ? 'Operational' : 'Degraded';
            tsEl.textContent      = payload.generated_at || new Date().toISOString();
        } catch (_) {
            text.textContent     = 'Status unavailable — retrying automatically';
            metricEl.textContent = 'Unknown';
            tsEl.textContent     = new Date().toISOString();
        }
    }

    /* ── Live global clock ── */
    const zones = {
        timeDouala : 'Africa/Douala',
        timeUtc    : 'UTC',
        timeNy     : 'America/New_York',
        timeLondon : 'Europe/London',
        timeTokyo  : 'Asia/Tokyo',
    };
    const fmts = {};
    for (const [id, tz] of Object.entries(zones)) {
        fmts[id] = new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: tz
        });
    }

    function updateClock() {
        const now = new Date();
        for (const [id, fmt] of Object.entries(fmts)) {
            const el = document.getElementById(id);
            if (el) el.textContent = fmt.format(now);
        }
    }

    loadHealth();
    updateClock();
    setInterval(loadHealth, 60000);
    setInterval(updateClock, 1000);
}());
</script>
</body>
</html>