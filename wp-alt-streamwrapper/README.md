# wp-alt-streamwrapper

A WordPress plugin that transparently routes file operations in `wp-content/` to remote object storage (S3 or Vercel Blob) via a PHP stream wrapper. Designed for serverless WordPress on AWS Lambda ([ServerlessWP](https://serverlesswp.com)).

User uploads, image variants, and plugin-generated files (cache, CSS) are stored remotely and served from there. No changes to WordPress core or other plugins are required.

## How it works

A PHP stream wrapper overrides the built-in `file://` protocol. When WordPress reads or writes a file under a targeted path (e.g. `wp-content/uploads/`), the wrapper intercepts the operation and delegates it to the configured storage backend. All other paths pass through to the real filesystem.

Register the bootstrap via `auto_prepend_file` so interception starts before WordPress loads:

```ini
auto_prepend_file = /path/to/wp-content/plugins/wp-alt-streamwrapper/bootstrap/prepend.php
```

Then activate the plugin in WordPress to enable URL rewriting.

## Configuration

All configuration is via environment variables (or PHP constants of the same name defined before the bootstrap runs).

| Variable | Required | Description |
|---|---|---|
| `WP_STREAM_PROVIDER` | yes | `s3` or `vercel-blob` |
| `WP_STREAM_WP_CONTENT_DIR` | no | Absolute path to `wp-content`. Defaults to the path inferred from the plugin's own location. |
| `WP_STREAM_TARGET_PATHS` | no | Comma-separated paths relative to the WordPress root to route remotely. Default: `wp-content` |
| `WP_STREAM_EXCLUDE_PATHS` | no | Comma-separated path prefixes relative to the WordPress root forced to local storage, even when inside a target path. Default: `wp-content/plugins,wp-content/themes,wp-content/mu-plugins,wp-content/languages,wp-content/upgrade` |
| `WP_STREAM_EXCLUDE_PATTERNS` | no | Comma-separated glob patterns matched against the filename to force local storage. Default: `*.sqlite,*.db,*.php,.htaccess` |
| `WP_STREAM_CDN_BASE_URL` | no | Public base URL for rewriting media URLs (e.g. a CloudFront domain). Derived from bucket/store if not set. |
| `WP_STREAM_CACHE_CONTROL` | no | `Cache-Control` header for files served by the built-in proxy. Default: `public, max-age=3600, s-maxage=86400` — browsers revalidate hourly, the edge keeps a copy for a day. Raise it (e.g. `public, max-age=31536000, immutable`) only if your media is never replaced in place. Misses always send `no-cache` so 404s aren't cached. |

### S3

| Variable | Description |
|---|---|
| `WP_STREAM_S3_BUCKET` | Bucket name (fallback: `SQLITE_S3_BUCKET`, `S3_OFFLOAD_BUCKET`) |
| `WP_STREAM_S3_REGION` | Region (fallback: `SQLITE_S3_REGION`; default: `us-east-1`) |
| `WP_STREAM_S3_PREFIX` | Key prefix within the bucket (optional) |
| `WP_STREAM_S3_ENDPOINT` | Custom endpoint URL — use for MinIO/R2 or other S3-compatible stores (fallback: `SQLITE_S3_ENDPOINT`) |
| `WP_STREAM_S3_KEY` | Access key ID (fallback: `SQLITE_S3_API_KEY`, `S3_KEY_ID`). Omit to use the IAM role (recommended on Lambda). |
| `WP_STREAM_S3_SECRET` | Secret access key (fallback: `SQLITE_S3_API_SECRET`, `S3_ACCESS_KEY`). Omit to use the IAM role. |
| `WP_STREAM_S3_FORCE_PATH_STYLE` | Force path-style addressing even without a custom endpoint (fallback: `SQLITE_S3_FORCE_PATH_STYLE`) |
| `WP_STREAM_S3_ACL` | Canned ACL applied to writes, e.g. `public-read` (default: bucket default) |

The `SQLITE_S3_*` / `S3_*` fallbacks match the variables [ServerlessWP](https://serverlesswp.com) users already configure, so a typical SQLite+S3 site only needs `WP_STREAM_PROVIDER=s3`.

### Vercel Blob

The adapter speaks the same wire protocol as the official `@vercel/blob` JS SDK (Vercel ships no PHP SDK): uploads pass the key as a `pathname` query parameter with `x-allow-overwrite` so in-place rewrites work, reads bypass the CDN cache (`cache=0`) so an overwritten file is never served stale, metadata comes from the `?url=` endpoint, and deletes `POST /delete`. The E2E suite validates this against the blob emulator from the ServerlessWP repo.

| Variable | Description |
|---|---|
| `WP_STREAM_VERCEL_TOKEN` | Blob read/write token |
| `WP_STREAM_VERCEL_STORE_ID` | Store ID (used to construct deterministic blob URLs) |
| `WP_STREAM_VERCEL_ACCESS` | Store access mode, `public` (default) or `private`; shapes the download host |
| `WP_STREAM_VERCEL_API_BASE` | Override the Blob API base URL (tests/emulator) |
| `WP_STREAM_VERCEL_DOWNLOAD_BASE` | Override the blob download base URL (tests/emulator) |

## Hooks

Other plugins can override the routing decision for any individual path:

```php
add_filter('wp_alt_streamwrapper_use_remote', function (bool $useRemote, string $path): bool {
    // Keep SQLite databases local (also excluded by default)
    if (str_ends_with($path, '.sqlite')) {
        return false;
    }
    return $useRemote;
}, 10, 2);
```

## Known limitations

**Imagick is not used for thumbnail generation.** The plugin forces GD as the image editor. Imagick writes files via its own C-level I/O, bypassing PHP stream wrappers entirely — thumbnails would be written to the local filesystem instead of remote storage, and WordPress would fail to verify them. GD writes through PHP streams so thumbnails go directly to the configured adapter.

If you need Imagick quality for specific operations, remove the `wp_image_editors` filter added by this plugin and ensure your thumbnail writes reach remote storage through another mechanism.

**Remote files are fully buffered in memory.** Reads download the whole object into a `php://temp` buffer and writes upload the whole buffer on close, so the largest file you can handle is bounded by PHP's memory limit / the function's memory allocation. Fine for uploads and generated assets; not suited to multi-gigabyte files.

**No locking on remote storage.** `flock()` on a remote file reports success without acquiring anything. Concurrent invocations writing the same file race; last write wins.

## Tests

Requires Docker.

```bash
# Unit tests
./run-tests.sh

# E2E tests (WordPress + MySQL + MinIO via Docker Compose)
./run-tests.sh e2e

# Both
./run-tests.sh all
```

## Installation

```bash
composer install
```

For production, install without dev dependencies:

```bash
composer install --no-dev
```

The AWS SDK is trimmed to the S3 service only at install time (via the SDK's `removeUnusedServices` Composer script configured in `composer.json`), keeping the production vendor directory to ~7MB.

Place or symlink the plugin directory into `wp-content/plugins/`, then add the `auto_prepend_file` directive and activate the plugin.
