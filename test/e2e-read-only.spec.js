const { test, expect } = require('@playwright/test');

test.use({ storageState: { cookies: [], origins: [] } });

test.afterEach(async ({ page }, testInfo) => {
    if (process.env.SCREENSHOTS) {
        const name = testInfo.title.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
        await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    }
});

test('homepage loads', async ({ page }) => {
    const response = await page.goto('/');
    await expect(page).toHaveTitle(/.+/);
    const expectedMaxAge = process.env.SERVERLESSWP_READ_ONLY_CACHE_MAX_AGE || 86400;
    expect(response.headers()['cache-control']).toMatch(new RegExp(`max-age=${expectedMaxAge}`));
});

test('GET requests are allowed', async ({ page }) => {
    await page.goto('/wp-login.php');
    await expect(page.getByLabel('Username or Email Address')).toBeVisible();
});

test('POST requests are blocked', async ({ request }) => {
    const response = await request.post('/wp-login.php', {
        form: { log: 'admin', pwd: 'testpassword123' }
    });
    expect(response.status()).toBe(403);
    const body = await response.text();
    expect(body).toContain('read-only');
});

test('PUT requests are blocked', async ({ request }) => {
    const response = await request.put('/wp-json/wp/v2/posts/1');
    expect(response.status()).toBe(403);
});

test('DELETE requests are blocked', async ({ request }) => {
    const response = await request.delete('/wp-json/wp/v2/posts/1');
    expect(response.status()).toBe(403);
});
