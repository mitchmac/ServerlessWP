// Concurrency tests for util/sqliteVercelBlob.js.
//
// These mirror sqliteS3.concurrency.test.js: each request works on its own
// copy of the database, and the conditional write must use the blob version
// that copy came from - not whatever the shared etag file says at write time.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs').promises;
const path = require('path');
const os = require('os');
const { Readable } = require('node:stream');
const sqlite3 = require('sqlite3').verbose();
const { BlobPreconditionFailedError, BlobNotFoundError } = require('@vercel/blob');

const sqliteVercelBlob = require('../util/sqliteVercelBlob.js');

const ETAG_CACHE = '/tmp/etag-vercel-blob.txt';
const CACHE_FILE = '/tmp/wp-sqlite-cache.sqlite';
const CTX_KEY = Symbol.for('serverlesswp.sqliteVercelBlob.context');

// Build a small valid SQLite db file and return its bytes.
async function buildDbBytes(seedRow) {
    const tmp = path.join(os.tmpdir(), `seed-${Date.now()}-${Math.random()}.sqlite`);
    await new Promise((resolve, reject) => {
        const db = new sqlite3.Database(tmp);
        db.serialize(() => {
            db.run('CREATE TABLE t (v TEXT)');
            db.run('INSERT INTO t VALUES (?)', [seedRow], (err) => err ? reject(err) : null);
            db.close((err) => err ? reject(err) : resolve());
        });
    });
    const bytes = await fs.readFile(tmp);
    await fs.unlink(tmp);
    return bytes;
}

// Insert a row through a separate connection - PRAGMA data_version only
// increments when another connection commits, which is how PHP's writes look
// to the Node-held handle in production.
function insertRow(dbPath, value) {
    return new Promise((resolve, reject) => {
        const writer = new sqlite3.Database(dbPath);
        writer.run('INSERT INTO t VALUES (?)', [value], (err) => {
            if (err) return reject(err);
            writer.close(() => resolve());
        });
    });
}

// Mimics the store behavior the plugin depends on: downloads report the weak
// validator (W/"...") while put reports the strong form, and x-if-match is
// compared against the strong one. `etagOnDownload: false` simulates a
// download that arrives without an ETag.
function makeMockBlobStore({ initialBody = null, etagOnDownload = true } = {}) {
    const state = {
        body: initialBody,
        etag: '"etag-1"',
        getCalls: 0,
        putCalls: 0,
        putOptions: [],
    };
    const api = {
        async get(pathname, options = {}) {
            state.getCalls++;
            if (state.body == null) {
                throw new BlobNotFoundError();
            }
            const weakEtag = 'W/' + state.etag;
            if (options.ifNoneMatch && options.ifNoneMatch === state.etag) {
                return { statusCode: 304 };
            }
            const result = {
                statusCode: 200,
                stream: Readable.toWeb(Readable.from([state.body])),
                blob: {},
            };
            if (etagOnDownload) {
                result.blob.etag = weakEtag;
            }
            return result;
        },
        async put(pathname, body, options = {}) {
            state.putCalls++;
            state.putOptions.push(options);
            if (options.ifMatch && options.ifMatch !== state.etag) {
                throw new BlobPreconditionFailedError();
            }
            if (options.allowOverwrite === false && state.body != null) {
                throw new BlobPreconditionFailedError();
            }
            state.body = Buffer.from(body);
            state.etag = '"etag-' + (state.putCalls + 1) + '"';
            return { etag: state.etag };
        },
    };
    return { api, state };
}

async function cleanupTmp() {
    for (const p of [ETAG_CACHE, CACHE_FILE]) {
        try { await fs.unlink(p); } catch (e) {}
    }
    const entries = await fs.readdir('/tmp');
    await Promise.all(entries
        .filter(e => e.startsWith('wp-sqlite-') && e !== 'wp-sqlite-cache.sqlite')
        .map(e => fs.unlink('/tmp/' + e).catch(() => {})));
}

test.beforeEach(async () => {
    await cleanupTmp();
});

test('a stale working copy cannot silently overwrite a committed write', async () => {
    // Lost-update regression: A and B start from the same version. A commits
    // first, which advances the shared etag file on this instance. B's
    // ifMatch must still be the version B *started from*, so the store
    // rejects it - reading the etag file again at write time would let B's
    // put pass and silently revert A's committed write.
    const { api, state } = makeMockBlobStore({ initialBody: await buildDbBytes('seed') });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const a = {}, b = {};
    await sqliteVercelBlob.preRequest(a);
    await sqliteVercelBlob.preRequest(b);

    await insertRow(a[CTX_KEY].workingPath, 'from-a');
    const resultA = await sqliteVercelBlob.postRequest(a, {});
    assert.strictEqual(resultA, undefined, 'A saves cleanly');
    const bodyAfterA = state.body;

    await insertRow(b[CTX_KEY].workingPath, 'from-b');
    const resultB = await sqliteVercelBlob.postRequest(b, {});
    assert.ok(resultB, 'B\'s save is rejected');
    assert.strictEqual(resultB.statusCode, 500);
    assert.strictEqual(resultB.retry, true, 'B is retried on fresh data');
    assert.deepStrictEqual(state.body, bodyAfterA, 'A\'s committed write is preserved');
});

