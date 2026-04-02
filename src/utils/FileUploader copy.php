<?php
// src/utils/FileUploader.php

class FileUploader {
    private $uploadDir;
    private $baseUrl;

    public function __construct($uploadDir = 'uploads', $baseUrl = null) {
        $this->uploadDir = rtrim($uploadDir, '/');
        
        // ສ້າງໂຟນເດີຖ້າຍັງບໍ່ມີ
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // ກຳນົດ base URL
        if ($baseUrl === null) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->baseUrl = $protocol . $host . '/' . $this->uploadDir;
        } else {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    public function upload($file, $options = []) {
        // ກວດສອບວ່າມີໄຟລ໌ບໍ
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'No file uploaded or upload error'
            ];
        }

        // ກວດສອບຊະນິດໄຟລ໌
        if (isset($options['allowed_types'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $options['allowed_types'])) {
                return [
                    'success' => false,
                    'message' => 'File type not allowed'
                ];
            }
        }

        // ກວດສອບຂະໜາດໄຟລ໌
        if (isset($options['max_size']) && $file['size'] > $options['max_size']) {
            return [
                'success' => false,
                'message' => 'File too large'
            ];
        }

        // ສ້າງຊື່ໄຟລ໌ໃໝ່
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        
        // ກຳນົດເສັ້ນທາງເຕັມ
        $filepath = $this->uploadDir . '/' . $filename;

        // ຍ້າຍໄຟລ໌
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filepath,
                'url' => $this->baseUrl . '/' . $filename
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to move uploaded file'
            ];
        }
    }
}
