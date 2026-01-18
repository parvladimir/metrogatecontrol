<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);


function out(bool $ok, string $error = ''): void {
  echo json_encode(['ok'=>$ok, 'error'=>$error], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$active = (int)($_POST['active'] ?? 1);
$type = (string)($_POST['type'] ?? 'daily');
$carrier = trim((string)($_POST['carrier'] ?? ''));
$event_time = trim((string)($_POST['event_time'] ?? ''));
$minutes_before = (int)($_POST['minutes_before'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));

if (!in_array($type, ['daily','schedule'], true)) out(false, 'Bad type');
if (!preg_match('/^\d{2}:\d{2}$/', $event_time)) out(false, 'event_time must be HH:MM');
if ($type === 'schedule' && $carrier === '') out(false, 'carrier required for schedule');
if ($message === '') out(false, 'message required');
if ($minutes_before < 0 || $minutes_before > 1440) out(false, 'minutes_before out of range');

$event_time_sql = $event_time . ':00';
$carrier_sql = ($type === 'daily') ? null : $carrier;

try {
  if ($id > 0) {
    $st = $pdo->prepare("
      UPDATE reminder_templates
      SET active = ?, type = ?, carrier = ?, event_time = ?, minutes_before = ?, message = ?
      WHERE id = ?
    ");
    $st->execute([$active, $type, $carrier_sql, $event_time_sql, $minutes_before, $message, $id]);
    out(true);
  } else {
    $st = $pdo->prepare("
      INSERT INTO reminder_templates (active, type, carrier, event_time, minutes_before, message)
      VALUES (?,?,?,?,?,?)
    ");
    $st->execute([$active, $type, $carrier_sql, $event_time_sql, $minutes_before, $message]);
    out(true);
  }
} catch (Throwable $e) {
  out(false, $e->getMessage());
}
