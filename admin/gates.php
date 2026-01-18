<?php
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../app/config/db.php';

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  try {
    if ($action === 'create_gate') {
      $code = trim((string)($_POST['gate_code'] ?? ''));
      if ($code === '') throw new RuntimeException('Gate code is required.');
      $pdo->prepare('INSERT INTO gates (gate_code) VALUES (:c)')->execute([':c'=>$code]);
    } elseif ($action === 'delete_gate') {
      $id = (int)($_POST['gate_id'] ?? 0);
      $pdo->prepare('DELETE FROM gate_positions WHERE gate_id=:id')->execute([':id'=>$id]);
      $pdo->prepare('DELETE FROM gates WHERE id=:id')->execute([':id'=>$id]);
    } elseif ($action === 'add_pos') {
      $gateId = (int)($_POST['gate_id'] ?? 0);
      $pos = trim((string)($_POST['position'] ?? ''));
      if ($pos === '') throw new RuntimeException('Position is required.');
      $pdo->prepare('INSERT INTO gate_positions (gate_id, position) VALUES (:g,:p)')->execute([':g'=>$gateId,':p'=>$pos]);
    } elseif ($action === 'del_pos') {
      $posId = (int)($_POST['pos_id'] ?? 0);
      $pdo->prepare('DELETE FROM gate_positions WHERE id=:id')->execute([':id'=>$posId]);
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$gates = $pdo->query('SELECT id, gate_code FROM gates ORDER BY gate_code ASC')->fetchAll(PDO::FETCH_ASSOC);
$pos = $pdo->query('SELECT id, gate_id, position FROM gate_positions ORDER BY gate_id ASC, position ASC')->fetchAll(PDO::FETCH_ASSOC);
$posByGate = [];
foreach ($pos as $p) { $posByGate[(int)$p['gate_id']][] = $p; }

admin_header('Gates', 'gates');
?>
<?php if ($err): ?><div class="card" style="border-color:rgba(255,107,107,.35);"><div class="danger"><?= htmlspecialchars($err) ?></div></div><?php endif; ?>

<div class="row" style="margin-top:12px;">
  <div class="col-4">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Add gate</div>
      <form method="post">
        <input type="hidden" name="action" value="create_gate">
        <label class="label">Gate code</label>
        <input class="input" type="text" name="gate_code" placeholder="T153" required>
        <button class="btn primary" style="margin-top:12px;width:100%;">Create gate</button>
      </form>
    </div>
  </div>

  <div class="col-8">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Gates & positions</div>
      <table>
        <thead><tr><th>Gate</th><th>Positions</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($gates as $g): $gid=(int)$g['id']; ?>
            <tr>
              <td style="width:120px;"><strong><?= htmlspecialchars((string)$g['gate_code']) ?></strong></td>
              <td>
                <div class="actions">
                  <?php foreach (($posByGate[$gid] ?? []) as $p): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete position?');">
                      <input type="hidden" name="action" value="del_pos">
                      <input type="hidden" name="pos_id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="btnlink" style="padding:6px 10px;"><?= htmlspecialchars((string)$p['position']) ?> <span class="danger" style="margin-left:6px;">×</span></button>
                    </form>
                  <?php endforeach; ?>
                </div>
                <form method="post" style="margin-top:8px;max-width:280px;">
                  <input type="hidden" name="action" value="add_pos">
                  <input type="hidden" name="gate_id" value="<?= $gid ?>">
                  <div class="actions">
                    <input class="input" type="text" name="position" placeholder="T153">
                    <button class="btn primary">Add</button>
                  </div>
                </form>
              </td>
              <td style="width:130px;">
                <form method="post" onsubmit="return confirm('Delete gate and all positions?');">
                  <input type="hidden" name="action" value="delete_gate">
                  <input type="hidden" name="gate_id" value="<?= $gid ?>">
                  <button class="btnlink danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$gates): ?><tr><td colspan="3" class="muted">No gates yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php admin_footer(); ?>
