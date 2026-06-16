<?php

require_once "db.php";
require_once "modules/Files.php";

$files = new Files($pdo);

$token = $_GET['token'] ?? null;

if (!$token) {
    die("Geen token opgegeven");
}

$file = $files->getByToken($token);

if (!$file) {
    die("Bestand niet gevonden");
}

$path = __DIR__ . "/modules/data/" . $file['directory'] . "/" . $file['stored_filename'];

if (!file_exists($path)) {
    die("Bestand bestaat niet meer");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['stored_filename']) . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;