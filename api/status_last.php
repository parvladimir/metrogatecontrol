<?php
declare(strict_types=1);
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);

header('Content-Type: application/json');

$gp = (int)($_GET['gate_position_id'] ?? 0);
if ($gp <= 0) { echo json_encode(['ok'=>false]); exit; }

$stmt = $pdo->prepare("SELECT * FROM statuses WHERE gate_position_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$gp]);
$row = $stmt->fetch();
echo json_encode(['ok'=>true,'data'=>$row]);
