<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\WordPress;

use WpAltStreamWrapper\Config;
use WpAltStreamWrapper\StreamWrapper;

class Plugin
{
    private ?Config $config = null;

    /**
     * Destination paths this request moved into place but could not persist.
     * Keyed by absolute path; drained by failUploadIfNotPersisted().
     *
     * @var array<string, true>
     */
    private array $failedPushes = [];

    /** Transient holding the most recent asset path the serving policy refused. */
    private const BLOCKED_NOTICE_KEY = 'wp_alt_streamwrapper_blocked_asset';

    /** Transient that rate-limits policy reporting across requests. */
    private const REPORT_COOLDOWN_KEY = 'wp_alt_streamwrapper_report_cooldown';

    /** Seconds between policy reports, however many requests arrive in between. */
    private const REPORT_COOLDOWN = 300;

    /** One policy report per request, however many files a page asks for. */
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

        // Imagick bypasses PHP streams; prefer GD so thumbnails reach storage.
        add_filter('wp_image_editors', [$this, 'preferGd']);

        // Persist the original before generation, then push generated sizes.
        add_filter('pre_move_uploaded_file',          [$this, 'moveUploadedFile'],    10, 4);
        add_filter('wp_generate_attachment_metadata', [$this, 'pushAfterGeneration'], 10, 3);

        // Turn a failed push into a failed upload. pre_move_uploaded_file cannot
        // do it itself: WordPress only runs its own error handling when that
        // filter returns null, so a false return there reads as success.
        add_filter('wp_handle_upload', [$this, 'failUploadIfNotPersisted'], 10, 2);

        // Serve remote files at their normal WordPress URLs.
        add_action('template_redirect', [$this, 'serveRemoteFile'], 1);

        // Surface a refused asset request in wp-admin; see reportBlocked().
        add_action('admin_notices', [$this, 'renderBlockedNotice']);
    }

    /** Prefer the editor whose writes pass through the stream wrapper. */
    public function preferGd(array $editors): array
    {
        $gd    = array_filter($editors, fn($e) => $e === 'WP_Image_Editor_GD');
        $other = array_filter($editors, fn($e) => $e !== 'WP_Image_Editor_GD');
        return array_values(array_merge($gd, $other));
    }

    /** Expose otherwise silent routing decisions when WP_STREAM_DEBUG is set. */
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
            // The prepend likely did not run for this request.
            $this->debug('decline=unregistered');
            return;
        }

        if (!StreamWrapper::isRemotePath($absolutePath)) {
            $this->debug('decline=not-remote path=' . $absolutePath);
            return;
        }

        // Persistence does not imply public access; routing covers private
        // backups, exports and logs as well as downloadable assets.
        if (!$this->isPublicPath($absolutePath)) {
            $this->debug('decline=not-public path=' . $absolutePath);
            $this->reportBlocked($absolutePath);
            return;
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->debug('decline=read-failed path=' . $absolutePath);
            // Prevent asset cache rules from retaining this 404 after upload.
            nocache_headers();
            return; // Fall through to WordPress's 404 handler.
        }

        $fileInfo = wp_check_filetype(basename($requestPath));
        $mime     = $fileInfo['type'] ?: 'application/octet-stream';

        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($contents));
        // Edge caching avoids a function invocation for every asset request.
        header('Cache-Control: ' . ($this->config ?? new Config())->cacheControl());
        echo $contents;
        exit;
    }

    /**
     * Whether a stored path may be served. Uploads are public by default;
     * configured asset paths additionally require an allowed extension.
     */
    private function isPublicPath(string $absolutePath): bool
    {
        $config = $this->config ?? new Config();

        $public = $this->matchesAnyPath($absolutePath, $config->publicPaths());

        // Asset paths are public only for asset filenames, so a directory that
        // mixes bundled CSS with cached HTML serves the first and not the second.
        if (!$public && $this->matchesAnyPath($absolutePath, $config->publicAssetPaths())) {
            $public = $this->hasAssetExtension($absolutePath);
        }

        return (bool) apply_filters('wp_alt_streamwrapper_is_public_path', $public, $absolutePath);
    }

    /** @param string[] $relativePaths paths relative to the WordPress root */
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

    /**
     * Report an existing object blocked by serving policy. Take the cooldown
     * before querying storage so unauthenticated probes cannot amplify work.
     */
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
            'wp-alt-streamwrapper: refused to serve %s — the object exists in storage but is '
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

    /** Show an admin notice for a confirmed stored asset blocked by policy. */
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
            esc_html__('Stored file not served', 'wp-alt-streamwrapper'),
            esc_html__(
                'An asset was requested that exists in object storage but sits outside this '
                . "site's serving policy, so it returned 404:",
                'wp-alt-streamwrapper',
            ),
            esc_html($path),
            esc_html__(
                'If it is meant to be public, add its directory to WP_STREAM_PUBLIC_ASSET_PATHS '
                . '(served only for CSS, JS, images and fonts) or WP_STREAM_PUBLIC_PATHS (served '
                . 'whatever the file type).',
                'wp-alt-streamwrapper',
            ),
        );
    }

    /**
     * Move an upload to local disk for image generation and persist it remotely.
     * A non-null return tells WordPress the move was handled.
     */
    public function moveUploadedFile(mixed $default, array $fileInfo, string $destPath, string $mimeType): mixed
    {
        if (!StreamWrapper::isRegistered() || !StreamWrapper::isRemotePath($destPath)) {
            return $default;
        }

        stream_wrapper_restore('file');
        wp_mkdir_p(dirname($destPath));
        // Sideloads are not PHP uploads, so they must use rename().
        $moved = is_uploaded_file($fileInfo['tmp_name'])
            ? (bool) @move_uploaded_file($fileInfo['tmp_name'], $destPath)
            : (bool) @rename($fileInfo['tmp_name'], $destPath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);

        if (!$moved) {
            return $default;
        }

        // Make file_exists() succeed while the local original awaits cleanup.
        StreamWrapper::preCacheLocalFile($destPath);
        // Keep the local copy for GD; pushAfterGeneration() removes it.
        if (!StreamWrapper::pushLocalFile($destPath, deleteLocal: false)) {
            // Prevent metadata for a file that will disappear with this request.
            $this->failedPushes[$destPath] = true;
        }

        // Returning a truthy value suppresses WordPress's own move_uploaded_file() call.
        return $destPath;
    }

    /**
     * Return WordPress's upload-error shape when remote persistence failed, so
     * it does not create attachment metadata for an ephemeral file.
     */
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

    /**
     * Persist generated image sizes and remove metadata for any missing variant.
     * Keep original-file metadata intact because removing it breaks the attachment.
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
        $originalPath = $basedir . '/' . $file;
        if ($this->notPersisted($originalPath, StreamWrapper::pushLocalFileStatus($originalPath))) {
            trigger_error(
                "wp-alt-streamwrapper: attachment {$attachmentId} original '{$file}' did not reach "
                . 'remote storage on this request; metadata left intact',
                E_USER_WARNING,
            );
        }

        // Push every generated size variant.
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

    /**
     * Distinguish a direct wrapper write from a failed one when neither leaves a
     * local copy; fclose() itself cannot report the remote failure.
     */
    private function notPersisted(string $absolutePath, string $pushStatus): bool
    {
        if ($pushStatus === StreamWrapper::PUSH_FAILED) {
            return true;
        }

        return $pushStatus === StreamWrapper::PUSH_NO_LOCAL_COPY
            && StreamWrapper::writeFailed($absolutePath);
    }
}
