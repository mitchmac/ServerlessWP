<?php

declare(strict_types=1);

namespace ServerlessWpStreamWrapper;

final class Bootstrap
{
    public static function consumeVercelOidcToken(array &$server): ?string
    {
        $token = $server['HTTP_X_VERCEL_OIDC_TOKEN'] ?? null;
        unset($server['HTTP_X_VERCEL_OIDC_TOKEN']);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function validateVercelBlob(?string $token, ?string $storeId): ?string
    {
        $missing = [];
        if ($token === null || $token === '') {
            $missing[] = 'an OIDC or read-write token';
        }
        if ($storeId === null || $storeId === '') {
            $missing[] = 'a Blob store id';
        }

        if ($missing === []) {
            return null;
        }

        return 'Vercel Blob requires ' . implode(' and ', $missing)
            . '. Not registering the stream wrapper.';
    }

    public static function resolveWpContentDir(?string $configured, string $inferred, ?string $documentRoot = null): array
    {
        if ($configured !== null && $configured !== '') {
            if (!is_dir($configured)) {
                return [
                    'dir'     => null,
                    'warning' => "SERVERLESSWP_STREAM_WP_CONTENT_DIR is set to '{$configured}', which is not a directory. "
                        . 'Not registering the stream wrapper.',
                ];
            }

            return ['dir' => $configured, 'warning' => null];
        }

        if (!is_dir($inferred)) {
            return [
                'dir'     => null,
                'warning' => "wp-content inferred from the bootstrap file's location as '{$inferred}', "
                    . 'which is not a directory. Set SERVERLESSWP_STREAM_WP_CONTENT_DIR. '
                    . 'Not registering the stream wrapper.',
            ];
        }

        if ($documentRoot !== null && $documentRoot !== '' && !self::isWithin($inferred, $documentRoot)) {
            return [
                'dir'     => $inferred,
                'warning' => "wp-content inferred as '{$inferred}', which is outside the document root "
                    . "'{$documentRoot}'. If WordPress runs from a different copy of the tree, set "
                    . 'SERVERLESSWP_STREAM_WP_CONTENT_DIR to the path it actually uses or no files will be routed to storage.',
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
