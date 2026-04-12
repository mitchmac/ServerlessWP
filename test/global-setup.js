const { request } = require('@playwright/test');

module.exports = async function globalSetup() {
    if (process.env['SKIP_AUTH']) return;
    const api = await request.newContext({
        baseURL: 'https://localhost:3000',
        ignoreHTTPSErrors: true,
    });

    // Install WordPress
    const install = await api.get('/installer.php');
    if (!install.ok()) throw new Error(`Installer failed: ${install.status()}`);

    // Fetch login page to get the test cookie set
    await api.get('/wp-login.php');

    // Log in
    const login = await api.post('/wp-login.php', {
        form: {
            log: 'admin',
            pwd: 'testpassword123',
            'wp-submit': 'Log In',
            redirect_to: '/wp-admin/',
            testcookie: '1',
        },
    });
    if (!login.url().includes('wp-admin')) {
        throw new Error(`Login failed, ended up at: ${login.url()}`);
    }

    // Save auth cookies for all tests
    await api.storageState({ path: 'auth.json' });
    await api.dispose();
};
