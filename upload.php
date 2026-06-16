<?php
session_start();

require_once "modules/Files.php";
require_once "db.php";

$files = new Files($pdo);

$message = "";

if (isset($_FILES['file'])) {

    $file = $_FILES['file'];

    if ($file['error'] === UPLOAD_ERR_OK) {

        $uploaderId = $_SESSION['user_id'] ?? null;

        $success = $files->uploadFile($uploaderId, $file);

        $message = $success ? "Upload gelukt!" : "Upload mislukt!";
    } else {
        $message = "Upload error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
</head>
<body>

<?php if ($message): ?>
    <p><?= $message ?></p>
<?php endif; ?>

<form action="upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="file" id="file">
    <input type="submit" value="Upload File" name="submit">
</form>

</body>
</html>
