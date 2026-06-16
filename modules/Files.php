<?php

class Files
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function uploadFile(?int $uploaderId, array $file): ?string
    {
        $originalName = $file['name'];
        $size = $file['size'];

        $mime = $this->checkFileType($file);
        $directory = $this->getDirectoryByType($mime);

        $basePath = __DIR__ . "/data/" . $directory;

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        $storedFilename = uniqid('', true) . "_" . basename($originalName);
        $destination = $basePath . "/" . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }

        $shareToken = bin2hex(random_bytes(16));

        $stmt = $this->pdo->prepare("
            INSERT INTO files (uploader_id, stored_filename, file_size, directory, share_token)
            VALUES (:uploader_id, :stored_filename, :file_size, :directory, :share_token)
        ");

        $ok = $stmt->execute([
            'uploader_id' => $uploaderId,
            'stored_filename' => $storedFilename,
            'file_size' => $size,
            'directory' => $directory,
            'share_token' => $shareToken
        ]);

        return $ok ? $shareToken : null;
    }

    public function getByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM files WHERE share_token = :token
        ");
        $stmt->execute(['token' => $token]);

        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        return $file ?: null;
    }

    public function deleteByToken(string $token): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM files WHERE share_token = :token
        ");

        $stmt->execute(['token' => $token]);

        return $stmt->rowCount() > 0;
    }

    public function checkFileType(array $file): string
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