<?php

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /** Ensure session is started with secure defaults (idempotent). */
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure cookie flags — must run BEFORE session_start()
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', '1800'); // 30 min

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,   // true on HTTPS; false on local HTTP
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /** Get (or create) CSRF token for the current session. */
    public static function token(): string
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Validate token from form (_token) or header (X-CSRF-Token).
     * Use on every POST/PATCH/DELETE after login.
     */
    public static function validate(?string $token = null): bool
    {
        self::ensureSession();

        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_POST['_token']
                ?? '';
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if ($sessionToken === '' || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /** Rotate token (call after successful login with session_regenerate_id). */
    public static function rotate(): string
    {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }
}