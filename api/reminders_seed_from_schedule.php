<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);
require_admin(true);


try {
  // вытащим уникальные carrier+event_time из schedule
  $sql = "SELECT DISTINCT carrier, event_time
          FROM schedule
          WHERE active = 1 AND carrier IS NOT NULL AND carrier <> '' AND event_time IS NOT NULL";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  $ins = $pdo->prepare("
    INSERT INTO reminder_templates (active, carrier, event_time, minutes_before, message, type)
    VALUES (1, :carrier, :event_time, 40, :msg, 'schedule')
  ");

  $exists = $pdo->prepare("
    SELECT id FROM reminder_templates
    WHERE type='schedule' AND carrier=:carrier AND event_time=:event_time
    LIMIT 1
  ");

  $created = 0;
  foreach ($rows as $r) {
    $carrier = (string)$r['carrier'];
    $eventTime = (string)$r['event_time']; // TIME
    $exists->execute([':carrier'=>$carrier, ':event_time'=>$eventTime]);
    if ($exists->fetchColumn()) continue;

    $msg = "Pickup soon: {$carrier} " . substr($eventTime,0,5) . " (40 min). Close TU / docs.";
    $ins->execute([':carrier'=>$carrier, ':event_time'=>$eventTime, ':msg'=>$msg]);
    $created++;
  }

  echo json_encode(['ok'=>true,'created'=>$created], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
