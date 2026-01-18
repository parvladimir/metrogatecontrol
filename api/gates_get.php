<?php
declare(strict_types=1);
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);


header('Content-Type: application/json; charset=utf-8');

function norm_carrier(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $s = mb_strtoupper($s, 'UTF-8');
  $s = preg_replace('/[\s\-]+/u', '_', $s);
  $s = preg_replace('/[^A-Z0-9_]/u', '', $s);
  return $s ?? '';
}


$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$sql = "
SELECT
  g.gate_code,
  gp.id AS gate_position_id,
  gp.position,
  s.carrier,
  s.event_at,
  s.trailer_number,
  s.tu_number,
  s.seal_number,
  s.load_percent,
  s.pallets,
  s.rollis,
  s.packages,
  s.remark
FROM gates g
JOIN gate_positions gp ON gp.gate_id = g.id
LEFT JOIN statuses s
  ON s.gate_position_id = gp.id AND s.work_date = ?
ORDER BY g.gate_code ASC, gp.position ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$date]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$map = [];
foreach ($rows as $r) {
  $gate = $r['gate_code'];
  if (!isset($map[$gate])) {
    $map[$gate] = ['gate_code'=>$gate, 'slots'=>[]];
  }
  $map[$gate]['slots'][] = [
    'gate_position_id' => (int)$r['gate_position_id'],
    'position' => (int)$r['position'],
    'carrier' => norm_carrier((string)($r['carrier'] ?? '')),
    'event_at' => $r['event_at'],
    'trailer_number' => $r['trailer_number'],
    'tu_number' => $r['tu_number'],
    'seal_number' => $r['seal_number'],
    'load_percent' => (int)($r['load_percent'] ?? 0),
    'pallets' => $r['pallets'],
    'rollis' => $r['rollis'],
    'packages' => $r['packages'],
    'remark' => $r['remark'],
  ];
}

echo json_encode(array_values($map), JSON_UNESCAPED_UNICODE);
