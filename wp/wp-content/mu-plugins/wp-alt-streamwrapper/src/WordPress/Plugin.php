<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\WordPress;

use WpAltStreamWrapper\Config;
use WpAltStreamWrapper\StreamWrapper;

class Plugin
{
    private ?Config $config = null;

    public function register(): void
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        $config       = new Config();
        $this->config = $config;
        $rewriter     = new UrlRewriter($config);
        $rewriter->register();

        // GD writes go through PHP stream wrappers → thumbnails reach remote storage directly.
        // Imagick uses C-level I/O and bypasses PHP streams, so thumbnails end up only on
        // the local filesystem and WordPress cannot verify them via our url_stat.
        add_filter('wp_image_editors', [$this, 'preferGd']);

        // Phase 1 (pre_move_uploaded_file): write the uploaded file to the real
        // local filesystem, push it to remote storage, and keep the local copy so
        // that Imagick (if ever used as fallback) can still read the original.
        //
        // Phase 2 (wp_generate_attachment_metadata): GD thumbnail writes go through
        // the stream wrapper directly to remote storage.  pushAfterGeneration pushes
        // the original file (which was kept locally) and cleans up local copies.
        add_filter('pre_move_uploaded_file',          [$this, 'moveUploadedFile'],    10, 4);
        add_filter('wp_generate_attachment_metadata', [$this, 'pushAfterGeneration'], 10, 3);

