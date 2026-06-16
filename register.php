<?php
require_once 'modules/User.php';
require_once 'db.php';

$user = new User($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST"){
    try {
        $userRegistered = $user->register($_POST['username'], $_POST['email'], $_POST['password']);
        if ($userRegistered){
            echo "Register successfull!";
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}
