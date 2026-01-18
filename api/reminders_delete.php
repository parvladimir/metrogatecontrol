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
if ($id <= 0) out(false, 'Bad id');

try {
  $st = $pdo->prepare("DELETE FROM reminder_templates WHERE id = ?");
  $st->execute([$id]);
  out(true);
} catch (Throwable $e) {
  out(false, $e->getMessage());
}
