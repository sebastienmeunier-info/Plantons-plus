<?php

declare(strict_types=1);

namespace Plantons\Services;

use Plantons\Repositories\UserRepository;
use RuntimeException;

final class AuthService
{
    public function __construct(private readonly UserRepository $users, private readonly array $config) {}

    public function currentUser(): ?array
    {
        $this->startSession();
        $id = $_SESSION[$this->config['session_key']] ?? null;
        $user = is_string($id) ? $this->users->findById($id) : null;
        if ($user !== null && ($user['registration_status'] ?? 'approved') !== 'approved') {
            unset($_SESSION[$this->config['session_key']]);
            return null;
        }
        return $user;
    }

    public function login(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || ($user['registration_status'] ?? 'approved') !== 'approved' || !is_string($user['password_hash'] ?? null) || !password_verify($password, $user['password_hash'])) { return null; }
        $this->startSession(); session_regenerate_id(true); $_SESSION[$this->config['session_key']] = $user['id'];
        return $user;
    }

    public function logout(): void
    {
        $this->startSession(); unset($_SESSION[$this->config['session_key']]);
    }

    public function csrfToken(): string
    {
        $this->startSession(); return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public function verifyCsrf(string $token): bool
    {
        $this->startSession(); return $token !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $sessionsPath = (string) ($this->config['sessions_path'] ?? '');
        if ($sessionsPath !== '') {
            if (!is_dir($sessionsPath) && !mkdir($sessionsPath, 0700, true) && !is_dir($sessionsPath)) {
                throw new RuntimeException('Impossible de créer le répertoire de sessions.');
            }
            session_save_path($sessionsPath);
        }
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
        session_start();
    }
}
