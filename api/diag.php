<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/auth.php';
require_login(true);
require_admin(true);

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "PHP OK\n";
echo "PHP Version: " . PHP_VERSION . "\n";

$path = __DIR__ . '/../app/config/db.php';
echo "db.php path: $path\n";
echo "db.php exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";

require $path;

try {
  $pdo->query("SELECT 1");
  echo "DB OK\n";
} catch (Throwable $e) {
  echo "DB ERROR: " . $e->getMessage() . "\n";
}
