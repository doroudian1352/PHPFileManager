<?php
return [
    'api_key' => 'ChangeToBetter_14050816_20261107',
    'storage_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'storage',
    'max_file_size' => 5 * 1024 * 1024 * 1024,
    'require_read_key' => false,
    'allowed_extensions' => ['avi', 'm4v', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'webm', 'wmv'],
    'image_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'allowed_mime_types' => [
        'video/avi', 'video/mp2t', 'video/mp4', 'video/mpeg', 'video/quicktime',
        'video/webm', 'video/x-flv', 'video/x-matroska', 'video/x-ms-asf',
        'video/x-ms-wmv', 'application/octet-stream', 'image/jpeg','image/jpg', 
        'image/png', 'image/webp', 'image/gif',
    ],
];