        // Serve files that live in remote storage at their normal WordPress URLs.
        // When a request arrives for a path under wp-content that does not exist
        // on the local filesystem, Apache's .htaccess routes it through index.php.
        // We intercept it here, read from the stream wrapper (which proxies MinIO/S3),
        // and stream the bytes back to the browser.
        add_action('template_redirect', [$this, 'serveRemoteFile'], 1);
    }

    /**
     * Intercept HTTP requests for files that live in remote storage and serve
     * them transparently at their normal /wp-content/... URL.
     *
     * This makes plugin-generated files (Elementor CSS, Autoptimize bundles,
     * WP Super Cache HTML, etc.) work without any per-plugin URL rewriting.
     */
    public function preferGd(array $editors): array
    {
        $gd    = array_filter($editors, fn($e) => $e === 'WP_Image_Editor_GD');
        $other = array_filter($editors, fn($e) => $e !== 'WP_Image_Editor_GD');
        return array_values(array_merge($gd, $other));
    }

    /**
     * Names this handler's decision in a response header when WP_STREAM_DEBUG
     * is set. Every decline below is silent by design (fall through to
     * WordPress), which makes a misroute invisible from the outside — this is
     * the only way to see which branch ran on a real request.
     */
    private function debug(string $value): void
    {
        if (getenv('WP_STREAM_DEBUG') !== false && !headers_sent()) {
            header('X-Wp-Stream-Debug: ' . $value, false);
        }
    }

    public function serveRemoteFile(): void
    {
        $requestPath = urldecode(strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
        $this->debug('uri=' . $requestPath);

        // Basic path-traversal guard.
        if (str_contains($requestPath, '..') || str_contains($requestPath, "\0")) {
            $this->debug('decline=traversal');
            return;
        }

        // Map the URL path to an absolute filesystem path using WP_CONTENT_DIR /
        // content_url() so that non-standard WP_CONTENT_DIR locations work.
        $contentUrlPath = rtrim((string) parse_url(content_url(), PHP_URL_PATH), '/');
        if ($contentUrlPath === '' || !str_starts_with($requestPath, $contentUrlPath . '/')) {
            $this->debug('decline=prefix content-url-path=' . $contentUrlPath);
            return;
        }

        $absolutePath = WP_CONTENT_DIR . substr($requestPath, strlen($contentUrlPath));

        if (!StreamWrapper::isRegistered()) {
            // The likely cause is the prepend never running for this request —
            // e.g. a `php -S` router that handles requests inline without
            // loading it (see ServerlessWP's wp/router.php).
            $this->debug('decline=unregistered');
            return;
        }

        if (!StreamWrapper::isRemotePath($absolutePath)) {
            $this->debug('decline=not-remote path=' . $absolutePath);
            return;
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->debug('decline=read-failed path=' . $absolutePath);
            // Remote-routed path with no object behind it. Send no-cache
            // headers before falling through to WordPress's 404 handler:
            // platform header rules keyed on asset extensions (e.g.
            // vercel.json's max-age on *.png) would otherwise let browsers
            // and edges cache the 404 long after the file is uploaded.
            nocache_headers();
            return; // Fall through to WordPress's 404 handler.
        }

        $fileInfo = wp_check_filetype(basename($requestPath));
        $mime     = $fileInfo['type'] ?: 'application/octet-stream';

        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($contents));
        // s-maxage lets the platform edge cache absorb repeat views so an
        // S3-only file doesn't cost a function invocation per request.
        // Value is configurable via WP_STREAM_CACHE_CONTROL.
        header('Cache-Control: ' . ($this->config ?? new Config())->cacheControl());
        echo $contents;
        exit;
    }

    /**
     * Write the uploaded/sideloaded file to the real filesystem so GD can
     * access it for thumbnail generation.  Returns a non-null value to signal
     * that we handled the move, suppressing WordPress's own move_uploaded_file().
     *
     * @param mixed  $default  null (pass-through sentinel)
     * @param array  $fileInfo Uploaded file info array (tmp_name, name, …)
     * @param string $destPath Final destination path inside wp-content/uploads
     * @param string $type     'wp_handle_upload' or 'wp_handle_sideload'
     */
    public function moveUploadedFile(mixed $default, array $fileInfo, string $destPath, string $type): mixed
    {
        if (!StreamWrapper::isRegistered() || !StreamWrapper::isRemotePath($destPath)) {
            return $default;
        }

        stream_wrapper_restore('file');
        wp_mkdir_p(dirname($destPath));
        $moved = ($type === 'wp_handle_sideload')
            ? (bool) @rename($fileInfo['tmp_name'], $destPath)
            : (bool) @move_uploaded_file($fileInfo['tmp_name'], $destPath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);

        if ($moved) {
            // Push to remote storage immediately.  GD's imagecreatefrom*()
            // functions use PHP stream wrappers, so the file must be in MinIO/S3
            // before wp_generate_attachment_metadata() calls GD.
            // preCacheLocalFile first so file_exists() returns true even if the
            // push is delayed or fails.
            StreamWrapper::preCacheLocalFile($destPath);
            // Push to remote storage but keep the local copy so GD can read the
            // original for thumbnail generation.  pushAfterGeneration handles
            // the final cleanup of the original and all generated size variants.
            StreamWrapper::pushLocalFile($destPath, deleteLocal: false);
        }

        // Returning a truthy value suppresses WordPress's own move_uploaded_file() call.
        return $moved ? $destPath : $default;
    }

    /**
     * After WordPress has finished generating all intermediate image sizes on
     * the local filesystem, push the original file and every size variant to
     * remote storage, then delete the local copies.
     *
     * @param array  $metadata     Attachment metadata (sizes, width, height, …)
     * @param int    $attachmentId Attachment post ID
     * @param string $context      'create' for new uploads, 'update' for regeneration
     */
    public function pushAfterGeneration(array $metadata, int $attachmentId, string $context = 'create'): array
    {
        if (!StreamWrapper::isRegistered()) {
            return $metadata;
        }

        $uploadDir = wp_upload_dir();
        $basedir   = rtrim($uploadDir['basedir'], '/');
        $file      = $metadata['file'] ?? '';

        if ($file === '') {
            return $metadata;
        }

        // Push the original file.
        StreamWrapper::pushLocalFile($basedir . '/' . $file);

        // Push every generated size variant.
        $sizeDir = $basedir . '/' . dirname($file);
        foreach ($metadata['sizes'] ?? [] as $size) {
            StreamWrapper::pushLocalFile($sizeDir . '/' . $size['file']);
        }

        return $metadata;
    }
}
