<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);


$rows = $pdo->query("
  SELECT
    id, active, type, carrier, event_time, minutes_before, message, created_at, updated_at
  FROM reminder_templates
  ORDER BY active DESC, event_time ASC, type ASC, carrier ASC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
