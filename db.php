<?php
$env = parse_ini_file('.env');

$dbData = [
    "host" => $env["DB_HOST"],
    "name" => $env["DB_NAME"],
    "user" => $env["DB_USER"],
    "password" => $env["DB_PASSWORD"]
];

try {
    $pdo = new PDO("mysql:host={$dbData['host']};dbname={$dbData['name']}", $dbData['user'], $dbData['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
