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


$gate_position_id = (int)($_POST['gate_position_id'] ?? 0);
$work_date = $_POST['work_date'] ?? date('Y-m-d');

if ($gate_position_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Bad parameters']);
  exit;
}

$carrier = trim((string)($_POST['carrier'] ?? ''));
$event_at = trim((string)($_POST['event_at'] ?? ''));
$trailer = trim((string)($_POST['trailer_number'] ?? ''));
$tu = trim((string)($_POST['tu_number'] ?? ''));
$seal = trim((string)($_POST['seal_number'] ?? ''));
$remark = trim((string)($_POST['remark'] ?? ''));

$percent = (int)($_POST['load_percent'] ?? 0);
if ($percent < 0) $percent = 0;
if ($percent > 100) $percent = 100;
$percent = (int)(round($percent/5)*5);

$pallets = $_POST['pallets'] ?? '';
$rollis = $_POST['rollis'] ?? '';
$packages = $_POST['packages'] ?? '';

$pallets = ($pallets === '' ? null : (int)$pallets);
$rollis = ($rollis === '' ? null : (int)$rollis);
$packages = ($packages === '' ? null : (int)$packages);

$carrier = ($carrier === '' ? null : $carrier);
$trailer = ($trailer === '' ? null : $trailer);
$tu = ($tu === '' ? null : $tu);
$seal = ($seal === '' ? null : $seal);
$remark = ($remark === '' ? null : $remark);

// datetime-local -> "YYYY-MM-DD HH:MM:SS"
$event_at_sql = null;
if ($event_at !== '') {
  $event_at_sql = str_replace('T',' ', $event_at);
  if (strlen($event_at_sql) === 16) $event_at_sql .= ':00';
}

$sql = "
INSERT INTO statuses
(work_date, gate_position_id, carrier, event_at, trailer_number, tu_number, seal_number, load_percent, pallets, rollis, packages, remark)
VALUES
(:work_date, :gp, :carrier, :event_at, :trailer, :tu, :seal, :pct, :pallets, :rollis, :packages, :remark)
ON DUPLICATE KEY UPDATE
carrier = VALUES(carrier),
event_at = VALUES(event_at),
trailer_number = VALUES(trailer_number),
tu_number = VALUES(tu_number),
seal_number = VALUES(seal_number),
load_percent = VALUES(load_percent),
pallets = VALUES(pallets),
rollis = VALUES(rollis),
packages = VALUES(packages),
remark = VALUES(remark),
updated_at = CURRENT_TIMESTAMP
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':work_date' => $work_date,
  ':gp' => $gate_position_id,
  ':carrier' => $carrier,
  ':event_at' => $event_at_sql,
  ':trailer' => $trailer,
  ':tu' => $tu,
  ':seal' => $seal,
  ':pct' => $percent,
  ':pallets' => $pallets,
  ':rollis' => $rollis,
  ':packages' => $packages,
  ':remark' => $remark,
]);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
