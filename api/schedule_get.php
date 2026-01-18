<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);

function norm_carrier(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $s = mb_strtoupper($s, 'UTF-8');
  $s = preg_replace('/[\s\-]+/u', '_', $s);
  $s = preg_replace('/[^A-Z0-9_]/u', '', $s);
  return $s ?? '';
}

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  $date = date('Y-m-d');
}

$default_time_corridors = [
  '06:30','07:30','08:30','09:30','10:30','11:30','12:30','13:30','14:30','15:30','16:30','17:30','18:30'
];

try {
  $carriers = $pdo->query("SELECT code, name, active, sort_order FROM carriers WHERE active=1 ORDER BY sort_order ASC, name ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $carriers = [];
}

// schedule_items has existed in a few schema variants during development.
// We try the newest variant first (underscore columns), then fall back to the legacy one.
$rows = [];
try {
  $stmt = $pdo->prepare("SELECT id, work_date, carrier_code, event_time, title, route, note, active
                         FROM schedule_items
                         WHERE work_date = :d AND active=1
                         ORDER BY event_time ASC, carrier_code ASC");
  $stmt->execute([':d'=>$date]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  try {
    $stmt = $pdo->prepare("SELECT id, work_date, carrier, event_time, title, route, allowed_gates, note, active
                           FROM schedule_items
                           WHERE work_date = :d AND active=1
                           ORDER BY event_time ASC, carrier ASC");
    $stmt->execute([':d'=>$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e2) {
    $rows = [];
  }
}

$ids = array_map(fn($r)=>(int)$r['id'], $rows);
$gatesById = [];

// If schedule_item_gates exists, prefer it over CSV in schedule_items.allowed_gates
if ($ids) {
  try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT schedule_item_id, gate_code FROM schedule_item_gates WHERE schedule_item_id IN ($in) ORDER BY gate_code ASC");
    $st->execute($ids);
    while ($g = $st->fetch(PDO::FETCH_ASSOC)) {
      $sid = (int)$g['schedule_item_id'];
      $gc = (string)$g['gate_code'];
      if ($gc==='') continue;
      if (!isset($gatesById[$sid])) $gatesById[$sid] = [];
      $gatesById[$sid][] = $gc;
    }
  } catch (Throwable $e) {
    $gatesById = [];
  }
}

$items = [];
$times = [];

foreach ($rows as $r) {
  $id = (int)$r['id'];
  $carrier_code = norm_carrier((string)($r['carrier_code'] ?? $r['carrier'] ?? ''));
  $t = substr((string)$r['event_time'], 0, 5);
  if ($t !== '') $times[$t] = true;

  $gates = $gatesById[$id] ?? [];
  if (!$gates) {
    // fallback: parse CSV
    $ag = (string)($r['allowed_gates'] ?? '');
    if ($ag !== '') {
      $parts = preg_split('/[;,\s]+/', $ag, -1, PREG_SPLIT_NO_EMPTY);
      foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $gates[] = $p;
      }
    }
  }

  $items[] = [
    'id' => $id,
    'work_date' => (string)$r['work_date'],
    'carrier_code' => $carrier_code,
    'event_time' => $t,
    'title' => (string)($r['title'] ?? ''),
    'route' => (string)($r['route'] ?? ''),
    'allowed_gates' => array_values(array_unique($gates)),
    'note' => (string)($r['note'] ?? ''),
    'active' => (int)($r['active'] ?? 1),
  ];
}

$time_corridors = array_keys($times);
// Keep the full default list so the UI can always show the available planning corridors,
// even if only a subset has schedule_items rows for the selected date.
$time_corridors = array_values(array_unique(array_merge($default_time_corridors, $time_corridors)));
sort($time_corridors);

echo json_encode([
  'ok' => true,
  'date' => $date,
  'data' => $items,
  'meta' => [
    'time_corridors' => array_values($time_corridors),
    'carriers' => array_map(function($c){
      return [
        'code' => norm_carrier((string)($c['code'] ?? '')),
        'name' => (string)($c['name'] ?? ''),
        'sort_order' => (int)($c['sort_order'] ?? 0),
        'active' => (int)($c['active'] ?? 1),
      ];
    }, $carriers),
  ],
], JSON_UNESCAPED_UNICODE);
