<?php
// src/utils/FileUploader.php

class FileUploader {
    private $uploadDir;
    private $baseUrl;

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
        $allowedTypes = $options['allowed_types'] ?? [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf'
        ];
        
        $maxSize = $options['max_size'] ?? 10 * 1024 * 1024;
        $prefix = $options['prefix'] ?? 'file';

        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded'];
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

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $this->uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            chmod($filepath, 0644);
            
            return [
                'success' => true,
                'path' => $filepath,
                'url' => $this->baseUrl . '/' . $filename,
                'filename' => $filename
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
}
?>