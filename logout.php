<?php
require_once "modules/User.php";

$user = new User($pdo);
$user->logout();
header('location: login.php');
exit;