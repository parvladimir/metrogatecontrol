<?php
declare(strict_types=1);

function admin_header(string $title, string $active = ''): void {
  $items = [
    ['href'=>'/admin/','label'=>'Dashboard','key'=>'dashboard'],
    ['href'=>'/admin/schedule.php','label'=>'Schedule','key'=>'schedule'],
    ['href'=>'/admin/gates.php','label'=>'Gates','key'=>'gates'],
    ['href'=>'/admin/carriers.php','label'=>'Carriers','key'=>'carriers'],
    ['href'=>'/admin/users.php','label'=>'Users','key'=>'users'],
  ];
  ?><!doctype html>
  <html lang="en"><head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css"/>
    <style>
      body{min-height:100vh}
      .adminShell{display:grid;grid-template-columns:240px 1fr;gap:0;min-height:100vh}
      .side{background:rgba(10,18,32,.55);border-right:1px solid rgba(255,255,255,.08);padding:18px 14px}
      .brand{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:6px 8px}
      .brand img{height:24px}
      .kword{font-weight:900;letter-spacing:.06em;color:#ffcc00;text-transform:uppercase;font-size:13px}
      .nav a{display:flex;align-items:center;gap:10px;padding:10px 10px;border-radius:12px;text-decoration:none;color:rgba(255,255,255,.86);margin-bottom:6px;border:1px solid transparent}
      .nav a.active{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.10)}
      .main{padding:22px 22px 40px 22px}
      .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
      .crumb{opacity:.75;font-size:13px}
      .topActions{display:flex;gap:10px;align-items:center}
      .pill{padding:8px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);font-size:12px;opacity:.9}
      .btnlink{padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.10);text-decoration:none;color:inherit}
      .card{border-radius:16px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);padding:14px}
      table{width:100%;border-collapse:collapse}
      th,td{padding:10px 10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;font-size:13px}
      th{opacity:.8;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
      input[type=text],input[type=time],input[type=number],textarea,select{width:100%}
      textarea{min-height:70px;resize:vertical}
      .row{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
      .col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-5{grid-column:span 5}.col-6{grid-column:span 6}.col-7{grid-column:span 7}.col-8{grid-column:span 8}.col-12{grid-column:span 12}
      .actions{display:flex;gap:8px;flex-wrap:wrap}
      .danger{color:#ff6b6b}
      .muted{opacity:.7}
      @media (max-width: 880px){.adminShell{grid-template-columns:1fr}.side{border-right:0;border-bottom:1px solid rgba(255,255,255,.08)}}
    </style>
  </head><body>
  <div class="adminShell">
    <aside class="side">
      <div class="brand">
        <img src="/assets/img/metro-logistics.svg" alt="METRO LOGISTICS">
        <span class="kword">KÄRCHER</span>
      </div>
      <nav class="nav">
        <?php foreach ($items as $it): $is = ($active === $it['key']); ?>
          <a class="<?= $is ? 'active' : '' ?>" href="<?= $it['href'] ?>"><?= htmlspecialchars($it['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div style="margin-top:14px;padding:8px 10px;opacity:.65;font-size:12px;">
        Signed in as <strong><?= htmlspecialchars((string)current_username()) ?></strong>
      </div>
    </aside>
    <main class="main">
      <div class="topbar">
        <div>
          <div style="font-size:20px;font-weight:900;"><?= htmlspecialchars($title) ?></div>
          <div class="crumb">Metro Gate Control — Admin panel</div>
        </div>
        <div class="topActions">
          <div class="pill"><?= htmlspecialchars((string)current_username()) ?> (<?= htmlspecialchars((string)($_SESSION['role'] ?? '')) ?>)</div>
          <a class="btnlink" href="/">System</a>
          <a class="btnlink" href="/logout.php">Logout</a>
        </div>
      </div>
<?php
}

function admin_footer(): void {
  ?>
      <div style="margin-top:18px;opacity:.55;font-size:12px;">Developed by Volodymyr Parashchak</div>
    </main>
  </div>
</body></html><?php
}
