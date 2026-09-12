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

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Try standard DB auth
        $res = Auth::login($email, $password);
        if ($res['status']) {
            $_SESSION['admin_logged_in'] = true;
            $user = [
                'id' => $_SESSION['user_id'] ?? 'admin-1',
                'name' => $_SESSION['user_name'] ?? 'Admin User',
                'email' => $email,
                'role' => 'admin',
            ];
            Envelope::ok(['user' => $user]);
        }

        // Fallback check against users_tbl without email verification restriction for admin
        $userRow = db_fetch_one("SELECT * FROM users_tbl WHERE email = ?", [$email]);
        if ($userRow && password_verify($password, $userRow['password'])) {
            $_SESSION['user_id'] = $userRow['id'];
            $_SESSION['user_email'] = $userRow['email'];
            $_SESSION['user_name'] = $userRow['name'] ?? 'Admin';
            $_SESSION['user_role'] = 'admin';
            $_SESSION['admin_logged_in'] = true;

            Envelope::ok(['user' => [
                'id' => $userRow['id'],
                'name' => $userRow['name'] ?? 'Admin',
                'email' => $email,
                'role' => 'admin',
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
}
