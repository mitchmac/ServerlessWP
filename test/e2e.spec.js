const { test, expect } = require('@playwright/test');

test.afterEach(async ({ page }, testInfo) => {
    if (process.env.SCREENSHOTS) {
        const name = testInfo.title.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
        await page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
    }
});

test('homepage loads', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/.+/);
});

test.describe('unauthenticated', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login', async ({ page }) => {
        await page.goto('/wp-login.php');
        await page.getByLabel('Username or Email Address').fill('admin');
        await page.getByLabel('Password', { exact: true }).fill('testpassword123');
        await page.getByRole('button', { name: 'Log In' }).click();
        await page.waitForURL(/wp-admin/, { waitUntil: 'commit' });
        await expect(page).toHaveURL(/wp-admin/);
    });
});

test('create and view a post', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');

    // Gutenberg renders editor content inside an iframe in WP 6.x
    const editorFrame = page.frameLocator('[aria-label="Editor content"] iframe');

    const titleBox = editorFrame.getByRole('textbox', { name: /add title/i });
    await titleBox.fill('Playwright Test Post');
    await titleBox.press('Enter');
    await page.keyboard.type('Hello from Playwright.');

    await page.getByRole('button', { name: 'Publish', exact: true }).first().click();
    // Confirm in pre-publish panel
    await page.locator('.editor-post-publish-panel').getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page.locator('.components-snackbar')).toBeVisible({ timeout: 15000 });

    const [postPage] = await Promise.all([
        page.waitForEvent('popup'),
        page.locator('.components-snackbar').getByRole('link', { name: 'View Post' }).click(),
    ]);
    await expect(postPage.locator('h1.wp-block-post-title')).toHaveText('Playwright Test Post');
    await expect(postPage.getByText('Hello from Playwright.')).toBeVisible();
});

test('search for a post', async ({ page }) => {
    await page.goto('/?s=Playwright+Test+Post');
    await expect(page.locator('.wp-block-query.alignfull').getByRole('heading', { name: /Playwright Test Post/i })).toBeVisible();
});

test('edit a post', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    await page.getByRole('link', { name: 'Playwright Test Post' }).first().click();

    // Gutenberg renders editor content inside an iframe in WP 6.x
    const editorFrame = page.frameLocator('[aria-label="Editor content"] iframe');
    const title = editorFrame.getByRole('textbox', { name: /add title/i });
    await title.clear();
    await title.fill('Playwright Test Post (edited)');

    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.components-snackbar')).toBeVisible({ timeout: 15000 });

    const [editedPage] = await Promise.all([
        page.waitForEvent('popup'),
        page.locator('.components-snackbar').getByRole('link', { name: 'View Post' }).click(),
    ]);
    await expect(editedPage.locator('h1.wp-block-post-title')).toHaveText('Playwright Test Post (edited)');
});

test('delete a post', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');

    const row = page.locator('tr').filter({ hasText: 'Playwright Test Post (edited)' });
    await row.hover();
    await row.getByRole('link', { name: 'Trash' }).click();

    await expect(page.getByText(/moved to the Trash/i)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Playwright Test Post (edited)' })).toHaveCount(0);
});

test('change site name', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php');
    await page.getByLabel('Site Title').fill('ServerlessWP Test Site');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('#message, .notice-success')).toContainText(/saved/i);

    await page.goto('/');
    await expect(page).toHaveTitle(/ServerlessWP Test Site/);
});
