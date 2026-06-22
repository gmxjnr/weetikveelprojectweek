<?php
class Logs
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function logAction($userId, $action, $details)
    {
        $stmt = $this->pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (:user_id, :action, :details)");
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':details' => $details
        ]);
    }

    public function getLogByAction($action)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM logs WHERE action = :action");
        $stmt->execute([':action' => $action]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllLogs()
    {
        $stmt = $this->pdo->query("SELECT * FROM logs ORDER BY timestamp DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogsByUser($userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM logs WHERE user_id = :user_id ORDER BY timestamp DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
