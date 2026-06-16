<?php

class Files
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getFiles($filename)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE stored_filename = :filename");
        $stmt->execute(['filename' => $filename]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function downloadFile($filename): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE stored_filename = :filename');
        $stmt->execute(['filename' => $filename]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteFiles($filename): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM files WHERE stored_filename = :filename');
        $stmt->execute(['filename' => $filename]);
        return $stmt->rowCount() > 0;
    }

    public function uploadFile($uploaderId, $file): bool
    {
        $originalName = $file['name'];
        $size = $file['size'];

        $mime = $this->checkFileType($file);

        $directory = $this->getDirectoryByType($mime);

        $basePath = __DIR__ . "/data/" . $directory;

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        $storedFilename = uniqid() . "_" . basename($originalName);

        $destination = $basePath . "/" . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return false;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO files (uploader_id, stored_filename, file_size, directory)
            VALUES (:uploader_id, :stored_filename, :file_size, :directory)
        ');

        return $stmt->execute([
            'uploader_id' => $uploaderId,
            'stored_filename' => $storedFilename,
            'file_size' => $size,
            'directory' => $directory
        ]);
    }

    public function checkFileType($file): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    }

    private function getDirectoryByType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'text/')  => 'text',
            default => 'others',
        };
    }
}