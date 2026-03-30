<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> | Connected Care Network</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #08131f;
            --bg-mid: #10253a;
            --text: #ebf3ff;
            --muted: #9db0c7;
            --surface: rgba(255, 255, 255, 0.08);
            --surface-border: rgba(255, 255, 255, 0.18);
            --ok: #38d39f;
            --warn: #ffcb66;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 500px at 10% -10%, rgba(0, 179, 134, 0.28), transparent 60%),
                radial-gradient(900px 450px at 100% 0%, rgba(255, 127, 80, 0.18), transparent 60%),
                linear-gradient(160deg, var(--bg-deep), var(--bg-mid));
            background-attachment: fixed;
        }
        .page { max-width: 1180px; margin: 0 auto; padding: 2rem 1rem 3rem; }
        .topbar {
            display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem;
            align-items: center; margin-bottom: 2rem; animation: reveal 700ms ease-out;
        }
        .brand { font-family: 'Fraunces', serif; font-size: 1.3rem; letter-spacing: 0.2px; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 0.45rem; border: 1px solid var(--surface-border);
            border-radius: 999px; padding: 0.35rem 0.8rem; background: var(--surface); font-size: 0.9rem;
        }
        .dot {
            width: 8px; height: 8px; border-radius: 999px; background: var(--warn);
            box-shadow: 0 0 0 0 rgba(255, 203, 102, 0.55); animation: pulse 1.6s infinite;
        }
        .dot.ok { background: var(--ok); box-shadow: 0 0 0 0 rgba(56, 211, 159, 0.55); }
        .hero {
            border: 1px solid var(--surface-border); border-radius: 22px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            padding: 2rem; box-shadow: 0 22px 60px rgba(0, 0, 0, 0.22); animation: reveal 950ms ease-out;
        }
        .hero h1 {
            margin: 0 0 0.7rem; font-family: 'Fraunces', serif; font-size: clamp(2rem, 4.2vw, 3.2rem); line-height: 1.1;
        }
        .hero p { margin: 0; color: var(--muted); max-width: 760px; font-size: 1.03rem; }
        .actions { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.7rem; }
        .btn {
            text-decoration: none; color: #08131f; background: #ffffff; border-radius: 12px;
            padding: 0.72rem 1rem; font-weight: 700; transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2); }
        .btn.primary { background: linear-gradient(90deg, #00d49f, #46f0c4); }
        .btn.alt { background: linear-gradient(90deg, #ffd17f, #ff9e68); }
        .metrics { margin-top: 1.2rem; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.8rem; }
        .metric {
            border: 1px solid var(--surface-border); border-radius: 14px; background: var(--surface); padding: 0.9rem;
            animation: reveal 1000ms ease-out;
        }
        .metric .label { font-size: 0.84rem; color: var(--muted); }
        .metric .value { font-size: 1.12rem; font-weight: 800; margin-top: 0.25rem; }
        .grid { margin-top: 1.4rem; display: grid; grid-template-columns: 2fr 1fr; gap: 0.9rem; }
        .panel { border: 1px solid var(--surface-border); border-radius: 16px; background: var(--surface); padding: 1rem; }
        .panel h2 { margin: 0 0 0.6rem; font-size: 1.1rem; }
        .feature-list { margin: 0; padding-left: 1.1rem; color: var(--muted); line-height: 1.6; }
        .time-row {
            display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.18);
            padding: 0.46rem 0; font-size: 0.94rem;
        }
        .time-row:last-child { border-bottom: 0; }
        .support { margin-top: 1rem; color: var(--muted); font-size: 0.96rem; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 203, 102, 0.55); }
            70% { box-shadow: 0 0 0 9px rgba(255, 203, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 203, 102, 0); }
        }
        @keyframes reveal {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 900px) {
            .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .hero { padding: 1.2rem; }
            .actions { flex-direction: column; }
            .btn { text-align: center; width: 100%; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div class="brand"><?php echo SITE_NAME; ?></div>
            <div class="status-pill" id="statusPill">
                <span class="dot" id="statusDot"></span>
                <span id="statusText">Checking live service status...</span>
            </div>
        </div>

        <section class="hero">
            <h1>Trusted care delivery, engineered for global reliability.</h1>
            <p>
                A unified care platform for patients, doctors, and administrators with live system health monitoring,
                multilingual readiness, and always-on access designed for regional and cross-border healthcare operations.
            </p>

            <div class="actions">
                <a href="patient/login.php" class="btn primary">Patient Sign In</a>
                <a href="patient/register.php" class="btn">Create Patient Account</a>
                <a href="doctor/login.php" class="btn">Doctor Sign In</a>
                <a href="admin/login.php" class="btn">Admin Sign In</a>
                <a href="accreditation.php" class="btn alt">Accreditation Center</a>
            </div>

            <div class="metrics">
                <article class="metric">
                    <div class="label">Current Platform Status</div>
                    <div class="value" id="metricStatus">Syncing...</div>
                </article>
                <article class="metric">
                    <div class="label">Supported Languages</div>
                    <div class="value">English / Francais</div>
                </article>
                <article class="metric">
                    <div class="label">Payment Hotline</div>
                    <div class="value"><?php echo PAYMENT_NUMBER; ?></div>
                </article>
                <article class="metric">
                    <div class="label">Customer Service</div>
                    <div class="value"><?php echo CUSTOMER_SERVICE_NUMBER; ?></div>
                </article>
            </div>

            <div class="grid">
                <section class="panel">
                    <h2>Worldwide Reliability Profile</h2>
                    <ul class="feature-list">
                        <li>Live database heartbeat through API health checks.</li>
                        <li>Role-based access flows for patient, doctor, and admin portals.</li>
                        <li>Cross-timezone operational visibility for distributed care teams.</li>
                        <li>Structured accreditation workflow supporting compliance programs.</li>
                        <li>Resilient user experience with clear operational status feedback.</li>
                    </ul>
                </section>
                <aside class="panel">
                    <h2>Global Clock</h2>
                    <div class="time-row"><span>Douala</span><strong id="timeDouala">--:--</strong></div>
                    <div class="time-row"><span>UTC</span><strong id="timeUtc">--:--</strong></div>
                    <div class="time-row"><span>New York</span><strong id="timeNy">--:--</strong></div>
                    <div class="time-row"><span>Tokyo</span><strong id="timeTokyo">--:--</strong></div>
                </aside>
            </div>

            <p class="support">
                Service health endpoint: <strong>/api/health.php</strong> | Last health check: <span id="healthTimestamp">pending</span>
            </p>
        </section>
    </main>

    <script>
        async function loadHealth() {
            const statusPill = document.getElementById('statusPill');
            const statusDot = document.getElementById('statusDot');
            const statusText = document.getElementById('statusText');
            const metricStatus = document.getElementById('metricStatus');
            const healthTimestamp = document.getElementById('healthTimestamp');

            try {
                const response = await fetch('api/health.php', { cache: 'no-store' });
                const payload = await response.json();
                const ok = payload.status === 'operational';

                statusDot.classList.toggle('ok', ok);
                statusText.textContent = ok ? 'All core services operational' : 'Service degraded - monitoring in progress';
                metricStatus.textContent = ok ? 'Operational' : 'Degraded';
                statusPill.setAttribute('aria-label', statusText.textContent);
                healthTimestamp.textContent = payload.generated_at || 'unknown';
            } catch (error) {
                statusText.textContent = 'Status unavailable - retrying automatically';
                metricStatus.textContent = 'Unknown';
                healthTimestamp.textContent = new Date().toISOString();
            }
        }

        function updateGlobalClock() {
            const now = new Date();
            const formatters = {
                Douala: new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Africa/Douala' }),
                UTC: new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'UTC' }),
                NewYork: new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'America/New_York' }),
                Tokyo: new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Tokyo' })
            };

            document.getElementById('timeDouala').textContent = formatters.Douala.format(now);
            document.getElementById('timeUtc').textContent = formatters.UTC.format(now);
            document.getElementById('timeNy').textContent = formatters.NewYork.format(now);
            document.getElementById('timeTokyo').textContent = formatters.Tokyo.format(now);
        }

        loadHealth();
        updateGlobalClock();
        setInterval(loadHealth, 60000);
        setInterval(updateGlobalClock, 1000);
    </script>
</body>
</html>
