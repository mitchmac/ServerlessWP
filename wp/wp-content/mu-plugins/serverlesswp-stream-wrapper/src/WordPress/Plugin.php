<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper\WordPress;

use ServerlessWpStreamWrapper\Config;
use ServerlessWpStreamWrapper\StreamWrapper;

class Plugin
{
    private ?Config $config = null;
    private array $failedPushes = [];
    private const BLOCKED_NOTICE_KEY = 'serverlesswp_stream_wrapper_blocked_asset';
    private const REPORT_COOLDOWN_KEY = 'serverlesswp_stream_wrapper_report_cooldown';
    private const REPORT_COOLDOWN = 300;
    private static bool $blockedReported = false;
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

        // Imagick bypasses PHP streams.
        add_filter('wp_image_editors', [$this, 'preferGd']);

        add_filter('pre_move_uploaded_file',          [$this, 'moveUploadedFile'],    10, 4);
        add_filter('wp_generate_attachment_metadata', [$this, 'pushAfterGeneration'], 10, 3);

        // Convert delayed persistence failures into upload errors.
        add_filter('wp_handle_upload', [$this, 'failUploadIfNotPersisted'], 10, 2);

        add_action('template_redirect', [$this, 'serveRemoteFile'], 1);

        add_action('admin_notices', [$this, 'renderBlockedNotice']);
    }

    public function preferGd(array $editors): array
    {
        $gd    = array_filter($editors, fn($e) => $e === 'WP_Image_Editor_GD');
        $other = array_filter($editors, fn($e) => $e !== 'WP_Image_Editor_GD');
        return array_values(array_merge($gd, $other));
    }

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

        if (str_contains($requestPath, '..') || str_contains($requestPath, "\0")) {
            $this->debug('decline=traversal');
            return;
        }

        $contentUrlPath = rtrim((string) parse_url(content_url(), PHP_URL_PATH), '/');
        if ($contentUrlPath === '' || !str_starts_with($requestPath, $contentUrlPath . '/')) {
            $this->debug('decline=prefix content-url-path=' . $contentUrlPath);
            return;
        }

        $absolutePath = WP_CONTENT_DIR . substr($requestPath, strlen($contentUrlPath));

        if (!StreamWrapper::isRegistered()) {
            $this->debug('decline=unregistered');
            return;
        }

        if (!StreamWrapper::isRemotePath($absolutePath)) {
            $this->debug('decline=not-remote path=' . $absolutePath);
            return;
        }

        // Stored does not mean public.
        if (!$this->isPublicPath($absolutePath)) {
            $this->debug('decline=not-public path=' . $absolutePath);
            $this->reportBlocked($absolutePath);
            return;
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->debug('decline=read-failed path=' . $absolutePath);

            nocache_headers();
            return;
        }

        $fileInfo = wp_check_filetype(basename($requestPath));
        $mime     = $fileInfo['type'] ?: 'application/octet-stream';

        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($contents));

        header('Cache-Control: ' . ($this->config ?? new Config())->cacheControl());
        echo $contents;
        exit;
    }

    private function isPublicPath(string $absolutePath): bool
    {
        $config = $this->config ?? new Config();

        $public = $this->matchesAnyPath($absolutePath, $config->publicPaths());

        if (!$public && $this->matchesAnyPath($absolutePath, $config->publicAssetPaths())) {
            $public = $this->hasAssetExtension($absolutePath);
        }

        return (bool) apply_filters('serverlesswp_stream_wrapper_is_public_path', $public, $absolutePath);
    }

    private function matchesAnyPath(string $absolutePath, array $relativePaths): bool
    {
        $wpRoot = dirname(rtrim(WP_CONTENT_DIR, '/'));

        foreach ($relativePaths as $relative) {
            $prefix = $wpRoot . '/' . ltrim($relative, '/');
            if ($absolutePath === $prefix || str_starts_with($absolutePath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function hasAssetExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, Config::PUBLIC_ASSET_EXTENSIONS, true);
    }

    private function reportBlocked(string $absolutePath): void
    {
        if (self::$blockedReported) {
            return;
        }
        self::$blockedReported = true;

        if (get_transient(self::REPORT_COOLDOWN_KEY) !== false) {
            return;
        }
        set_transient(self::REPORT_COOLDOWN_KEY, time(), self::REPORT_COOLDOWN);

        if (!StreamWrapper::existsInStorage($absolutePath)) {
            return;
        }

        $config = $this->config ?? new Config();

        error_log(sprintf(
            'serverlesswp-stream-wrapper: refused to serve %s — the object exists in storage but is '
            . 'outside the serving policy (WP_STREAM_PUBLIC_PATHS=%s, '
            . 'WP_STREAM_PUBLIC_ASSET_PATHS=%s). Add its directory to one of those if it is '
            . 'meant to be downloadable.',
            $absolutePath,
            implode(',', $config->publicPaths()),
            implode(',', $config->publicAssetPaths()),
        ));

        if (!$this->hasAssetExtension($absolutePath)) {
            return;
        }

        if (get_transient(self::BLOCKED_NOTICE_KEY) === false) {
            set_transient(self::BLOCKED_NOTICE_KEY, $absolutePath, HOUR_IN_SECONDS);
        }
    }

    public function renderBlockedNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $path = get_transient(self::BLOCKED_NOTICE_KEY);
        if (!is_string($path) || $path === '') {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><code>%s</code></p>'
            . '<p>%s</p></div>',
            esc_html__('Stored file not served', 'serverlesswp-stream-wrapper'),
            esc_html__(
                'An asset was requested that exists in object storage but sits outside this '
                . "site's serving policy, so it returned 404:",
                'serverlesswp-stream-wrapper',
            ),
            esc_html($path),
            esc_html__(
                'If it is meant to be public, add its directory to WP_STREAM_PUBLIC_ASSET_PATHS '
                . '(served only for CSS, JS, images and fonts) or WP_STREAM_PUBLIC_PATHS (served '
                . 'whatever the file type).',
                'serverlesswp-stream-wrapper',
            ),
        );
    }

    public function moveUploadedFile(mixed $default, array $fileInfo, string $destPath, string $mimeType): mixed
    {
        if (!StreamWrapper::isRegistered() || !StreamWrapper::isRemotePath($destPath)) {
            return $default;
        }

        stream_wrapper_restore('file');
        wp_mkdir_p(dirname($destPath));

        // Sideloads are not PHP uploads.
        $moved = is_uploaded_file($fileInfo['tmp_name'])
            ? (bool) @move_uploaded_file($fileInfo['tmp_name'], $destPath)
            : (bool) @rename($fileInfo['tmp_name'], $destPath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);

        if (!$moved) {
            return $default;
        }

        StreamWrapper::preCacheLocalFile($destPath);

        if (!StreamWrapper::pushLocalFile($destPath, deleteLocal: false)) {
            $this->failedPushes[$destPath] = true;
        }

        return $destPath;
    }

    public function failUploadIfNotPersisted(array $upload, string $context): array
    {
        $file = $upload['file'] ?? '';

        if ($file === '' || !isset($this->failedPushes[$file])) {
            return $upload;
        }

        unset($this->failedPushes[$file]);
        StreamWrapper::discardLocalFile($file);

        return [
            'error' => sprintf(
                'The uploaded file could not be saved to remote storage (%s). Nothing was kept.',
                $context,
            ),
        ];
    }

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

        $originalPath = $basedir . '/' . $file;
        if ($this->notPersisted($originalPath, StreamWrapper::pushLocalFileStatus($originalPath))) {
            trigger_error(
                "serverlesswp-stream-wrapper: attachment {$attachmentId} original '{$file}' did not reach "
                . 'remote storage on this request; metadata left intact',
                E_USER_WARNING,
            );
        }

        $sizeDir = $basedir . '/' . dirname($file);
        foreach ($metadata['sizes'] ?? [] as $sizeName => $size) {
            if (empty($size['file'])) {
                continue;
            }

            $sizePath = $sizeDir . '/' . $size['file'];
            if ($this->notPersisted($sizePath, StreamWrapper::pushLocalFileStatus($sizePath))) {
                unset($metadata['sizes'][$sizeName]);
            }
        }

        return $metadata;
    }

    private function notPersisted(string $absolutePath, string $pushStatus): bool
    {
        if ($pushStatus === StreamWrapper::PUSH_FAILED) {
            return true;
        }

        return $pushStatus === StreamWrapper::PUSH_NO_LOCAL_COPY
            && StreamWrapper::writeFailed($absolutePath);
    }
}
