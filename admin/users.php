<?php
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../app/config/db.php';

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  try {
    if ($action === 'create') {
      $username = trim((string)($_POST['username'] ?? ''));
      $password = (string)($_POST['password'] ?? '');
      $role = (string)($_POST['role'] ?? 'user');
      $active = isset($_POST['active']) ? 1 : 0;

      if ($username === '' || $password === '') throw new RuntimeException('Username and password are required.');
      if (!in_array($role, ['admin','user'], true)) $role = 'user';

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare('INSERT INTO users (username, password_hash, role, active) VALUES (:u,:p,:r,:a)')
          ->execute([':u'=>$username,':p'=>$hash,':r'=>$role,':a'=>$active]);
    } elseif ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $role = (string)($_POST['role'] ?? 'user');
      $active = isset($_POST['active']) ? 1 : 0;
      if (!in_array($role, ['admin','user'], true)) $role = 'user';
      $pdo->prepare('UPDATE users SET role=:r, active=:a WHERE id=:id')->execute([':r'=>$role,':a'=>$active,':id'=>$id]);
    } elseif ($action === 'reset_password') {
      $id = (int)($_POST['id'] ?? 0);
      $password = (string)($_POST['password'] ?? '');
      if ($password === '') throw new RuntimeException('New password is required.');
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare('UPDATE users SET password_hash=:p WHERE id=:id')->execute([':p'=>$hash,':id'=>$id]);
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare('DELETE FROM users WHERE id=:id')->execute([':id'=>$id]);
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$users = $pdo->query('SELECT id, username, role, active FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

admin_header('Users', 'users');
?>
<?php if ($err): ?><div class="card" style="border-color:rgba(255,107,107,.35);"><div class="danger"><?= htmlspecialchars($err) ?></div></div><?php endif; ?>

<div class="row" style="margin-top:12px;">
  <div class="col-4">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Create user</div>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <label class="label">Username</label>
        <input class="input" type="text" name="username" required>
        <label class="label" style="margin-top:8px;">Password</label>
        <input class="input" type="text" name="password" required>
        <div class="row" style="margin-top:8px;">
          <div class="col-6">
            <label class="label">Role</label>
            <select class="input" name="role">
              <option value="user">user</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="col-6">
            <label class="label">Active</label>
            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;opacity:.85;">
              <input type="checkbox" name="active" checked> Enabled
            </label>
          </div>
        </div>
        <button class="btn primary" style="width:100%;margin-top:12px;">Create</button>
      </form>
    </div>
  </div>

  <div class="col-8">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Existing users</div>
      <table>
        <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td style="width:60px;"><?= (int)$u['id'] ?></td>
              <td><?= htmlspecialchars((string)$u['username']) ?></td>
              <td style="width:110px;"><?= htmlspecialchars((string)$u['role']) ?></td>
              <td style="width:80px;"><?= ((int)$u['active']===1)?'Yes':'No' ?></td>
              <td style="width:360px;">
                <details>
                  <summary style="cursor:pointer;opacity:.85;">Manage</summary>
                  <form method="post" style="margin-top:10px;">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <div class="row">
                      <div class="col-6">
                        <label class="label">Role</label>
                        <select class="input" name="role">
                          <option value="user" <?= ((string)$u['role']==='user')?'selected':'' ?>>user</option>
                          <option value="admin" <?= ((string)$u['role']==='admin')?'selected':'' ?>>admin</option>
                        </select>
                      </div>
                      <div class="col-6">
                        <label class="label">Active</label>
                        <label style="display:flex;gap:8px;align-items:center;margin-top:8px;opacity:.85;">
                          <input type="checkbox" name="active" <?= ((int)$u['active']===1)?'checked':'' ?>> Enabled
                        </label>
                      </div>
                      <div class="col-12" style="display:flex;gap:10px;align-items:center;margin-top:6px;">
                        <button class="btn primary" style="margin-left:auto;">Save</button>
                      </div>
                    </div>
                  </form>

                  <form method="post" style="margin-top:8px;">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <label class="label">New password</label>
                    <input class="input" type="text" name="password" required>
                    <button class="btn primary" style="margin-top:8px;">Reset password</button>
                  </form>

                  <form method="post" onsubmit="return confirm('Delete user?');" style="margin-top:8px;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btnlink danger" type="submit">Delete</button>
                  </form>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php admin_footer(); ?>
