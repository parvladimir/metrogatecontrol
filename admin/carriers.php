<?php
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../app/config/db.php';

function norm_carrier(string $s): string {
  $s = trim($s);
  if ($s==='') return '';
  $s = mb_strtoupper($s,'UTF-8');
  $s = preg_replace('/[\s\-]+/u','_',$s);
  $s = preg_replace('/[^A-Z0-9_]/u','',$s);
  return $s ?? '';
}


$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  try {
    if ($action === 'create' || $action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $code = norm_carrier(trim((string)($_POST['code'] ?? '')));
      $name = trim((string)($_POST['name'] ?? ''));
      $sort = (int)($_POST['sort_order'] ?? 0);
      $active = isset($_POST['active']) ? 1 : 0;

      if ($code === '' || $name === '') throw new RuntimeException('Code and Name are required.');

      $logoPath = null;
      if (isset($_FILES['logo']) && is_array($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = (string)$_FILES['logo']['tmp_name'];
        $orig = (string)$_FILES['logo']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','webp','svg'];
        if (!in_array($ext, $allowed, true)) throw new RuntimeException('Unsupported logo format. Use png/jpg/webp/svg.');

        $safeCode = preg_replace('/[^a-z0-9_-]/i', '', $code);
        if ($safeCode === '') $safeCode = 'carrier';
        $destRel = '/assets/img/carriers/' . $safeCode . '.' . $ext;
        $destAbs = __DIR__ . '/..' . $destRel;

        if (!is_dir(dirname($destAbs))) mkdir(dirname($destAbs), 0755, true);
        if (!move_uploaded_file($tmp, $destAbs)) throw new RuntimeException('Failed to upload logo.');
        $logoPath = $destRel;
      } elseif (($action === 'update') && isset($_POST['logo_path']) && $_POST['logo_path'] !== '') {
        $logoPath = (string)$_POST['logo_path'];
      }

      if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO carriers (code, name, logo_path, sort_order, active, created_at) VALUES (:c,:n,:l,:s,:a, NOW())');
        $stmt->execute([':c'=>$code,':n'=>$name,':l'=>$logoPath,':s'=>$sort,':a'=>$active]);
      } else {
        $stmt = $pdo->prepare('UPDATE carriers SET code=:c, name=:n, logo_path=COALESCE(:l, logo_path), sort_order=:s, active=:a WHERE id=:id');
        $stmt->execute([':c'=>$code,':n'=>$name,':l'=>$logoPath,':s'=>$sort,':a'=>$active,':id'=>$id]);
      }
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare('DELETE FROM carriers WHERE id=:id')->execute([':id'=>$id]);
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$rows = [];
try {
  $rows = $pdo->query('SELECT id, code, name, logo_path, sort_order, active FROM carriers ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $rows = [];
  $err = $err ?: 'Carriers table is not available yet. Import the database or run migrations.';
}

admin_header('Carriers', 'carriers');
?>
<?php if ($err): ?><div class="card" style="border-color:rgba(255,107,107,.35);"><div class="danger"><?= htmlspecialchars($err) ?></div></div><?php endif; ?>

<div class="row" style="margin-top:12px;">
  <div class="col-5">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Add carrier</div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <div class="row">
          <div class="col-6">
            <label class="label">Code</label>
            <input class="input" type="text" name="code" placeholder="DACHSER" required>
          </div>
          <div class="col-6">
            <label class="label">Sort order</label>
            <input class="input" type="number" name="sort_order" value="0">
          </div>
          <div class="col-12">
            <label class="label">Name</label>
            <input class="input" type="text" name="name" placeholder="Dachser Stückgut" required>
          </div>
          <div class="col-12">
            <label class="label">Logo (optional)</label>
            <input class="input" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
          </div>
          <div class="col-12" style="display:flex;gap:10px;align-items:center;margin-top:6px;">
            <label style="display:flex;gap:8px;align-items:center;opacity:.85;">
              <input type="checkbox" name="active" checked> Active
            </label>
            <button class="btn primary" style="margin-left:auto;">Create</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-7">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Existing carriers</div>
      <table>
        <thead><tr><th>Logo</th><th>Code</th><th>Name</th><th>Order</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="width:90px;">
              <?php if (!empty($r['logo_path'])): ?>
                <img src="<?= htmlspecialchars((string)$r['logo_path']) ?>" alt="" style="height:22px;max-width:80px;">
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)$r['code']) ?></td>
            <td><?= htmlspecialchars((string)$r['name']) ?></td>
            <td><?= (int)$r['sort_order'] ?></td>
            <td><?= ((int)$r['active']===1) ? 'Yes' : 'No' ?></td>
            <td style="width:280px;">
              <details>
                <summary style="cursor:pointer;opacity:.85;">Edit</summary>
                <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="logo_path" value="<?= htmlspecialchars((string)($r['logo_path'] ?? '')) ?>">
                  <div class="row">
                    <div class="col-6"><label class="label">Code</label><input class="input" type="text" name="code" value="<?= htmlspecialchars((string)$r['code']) ?>"></div>
                    <div class="col-6"><label class="label">Sort</label><input class="input" type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>"></div>
                    <div class="col-12"><label class="label">Name</label><input class="input" type="text" name="name" value="<?= htmlspecialchars((string)$r['name']) ?>"></div>
                    <div class="col-12"><label class="label">Replace logo</label><input class="input" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg"></div>
                    <div class="col-12" style="display:flex;gap:10px;align-items:center;margin-top:6px;">
                      <label style="display:flex;gap:8px;align-items:center;opacity:.85;">
                        <input type="checkbox" name="active" <?= ((int)$r['active']===1)?'checked':'' ?>> Active
                      </label>
                      <button class="btn primary" style="margin-left:auto;">Save</button>
                    </div>
                  </div>
                </form>
                <form method="post" onsubmit="return confirm('Delete this carrier?');" style="margin-top:8px;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btnlink danger" type="submit">Delete</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted">No carriers yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div style="margin-top:10px;opacity:.7;font-size:12px;">
        Carriers feed the “Carrier” filter and schedule labeling in the main UI.
      </div>
    </div>
  </div>
</div>

<?php admin_footer(); ?>
