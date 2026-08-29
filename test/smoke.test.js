// Version-agnostic smoke tests. These assert only stable WordPress contracts --
// HTTP status, the REST API shape, the login form's field names -- never the
// wp-admin or theme markup that shifts between releases. So the same file passes
// across WordPress versions and runs anywhere a site is reachable, not just the
// test container: point it at a deployment with SMOKE_BASE_URL.
//
//   node --test smoke.test.js                                  # local container
//   SMOKE_BASE_URL=https://example.com node --test smoke.test.js
//
// Read-only on purpose: nothing here writes, so it is safe against a live site.
// SMOKE_INSECURE=1 (implied for localhost) accepts the self-signed test cert.

const { test, before } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const https = require('node:https');

const BASE = (process.env.SMOKE_BASE_URL || 'https://localhost:3000').replace(/\/+$/, '');
const INSECURE = process.env.SMOKE_INSECURE === '1' || /^https:\/\/(localhost|127\.0\.0\.1)/.test(BASE);

const FATAL = /(Fatal error|Parse error|Uncaught \w*Error|There has been a critical error)/i;

function get(pathname, { headers = {} } = {}) {
    const url = new URL(pathname, BASE + '/');
    const client = url.protocol === 'https:' ? https : http;

    return new Promise((resolve, reject) => {
        const req = client.get(url, { headers, rejectUnauthorized: !INSECURE }, (res) => {
            const chunks = [];
            res.on('data', (chunk) => chunks.push(chunk));
            res.on('end', () => resolve({
                status: res.statusCode,
                headers: res.headers,
                body: Buffer.concat(chunks).toString('utf8'),
            }));
        });
        req.on('error', reject);
        req.setTimeout(30000, () => req.destroy(new Error(`timed out after 30s requesting ${url.href}`)));
    });
}

// One reachability probe up front, so a site that is down reads as that rather
// than every test failing with a connection error.
before(async () => {
    try {
        await get('/');
    } catch (error) {
        throw new Error(`${BASE} is not reachable: ${error.message}`);
    }
});

test('homepage renders an HTML document', async () => {
    const res = await get('/');
    assert.equal(res.status, 200, `expected 200 from /, got ${res.status}`);
    assert.match(res.body, /<html/i, 'homepage is not HTML');
    assert.match(res.body, /<title[\s>]/i, 'homepage has no <title>');
    assert.doesNotMatch(res.body, FATAL, 'homepage shows a PHP error');
});

test('login form is present with its stable field names', async () => {
    const res = await get('/wp-login.php');
    assert.equal(res.status, 200, `expected 200 from /wp-login.php, got ${res.status}`);
    assert.match(res.body, /name=["']log["']/, 'no username field (name="log")');
    assert.match(res.body, /name=["']pwd["']/, 'no password field (name="pwd")');
});

// The ?rest_route= form works whatever the permalink setting is; /wp-json/ only
// resolves with pretty permalinks, which a fresh install doesn't have.
test('REST API root advertises the wp/v2 namespace', async () => {
    const res = await get('/?rest_route=/');
    assert.equal(res.status, 200, `expected 200 from the REST root, got ${res.status}`);

    const body = JSON.parse(res.body);
    assert.ok(Array.isArray(body.namespaces), 'REST root has no namespaces array');
    assert.ok(body.namespaces.includes('wp/v2'), 'REST root does not advertise wp/v2');
});

test('REST posts endpoint returns a collection', async () => {
    const res = await get('/?rest_route=/wp/v2/posts');
    assert.equal(res.status, 200, `expected 200 from the posts endpoint, got ${res.status}`);
    assert.ok(Array.isArray(JSON.parse(res.body)), 'posts endpoint did not return an array');
});

// ?p= with a missing id is a 404 on any permalink setting; an unknown pretty
// path is not (WordPress canonical-redirects it on plain permalinks).
test('a missing post returns 404, not a server error', async () => {
    const res = await get('/?p=999999999');
    assert.equal(res.status, 404, `expected 404 for a missing post, got ${res.status}`);
});
