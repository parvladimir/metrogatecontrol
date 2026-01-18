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


try {
  $rows = $pdo->query("SELECT id, code, name, logo_path, active, sort_order
                       FROM carriers
                       ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

  $out = array_map(function($r) {
    return [
      'id' => (int)$r['id'],
      'code' => norm_carrier((string)$r['code']),
      'name' => (string)$r['name'],
      'logo' => ($r['logo_path'] ?? null) ? (string)$r['logo_path'] : null,
      'active' => ((int)$r['active'] === 1),
      'sortOrder' => (int)$r['sort_order'],
    ];
  }, $rows);

  echo json_encode(['ok' => true, 'data' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
