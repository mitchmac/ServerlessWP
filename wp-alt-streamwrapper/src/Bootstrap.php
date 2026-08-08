<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

/**
 * Helpers for bootstrap/prepend.php, kept here so they can be unit tested —
 * the prepend file itself runs before anything else and can't be loaded twice.
 */
final class Bootstrap
{
    /**
     * Work out which wp-content directory PathRouter should match runtime paths
     * against, and report anything that looks wrong about the answer.
     *
     * Getting this wrong is silent: PathRouter compares absolute paths against
     * this prefix, so a directory that WordPress never actually uses routes
     * nothing to storage. Every write then lands on local disk and disappears
     * with the function, without an error anywhere. Hence the checks.
     *
     * @param ?string $configured   WP_STREAM_WP_CONTENT_DIR, or null if unset
     * @param string  $inferred     directory derived from the bootstrap file's own location
     * @param ?string $documentRoot the server's document root, when known
     *
     * @return array{dir: ?string, warning: ?string} `dir` is null when the wrapper
     *         must not be registered at all.
     */
    public static function resolveWpContentDir(?string $configured, string $inferred, ?string $documentRoot = null): array
    {
        if ($configured !== null && $configured !== '') {
            if (!is_dir($configured)) {
                return [
                    'dir'     => null,
                    'warning' => "WP_STREAM_WP_CONTENT_DIR is set to '{$configured}', which is not a directory. "
                        . 'Not registering the stream wrapper.',
                ];
            }

            return ['dir' => $configured, 'warning' => null];
        }

        if (!is_dir($inferred)) {
            return [
                'dir'     => null,
                'warning' => "wp-content inferred from the bootstrap file's location as '{$inferred}', "
                    . 'which is not a directory. Set WP_STREAM_WP_CONTENT_DIR. '
                    . 'Not registering the stream wrapper.',
            ];
        }

        // The bootstrap is deliberately loaded from a read-only copy of the
        // WordPress tree on serverless platforms, while WordPress itself runs
        // from a writable copy elsewhere. Both directories exist, so only the
        // document root reveals the mismatch.
        if ($documentRoot !== null && $documentRoot !== '' && !self::isWithin($inferred, $documentRoot)) {
            return [
                'dir'     => $inferred,
                'warning' => "wp-content inferred as '{$inferred}', which is outside the document root "
                    . "'{$documentRoot}'. If WordPress runs from a different copy of the tree, set "
                    . 'WP_STREAM_WP_CONTENT_DIR to the path it actually uses or no files will be routed to storage.',
            ];
        }

        return ['dir' => $inferred, 'warning' => null];
    }

    private static function isWithin(string $path, string $parent): bool
    {
        $path   = realpath($path) ?: rtrim($path, '/');
        $parent = realpath($parent) ?: rtrim($parent, '/');

        return $path === $parent || str_starts_with($path, rtrim($parent, '/') . '/');
    }
}
