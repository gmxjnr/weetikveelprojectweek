<?php
require_once 'modules/User.php';
require_once 'modules/Logs.php';
require_once 'db.php';

$user = new User($pdo);
$logs = new Logs($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST"){
    try {
        $userRegistered = $user->register($_POST['username'], $_POST['email'], $_POST['password']);
        if ($userRegistered){
            echo "Register successfull!";
            $userId = is_array($userRegistered) ? ($userRegistered['id'] ?? null) : null;
            $logs->logAction($userId, 'user_registration', "New user registered: {$_POST['username']}");
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}