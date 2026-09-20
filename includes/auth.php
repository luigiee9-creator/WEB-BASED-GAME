<?php
require_once __DIR__ . '/config.php';

// ─── Session Bootstrap ───────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Auth Checks ─────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isStudent(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'student';
}

/** Redirects to login if not authenticated. */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** Redirects to dashboard if not admin. */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/dashboard.php?error=access_denied');
        exit;
    }
}

/** Logs a user in by populating session data. */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $user['role'];
}

/** Destroys the session and redirects to login. */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?logout=1');
    exit;
}

// ─── CSRF ────────────────────────────────────────────────────────────────────

/** Returns (and creates if needed) a CSRF token for the current session. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validates that the submitted CSRF token matches the session token. */
function verifyCsrf(string $submitted): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $submitted);
}

/** Outputs a hidden CSRF input field. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
