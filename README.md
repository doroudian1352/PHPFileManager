# PHP video file API

This folder is intended to be deployed on the file host over HTTPS.

## Configure

Set a random secret of at least 32 characters in `config.php` (or use `FILE_API_KEY` in the host environment). Uploads fail closed until this is configured. `config.php` is intentionally blocked by Apache. Ensure the PHP process can write to `storage/`.

PHP must have `fileinfo` enabled. For uploads larger than the PHP defaults, also increase `upload_max_filesize` and `post_max_size` in `php.ini` (the API limit is 5 GiB by default).

## Upload from the .NET API

Use the generated safe filename and send either multipart form data:

```http
POST /php-file-api/index.php?file=video-20261105-abc123.mp4
X-API-Key: <secret>
Content-Type: multipart/form-data

file=<video bytes>
```

Or send raw bytes with `PUT`, `X-File-Name`, and `Content-Type` set by the client. The API validates the extension, detected MIME type, size, and filename before atomically storing the file.

## Read / stream

```text
https://files.example.com/php-file-api/index.php?file=video-20261105-abc123.mp4
```

`GET`, `HEAD`, and HTTP byte ranges are supported, so browsers and video players can seek. Reads are public by default; set `require_read_key` to `true` if URLs should also require `X-API-Key`.

The included `.htaccess` protects Apache deployments. For Nginx, also deny direct access to `/php-file-api/storage/` and `/php-file-api/config.php`, and route only `index.php` to PHP-FPM.
"# PHPFileManager" 
