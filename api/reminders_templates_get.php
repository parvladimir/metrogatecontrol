<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);


try {
  $rows = $pdo->query("SELECT * FROM reminder_templates ORDER BY type, carrier, event_time")->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
