<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);
require_admin(true);


function post(string $k, $def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def; }

try {
  $id = (int)post('id', '0');
  $active = (int)post('active', '1') ? 1 : 0;
  $type = post('type','schedule');
  if (!in_array($type, ['schedule','daily'], true)) $type = 'schedule';

  $carrier = post('carrier','');
  if ($carrier === '') $carrier = null; // daily может быть без carrier

  $event_time = post('event_time','');
  if (!preg_match('/^\d{2}:\d{2}$/', $event_time)) {
    throw new Exception('event_time must be HH:MM');
  }
  $event_time .= ':00';

  $minutes_before = (int)post('minutes_before','40');
  if ($minutes_before < 0 || $minutes_before > 1440) throw new Exception('minutes_before out of range');

  $message = post('message','');
  if ($message === '') throw new Exception('message required');
  if (mb_strlen($message) > 255) $message = mb_substr($message, 0, 255);

  if ($id > 0) {
    $st = $pdo->prepare("
      UPDATE reminder_templates
      SET active=:active, carrier=:carrier, event_time=:event_time, minutes_before=:mb, message=:msg, type=:type
      WHERE id=:id
      LIMIT 1
    ");
    $st->execute([
      ':active'=>$active, ':carrier'=>$carrier, ':event_time'=>$event_time,
      ':mb'=>$minutes_before, ':msg'=>$message, ':type'=>$type, ':id'=>$id
    ]);
    echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
  } else {
    $st = $pdo->prepare("
      INSERT INTO reminder_templates (active, carrier, event_time, minutes_before, message, type)
      VALUES (:active, :carrier, :event_time, :mb, :msg, :type)
    ");
    $st->execute([
      ':active'=>$active, ':carrier'=>$carrier, ':event_time'=>$event_time,
      ':mb'=>$minutes_before, ':msg'=>$message, ':type'=>$type
    ]);
    echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
  }
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
