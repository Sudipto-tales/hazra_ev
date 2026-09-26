<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../../core/Auth.php';

final class AdminAuthController extends V1Controller
{
    public function me(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!Auth::isAuthenticated() && empty($_SESSION['user_id']) && empty($_SESSION['admin_logged_in'])) {
            Envelope::fail('UNAUTHORIZED', 'Not signed in', 401);
        }

        $user = [
            'id' => $_SESSION['user_id'] ?? 'admin-1',
            'name' => $_SESSION['user_name'] ?? 'Admin User',
            'email' => $_SESSION['user_email'] ?? 'admin@hazraev.com',
            'role' => $_SESSION['user_role'] ?? 'admin',
            'permissions' => new stdClass(),
        ];

        Envelope::ok(['user' => $user, 'permissions' => new stdClass()]);
    }

    public function login(): never
    {
        $email = trim((string) ($this->input('email') ?? ''));
        $password = (string) ($this->input('password') ?? '');

        if ($email === '' || $password === '') {
            Envelope::invalid('Email and password are required', 'email');
        }

        Csrf::ensureSession();

        $user = Users::byEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!(int) $user['active']) {
                Envelope::forbidden('This account is deactivated');
            }

            if ($user['role'] !== 'admin') {
                Envelope::forbidden('Admin role required');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            Csrf::rotate();

            Envelope::ok(['user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]]);
        }

        Envelope::fail('INVALID_CREDENTIALS', 'Email or password is incorrect', 401);
    }

    public function logout(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();

        Envelope::ok(['signedOut' => true]);
    }

    public function changePassword(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['user_id'])) {
            Envelope::unauthorized('Admin sign-in required');
        }

        $body = ApiRequest::body();
        $currentPassword = (string) ($body['currentPassword'] ?? $body['current_password'] ?? '');
        $newPassword = (string) ($body['newPassword'] ?? $body['new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            Envelope::invalid('Current password and new password are required');
        }

        if (strlen($newPassword) < 6) {
            Envelope::invalid('New password must be at least 6 characters long', 'newPassword');
        }

        $userId = $_SESSION['user_id'];
        $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$userId]);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            Envelope::fail('INVALID_PASSWORD', 'Current password is incorrect', 400);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $now = Wire::now();

        db_execute("UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?", [$newHash, $now, $userId]);

        Envelope::ok(['message' => 'Password updated successfully']);
    }
}
