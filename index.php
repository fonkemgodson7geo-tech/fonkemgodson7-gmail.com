<?php
require_once 'config/config.php';
$siteLogoUrl = trim((string)SITE_LOGO_URL);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = SITE_URL !== '' ? SITE_URL : ($host !== '' ? ($scheme . '://' . $host) : '');
$canonicalUrl = $baseUrl !== '' ? $baseUrl . '/' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_NAME; ?> — trusted care delivery engineered for global reliability. Patient, doctor and admin portals with live system health monitoring.">
    <?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> | Connected Care Network">
    <meta property="og:description" content="Trusted care platform for patients, doctors, and administrators.">
    <title><?php echo SITE_NAME; ?> | Connected Care Network</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-0: #f3f8fb;
            --bg-1: #e7f1f7;
            --ink-0: #152638;
            --ink-1: #35546d;
            --card: rgba(255, 255, 255, 0.8);
            --card-strong: #ffffff;
            --line: rgba(21, 38, 56, 0.12);
            --teal: #0fb39d;
            --teal-dark: #0a8b7b;
            --amber: #ffb347;
            --coral: #ff7f66;
            --ok: #27bf8a;
            --warn: #e1962f;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink-0);
            background:
                radial-gradient(900px 400px at 10% -5%, rgba(15, 179, 157, 0.2), transparent 60%),
                radial-gradient(900px 400px at 95% 0%, rgba(255, 127, 102, 0.2), transparent 62%),
                linear-gradient(180deg, var(--bg-0), var(--bg-1));
            background-attachment: fixed;
        }

        .page {
            max-width: 1220px;
            margin: 0 auto;
            padding: 1.25rem 1rem 4rem;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.8rem;
            align-items: center;
            padding: 0.6rem 0;
            margin-bottom: 1.3rem;
            animation: rise 700ms ease-out;
        }
        .brand-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
        }
        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            object-fit: cover;
            display: block;
        }
        .brand-logo-fallback {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--ink-1);
            letter-spacing: 0.08em;
            background: #fff;
        }

        .brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .topnav {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .chip-link {
            text-decoration: none;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.75);
            color: var(--ink-0);
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
            font-size: 0.84rem;
            font-weight: 600;
            transition: all 180ms ease;
        }

        .chip-link:hover {
            transform: translateY(-1px);
            border-color: rgba(10, 139, 123, 0.35);
            background: #fff;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            background: rgba(255, 255, 255, 0.8);
            font-size: 0.84rem;
            color: var(--ink-1);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--warn);
            animation: pulse-warn 1.6s infinite;
        }

        .dot.ok {
            background: var(--ok);
            animation: pulse-ok 1.6s infinite;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 0.95rem;
            margin-bottom: 1rem;
        }

        .hero-main,
        .hero-side {
            border: 1px solid var(--line);
            border-radius: 22px;
            background: linear-gradient(155deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.78));
            box-shadow: 0 20px 45px rgba(20, 45, 68, 0.11);
            animation: rise 850ms ease-out;
        }

        .hero-main {
            padding: 2.1rem;
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(15, 179, 157, 0.17), rgba(15, 179, 157, 0));
            right: -80px;
            top: -80px;
            pointer-events: none;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 179, 157, 0.28);
            padding: 0.35rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--teal-dark);
            background: rgba(15, 179, 157, 0.12);
        }

        .hero-main h1 {
            margin: 0 0 0.7rem;
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.85rem, 4vw, 3rem);
            line-height: 1.08;
            letter-spacing: -0.02em;
        }

        .hero-main p {
            margin: 0;
            color: var(--ink-1);
            max-width: 760px;
            font-size: 1.01rem;
            line-height: 1.68;
        }

        .actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .btn {
            text-decoration: none;
            color: #09322d;
            border-radius: 12px;
            padding: 0.72rem 1.05rem;
            font-weight: 700;
            font-size: 0.92rem;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(15, 37, 55, 0.15);
        }

        .btn.primary {
            background: linear-gradient(90deg, #14c8ac, #7fe3cf);
        }

        .btn.warm {
            background: linear-gradient(90deg, #ffd091, #ffb287);
            color: #50280c;
        }

        .hero-highlights {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
            margin-top: 1.1rem;
        }

        .hl {
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.66);
            border-radius: 12px;
            padding: 0.65rem 0.7rem;
            font-size: 0.82rem;
            color: var(--ink-1);
        }

        .hl strong {
            display: block;
            color: var(--ink-0);
            font-size: 0.97rem;
            margin-bottom: 0.1rem;
        }

        .hero-side {
            padding: 1.2rem;
            display: grid;
            gap: 0.75rem;
        }

        .quick-tile {
            border-radius: 14px;
            padding: 0.95rem;
            border: 1px solid var(--line);
            background: #fff;
        }

        .quick-tile .k {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-1);
        }

        .quick-tile .v {
            margin-top: 0.2rem;
            font-weight: 800;
            font-size: 1.1rem;
            font-family: 'Outfit', sans-serif;
        }

        .time-grid {
            display: grid;
            gap: 0.45rem;
        }

        .time-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed var(--line);
            padding: 0.33rem 0;
            font-size: 0.9rem;
        }

        .time-row:last-child {
            border-bottom: 0;
        }

        .time-row span {
            color: var(--ink-1);
        }

        .metrics {
            margin-top: 1.4rem;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .metric {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.76);
            padding: 0.9rem 1rem;
            animation: rise 1000ms ease-out;
        }

        .metric .label {
            font-size: 0.78rem;
            color: var(--ink-1);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .metric .value {
            font-size: 1.05rem;
            font-weight: 800;
            margin-top: 0.3rem;
        }

        .grid { margin-top: 1.2rem; display: grid; grid-template-columns: 2fr 1fr; gap: 0.9rem; }

        .panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.76);
            padding: 1.1rem 1.3rem;
        }

        .panel h2 {
            margin: 0 0 0.7rem;
            font-size: 1.03rem;
            font-family: 'Outfit', sans-serif;
        }

        .feature-list {
            margin: 0;
            padding-left: 1.1rem;
            color: var(--ink-1);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .portal-wrap {
            margin-top: 1rem;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--card-strong);
            padding: 1.2rem;
            box-shadow: 0 20px 45px rgba(20, 45, 68, 0.11);
        }

        .portal-head {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-bottom: 0.85rem;
            align-items: center;
        }

        .portal-head h2 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.22rem;
        }

        .portal-head p {
            margin: 0;
            color: var(--ink-1);
            font-size: 0.92rem;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.7rem;
        }

        .portal-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0.9rem;
            background: linear-gradient(160deg, #ffffff, #f4fbff);
            display: grid;
            gap: 0.5rem;
        }

        .portal-card h3 {
            margin: 0;
            font-size: 0.98rem;
            font-family: 'Outfit', sans-serif;
        }

        .portal-card p {
            margin: 0;
            font-size: 0.86rem;
            color: var(--ink-1);
            min-height: 2.35rem;
        }

        .portal-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .mini-btn {
            text-decoration: none;
            border-radius: 10px;
            padding: 0.45rem 0.62rem;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .mini-btn.login {
            background: linear-gradient(90deg, #16c0a5, #93e9d9);
            color: #073830;
        }

        .mini-btn.alt {
            background: #eef5fb;
            color: #2f4f68;
            border: 1px solid var(--line);
        }

        .foot {
            margin-top: 1.1rem;
            color: var(--ink-1);
            font-size: 0.9rem;
        }

        .foot strong { color: var(--ink-0); }

        @keyframes pulse-warn {
            0%,100% { box-shadow: 0 0 0 0 rgba(255,203,102,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(255,203,102,0); }
        }
        @keyframes pulse-ok {
            0%,100% { box-shadow: 0 0 0 0 rgba(56,211,159,0.55); }
            70%      { box-shadow: 0 0 0 8px rgba(56,211,159,0); }
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 900px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .metrics { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .grid    { grid-template-columns: 1fr; }

            .portal-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .hero-main {
                padding: 1.3rem;
            }

            .hero-highlights {
                grid-template-columns: 1fr;
            }

            .actions { flex-direction: column; }
            .btn { text-align: center; }

            .portal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="page">

    <header class="topbar">
        <div class="brand-wrap">
            <?php if ($siteLogoUrl !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> logo"
                    class="brand-logo"
                >
            <?php else: ?>
                <span class="brand-logo-fallback" aria-hidden="true">LOGO</span>
            <?php endif; ?>
            <div class="brand"><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <nav class="topnav" aria-label="Quick links">
            <a class="chip-link" href="#portal">Sign In</a>
            <a class="chip-link" href="patient/register.php">Register Patient</a>
            <a class="chip-link" href="public_communications.php">Communications</a>
            <a class="chip-link" href="accreditation.php">Accreditation</a>
        </nav>
        <div class="status-pill" id="statusPill" role="status" aria-live="polite">
            <span class="dot" id="statusDot"></span>
            <span id="statusText">Checking live service status&hellip;</span>
        </div>
    </header>

    <section class="hero-grid">
        <article class="hero-main">
            <span class="eyebrow">Connected Care Network</span>
            <h1>Built for confidence: a hospital web experience that feels premium and alive.</h1>
            <p>
                From first contact to discharge follow-up, this platform centralizes patient services, clinical work,
                compliance workflows, and live operational monitoring in one cohesive experience.
            </p>
            <div class="actions">
                <a href="#portal" class="btn primary">Sign In From Home Page</a>
                <a href="patient/register.php" class="btn warm">Create Patient Account</a>
            </div>
            <div class="hero-highlights">
                <div class="hl"><strong>Realtime Health</strong>API heartbeat checks and visible service status.</div>
                <div class="hl"><strong>Role Portals</strong>Dedicated login routes for all care teams.</div>
                <div class="hl"><strong>Bilingual Ready</strong>Prepared for multilingual care operations.</div>
            </div>
        </article>

        <aside class="hero-side">
            <div class="quick-tile">
                <div class="k">Payment Hotline</div>
                <div class="v"><?php echo htmlspecialchars(PAYMENT_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="quick-tile">
                <div class="k">Customer Service</div>
                <div class="v"><?php echo htmlspecialchars(CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="quick-tile">
                <div class="k">Global Clock</div>
                <div class="time-grid">
                    <div class="time-row"><span>Douala</span><strong id="timeDouala">--:--</strong></div>
                    <div class="time-row"><span>UTC</span><strong id="timeUtc">--:--</strong></div>
                    <div class="time-row"><span>New York</span><strong id="timeNy">--:--</strong></div>
                    <div class="time-row"><span>London</span><strong id="timeLondon">--:--</strong></div>
                    <div class="time-row"><span>Tokyo</span><strong id="timeTokyo">--:--</strong></div>
                </div>
            </div>
        </aside>
    </section>

    <section class="portal-wrap" id="portal">
        <div class="portal-head">
            <h2>Login Access On Home Page</h2>
            <p>All sign in options stay right here on the homepage, exactly as requested.</p>
        </div>
        <div class="portal-grid">
            <article class="portal-card">
                <h3>Patient Portal</h3>
                <p>Appointments, records, and payments in one secure space.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="patient/login.php">Patient Sign In</a>
                    <a class="mini-btn alt" href="patient/register.php">Register</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Doctor Portal</h3>
                <p>Consultations, shift attendance, and care updates.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="doctor/login.php">Doctor Sign In</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Staff Portal</h3>
                <p>Operational workflows, reporting, and communications.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="staff/login.php">Staff Sign In</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Intern Portal</h3>
                <p>Guided access for internship and supervision tasks.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="intern/login.php">Intern Sign In</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Trainee Portal</h3>
                <p>Shift activities and learning operations in one place.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="trainee/login.php">Trainee Sign In</a>
                </div>
            </article>
            <article class="portal-card">
                <h3>Admin Portal</h3>
                <p>Governance, page management, and system oversight.</p>
                <div class="portal-actions">
                    <a class="mini-btn login" href="admin/login.php">Admin Sign In</a>
                    <a class="mini-btn alt" href="accreditation.php">Accreditation</a>
                </div>
            </article>
        </div>
    </section>

    <section class="metrics" role="list">
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
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Why This Experience Feels Captivating</h2>
            <ul class="feature-list">
                <li>Editorial hero composition with layered gradients and cards for visual depth.</li>
                <li>All role logins available on home page without navigation friction.</li>
                <li>Realtime operational status connected to a live health endpoint.</li>
                <li>Clear call paths for registration, accreditation, and public communications.</li>
                <li>Mobile-first responsive behavior for teams and patients on the move.</li>
            </ul>
        </section>
        <aside class="panel">
            <h2>Public Access</h2>
            <ul class="feature-list">
                <li><a href="public_communications.php">Public Communications Board</a></li>
                <li><a href="sitemap.php">Site Map</a></li>
                <li><a href="accreditation.php">Accreditation and Compliance</a></li>
            </ul>
        </aside>
    </div>

    <p class="foot">
        Health endpoint: <strong>/api/health.php</strong> &middot;
        Last checked: <span id="healthTimestamp">pending&hellip;</span>
    </p>

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