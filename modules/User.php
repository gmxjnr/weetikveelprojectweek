<?php

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class User
{
    private $pdo;

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    private function getIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private function isRateLimited(string $action): bool
    {
        $key = "rate_limit:{$action}:" . $this->getIp();

        if (!isset($_SESSION[$key])) {
            return false;
        }

        if ($_SESSION[$key]['expires'] < time()) {
            unset($_SESSION[$key]);
            return false;
        }

        return $_SESSION[$key]['attempts'] >= self::MAX_ATTEMPTS;
    }

    private function recordAttempt(string $action): void
    {
        $key = "rate_limit:{$action}:" . $this->getIp();

        if (
            !isset($_SESSION[$key]) ||
            $_SESSION[$key]['expires'] < time()
        ) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'expires' => time() + (self::LOCKOUT_MINUTES * 60)
            ];
        } else {
            $_SESSION[$key]['attempts']++;
        }
    }

    private function clearAttempts(string $action): void
    {
        $key = "rate_limit:{$action}:" . $this->getIp();
        unset($_SESSION[$key]);
    }

    public function login(string $usernameOrEmail, string $password)
    {
        if ($this->isRateLimited('login')) {
            throw new RuntimeException(
                'Te veel inlogpogingen. Probeer het over '
                . self::LOCKOUT_MINUTES
                . ' minuten opnieuw.'
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = :username OR email = :email'
        );

        $stmt->execute([
            'username' => $usernameOrEmail,
            'email' => $usernameOrEmail
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->clearAttempts('login');
            $_SESSION['user_id'] = $user['id'];
            return $user;
        }

        $this->recordAttempt('login');
        return false;
    }

    public function register(
        string $username,
        string $email,
        string $password
    ): bool {

        if ($this->isRateLimited('register')) {
            throw new RuntimeException(
                'Te veel registraties. Probeer het over '
                . self::LOCKOUT_MINUTES
                . ' minuten opnieuw.'
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email'
        );

        $stmt->execute([
            'username' => $username,
            'email' => $email
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException(
                'Gebruikersnaam of e-mailadres bestaat al.'
            );
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash)
             VALUES (:username, :email, :password)'
        );

        $success = $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        if (!$success) {
            $this->recordAttempt('register');
            return false;
        }

        $this->clearAttempts('register');
        return true;
    }
}