test('a 304 revalidation binds the cached etag to the request', async () => {
    const { api, state } = makeMockBlobStore({ initialBody: await buildDbBytes('seed') });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    // First request warms the cache (full download), second revalidates.
    const warm = {};
    await sqliteVercelBlob.preRequest(warm);
    await sqliteVercelBlob.postRequest(warm, {});

    const event = {};
    await sqliteVercelBlob.preRequest(event);
    assert.strictEqual(state.getCalls, 2);
    assert.strictEqual(event[CTX_KEY].etag, state.etag, '304 path bound the strong etag');

    await insertRow(event[CTX_KEY].workingPath, 'row');
    const result = await sqliteVercelBlob.postRequest(event, {});
    assert.strictEqual(result, undefined, 'conditional write succeeds from the 304 path');
});

test('a missing blob is a new site and still gets saved', async () => {
    const { api, state } = makeMockBlobStore({ initialBody: null });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const event = {};
    const response = await sqliteVercelBlob.preRequest(event);
    assert.strictEqual(response, undefined, 'no database yet is not an error');

    // Stand in for WordPress installing itself into the working file.
    const ctx = event[CTX_KEY];
    await fs.writeFile(ctx.workingPath, await buildDbBytes('installed'));

    const result = await sqliteVercelBlob.postRequest(event, {});
    assert.strictEqual(result, undefined, 'the request succeeds');
    assert.strictEqual(state.putCalls, 1, 'the new database was saved');
    assert.strictEqual(state.putOptions[0].allowOverwrite, false, 'the first save is create-only');
});

test('concurrent first creates preserve the blob that wins', async () => {
    const { api, state } = makeMockBlobStore({ initialBody: null });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const a = {}, b = {};
    await sqliteVercelBlob.preRequest(a);
    await sqliteVercelBlob.preRequest(b);
    await fs.writeFile(a[CTX_KEY].workingPath, await buildDbBytes('winner'));
    await fs.writeFile(b[CTX_KEY].workingPath, await buildDbBytes('loser'));

    assert.strictEqual(await sqliteVercelBlob.postRequest(a, {}), undefined);
    const winningBody = Buffer.from(state.body);
    const result = await sqliteVercelBlob.postRequest(b, {});

    assert.strictEqual(result.statusCode, 500, 'the losing create fails closed');
    assert.strictEqual(result.retry, undefined, 'an installation request is not replayed automatically');
    assert.deepStrictEqual(state.body, winningBody, 'the winner is not overwritten');
    assert.deepStrictEqual(state.putOptions.map(options => options.allowOverwrite), [false, false]);
});

test('an unknown starting version refuses to write instead of clobbering', async () => {
    // The blob exists but the download carried no ETag, so the request never
    // learned which version it started from. An unconditional put could
    // overwrite another instance's commit - the save must fail instead.
    const { api, state } = makeMockBlobStore({
        initialBody: await buildDbBytes('seed'),
        etagOnDownload: false,
    });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const event = {};
    await sqliteVercelBlob.preRequest(event);

    await insertRow(event[CTX_KEY].workingPath, 'row');
    const result = await sqliteVercelBlob.postRequest(event, {});
    assert.ok(result, 'the save is refused');
    assert.strictEqual(result.statusCode, 500);
    assert.strictEqual(result.retry, true);
    assert.strictEqual(state.putCalls, 0, 'nothing was written to the store');
});

test('a data_version failure returns an error instead of the WordPress success response', async () => {
    const { api, state } = makeMockBlobStore({ initialBody: await buildDbBytes('seed') });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const event = {};
    await sqliteVercelBlob.preRequest(event);
    const ctx = event[CTX_KEY];
    await insertRow(ctx.workingPath, 'not-persisted');

    await new Promise((resolve, reject) => {
        ctx.db.close((err) => err ? reject(err) : resolve());
    });

    const result = await sqliteVercelBlob.postRequest(event, { statusCode: 200, body: 'saved' });
    assert.strictEqual(result.statusCode, 500);
    assert.strictEqual(result.headers['cache-control'], 'no-store');
    assert.match(result.body, /check your function logs for more information/i);
    assert.strictEqual(state.putCalls, 0, 'the uncertain database is not uploaded');
});

test('a missing working file returns an error instead of the WordPress success response', async () => {
    const { api, state } = makeMockBlobStore({ initialBody: await buildDbBytes('seed') });
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config({ pathname: 'wp-sqlite-test.sqlite' });

    const event = {};
    await sqliteVercelBlob.preRequest(event);
    await fs.unlink(event[CTX_KEY].workingPath);

    const result = await sqliteVercelBlob.postRequest(event, { statusCode: 200, body: 'saved' });
    assert.strictEqual(result.statusCode, 500);
    assert.match(result.body, /check your function logs for more information/i);
    assert.strictEqual(state.putCalls, 0, 'nothing is uploaded when the working file disappeared');
});
