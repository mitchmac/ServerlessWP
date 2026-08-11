/**
 * Browser-based E2E tests for path-traversal protection in the stream wrapper.
 *
 * PathRouter::resolveDots() collapses ".." segments before checking target prefixes,
 * preventing traversal paths from escaping wp-content and being routed to remote
 * storage when they should resolve to the local filesystem.
 */

const { test, expect } = require('@playwright/test');

const HELPER = '/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/path-traversal.php';

async function clearLocalFiles(page) {
  const resp = await page.request.get(
    '/wp-content/mu-plugins/wp-alt-streamwrapper/tests/E2E/setup/cleanup-uploads.php',
  );
  if (resp.status() !== 200) {
    throw new Error(`cleanup-uploads.php returned HTTP ${resp.status()}`);
  }
}

test.describe('Path traversal protection', () => {
  // ── 1. Read: traversal path escaping wp-content stays on local filesystem ──
  test('reading via traversal path that escapes wp-content uses local filesystem', async ({ page }) => {
    // The helper writes a sentinel file to /tmp/ (local, not in any targeted path)
    // then reads it back via a traversal path starting inside uploads/.  If the
    // stream wrapper resolves ".." before checking the prefix, it sees a /tmp/ path
    // and reads from the local filesystem.  Without that resolution the uploads/
    // prefix match routes to MinIO, which has no such key, so the read returns false.
    const resp = await page.request.get(`${HELPER}?action=read-check`);
    expect(resp.status(), 'read-check helper must succeed').toBe(200);
    const body = await resp.text();
    expect(body.trim(), 'helper must report that local filesystem was used').toBe('OK');
  });

  // ── 2. Write: traversal within wp-content stores file at dot-resolved key ──
  test('writing via traversal path stores file at the dot-resolved storage key', async ({ page }) => {
    // The helper writes to uploads/../cache/e2e-traversal-write.css.
    // With dot-resolution the stream wrapper resolves this to cache/e2e-traversal-write.css
    // and stores the file in MinIO under that key, making it accessible at the
    // expected normalized URL.  Without resolution the key contains literal ".."
    // and the proxy cannot map the clean URL to the object.
    const writeResp = await page.request.get(`${HELPER}?action=write`);
    expect(writeResp.status(), 'write action must succeed').toBe(200);
    const fileUrl = (await writeResp.text()).trim();
    expect(fileUrl).toContain('/wp-content/cache/e2e-traversal-write.css');

    await clearLocalFiles(page);

    try {
      const resp = await page.request.get(fileUrl);
      expect(resp.status(), `${fileUrl} must be 200 — file must be at the dot-resolved key in MinIO`).toBe(200);
      const body = await resp.text();
      expect(body.trim()).toBe('traversal-write-sentinel');
    } finally {
      await page.request.get(`${HELPER}?action=cleanup`);
    }
  });
});
