<?php
require_once __DIR__ . '/config/config.php';
http_response_code(500);
error_log('500 error page displayed at ' . date('c'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error &mdash; <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg-deep: #08131f; --bg-mid: #10253a;
            --text: #ebf3ff; --muted: #9db0c7;
            --surface: rgba(255,255,255,0.07);
            --surface-border: rgba(255,255,255,0.16);
            --warn: #ffcb66;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: var(--text); min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background:
                radial-gradient(900px 400px at 90% -10%, rgba(255,127,80,0.2), transparent 55%),
                linear-gradient(160deg, var(--bg-deep), var(--bg-mid));
        }
        .card {
            border: 1px solid var(--surface-border); border-radius: 22px;
            background: var(--surface); padding: 3rem 2.5rem;
            text-align: center; max-width: 480px; width: 92%;
            backdrop-filter: blur(12px);
        }
        .code { font-size: 5rem; font-weight: 800; color: var(--warn); line-height: 1; }
        h1 { font-size: 1.6rem; margin: 0.6rem 0 0.4rem; }
        p  { color: var(--muted); line-height: 1.7; }
        .actions { margin-top: 1.8rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            text-decoration: none; font-weight: 700; border-radius: 12px;
            padding: 0.65rem 1.2rem; font-size: 0.95rem;
            background: linear-gradient(90deg,#ffd17f,#ff9e68); color: #08131f;
            transition: transform 180ms, box-shadow 180ms;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.25); }
        .btn.ghost { background: var(--surface); border: 1px solid var(--surface-border); color: var(--text); }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">500</div>
        <h1>Something went wrong</h1>
        <p>We encountered an unexpected server error.<br>
           Our team has been notified. Please try again shortly.</p>
        <div class="actions">
            <a href="/" class="btn">Go to Home</a>
            <a href="javascript:history.back()" class="btn ghost">Go Back</a>
        </div>
        <p style="margin-top:1.5rem;font-size:0.85rem;">
            Need urgent help? Call <?php echo htmlspecialchars(CUSTOMER_SERVICE_NUMBER, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</body>
</html>
