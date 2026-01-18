<?php
declare(strict_types=1);
require __DIR__ . '/app/auth.php';

// Support both new and legacy function names
if (function_exists('logout_user')) {
  logout_user();
} elseif (function_exists('auth_logout_user')) {
  auth_logout_user();
} else {
  // fallback: clear session best-effort
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}

header('Location: /login.php');
exit;
