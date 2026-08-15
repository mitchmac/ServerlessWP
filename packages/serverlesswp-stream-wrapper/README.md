# ServerlessWP Stream Wrapper

A WordPress plugin that transparently routes file operations in `wp-content/` to remote object storage (S3 or Vercel Blob) via a PHP stream wrapper. Designed for serverless WordPress on AWS Lambda ([ServerlessWP](https://serverlesswp.com)).

User uploads, image variants, and plugin-generated files (cache, CSS) are stored remotely and served from there. No changes to WordPress core or other plugins are required.

## How it works

A PHP stream wrapper overrides the built-in `file://` protocol. When WordPress reads or writes a file under a targeted path (e.g. `wp-content/uploads/`), the wrapper intercepts the operation and delegates it to the configured storage backend. All other paths pass through to the real filesystem.

Register the bootstrap via `auto_prepend_file` so interception starts before WordPress loads:

```ini
auto_prepend_file = /path/to/wp-content/mu-plugins/serverlesswp-stream-wrapper/bootstrap/prepend.php
```

Then activate the plugin in WordPress to enable URL rewriting.

**Uploads fail loudly.** If an uploaded file cannot be pushed to storage, the upload is
reported to WordPress as an error and the local copy is removed, rather than creating an
attachment whose file disappears with the container. An image size that cannot be pushed is
dropped from the attachment metadata so WordPress falls back to a size that exists, instead
of putting a broken image in every `srcset`.

**`rmdir()` keeps native semantics.** It fails on a non-empty directory. Code that means to
delete a tree walks it and removes each entry first — the wrapper does not delete
descendants the caller never named.

**Log files stay local.** `*.log` is excluded from routing. WordPress points `error_log` at
`WP_CONTENT_DIR/debug.log` when `WP_DEBUG_LOG` is set, and routing that would make every
logged line an append — a download plus a conditional upload of an object that only grows.
The wrapper's own diagnostics reach the platform's function logs through stderr regardless, so
point `WP_DEBUG_LOG` at somewhere outside `wp-content` (`/tmp/debug.log`) if you want a file.
The trade-off: a plugin that keeps `.log` files under `wp-content` and reads them back — a log
viewer in an admin screen — will find them gone once the container recycles.

## Configuration

All configuration is via environment variables (or PHP constants of the same name defined before the bootstrap runs).

| Variable | Required | Description |
|---|---|---|
| `WP_STREAM_PROVIDER` | yes | `s3` or `vercel-blob` |
| `WP_STREAM_WP_CONTENT_DIR` | no | Absolute path to `wp-content`. Defaults to the path inferred from the plugin's own location. |
| `WP_STREAM_TARGET_PATHS` | no | Comma-separated paths relative to the WordPress root to route remotely. Default: `wp-content` |
| `WP_STREAM_PUBLIC_PATHS` | no | Comma-separated paths relative to the WordPress root that the built-in proxy will serve over HTTP, whatever the file type. Default: `wp-content/uploads`. Deliberately narrower than `WP_STREAM_TARGET_PATHS` — see [What gets served](#what-gets-served). |
| `WP_STREAM_PUBLIC_ASSET_PATHS` | no | Paths served only when the filename is a web asset (css, js, mjs, svg, png, jpg, jpeg, gif, webp, avif, ico, bmp, woff, woff2, ttf, otf, eot). Default: `wp-content/cache`. |
| `WP_STREAM_EXCLUDE_PATHS` | no | Comma-separated path prefixes relative to the WordPress root forced to local storage, even when inside a target path. Default: `wp-content/plugins,wp-content/themes,wp-content/mu-plugins,wp-content/languages,wp-content/upgrade` |
| `WP_STREAM_EXCLUDE_PATTERNS` | no | Comma-separated glob patterns matched against the filename to force local storage. Default: `*.sqlite,*.db,*.php,*.log,.htaccess` |
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

## What gets served

Routing and serving are two different policies. `WP_STREAM_TARGET_PATHS` decides what
persists to object storage — all of `wp-content` by default, because any plugin may write
anywhere under it. Serving is narrower, and has two tiers:
`WP_STREAM_PUBLIC_PATHS` (default `wp-content/uploads`) is served whatever the file type, and
`WP_STREAM_PUBLIC_ASSET_PATHS` (default `wp-content/cache`) is served only for web-asset
filenames.

The difference matters because there is no web server in front of these files. On serverless
there is no `.htaccess` to deny a directory (and `.htaccess` is excluded from remote storage
anyway), and the proxy answers before any such rule would apply. Without a narrower serving
policy, a plugin's backups, exports or debug log would persist *and* be downloadable by
anyone who guesses the URL.

A file that is stored but not public 404s. To publish a directory outside the default, name
it in `WP_STREAM_PUBLIC_PATHS` or use the filter below.

### wp-content/cache: gated by extension, not by opt-in

`wp-content/cache` holds two unrelated kinds of file. Asset bundlers (Autoptimize and
friends) write CSS and JS the browser must fetch or the site renders unstyled. Page caches
write rendered HTML, which can be a page only some users are meant to see.

`WP_STREAM_PUBLIC_ASSET_PATHS` covers that directory for asset filenames only, so
`cache/autoptimize/css/x.css` is served and `cache/supercache/index.html` is not. Neither
outcome depends on an admin knowing to set a variable — an opt-in nobody discovers is the same
as broken, because a 404 on a CSS file names no cause.

If a request is refused, the wrapper says so rather than leaving a bare 404:

- the error log gets a line naming the path and the two variables that govern it;
- wp-admin shows a warning notice when the refused file was an asset — the case that visibly
  breaks a site — self-clearing an hour after the last occurrence.

Both are reported only for objects that actually exist in storage, and at most once every five
minutes however many requests arrive. Anyone can request any URL, so an unrate-limited report
would let a stranger fill the log, and a report issued before checking storage would name paths
nobody ever wrote — telling an admin a file is stored when it isn't. The cooldown is claimed
before that existence check, so probing cannot turn into a stream of storage requests either.
The trade-off is that a probe can occupy the window and delay a real report by a few minutes.

The extension list is deliberately tight: no `html`, `htm`, `json`, `xml`, `txt`, `log`,
`sql`, or `php`. One hole to be aware of: a plugin that caches *protected* media as `.jpg`
under an asset path is served like any other image. If that applies to your site, narrow
`WP_STREAM_PUBLIC_ASSET_PATHS` to the bundler's own subdirectory
(e.g. `wp-content/cache/autoptimize`) or close it with the filter below.

## Hooks

Widen what the proxy will serve:

```php
add_filter('serverlesswp_stream_wrapper_is_public_path', function (bool $public, string $path): bool {
    return $public || str_contains($path, '/wp-content/my-public-dir/');
}, 10, 2);
```

Other plugins can override the routing decision for any individual path:

```php
add_filter('serverlesswp_stream_wrapper_use_remote', function (bool $useRemote, string $path): bool {
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

**No locking on remote storage.** `flock()` on a remote file reports success without acquiring anything, because callers like `WP_Filesystem` and caching plugins treat a `false` return as a hard failure and abort. Do not rely on it for mutual exclusion.

Concurrency is handled at the write instead. A mode that reads before writing (`r+`, `a`, `a+`, `c`, `c+`) keeps the ETag from that read and makes the upload conditional on it, so a concurrent invocation's changes are never silently overwritten — S3 `If-Match`, `x-if-match` on Vercel Blob. The ETag rides along on the response that carried the body, so this costs no extra request.

A write that *creates* the object is conditional the other way: it requires the key to still be free (S3 `If-None-Match: *`; on Vercel Blob, withholding `x-allow-overwrite`, which is the only create-only precondition it offers). That covers `fopen(..., 'x')`, whose whole contract is "only if it does not exist", and the case where two invocations append to a file neither has created yet. `w` and `w+` stay unconditional whether or not the key exists: replacing the object is the point, and a condition would break every in-place rewrite like a regenerated thumbnail or CSS bundle.

A read that fails is not treated as an empty file. Opening `a` or `c` when the current contents cannot be read fails the `fopen()` rather than writing this handle's bytes over content that is still there.

When a conditional write loses, an append is replayed on top of the version that won — the appended bytes are known, so both writers' lines survive. Any other losing write is dropped with an `E_USER_WARNING`, because replaying an in-place edit would discard the change that won. `fclose()` cannot report failure to its caller, so a dropped write can only warn.

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
