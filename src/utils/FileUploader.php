<?php
// src/utils/FileUploader.php

class FileUploader {
    private $uploadDir;
    private $baseUrl;

    public function __construct() {
        // Resolve upload dir: D:\suvinhome\src/uploads
        $this->uploadDir = __DIR__ . '/../uploads';
        $this->createDirectoryIfNotExists($this->uploadDir);
        
        // Resolve base URL dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
        $this->baseUrl = "$protocol://$host/uploads";
    }

    private function createDirectoryIfNotExists($path) {
        if (!file_exists($path)) {
            // ລອງສ້າງໂຟນເດີ
            if (!mkdir($path, 0755, true)) {
                // ຖ້າສ້າງບໍ່ໄດ້, ລອງໃຊ້ exec
                exec("mkdir -p " . escapeshellarg($path));
            }
        }
        
        // ຕັ້ງສິດໃຫ້ www-data ສາມາດຂຽນໄດ້
        if (function_exists('chown')) {
            @chown($path, 'www-data');
        }
        @chmod($path, 0755);
    }

    public function upload($file, $options = []) {
        // Handle if options is a string (e.g. 'uploads/avatars/' or 'uploads/products/')
        $subDir = '';
        if (is_string($options)) {
            $subDir = $options;
            $options = [];
        } elseif (isset($options['upload_dir'])) {
            $subDir = $options['upload_dir'];
        }

        // Clean up subDir. If it starts with 'uploads/', remove it because uploadDir is already .../uploads
        if (!empty($subDir)) {
            if (strpos($subDir, 'uploads/') === 0) {
                $subDir = substr($subDir, 8);
            }
            $subDir = trim($subDir, '/');
        }

        $allowedTypes = $options['allowed_types'] ?? [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf'
        ];
        
        $maxSize = $options['max_size'] ?? 10 * 1024 * 1024;
        $prefix = $options['prefix'] ?? 'file';

        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }

        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds limit'];
        }

        $targetDir = $this->uploadDir;
        if (!empty($subDir)) {
            $targetDir = $this->uploadDir . '/' . $subDir;
        }
        $this->createDirectoryIfNotExists($targetDir);

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $targetDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            chmod($filepath, 0644);
            
            // Relative URL for DB storage, e.g. "uploads/avatars/filename.jpg"
            $relativeUrl = 'uploads/' . ($subDir ? $subDir . '/' : '') . $filename;
            $url = $this->baseUrl . '/' . ($subDir ? $subDir . '/' : '') . $filename;
            
            return [
                'success' => true,
                'path' => $relativeUrl,
                'url' => $url,
                'filename' => $filename
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
}
?>