const { test, expect } = require('@playwright/test');

test.afterEach(async ({ page }, testInfo) => {
    if (process.env.SCREENSHOTS) {
        const name = testInfo.title.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
        await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    }
});

test('upload media to s3', async ({ page }) => {
    await page.goto('/wp-admin/media-new.php');

    // Switch to the simple browser uploader (HTML file input)
    await page.getByRole('button', { name: /browser uploader/i }).click();

    const png1x1 = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI6QAAAABJRU5ErkJggg==',
        'base64'
    );

    await page.locator('input[name="async-upload"]').setInputFiles({
        name: 'test-s3-upload.png',
        mimeType: 'image/png',
        buffer: png1x1,
    });

    // Submit and wait for the redirect to upload.php
    await Promise.all([
        page.waitForURL('**/upload.php**'),
        page.locator('#html-upload').click(),
    ]);

    // Navigate to the attachment edit page to inspect the file URL.
    await page.goto('/wp-admin/upload.php?mode=list');
    const editUrl = await page.evaluate(() => {
        const link = document.querySelector('table.wp-list-table tbody td strong a');
        return link ? link.href : null;
    });
    await page.goto(editUrl);

    // The block editor sidebar fetches the attachment via REST API and shows the file URL
    // from wp_get_attachment_url(), which WP Offload Media rewrites to the MinIO URL.
    // Poll the DOM until that URL appears anywhere in an input or link.
    const urlHandle = await page.waitForFunction(() => {
        for (const el of document.querySelectorAll('input')) {
            if (el.value && el.value.includes('localhost:9010')) return el.value;
        }
        for (const el of document.querySelectorAll('a[href]')) {
            if (el.href && el.href.includes('localhost:9010')) return el.href;
        }
        return null;
    }, { timeout: 30000 });
    const url = await urlHandle.jsonValue();

    // File should be directly accessible from MinIO
    const imageResponse = await page.request.get(url);
    expect(imageResponse.status()).toBe(200);
});
