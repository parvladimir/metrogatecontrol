<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);
require_admin(true);


try {
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($id <= 0) throw new Exception('id required');

  $st = $pdo->prepare("DELETE FROM reminder_templates WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
