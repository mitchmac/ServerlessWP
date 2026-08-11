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

        // Turn a failed push into a failed upload. pre_move_uploaded_file cannot
        // do it itself: WordPress only runs its own error handling when that
        // filter returns null, so a false return there reads as success.
        add_filter('wp_handle_upload', [$this, 'failUploadIfNotPersisted'], 10, 2);

        // Serve files that live in remote storage at their normal WordPress URLs.
        // When a request arrives for a path under wp-content that does not exist
        // on the local filesystem, Apache's .htaccess routes it through index.php.
        // We intercept it here, read from the stream wrapper (which proxies MinIO/S3),
        // and stream the bytes back to the browser.
        add_action('template_redirect', [$this, 'serveRemoteFile'], 1);

        // Surface a refused asset request in wp-admin; see reportBlocked().
        add_action('admin_notices', [$this, 'renderBlockedNotice']);
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

        // Being stored remotely is not permission to be downloaded. Routing
        // covers all of wp-content by default, which is right for persistence
        // and wrong for serving: a plugin's backups, exports or debug log would
        // otherwise be readable by anyone who guesses the URL, with no
        // .htaccess to stop it (this proxy answers before any such rule, and
        // .htaccess is excluded from remote storage anyway).
        if (!$this->isPublicPath($absolutePath)) {
            $this->debug('decline=not-public path=' . $absolutePath);
            $this->reportBlocked($absolutePath);
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
     * Whether a stored path may be handed back over HTTP.
     *
     * Configured with WP_STREAM_PUBLIC_PATHS (default: uploads and cache — the
     * trees a conventional web server already exposes). Directories outside it
     * can be opted in per site:
     *
     *     add_filter('wp_alt_streamwrapper_is_public_path', function ($public, $path) {
     *         return $public || str_contains($path, '/wp-content/my-public-dir/');
     *     }, 10, 2);
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
     * Say why a stored file was not served, in the two places an admin might look.
     *
     * A blocked request is otherwise indistinguishable from a plain 404: the
     * response says nothing, the wrapper logs nothing, and a site whose assets
     * stopped resolving gives no clue that a serving policy is the reason.
     *
     * Everything an unauthenticated request can reach here is attacker-triggered,
     * so the order of these three steps is the whole design:
     *
     *  1. Take a cooldown that spans requests, before doing anything that costs
     *     something. Whatever volume a scanner sends, this path does at most one
     *     report, one storage request and one log line per cooldown window.
     *  2. Only then check whether the object is actually there. A request for a
     *     path nobody ever wrote is an ordinary 404 and says nothing about
     *     policy — reporting it would log a claim that isn't true and could put
     *     an attacker's invented path in front of an admin as if it were real.
     *  3. Report. The notice covers asset filenames only, the case that visibly
     *     breaks a site.
     *
     * The cost of the cooldown is that a probe can consume the window and delay
     * a real report by a few minutes. That is why the window is minutes rather
     * than an hour: long enough to bound the amplification, short enough that a
     * genuinely broken asset still surfaces promptly.
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

    /**
     * Tell an admin that an asset request was refused, so a site rendering
     * without its CSS points at the cause instead of looking like a 404.
     * Self-clearing: the transient expires an hour after the last occurrence.
     *
     * reportBlocked() confirms the object exists before setting the transient, so
     * this names a file that is really in storage rather than any path a visitor
     * asked for.
     */
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
     * Write the uploaded/sideloaded file to the real filesystem so GD can
     * access it for thumbnail generation.  Returns a non-null value to signal
     * that we handled the move, suppressing WordPress's own move_uploaded_file().
     *
     * @param mixed  $default  null (pass-through sentinel)
     * @param array  $fileInfo Uploaded file info array (tmp_name, name, …)
     * @param string $destPath Final destination path inside wp-content/uploads
     * @param string $mimeType Detected MIME type of the file — WordPress passes
     *                         this, not the action name, so which of
     *                         wp_handle_upload / wp_handle_sideload is running
     *                         has to be worked out from the file itself.
     */
    public function moveUploadedFile(mixed $default, array $fileInfo, string $destPath, string $mimeType): mixed
    {
        if (!StreamWrapper::isRegistered() || !StreamWrapper::isRemotePath($destPath)) {
            return $default;
        }

        stream_wrapper_restore('file');
        wp_mkdir_p(dirname($destPath));
        // move_uploaded_file() only accepts a path PHP recorded in $_FILES, so a
        // sideload (import, "add from URL", any plugin-fetched file) has to move
        // by rename. Both run against the real filesystem, with the wrapper
        // restored, because neither goes through PHP streams.
        $moved = is_uploaded_file($fileInfo['tmp_name'])
            ? (bool) @move_uploaded_file($fileInfo['tmp_name'], $destPath)
            : (bool) @rename($fileInfo['tmp_name'], $destPath);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', StreamWrapper::class);

        if (!$moved) {
            return $default;
        }

        // Push to remote storage immediately.  GD's imagecreatefrom*()
        // functions use PHP stream wrappers, so the file must be in MinIO/S3
        // before wp_generate_attachment_metadata() calls GD.
        // preCacheLocalFile first so file_exists() returns true even if the
        // push is delayed or fails.
        StreamWrapper::preCacheLocalFile($destPath);
        // Push to remote storage but keep the local copy so GD can read the
        // original for thumbnail generation.  pushAfterGeneration handles
        // the final cleanup of the original and all generated size variants.
        if (!StreamWrapper::pushLocalFile($destPath, deleteLocal: false)) {
            // The file is on a disk that disappears with this invocation.
            // Record it so failUploadIfNotPersisted() can stop WordPress from
            // committing attachment metadata for a file that is already gone.
            $this->failedPushes[$destPath] = true;
        }

        // Returning a truthy value suppresses WordPress's own move_uploaded_file() call.
        return $destPath;
    }

    /**
     * Report an upload whose file never reached remote storage as a failure.
     *
     * WordPress hands the array from this filter to media_handle_upload() and
     * friends, which check for an 'error' key — the same shape core's own
     * wp_handle_upload_error() returns. Without this the attachment row is
     * created and the file it points at vanishes with the container.
     *
     * @param array  $upload  {file, url, type} on success
     * @param string $context 'upload' or 'sideload'
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
     * After WordPress has finished generating all intermediate image sizes on
     * the local filesystem, push the original file and every size variant to
     * remote storage, then delete the local copies.
     *
     * A size that did not reach storage is removed from the metadata: this
     * filter runs after wp_handle_upload has returned, so the upload can no
     * longer be failed, and advertising a size with no object behind it puts a
     * broken image in every srcset that references it. Dropping the entry makes
     * WordPress fall back to a size that is actually there.
     *
     * The original file is kept in the metadata whatever happens to it. On the
     * upload path it is already in storage — moveUploadedFile() pushed it and
     * failed the upload if that did not work — so a failure here means the
     * stored copy is stale, not missing, and the attachment URLs still resolve.
     * On the regeneration path ('update') the original was never this request's
     * to push. Dropping 'file' would break the attachment outright, so the
     * failure is reported and the metadata left intact.
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
     * Whether a file this request generated is missing from remote storage.
     *
     * "No local copy" is the ordinary outcome for a thumbnail GD wrote straight
     * through the wrapper — nothing is left on disk to push because the bytes
     * already went to storage. It is also what a *failed* wrapper write leaves
     * behind: the buffer is discarded on close either way, and fclose() cannot
     * report the failure, so the two are indistinguishable from the file alone.
     * StreamWrapper::writeFailed() separates them without another HEAD request.
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
