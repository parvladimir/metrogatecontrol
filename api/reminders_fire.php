<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);


date_default_timezone_set('Europe/Berlin');

function out(array $data): void {
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

$now = new DateTimeImmutable('now');
$today = $now->format('Y-m-d');
$nowTs = $now->getTimestamp();

try {
  // active templates
  $tpl = $pdo->query("
    SELECT id, active, type, carrier, event_time, minutes_before, message
    FROM reminder_templates
    WHERE active = 1
    ORDER BY event_time ASC, id ASC
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (!$tpl) out(['ok'=>true, 'fired'=>[]]);

  // preload schedule set for today (carrier|HH:MM)
  $schedRows = $pdo->prepare("
    SELECT carrier, event_time
    FROM schedule_items
    WHERE work_date = ? AND active = 1
  ");
  $schedRows->execute([$today]);
  $schedSet = [];
  foreach ($schedRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['carrier'].'|'.substr((string)$r['event_time'],0,5);
    $schedSet[$k] = true;
  }

  $fired = [];

  foreach ($tpl as $t) {
    $id = (int)$t['id'];
    $type = (string)$t['type'];
    $carrier = $t['carrier'] !== null ? (string)$t['carrier'] : '';
    $eventHHMM = substr((string)$t['event_time'], 0, 5);
    $before = (int)$t['minutes_before'];
    $msg = (string)$t['message'];

    // schedule template must match today's schedule
    if ($type === 'schedule') {
      if ($carrier === '') continue;
      $k = $carrier.'|'.$eventHHMM;
      if (!isset($schedSet[$k])) continue;
    }

    // compute remindAt = today event_time - minutes_before
    $eventAt = new DateTimeImmutable($today.' '.$eventHHMM.':00');
    $remindAt = $eventAt->modify('-'.$before.' minutes');

    if ($nowTs < $remindAt->getTimestamp()) {
      continue; // not yet
    }

    // try insert into fires (unique per template/day)
    try {
      $st = $pdo->prepare("
        INSERT INTO reminder_fires (template_id, work_date, fired_at)
        VALUES (?, ?, ?)
      ");
      $st->execute([$id, $today, $now->format('Y-m-d H:i:s')]);

      // inserted => it is newly fired
      $fired[] = [
        'template_id' => $id,
        'fired_at' => $now->format('Y-m-d H:i:s'),
        'message' => $msg
      ];
    } catch (Throwable $e) {
      // likely duplicate key => already fired today, ignore
      continue;
    }
  }

  out(['ok'=>true, 'fired'=>$fired]);

} catch (Throwable $e) {
  out(['ok'=>false, 'error'=>$e->getMessage()]);
}
