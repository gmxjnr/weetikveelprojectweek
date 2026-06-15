<?php
require_once '../db.php';

class User {
    private $pdo;
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function getIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function isRateLimited(string $action): bool {
        $key = "rate_limit:{$action}:" . $this->getIp();
        $attempts = apcu_fetch($key) ?: 0;
        return $attempts >= self::MAX_ATTEMPTS;
    }

    private function recordAttempt(string $action): void {
        $key = "rate_limit:{$action}:" . $this->getIp();
        $attempts = apcu_fetch($key) ?: 0;

        if ($attempts === 0) {
            apcu_store($key, 1, self::LOCKOUT_MINUTES * 60);
        } else {
            apcu_inc($key);
        }
    }

    private function clearAttempts(string $action): void {
        apcu_delete("rate_limit:{$action}:" . $this->getIp());
    }

    public function login($usernameOrEmail, $password) {
        if ($this->isRateLimited('login')) {
            throw new \RuntimeException('Te veel pogingen. Probeer het over ' . self::LOCKOUT_MINUTES . ' minuten opnieuw.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $usernameOrEmail, 'email' => $usernameOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $this->clearAttempts('login');
            return $user;
        }

        $this->recordAttempt('login');
        return false;
    }

    public function register($username, $email, $password) {
        if ($this->isRateLimited('register')) {
            throw new \RuntimeException('Te veel registraties. Probeer het over ' . self::LOCKOUT_MINUTES . ' minuten opnieuw.');
        }

        $this->recordAttempt('register');

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password, created_at) VALUES (:username, :email, :password, NOW())');
        return $stmt->execute(['username' => $username, 'email' => $email, 'password' => $hashedPassword]);
    }
}