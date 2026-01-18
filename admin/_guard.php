<?php
declare(strict_types=1);
require __DIR__ . '/../app/auth.php';

$next = '/admin/';
if (!is_logged_in()) {
  header('Location: /login.php?next=' . urlencode($next));
  exit;
}

if (!is_admin()) {
  http_response_code(200);
  ?><!doctype html>
  <html lang="en"><head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Admin access required</title>
    <link rel="stylesheet" href="/assets/css/app.css"/>
    <style>
      body{min-height:100vh;display:flex;align-items:center;justify-content:center}
      .card{width:min(560px,92vw);padding:24px 26px;border-radius:18px;border:1px solid rgba(255,255,255,.10);background:rgba(10,18,32,.55);box-shadow:0 20px 60px rgba(0,0,0,.35)}
      .brand{display:flex;align-items:center;gap:12px}
      .brand img{height:26px}
      .kword{font-weight:900;letter-spacing:.06em;color:#ffcc00;text-transform:uppercase}
      .muted{opacity:.75}
      .actions{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap}
      .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.10);text-decoration:none}
      .btn.primary{background:#1db954;color:#071019;border-color:transparent;font-weight:800}
    </style>
  </head><body>
    <div class="card">
      <div class="brand">
        <img src="/assets/img/metro-logistics.svg" alt="METRO LOGISTICS">
        <span class="kword">KÄRCHER</span>
      </div>
      <h2 style="margin:12px 0 6px 0;">Admin access required</h2>
      <div class="muted">You are signed in as <strong><?= htmlspecialchars((string)current_username()) ?></strong>. Only users with role <strong>admin</strong> can access the admin panel.</div>
      <div class="actions">
        <a class="btn primary" href="/login.php?next=<?= urlencode($next) ?>&switch=1">Login as admin</a>
        <a class="btn" href="/">Back to system</a>
      </div>
    </div>
  </body></html><?php
  exit;
}
