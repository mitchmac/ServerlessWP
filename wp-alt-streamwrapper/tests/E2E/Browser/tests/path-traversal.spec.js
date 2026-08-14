
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
  test('reading via traversal path that escapes wp-content uses local filesystem', async ({ page }) => {
    const resp = await page.request.get(`${HELPER}?action=read-check`);
    expect(resp.status(), 'read-check helper must succeed').toBe(200);
    const body = await resp.text();
    expect(body.trim(), 'helper must report that local filesystem was used').toBe('OK');
  });

  test('writing via traversal path stores file at the dot-resolved storage key', async ({ page }) => {
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
