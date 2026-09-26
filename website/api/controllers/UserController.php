<?php

final class UserController extends ApiController
{
    public function login(): never
    {
        $data = $this->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = db_fetch_one(
            "SELECT * FROM users_tbl WHERE email = ?",
            [$data['email']],
        );

        if (!$user || !password_verify($data['password'], $user['password'])) {
            $this->error('Invalid credentials', 401);
        }

        $token = JwtAuth::generate([
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ]);

        $this->success(
            data: [
                'token' => $token,
                'user'  => [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                ],
            ],
            message: 'Login successful',
        );
    }

    public function register(): never
    {
        $data = $this->validate([
            'name'     => 'required|string|min:2',
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        $existing = db_fetch_one(
            "SELECT id FROM users_tbl WHERE email = ?",
            [$data['email']],
        );

        if ($existing) {
            $this->error('Email already registered', 409);
        }

        db_execute(
            "INSERT INTO users_tbl (name, email, password, status) VALUES (?, ?, ?, 1)",
            [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_BCRYPT)],
        );

        $userId = db_last_insert_id();

        $token = JwtAuth::generate([
            'id'    => $userId,
            'email' => $data['email'],
            'role'  => 'user',
        ]);

        ApiResponse::created(
            data: [
                'token' => $token,
                'user'  => [
                    'id'    => $userId,
                    'name'  => $data['name'],
                    'email' => $data['email'],
                ],
            ],
            message: 'Registration successful',
        );
    }

    public function index(): never
    {
        $users = db_fetch_all(
            "SELECT id, name, email, role FROM users_tbl WHERE status = 1",
        );

        $this->success(data: $users);
    }

    public function show(): never
    {
        $id = $this->param('id');

        $user = db_fetch_one(
            "SELECT id, name, email, role FROM users_tbl WHERE id = ?",
            [$id],
        );

        if (!$user) {
            ApiResponse::notFound('User not found');
        }

        $this->success(data: $user);
    }

    public function update(): never
    {
        $id = $this->param('id');

        $data = $this->validate([
            'name'  => 'string|min:2',
            'email' => 'email',
        ]);

        $user = db_fetch_one(
            "SELECT id FROM users_tbl WHERE id = ?",
            [$id],
        );

        if (!$user) {
            ApiResponse::notFound('User not found');
        }

        $sets = [];
        $params = [];

        foreach ($data as $field => $value) {
            if ($value !== null) {
                $sets[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if ($sets) {
            $params[] = $id;
            db_execute(
                "UPDATE users_tbl SET " . implode(', ', $sets) . " WHERE id = ?",
                $params,
            );
        }

        $updated = db_fetch_one(
            "SELECT id, name, email, role FROM users_tbl WHERE id = ?",
            [$id],
        );

        $this->success(data: $updated, message: 'User updated');
    }

    public function destroy(): never
    {
        $id = $this->param('id');

        $user = db_fetch_one(
            "SELECT id FROM users_tbl WHERE id = ?",
            [$id],
        );

        if (!$user) {
            ApiResponse::notFound('User not found');
        }

        db_execute("DELETE FROM users_tbl WHERE id = ?", [$id]);

        $this->success(message: 'User deleted');
    }
}
