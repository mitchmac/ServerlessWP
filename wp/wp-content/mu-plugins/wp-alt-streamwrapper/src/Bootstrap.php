<?php

declare(strict_types=1);

namespace WpAltStreamWrapper;

final class Bootstrap
{
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
