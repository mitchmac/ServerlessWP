
const { test, expect } = require('@playwright/test');
const zlib = require('zlib');

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

async function login(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**');
}

async function clearLocalUploads(page) {
  const resp = await page.request.get(
    '/wp-content/mu-plugins/serverlesswp-stream-wrapper/tests/E2E/setup/cleanup-uploads.php',
  );
  if (resp.status() !== 200) {
    throw new Error(`cleanup-uploads.php returned HTTP ${resp.status()}`);
  }
}

async function uploadImage(page, filename) {
  await page.goto('/wp-admin/media-new.php?browser-uploader');
  await page.setInputFiles('#async-upload', {
    name: filename,
    mimeType: 'image/png',
    buffer: TEST_IMAGE,
  });
  await Promise.all([
    page.waitForURL('**/upload.php**', { timeout: 30_000 }),
    page.click('#html-upload'),
  ]);
  await page.waitForLoadState('networkidle');
}

async function openAttachmentEditPage(page) {
  if (!page.url().includes('/wp-admin/upload.php') || page.url().includes('mode=list')) {
    await page.goto('/wp-admin/upload.php');
    await page.waitForLoadState('networkidle');
  }
  await page.waitForSelector('.attachment[data-id]', { timeout: 20_000 });
  const attachmentId = await page.locator('.attachment[data-id]').first().getAttribute('data-id');
  await page.goto(`/wp-admin/post.php?post=${attachmentId}&action=edit`);
  await page.waitForLoadState('networkidle');
  return page;
}

async function getFileUrlFromEditPage(page) {
  const input = page.locator('#attachment_url');
  await input.waitFor({ timeout: 8_000 });
  return input.inputValue();
}

test.describe('Real user upload flow', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('uploaded image thumbnail in media library loads correctly', async ({ page }) => {
    await uploadImage(page, 'e2e-library.png');
    await clearLocalUploads(page);

    await page.waitForFunction(
      () => {
        const img = document.querySelector('.attachment-preview img[src]');
        return img && img.complete && img.naturalWidth > 0;
      },
      { timeout: 30_000 },
    );

    const src = await page.locator('.attachment-preview img').first().getAttribute('src');
    expect(src, 'Thumbnail must have a src').toBeTruthy();
    expect(src, 'Thumbnail must be served from /wp-content/').toContain('/wp-content/');

    const resp = await page.request.get(src);
    expect(resp.status(), `GET ${src} must succeed`).toBe(200);
    expect(resp.headers()['content-type'], 'Response must be an image').toMatch(/^image\//);
  });

  test('attachment edit page shows accessible file URL', async ({ page }) => {
    await uploadImage(page, 'e2e-detail.png');
    await clearLocalUploads(page);

    await openAttachmentEditPage(page);
    const fileUrl = await getFileUrlFromEditPage(page);

    expect(fileUrl, 'File URL must be a /wp-content/ path').toContain('/wp-content/');

    const resp = await page.request.get(fileUrl);
    expect(resp.status(), `GET ${fileUrl} must succeed`).toBe(200);
    expect(resp.headers()['content-type']).toContain('image/');
  });

  test('navigating to the file URL downloads the exact original bytes', async ({ page }) => {
    await uploadImage(page, 'e2e-download.png');
    await clearLocalUploads(page);

    await openAttachmentEditPage(page);
    const fileUrl = await getFileUrlFromEditPage(page);

    expect(fileUrl, 'File URL must be a /wp-content/ path').toContain('/wp-content/');

    const resp = await page.request.get(fileUrl);
    expect(resp.status(), `GET ${fileUrl} must succeed`).toBe(200);
    expect(resp.headers()['content-type']).toContain('image/png');

    const body = await resp.body();
    expect(
      body.equals(TEST_IMAGE),
      'Downloaded bytes must be identical to what was uploaded',
    ).toBe(true);
  });

  test('generated thumbnail and medium sizes are accessible', async ({ page }) => {
    await uploadImage(page, 'e2e-sizes.png');
    await clearLocalUploads(page);

    await openAttachmentEditPage(page);

    const allSrcs = await page.$$eval('img[src]', els => els.map(e => e.src));
    const uploadSrcs = allSrcs.filter(src => src.includes('/wp-content/uploads/'));

    expect(
      uploadSrcs.length,
      `At least one image on the edit page must be from /wp-content/uploads/.\nAll srcs: ${allSrcs.join('\n')}`,
    ).toBeGreaterThan(0);

    for (const src of uploadSrcs) {
      const resp = await page.request.get(src);
      expect(resp.status(), `${src} must return HTTP 200`).toBe(200);
      expect(resp.headers()['content-type'], `${src} must be an image`).toMatch(/^image\//);
    }
  });

  test('plugin-generated CSS file is stored and served from remote storage', async ({ page }) => {
    const createResp = await page.request.get(
      '/wp-content/mu-plugins/serverlesswp-stream-wrapper/tests/E2E/setup/create-plugin-css.php',
    );
    expect(createResp.status(), 'create-plugin-css.php must succeed').toBe(200);

    const cssUrl = (await createResp.text()).trim();
    expect(cssUrl, 'Helper must return a /wp-content/cache/ URL').toContain('/wp-content/cache/');

    await clearLocalUploads(page);

    const resp = await page.request.get(cssUrl);
    expect(resp.status(), `GET ${cssUrl} must succeed`).toBe(200);
    expect(resp.headers()['content-type'], 'Response must be CSS').toMatch(/text\/css/);

    const body = await resp.text();
    expect(body, 'CSS body must contain generated rule').toContain('.e2e-generated');
  });

  test('file written outside targeted paths is not persisted to remote storage', async ({ page }) => {
    const helperBase = '/wp-content/mu-plugins/serverlesswp-stream-wrapper/tests/E2E/setup/out-of-scope.php';

    const createResp = await page.request.get(`${helperBase}?action=create`);
    expect(createResp.status(), 'create must succeed').toBe(200);
    const fileUrl = (await createResp.text()).trim();
    expect(fileUrl).toContain('/wp-content/themes/e2e-out-of-scope.txt');

    const beforeDelete = await page.request.get(fileUrl);
    expect(beforeDelete.status(), 'file must be accessible from disk').toBe(200);

    const deleteResp = await page.request.get(`${helperBase}?action=delete`);
    expect(deleteResp.status(), 'delete must succeed').toBe(200);

    const afterDelete = await page.request.get(fileUrl);
    expect(
      afterDelete.status(),
      'file must be 404 after local delete — it was never pushed to remote storage',
    ).toBe(404);
  });
});
