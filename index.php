<?php
declare(strict_types=1);

// A small, dependency-free file API for video files.
// Upload: POST /index.php with multipart field "file" and filename in
// X-File-Name or POST field "filename". The same filename can be read with GET.

$config = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $filename = requestFilename();

    if ($method === 'GET' || $method === 'HEAD') {
        if ($config['require_read_key']) {
            requireApiKey($config['api_key']);
        }
        if ($filename === null) {
            jsonResponse(['error' => 'A file query parameter is required.'], 400);
        }
        serveFile($config, $filename, $method === 'HEAD');
    }

    if ($method === 'POST' || $method === 'PUT') {
        requireApiKey($config['api_key']);
        if ($filename === null) {
            jsonResponse(['error' => 'A safe filename is required.'], 400);
        }
        saveFile($config, $filename, $method === 'POST');
    }

    header('Allow: GET, HEAD, POST, PUT');
    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $exception) {
    error_log('File API error: ' . $exception->getMessage());
    jsonResponse(['error' => 'Internal server error.'], 500);
}

function requestFilename(): ?string
{
    $value = $_GET['file'] ?? $_SERVER['HTTP_X_FILE_NAME'] ?? $_POST['filename'] ?? null;
    if (!is_string($value) || $value === '') {
        return null;
    }

    // Only flat, generated names are accepted. This blocks traversal and hidden files.
    $value = rawurldecode($value);
    if (strlen($value) > 200 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,199}$/D', $value)) {
        return null;
    }
    return $value;
}

function requireApiKey(string $expected): void
{
    if ($expected === '' || strlen($expected) < 32) {
        throw new RuntimeException('FILE_API_KEY must be configured with at least 32 characters.');
    }
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
        header('WWW-Authenticate: ApiKey');
        jsonResponse(['error' => 'Unauthorized.'], 401);
    }
}

function saveFile(array $config, string $filename, bool $multipart): void
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, $config['allowed_extensions'], true)) {
        jsonResponse(['error' => 'File type is not allowed.'], 415);
    }

    $source = null;
    $temporaryUpload = false;
    if ($multipart && isset($_FILES['file'])) {
        $upload = $_FILES['file'];
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Multipart field "file" is missing or invalid.'], 400);
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            jsonResponse(['error' => 'Invalid upload.'], 400);
        }
        $source = $upload['tmp_name'];
        $temporaryUpload = true;
    } else {
        $source = 'php://input';
    }

    $size = $temporaryUpload ? (int) filesize($source) : requestContentLength();
    if ($size < 1 || $size > $config['max_file_size']) {
        jsonResponse(['error' => 'File size is missing, empty, or too large.'], 413);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source) ?: 'application/octet-stream';
    if (!in_array($mime, $config['allowed_mime_types'], true)) {
        jsonResponse(['error' => 'The file content is not an allowed video type.'], 415);
    }

    $storage = storageDirFor($config, $filename);// realpath(dirname($config['storage_dir'])) . DIRECTORY_SEPARATOR . basename($config['storage_dir']);
    if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
        throw new RuntimeException('Could not create storage directory.');
    }
    $target = $storage . DIRECTORY_SEPARATOR . $filename;
    $temp = tempnam($storage, '.upload-');
    if ($temp === false) {
        throw new RuntimeException('Could not create temporary file.');
    }

    try {
        $input = fopen($source, 'rb');
        $output = fopen($temp, 'wb');
        if ($input === false || $output === false) {
            throw new RuntimeException('Could not open upload stream.');
        }
        $copied = stream_copy_to_stream($input, $output, $config['max_file_size'] + 1);
        fclose($input);
        fclose($output);
        if ($copied < 1 || $copied > $config['max_file_size']) {
            throw new RuntimeException('Upload size limit exceeded.');
        }
        chmod($temp, 0640);
        if (!rename($temp, $target)) {
            throw new RuntimeException('Could not finalize upload.');
        }
    } finally {
        if (is_file($temp)) {
            unlink($temp);
        }
    }

    http_response_code(201);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['fileName' => $filename, 'size' => $size, 'mimeType' => $mime], JSON_THROW_ON_ERROR);
    exit;
}

function serveFile(array $config, string $filename, bool $headOnly): void
{
    $storage =  storageDirFor($config, $filename);//realpath(dirname($config['storage_dir'])) . DIRECTORY_SEPARATOR . basename($config['storage_dir']);
    $path = $storage . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !is_readable($path)) {
        jsonResponse(['error' => 'File not found.'], 404);
    }

    $size = (int) filesize($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    $start = 0;
    $end = $size - 1;
    $status = 200;
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $match)) {
        $start = $match[1] === '' ? max(0, $size - (int) $match[2]) : (int) $match[1];
        $end = $match[2] === '' ? $end : (int) $match[2];
        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            jsonResponse(['error' => 'Invalid range.'], 416);
        }
        $end = min($end, $size - 1);
        $status = 206;
        header('Accept-Ranges: bytes');
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }
    http_response_code($status);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . ($end - $start + 1));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Accept-Ranges: bytes'); 
    header('Content-Disposition: inline; filename="' . addcslashes($filename, '"\\') . '"');
    if ($headOnly) {
        exit;
    }
    $handle = fopen($path, 'rb');
    fseek($handle, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(1024 * 1024, $remaining));
        if ($chunk === false) break;
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
    exit;
}

function requestContentLength(): int
{
    $length = $_SERVER['CONTENT_LENGTH'] ?? 0;
    return is_numeric($length) ? (int) $length : 0;
}

function jsonResponse(array $payload, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}
function storageDirFor(array $config, string $filename): string
{
    $base = realpath(dirname($config['storage_dir'])) . DIRECTORY_SEPARATOR . basename($config['storage_dir']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $config['image_extensions'], true)
        ? $base . DIRECTORY_SEPARATOR . 'images'
        : $base;
}
