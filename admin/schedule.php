<?php
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require __DIR__ . '/_layout.php';
require __DIR__ . '/../app/config/db.php';

$err = null;
$date = (string)($_GET['date'] ?? ($_POST['date'] ?? date('Y-m-d')));
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

function norm_carrier(string $s): string {
  $s = trim($s);
  if ($s==='') return '';
  $s = mb_strtoupper($s,'UTF-8');
  $s = preg_replace('/[\s\-]+/u','_',$s);
  $s = preg_replace('/[^A-Z0-9_]/u','',$s);
  return $s ?? '';
}

function sync_schedule_item_gates(PDO $pdo, int $schedule_item_id, array $gate_codes): void {
  try {
    // check table exists quickly
    $pdo->query("SELECT 1 FROM schedule_item_gates LIMIT 1");
  } catch (Throwable $e) {
    return;
  }
  $pdo->prepare("DELETE FROM schedule_item_gates WHERE schedule_item_id = ?")->execute([$schedule_item_id]);
  if (!$gate_codes) return;
  $ins = $pdo->prepare("INSERT INTO schedule_item_gates (schedule_item_id, gate_code) VALUES (?, ?)");
  foreach ($gate_codes as $gc) {
    $gc = trim((string)$gc);
    if ($gc==='') continue;
    $ins->execute([$schedule_item_id, $gc]);
  }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  try {
    if ($action === 'create' || $action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $carrier = norm_carrier(trim((string)($_POST['carrier'] ?? '')));
      $time = trim((string)($_POST['event_time'] ?? ''));
      $title = trim((string)($_POST['title'] ?? ''));
      $route = trim((string)($_POST['route'] ?? ''));
      $allowed_gates_arr = $_POST['allowed_gates_arr'] ?? [];
      if (!is_array($allowed_gates_arr)) $allowed_gates_arr = [];
      $allowed_gates_arr = array_values(array_filter(array_map('trim', $allowed_gates_arr), fn($x)=>$x!==''));
      $allowed_gates_arr = array_values(array_unique($allowed_gates_arr));
      sort($allowed_gates_arr);
      $allowed = implode(',', $allowed_gates_arr);
      $note = trim((string)($_POST['note'] ?? ''));
      $active = isset($_POST['active']) ? 1 : 0;

      if ($carrier === '' || $time === '') throw new RuntimeException('Carrier and Time are required.');
      if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ':00';

      if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO schedule_items (work_date, carrier, event_time, title, route, allowed_gates, note, active) VALUES (:d,:c,:t,:ti,:r,:a,:n,:ac)');
        $stmt->execute([':d'=>$date,':c'=>$carrier,':t'=>$time,':ti'=>$title,':r'=>$route,':a'=>$allowed,':n'=>$note,':ac'=>$active]);
      
        $newId = (int)$pdo->lastInsertId();
        sync_schedule_item_gates($pdo, $newId, $allowed_gates_arr);
} else {
        $stmt = $pdo->prepare('UPDATE schedule_items SET work_date=:d, carrier=:c, event_time=:t, title=:ti, route=:r, allowed_gates=:a, note=:n, active=:ac WHERE id=:id');
        $stmt->execute([':d'=>$date,':c'=>$carrier,':t'=>$time,':ti'=>$title,':r'=>$route,':a'=>$allowed,':n'=>$note,':ac'=>$active,':id'=>$id]);
      
        sync_schedule_item_gates($pdo, $id, $allowed_gates_arr);
}
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare('DELETE FROM schedule_items WHERE id=:id')->execute([':id'=>$id]);
      try { $pdo->prepare('DELETE FROM schedule_item_gates WHERE schedule_item_id=:id')->execute([':id'=>$id]); } catch(Throwable $e) {}
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$carriers = [];
$gates = [];
try {
  $carriers = $pdo->query('SELECT code, name FROM carriers WHERE active=1 ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $carriers = [];
$gates = []; }

$items = [];
try {
  $stmt = $pdo->prepare('SELECT id, carrier, event_time, title, route, allowed_gates, note, active FROM schedule_items WHERE work_date=:d ORDER BY event_time ASC, carrier ASC');
  $stmt->execute([':d'=>$date]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $items = [];
  $err = $err ?: 'schedule_items table is not available. Import the database.';
}

admin_header('Schedule', 'schedule');
?>
<?php if ($err): ?><div class="card" style="border-color:rgba(255,107,107,.35);"><div class="danger"><?= htmlspecialchars($err) ?></div></div><?php endif; ?>

<div class="card" style="margin-top:12px;">
  <form method="get" class="actions" style="align-items:end;">
    <div style="min-width:200px;">
      <label class="label">Work date</label>
      <input class="input" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    </div>
    <button class="btn primary">Open</button>
    <div style="opacity:.7;font-size:12px;margin-left:auto;">Items for selected date are shown in the main UI.</div>
  </form>
</div>

<div class="row" style="margin-top:12px;">
  <div class="col-5">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Add schedule item</div>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
        <label class="label">Carrier</label>
        <select class="input" name="carrier">
          <option value="">— select —</option>
          <?php foreach ($carriers as $c): ?>
            <option value="<?= htmlspecialchars((string)$c['code']) ?>"><?= htmlspecialchars((string)$c['code'] . ' — ' . (string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="margin-top:8px;" class="row">
          <div class="col-6"><label class="label">Time</label><input class="input" type="time" name="event_time" required></div>
          <div class="col-6"><label class="label">Active</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px;opacity:.85;"><input type="checkbox" name="active" checked> Enabled</label></div>
          <div class="col-12"><label class="label">Title</label><input class="input" type="text" name="title"></div>
          <div class="col-12"><label class="label">Route</label><input class="input" type="text" name="route"></div>
          <div class="col-12"><label class="label">Allowed gates</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;padding:6px 0;">
              <?php foreach($gates as $g){ $gc=(string)($g['gate_code']??''); if($gc==='') continue; ?>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid rgba(255,255,255,.12);border-radius:999px;">
                  <input type="checkbox" name="allowed_gates_arr[]" value="<?= htmlspecialchars($gc) ?>"/>
                  <span><?= htmlspecialchars($gc) ?></span>
                </label>
              <?php } ?>
            </div>
          </div>
          <div class="col-12"><label class="label">Note</label><textarea class="input" name="note"></textarea></div>
        </div>
        <button class="btn primary" style="width:100%;margin-top:12px;">Create</button>
      </form>
      <?php if (!$carriers): ?>
        <div style="margin-top:10px;opacity:.7;font-size:12px;">Tip: create carriers first to use the dropdown.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-7">
    <div class="card">
      <div style="font-weight:900;margin-bottom:10px;">Items on <?= htmlspecialchars($date) ?></div>
      <table>
        <thead><tr><th>Time</th><th>Carrier</th><th>Route / title</th><th>Gates</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td style="width:92px;"><?= htmlspecialchars(substr((string)$it['event_time'],0,5)) ?></td>
              <td style="width:120px;"><?= htmlspecialchars((string)$it['carrier']) ?></td>
              <td><?= htmlspecialchars((string)$it['route'] . (($it['title']!=='') ? ' — ' . $it['title'] : '')) ?></td>
              <td style="width:190px;"><?= htmlspecialchars((string)$it['allowed_gates']) ?></td>
              <td style="width:70px;"><?= ((int)$it['active']===1)?'Yes':'No' ?></td>
              <td style="width:260px;">
                <details>
                  <summary style="cursor:pointer;opacity:.85;">Edit</summary>
                  <form method="post" style="margin-top:10px;">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                    <div class="row">
                      <div class="col-6"><label class="label">Carrier</label><input class="input" type="text" name="carrier" value="<?= htmlspecialchars((string)$it['carrier']) ?>"></div>
                      <div class="col-6"><label class="label">Time</label><input class="input" type="time" name="event_time" value="<?= htmlspecialchars(substr((string)$it['event_time'],0,5)) ?>"></div>
                      <div class="col-12"><label class="label">Route</label><input class="input" type="text" name="route" value="<?= htmlspecialchars((string)$it['route']) ?>"></div>
                      <div class="col-12"><label class="label">Title</label><input class="input" type="text" name="title" value="<?= htmlspecialchars((string)$it['title']) ?>"></div>
                      <div class="col-12"><label class="label">Allowed gates</label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:6px 0;">
                      <?php
                        $sel = array_filter(array_map('trim', explode(',', (string)($it['allowed_gates'] ?? ''))));
                        $sel = array_flip($sel);
                        foreach($gates as $g){
                          $gc = (string)($g['gate_code'] ?? '');
                          if($gc==='') continue;
                          $checked = isset($sel[$gc]);
                      ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid rgba(255,255,255,.12);border-radius:999px;">
                          <input type="checkbox" name="allowed_gates_arr[]" value="<?= htmlspecialchars($gc) ?>" <?= $checked?'checked':'' ?>/>
                          <span><?= htmlspecialchars($gc) ?></span>
                        </label>
                      <?php } ?>
                    </div>
                  </div>
                      <div class="col-12"><label class="label">Note</label><textarea class="input" name="note"><?= htmlspecialchars((string)$it['note']) ?></textarea></div>
                      <div class="col-12" style="display:flex;gap:10px;align-items:center;margin-top:6px;">
                        <label style="display:flex;gap:8px;align-items:center;opacity:.85;"><input type="checkbox" name="active" <?= ((int)$it['active']===1)?'checked':'' ?>> Active</label>
                        <button class="btn primary" style="margin-left:auto;">Save</button>
                      </div>
                    </div>
                  </form>
                  <form method="post" onsubmit="return confirm('Delete schedule item?');" style="margin-top:8px;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                    <button class="btnlink danger" type="submit">Delete</button>
                  </form>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?><tr><td colspan="6" class="muted">No items for this date.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php admin_footer(); ?>
