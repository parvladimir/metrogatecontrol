<?php
declare(strict_types=1);

/**
 * Session-based authentication + RBAC.
 *
 * Session keys:
 *  - user_id
 *  - username
 *  - role  (admin|user)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.use_strict_mode', '1');

  // Cookie hardening (best-effort; some hosts may override)
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

/**
 * PHP 7.x compatibility: str_starts_with exists only in PHP 8+.
 */
function _starts_with(string $haystack, string $needle): bool {
  if ($needle === '') return true;
  return substr($haystack, 0, strlen($needle)) === $needle;
}

function _is_api_request(bool $apiFlag): bool {
  if ($apiFlag) return true;
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  return _starts_with($uri, '/api/');
}

function current_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_username(): ?string {
  return isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
}

function current_role(): string {
  return isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
}

function is_logged_in(): bool {
  return current_user_id() !== null;
}

function is_admin(): bool {
  return is_logged_in() && current_role() === 'admin';
}

/**
 * Require login.
 * - For pages: redirect to /login.php
 * - For API: JSON 401
 */
function require_login(bool $api = false): void {
  if (is_logged_in()) return;

  if (_is_api_request($api)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
    exit;
  }

  $next = $_SERVER['REQUEST_URI'] ?? '/';
  header('Location: /login.php?next=' . rawurlencode($next));
  exit;
}

/**
 * Require admin role.
 * - For pages: 403 page
 * - For API: JSON 403
 */
function require_admin(bool $api = false): void {
  if (is_admin()) return;

  if (_is_api_request($api)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit;
  }

  http_response_code(403);
  echo '<!doctype html><meta charset="utf-8"><title>403</title><h1>403 Forbidden</h1>';
  exit;
}

function login_user(int $userId, string $username, string $role): void {
  session_regenerate_id(true);
  $_SESSION['user_id'] = $userId;
  $_SESSION['username'] = $username;
  $_SESSION['role'] = $role;
}

function logout_user(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'] ?? '/',
      $params['domain'] ?? '',
      (bool)($params['secure'] ?? false),
      (bool)($params['httponly'] ?? true)
    );
  }
  session_destroy();
}


// Backward-compatible aliases (older code)
function auth_require_login(): void { require_login(); }
function auth_require_admin(): void { require_admin(); }
function auth_login_user(int $user_id, string $username, string $role): void { login_user($user_id, $username, $role); }
function auth_logout_user(): void { logout_user(); }
