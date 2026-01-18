<?php
declare(strict_types=1);
require __DIR__ . '/app/config/db.php';
require __DIR__ . '/app/auth.php';

$next = (string)($_GET['next'] ?? ($_POST['next'] ?? '/'));
if ($next === '' || $next[0] !== '/') $next = '/';
$switch = (string)($_GET['switch'] ?? ($_POST['switch'] ?? '')) === '1';

// If already logged in and not switching, go where requested.
if (is_logged_in() && !$switch) {
  header('Location: ' . $next);
  exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim((string)($_POST['username'] ?? ''));
  $p = (string)($_POST['password'] ?? '');

  if ($u === '' || $p === '') {
    $err = 'Please enter username and password.';
  } else {
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, active FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $u]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['active'] !== 1 || !password_verify($p, (string)$row['password_hash'])) {
      $err = 'Invalid credentials.';
    } else {
      @session_regenerate_id(true);
      login_user((int)$row['id'], (string)$row['username'], (string)$row['role']);

      // best-effort last login timestamp (column may not exist)
      try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => (int)$row['id']]);
      } catch (Throwable $e) { /* ignore */ }

      session_write_close();
      header('Location: ' . $next);
      exit;
    }
  }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Sign in — Metro Gate Control</title>
  <link rel="stylesheet" href="/assets/css/app.css"/>
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center}
    .card{width:min(520px,92vw);padding:26px 28px;border-radius:18px;border:1px solid rgba(255,255,255,.10);background:rgba(10,18,32,.50);box-shadow:0 20px 60px rgba(0,0,0,.35)}
    .brandrow{display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:10px}
    .brandrow img{height:30px}
    .kword{font-weight:900;letter-spacing:.06em;color:#ffcc00;text-transform:uppercase}
    .subtitle{opacity:.7;text-align:center;margin:0 0 16px 0;font-size:13px}
    .err{margin:10px 0 0 0;color:#ff6b6b;font-size:13px;text-align:center}
    .foot{margin-top:14px;opacity:.6;font-size:12px;text-align:center}
  </style>
</head>
<body>
  <div class="card">
    <div class="brandrow">
      <img src="/assets/img/metro-logistics.svg" alt="METRO LOGISTICS">
      <span class="kword">KÄRCHER</span>
    </div>

    <h2 style="text-align:center;margin:10px 0 6px 0;">Sign in</h2>
    <p class="subtitle">Enter your credentials to access the system.</p>

    <form method="post" action="/login.php">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">
      <input type="hidden" name="switch" value="<?= $switch ? '1' : '' ?>">

      <label class="label">Username</label>
      <input class="input" type="text" name="username" autocomplete="username" autofocus>

      <label class="label" style="margin-top:10px;">Password</label>
      <input class="input" type="password" name="password" autocomplete="current-password">

      <button class="btn primary" style="width:100%;margin-top:16px;">Sign in</button>

      <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <?php if (is_logged_in() && $switch): ?>
        <div class="err" style="color:rgba(255,255,255,.7);margin-top:10px;">
          You are currently signed in as <strong><?= htmlspecialchars((string)current_username()) ?></strong>. Signing in will switch the session.
        </div>
      <?php endif; ?>
    </form>

    <div class="foot">Developed by Volodymyr Parashchak</div>
  </div>
</body>
</html>
