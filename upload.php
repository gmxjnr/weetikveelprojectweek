<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header('location: login.php');
    exit;
}

require_once "db.php";
require_once "modules/Files.php";

$files = new Files($pdo);

$message = "";
$shareLink = null;

if (isset($_FILES['file'])) {

    $file = $_FILES['file'];

    if ($file['error'] === UPLOAD_ERR_OK) {

        $uploaderId = $_SESSION['user_id'] ?? null;

        $token = $files->uploadFile($uploaderId, $file);

        if ($token) {
            $message = "Upload gelukt!";
            $shareLink = "download.php?token=" . $token;
        } else {
            $message = "Upload mislukt!";
        }

    } else {
        $message = "Upload error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload File</title>
</head>
<body>

<h2>Upload file</h2>

<?php if ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($shareLink): ?>
    <p>Deelbare link:</p>
    <a href="<?= htmlspecialchars($shareLink) ?>" target="_blank"> <?= htmlspecialchars($shareLink) ?> </a>
<?php endif; ?>

<form action="upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button type="submit">Upload</button>
</form>

<a href="logout.php">Log out</a>
</body>
</html>