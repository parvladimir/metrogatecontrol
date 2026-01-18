<?php
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require __DIR__ . '/_layout.php';

admin_header('Dashboard', 'dashboard');
?>
<div class="row">
  <div class="col-6">
    <div class="card">
      <div style="font-weight:800;margin-bottom:8px;">Quick links</div>
      <div class="actions">
        <a class="btnlink" href="/admin/schedule.php">Manage schedule</a>
        <a class="btnlink" href="/admin/gates.php">Manage gates</a>
        <a class="btnlink" href="/admin/carriers.php">Manage carriers</a>
        <a class="btnlink" href="/admin/users.php">Manage users</a>
      </div>
      <div style="margin-top:10px;opacity:.75;font-size:13px;">
        Use this panel to maintain master data. Operators work in the main system UI.
      </div>
    </div>
  </div>
  <div class="col-6">
    <div class="card">
      <div style="font-weight:800;margin-bottom:8px;">Access control</div>
      <div style="opacity:.75;font-size:13px;">
        Admin pages are restricted to role <strong>admin</strong>. If you open /admin while signed in as a normal user, you will be offered a safe “switch to admin” login flow.
      </div>
    </div>
  </div>
</div>
<?php admin_footer(); ?>