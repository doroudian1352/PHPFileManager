<?php
// Copy this file to config.php and replace the placeholder before deployment.
// Prefer setting FILE_API_KEY in the server environment instead of storing it here.
return [
    // Empty by default so uploads fail closed until the deployment is configured.
    'api_key' => getenv('FILE_API_KEY') ?: '',
    'storage_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'storage',
    'max_file_size' => 5 * 1024 * 1024 * 1024, // 5 GiB
    'require_read_key' => false,
    'allowed_extensions' => [
        'avi', 'm4v', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'webm', 'wmv'
    ],
    'allowed_mime_types' => [
        'video/avi',
        'video/mp2t',
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/webm',
        'video/x-flv',
        'video/x-matroska',
        'video/x-ms-asf',
        'video/x-ms-wmv',
        'application/octet-stream' // Some hosts report MP4/MKV this way.
    ],
];
