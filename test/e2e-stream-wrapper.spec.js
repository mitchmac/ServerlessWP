/**
 * E2E for the wp-content stream wrapper, running through the Docker Lambda
 * image the same way e2e.spec.js and e2e-s3-offload.spec.js do.
 *
 * The plugin has its own E2E suite, but it runs under Apache with mod_php and
 * loads the prepend from a php.ini in conf.d — so the npm package, `php -S`
 * and api/index.js are all absent there. This file is the only coverage of
 * the real serverless path: api/index.js -> serverlesswp autoPrependFile ->
 * bootstrap/prepend.php -> S3.
 *
 * Driven by test/run-stream-wrapper-test.sh.
 */

const { test, expect } = require('@playwright/test');
const zlib = require('zlib');

const PROBE = '/stream-wrapper-probe.php';

// Requests carrying this header are allowed to fall through to the function
// when a wp-content file is missing from the local wp/ directory, the way a
// Vercel rewrite with a missing destination does. Scoped to this file so the
// single-threaded Lambda RIE isn't asked to serve core assets for other specs.
test.use({ extraHTTPHeaders: { 'x-serverlesswp-stream-wrapper-fallthrough': '1' } });

// Minimal dependency-free PNG generator. 800x600 makes WordPress produce
// thumbnail, medium and medium_large variants.
function solidPNG(width, height, r, g, b) {
    const crcT = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        crcT[n] = c;
    }
    const crc32 = (buf) => {
        let c = 0xffffffff;
        for (const b of buf) c = crcT[(c ^ b) & 0xff] ^ (c >>> 8);
        return (c ^ 0xffffffff) >>> 0;
    };
    const u32 = (n) => { const b = Buffer.alloc(4); b.writeUInt32BE(n); return b; };
    const chunk = (tag, data) => {
        const t = Buffer.from(tag, 'ascii');
        return Buffer.concat([u32(data.length), t, data, u32(crc32(Buffer.concat([t, data])))]);
    };
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0); ihdr.writeUInt32BE(height, 4);
    ihdr[8] = 8; ihdr[9] = 2;
    const row = Buffer.alloc(1 + width * 3);
    for (let x = 0; x < width; x++) {
        row[1 + x * 3] = r; row[2 + x * 3] = g; row[3 + x * 3] = b;
    }
    const raw = Buffer.concat(Array.from({ length: height }, () => row));
    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(raw, { level: 1 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

const TEST_IMAGE = solidPNG(800, 600, 66, 133, 244);

async function probe(request, query = '') {
    const response = await request.get(`${PROBE}${query}`);
    expect(response.status(), `${PROBE}${query} must succeed`).toBe(200);
    return response.json();
}

test.describe('stream wrapper bootstrap', () => {
    test('auto_prepend_file registers the wrapper before any other code', async ({ request }) => {
        const state = await probe(request);

        // False here means the package ignored autoPrependFile — the failure
        // an older serverlesswp version produces silently.
        expect(state.prepend_ran).toBe(true);
        expect(state.registered).toBe(true);
        expect(state.provider).toBe('s3');
    });

    test('wp-content resolves to the writable /tmp copy, not the bundle', async ({ request }) => {
        const state = await probe(request);

        // If this were /var/task/wp/wp-content, PathRouter would match nothing
        // and every write would land on the ephemeral disk with no error.
        expect(state.wp_content_dir).toBe('/tmp/wp/wp-content');
        expect(state.uploads_remote).toBe(true);
    });

    test('a wp-content write survives into a later invocation', async ({ request }) => {
        const written = await probe(request, '?action=write');
        expect(written.is_remote).toBe(true);
        expect(written.bytes_written).toBe(written.payload.length);

        const readBack = await probe(request, `?action=read&key=${written.write_key}`);
        expect(readBack.exists).toBe(true);
        expect(readBack.contents).toBe(written.payload);
    });
});

test.describe('WordPress integration', () => {
    // The wrapper being registered says nothing about the mu-plugin: the
    // prepend and the plugin load independently. Without these hooks uploads
    // bypass moveUploadedFile and stored files are never served, both of which
    // look like storage failures from the outside.
    test('the mu-plugin loads and registers its hooks', async ({ request }) => {
        const response = await request.get('/stream-wrapper-wp-probe.php');
        expect(response.status()).toBe(200);
        const wp = await response.json();

        expect(wp.loader_exists, `loader missing (${JSON.stringify(wp.mu_plugins_loaded)})`).toBe(true);
        expect(wp.payload_exists).toBe(true);
        expect(wp.plugin_class_loaded, 'plugin classes must be loadable').toBe(true);
        expect(wp.wrapper_registered).toBe(true);
        expect(wp.serve_hook, 'template_redirect handler must be registered').toBeTruthy();
        expect(wp.upload_hook, 'pre_move_uploaded_file filter must be registered').toBeTruthy();
        expect(wp.metadata_hook, 'wp_generate_attachment_metadata filter must be registered').toBeTruthy();

        // serveRemoteFile maps the request path with content_url(); a mismatch
        // here means it silently declines every wp-content request.
        expect(wp.content_url_path).toBe('/wp-content');
        expect(wp.wp_content_dir).toBe('/tmp/wp/wp-content');
    });
});

test.describe('serving from object storage', () => {
    test('plugin-generated CSS is served after the local copy is gone', async ({ request }) => {
        // A plugin writing a CSS file with plain file_put_contents — no
        // WordPress upload API involved.
        const created = await probe(request, '?action=css');
        expect(created.is_remote, 'wp-content/cache must route to storage').toBe(true);
        expect(created.bytes_written).toBeGreaterThan(0);

        // Wipe local disk so anything served now must come from storage. The
        // count is not asserted: when the wrapper is doing its job the file
        // never touched local disk, so there is legitimately nothing to delete.
        await probe(request, '?action=clear-local');

        // Separate the two ways this can fail: storage never got the file, or
        // WordPress failed to serve what storage has.
        const stored = await probe(request, '?action=read-css');
        expect(stored.exists, 'CSS must be readable through the wrapper').toBe(true);
        expect(stored.contents).toContain('.e2e-generated');

        // Replay serveRemoteFile's own conditions under WordPress, so a failure
        // names the condition instead of just showing an HTML page.
        const wpView = await request.get(`/stream-wrapper-wp-probe.php?path=${encodeURIComponent(created.url)}`);
        const conditions = await wpView.json();

        // First hop without following redirects: if WordPress canonical-redirects
        // this URL, the final response describes the wrong request entirely.
        const firstHop = await request.get(created.url, { maxRedirects: 0 });
        const hop = `first-hop status=${firstHop.status()} `
            + `location=${firstHop.headers()['location'] ?? '-'} `
            + `debug=${firstHop.headers()['x-wp-stream-debug'] ?? 'absent'}`;

        const response = await request.get(created.url);
        const detail = `${hop} wp-view=${JSON.stringify(conditions)} status=${response.status()} `
            + `headers=${JSON.stringify(response.headers())}`;

        expect(response.status(), `GET ${created.url} must succeed. ${detail}`).toBe(200);
        expect(response.headers()['content-type'], detail).toMatch(/text\/css/);
        expect(await response.text()).toContain('.e2e-generated');
    });

    test('files outside targeted paths are not persisted to storage', async ({ request }) => {
        // wp-content/themes is excluded, so this stays on local disk only.
        const created = await probe(request, '?action=scoped-create');
        expect(created.is_remote, 'excluded path must not route to storage').toBe(false);
        expect(created.bytes_written).toBeGreaterThan(0);

        const beforeDelete = await request.get(created.url);
        expect(beforeDelete.status(), 'file must be served from disk').toBe(200);

        const removed = await probe(request, '?action=scoped-delete');
        expect(removed.exists_after, `local copy must be gone (${removed.path})`).toBe(false);

        // Nothing to fall back to: it was never sent to storage. What matters
        // is that the content is not served — WordPress may answer with a 404
        // or, on a plain permalink structure, a 200 page. Either is fine; the
        // file's bytes coming back is not.
        const afterDelete = await request.get(created.url);
        expect(await afterDelete.text(), 'file content must not be served from storage')
            .not.toContain('out-of-scope content');
    });
});

test.describe('media uploads', () => {
    test('an uploaded image and its generated sizes survive local disk loss', async ({ page, request }) => {
        await page.goto('/wp-admin/media-new.php?browser-uploader');

        await page.setInputFiles('#async-upload', {
            name: 'stream-wrapper-test.png',
            mimeType: 'image/png',
            buffer: TEST_IMAGE,
        });
        // Wait on the POST itself: waitForURL would match the current
        // media-new.php URL instantly, and a premature goto() aborts the
        // in-flight multipart upload.
        const uploadPost = page.waitForResponse(
            (r) => r.request().method() === 'POST' && r.url().includes('media-new.php'),
            { timeout: 60000 },
        );
        await page.click('#html-upload');
        const postResponse = await uploadPost;
        await page.waitForLoadState();

        // A successful upload redirects to upload.php; a failure re-renders
        // media-new.php with the error in the page. Assert the redirect so a
        // server-side failure surfaces here, with WordPress's own message,
        // instead of as a missing row later.
        const bodyText = (await page.locator('body').innerText().catch(() => ''))
            .replace(/\s+/g, ' ').slice(0, 400);
        expect(
            page.url(),
            `upload POST returned ${postResponse.status()}; page says: ${bodyText}`,
        ).toContain('upload.php');

        // Find the attachment's URLs from the media library.
        await page.goto('/wp-admin/upload.php?mode=list');
        // Not a.row-title: WordPress 7.x dropped that class from the media
        // list's title link. Match the link by its accessible name.
        const link = page.getByRole('link', { name: 'stream-wrapper-test', exact: true }).first();
        await expect(link, 'uploaded item must appear in the media library').toBeVisible({ timeout: 20000 });
        await link.click();

        const fileUrl = await page.locator('#attachment_url').inputValue();
        expect(fileUrl, 'attachment URL must be under wp-content/uploads').toContain('/wp-content/uploads/');

        const uploadPath = new URL(fileUrl, 'https://localhost:3000').pathname;

        // Wipe local disk — everything below must now come from storage. No
        // assertion on the count: pushAfterGeneration already removed the
        // local copies, so zero deletions is the healthy case.
        await probe(request, '?action=clear-local');

        const original = await request.get(uploadPath);
        expect(original.status(), `GET ${uploadPath} must succeed`).toBe(200);
        expect(original.headers()['content-type']).toMatch(/^image\//);
        expect((await original.body()).length).toBe(TEST_IMAGE.length);

        // WordPress names size variants <base>-<w>x<h>.png next to the original.
        const thumbnail = uploadPath.replace(/\.png$/, '-150x150.png');
        const thumbResponse = await request.get(thumbnail);
        expect(thumbResponse.status(), `GET ${thumbnail} must succeed`).toBe(200);
        expect(thumbResponse.headers()['content-type']).toMatch(/^image\//);
    });
});
