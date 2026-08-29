const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');

async function availablePort() {
    const server = net.createServer();
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolve);
    });
    const { port } = server.address();
    await new Promise(resolve => server.close(resolve));
    return port;
}

async function waitForServer(url, child) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (child.exitCode !== null) {
            throw new Error(`PHP server exited with code ${child.exitCode}`);
        }
        try {
            const response = await fetch(url);
            if (response.ok) return;
        }
        catch (_) {}
        await new Promise(resolve => setTimeout(resolve, 25));
    }
    throw new Error('Timed out waiting for the PHP test server');
}

test('router hides sensitive upload extensions without blocking normal WordPress files', { timeout: 15000 }, async () => {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'serverlesswp-router-'));
    const uploads = path.join(root, 'wp-content', 'uploads', 'nested');
    const content = path.join(root, 'wp-content');
    const router = path.resolve(__dirname, '../wp/router.php');
    const packageRoot = path.dirname(require.resolve('serverlesswp/package.json'));
    const phpFiles = path.join(packageRoot, 'php-files');
    const php = path.join(phpFiles, 'php');
    const port = await availablePort();
    let stderr = '';
    let child;

    try {
        await fs.mkdir(uploads, { recursive: true });
        await fs.writeFile(path.join(root, 'index.php'), "<?php http_response_code(404); echo 'wordpress-404';");
        await fs.writeFile(path.join(root, 'wp-login.php'), "<?php echo 'login-endpoint';");
        await fs.writeFile(path.join(content, 'debug.log'), 'outside-upload-scope');
        await fs.writeFile(path.join(uploads, 'public.txt'), 'public-upload');
        await fs.mkdir(path.join(uploads, 'php-index'));
        await fs.writeFile(path.join(uploads, 'php-index', 'index.php'), "<?php echo 'INDEX-EXECUTED';");

        const blocked = ['php', 'sql', 'sqlite', 'sqlite3', 'db', 'log', 'env', 'ini'];
        for (const extension of blocked) {
            const body = extension === 'php' ? "<?php echo 'EXECUTED';" : `secret-${extension}`;
            await fs.writeFile(path.join(uploads, `secret.${extension}`), body);
        }
        await fs.writeFile(path.join(uploads, 'UPPER.LOG'), 'uppercase-secret');

        child = spawn(php, ['-n', '-S', `127.0.0.1:${port}`, '-t', root, router], {
            cwd: phpFiles,
            env: {
                ...process.env,
                LD_LIBRARY_PATH: [path.join(phpFiles, 'lib'), process.env.LD_LIBRARY_PATH]
                    .filter(Boolean)
                    .join(':'),
            },
            stdio: ['ignore', 'ignore', 'pipe'],
        });
        child.stderr.on('data', chunk => { stderr += chunk.toString(); });

        const base = `http://127.0.0.1:${port}`;
        await waitForServer(`${base}/wp-content/uploads/nested/public.txt`, child);

        for (const extension of blocked) {
            const response = await fetch(`${base}/wp-content/uploads/nested/secret.${extension}`);
            const body = await response.text();
            assert.strictEqual(response.status, 404, `.${extension} is hidden`);
            assert.strictEqual(body, 'Not Found', `.${extension} does not reveal its contents`);
            assert.strictEqual(response.headers.get('cache-control'), 'no-store');
        }

        const uppercase = await fetch(`${base}/wp-content/uploads/nested/UPPER.LOG`);
        assert.strictEqual(uppercase.status, 404, 'extension matching is case-insensitive');

        const phpIndex = await fetch(`${base}/wp-content/uploads/nested/php-index/`);
        assert.strictEqual(phpIndex.status, 404, 'an uploads directory cannot execute its index.php');
        assert.strictEqual(await phpIndex.text(), 'Not Found');

        const publicUpload = await fetch(`${base}/wp-content/uploads/nested/public.txt`);
        assert.strictEqual(publicUpload.status, 200);
        assert.strictEqual(await publicUpload.text(), 'public-upload');

        const login = await fetch(`${base}/wp-login.php`);
        assert.strictEqual(login.status, 200, 'WordPress PHP endpoints remain executable');
        assert.strictEqual(await login.text(), 'login-endpoint');

        const outsideScope = await fetch(`${base}/wp-content/debug.log`);
        assert.strictEqual(outsideScope.status, 200, 'the policy is limited to uploads');
        assert.strictEqual(await outsideScope.text(), 'outside-upload-scope');
    }
    finally {
        if (child && child.exitCode === null) {
            child.kill('SIGTERM');
            await new Promise(resolve => child.once('exit', resolve));
        }
        await fs.rm(root, { recursive: true, force: true });
    }

    assert.doesNotMatch(stderr, /fatal|parse error/i);
});
