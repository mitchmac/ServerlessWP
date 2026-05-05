// Concurrency tests for util/sqliteS3.js.
//
// These exercise the per-invocation working-file model: each request gets
// its own copy of the SQLite database, isolating concurrent requests on the
// same warm instance.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs').promises;
const path = require('path');
const os = require('os');
const sqlite3 = require('sqlite3').verbose();

const sqliteS3 = require('../util/sqliteS3.js');

const ETAG_CACHE = '/tmp/etag.txt';
const CACHE_FILE = '/tmp/wp-sqlite-cache.sqlite';

// Build a small valid SQLite db file in memory and return its bytes.
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

function makeMockS3({ initialBody, initialEtag = 'etag-1' }) {
    const state = {
        body: initialBody,
        etag: initialEtag,
        getCalls: 0,
        putCalls: 0,
        // Force the next N PUTs to fail with 412.
        forcePutPreconditionFailures: 0,
    };
    const client = {
        async send(command) {
            const name = command.constructor.name;
            if (name === 'GetObjectCommand') {
                state.getCalls++;
                const input = command.input;
                if (input.IfNoneMatch && input.IfNoneMatch === state.etag) {
                    const err = new Error('Not Modified');
                    err.$metadata = { httpStatusCode: 304 };
                    throw err;
                }
                return { Body: state.body, ETag: state.etag };
            }
            if (name === 'PutObjectCommand') {
                state.putCalls++;
                const input = command.input;
                if (state.forcePutPreconditionFailures > 0) {
                    state.forcePutPreconditionFailures--;
                    const err = new Error('Precondition Failed');
                    err.$metadata = { httpStatusCode: 412 };
                    throw err;
                }
                if (input.IfMatch && input.IfMatch !== state.etag) {
                    const err = new Error('Precondition Failed');
                    err.$metadata = { httpStatusCode: 412 };
                    throw err;
                }
                state.body = input.Body;
                state.etag = 'etag-' + (state.putCalls + 1);
                return { ETag: state.etag };
            }
            throw new Error('Unexpected command: ' + name);
        },
    };
    return { client, state };
}

// Make instances of the SDK command classes (without depending on the SDK
// directly). The mock checks .constructor.name, so define matching classes.
class GetObjectCommand { constructor(input) { this.input = input; } }
class PutObjectCommand { constructor(input) { this.input = input; } }

async function cleanupTmp() {
    for (const p of [ETAG_CACHE, CACHE_FILE]) {
        try { await fs.unlink(p); } catch (e) {}
    }
    // Remove leftover working files from previous test runs.
    const entries = await fs.readdir('/tmp');
    await Promise.all(entries
        .filter(e => e.startsWith('wp-sqlite-') && e !== 'wp-sqlite-cache.sqlite')
        .map(e => fs.unlink('/tmp/' + e).catch(() => {})));
}

test.beforeEach(async () => {
    await cleanupTmp();
});

test('concurrent preRequest calls produce distinct working files', async () => {
    const body = await buildDbBytes('hello');
    const { client } = makeMockS3({ initialBody: body });
    sqliteS3._setClientForTests(client, { bucket: 'b', file: 'f' });

    const events = [{}, {}, {}];
    await Promise.all(events.map(e => sqliteS3.preRequest(e)));

    const ctxKey = Symbol.for('serverlesswp.sqliteS3.context');
    const paths = events.map(e => e[ctxKey].workingPath);

    assert.strictEqual(new Set(paths).size, 3, 'each invocation gets a unique working path');
    for (const p of paths) {
        assert.notStrictEqual(p, CACHE_FILE, 'working path is not the cache path');
        const stat = await fs.stat(p);
        assert.ok(stat.size > 0, 'working file exists and is non-empty');
    }
    // Each request opened its own db handle.
    assert.ok(events.every(e => e[ctxKey].db), 'each context has its own db handle');
    assert.strictEqual(new Set(events.map(e => e[ctxKey].db)).size, 3, 'db handles are distinct');

    // Clean up
    await Promise.all(events.map(e => sqliteS3.postRequest(e, {})));
});

test('concurrent writes do not corrupt each other; S3 arbitrates via ETag', async () => {
    const body = await buildDbBytes('seed');
    const { client, state } = makeMockS3({ initialBody: body });
    sqliteS3._setClientForTests(client, { bucket: 'b', file: 'f' });

    // Run many request pairs concurrently. Each writes a row.
    const N = 8;
    const events = Array.from({ length: N }, () => ({}));

    await Promise.all(events.map(async (event, i) => {
        await sqliteS3.preRequest(event);
        const ctxKey = Symbol.for('serverlesswp.sqliteS3.context');
        const ctx = event[ctxKey];
        // Mutate via a *separate* connection — PRAGMA data_version only
        // increments when another connection commits. (In prod, PHP writes
        // through its own connection while Node holds ctx.db.)
        await new Promise((resolve, reject) => {
            const writer = new sqlite3.Database(ctx.workingPath);
            writer.run('INSERT INTO t VALUES (?)', ['row-' + i], (err) => {
                if (err) return reject(err);
                writer.close(() => resolve());
            });
        });
        await sqliteS3.postRequest(event, {});
    }));

    // At least one PUT should have succeeded; conflicting PUTs surface 412
    // through the retry response (the host runtime would re-invoke).
    assert.ok(state.putCalls >= 1, 'at least one PUT was attempted');

    // After all the dust settles, there should be no working files left
    // and no SQLite handles open. A new preRequest should successfully open
    // the cache as a valid (uncorrupted) SQLite database.
    const leftovers = (await fs.readdir('/tmp'))
        .filter(e => e.startsWith('wp-sqlite-') && e !== 'wp-sqlite-cache.sqlite');
    assert.deepStrictEqual(leftovers, [], 'no working files leaked');

    // Verify the canonical S3 body is a valid SQLite file (not corrupt).
    const verifyPath = '/tmp/sqliteS3-test-verify.sqlite';
    await fs.writeFile(verifyPath, state.body);
    const rows = await new Promise((resolve, reject) => {
        const db = new sqlite3.Database(verifyPath);
        db.all('SELECT v FROM t', (err, rows) => {
            if (err) return reject(err);
            db.close(() => resolve(rows));
        });
    });
    await fs.unlink(verifyPath);
    assert.ok(rows.length >= 1, 'final S3 body is a valid SQLite db with rows');
});

test('412 on PUT returns retry response and does not refresh local cache', async () => {
    const body = await buildDbBytes('seed');
    const { client, state } = makeMockS3({ initialBody: body });
    state.forcePutPreconditionFailures = 1;
    sqliteS3._setClientForTests(client, { bucket: 'b', file: 'f' });

    const event = {};
    await sqliteS3.preRequest(event);
    const ctxKey = Symbol.for('serverlesswp.sqliteS3.context');
    const ctx = event[ctxKey];

    // Snapshot the cache file's content/etag before mutating.
    const cacheBefore = await fs.readFile(CACHE_FILE);
    const etagBefore = await fs.readFile(ETAG_CACHE, 'utf8');

    await new Promise((resolve, reject) => {
        const writer = new sqlite3.Database(ctx.workingPath);
        writer.run('INSERT INTO t VALUES (?)', ['conflict'], (err) => {
            if (err) return reject(err);
            writer.close(() => resolve());
        });
    });

    const result = await sqliteS3.postRequest(event, {});
    assert.ok(result, 'postRequest returned an error response');
    assert.strictEqual(result.statusCode, 500);
    assert.strictEqual(result.retry, true);

    // Cache must NOT have been refreshed with the failed write.
    const cacheAfter = await fs.readFile(CACHE_FILE);
    const etagAfter = await fs.readFile(ETAG_CACHE, 'utf8');
    assert.deepStrictEqual(cacheAfter, cacheBefore, 'cache file unchanged after 412');
    assert.strictEqual(etagAfter, etagBefore, 'cached etag unchanged after 412');

    // Working file should be cleaned up.
    await assert.rejects(fs.access(ctx.workingPath));
});

test('module state is not shared between concurrent requests', async () => {
    // Specifically: request B mutating its db must not affect request A's
    // dataVersion/db reference.
    const body = await buildDbBytes('shared');
    const { client } = makeMockS3({ initialBody: body });
    sqliteS3._setClientForTests(client, { bucket: 'b', file: 'f' });

    const a = {}, b = {};
    await sqliteS3.preRequest(a);
    await sqliteS3.preRequest(b);

    const ctxKey = Symbol.for('serverlesswp.sqliteS3.context');
    assert.notStrictEqual(a[ctxKey].db, b[ctxKey].db);
    assert.notStrictEqual(a[ctxKey].workingPath, b[ctxKey].workingPath);

    // Mutate B; A's data_version (captured at preRequest) should not change
    // out from under it.
    const aVersionBefore = a[ctxKey].dataVersion;
    await new Promise((resolve, reject) => {
        b[ctxKey].db.run('INSERT INTO t VALUES (?)', ['from-b'], (err) => err ? reject(err) : resolve());
    });
    assert.strictEqual(a[ctxKey].dataVersion, aVersionBefore, 'A\'s captured dataVersion is unaffected by B');

    await sqliteS3.postRequest(a, {});
    await sqliteS3.postRequest(b, {});
});

test('client-supplied X-Serverlesswp-Sqlite-File header is stripped', async () => {
    const body = await buildDbBytes('seed');
    const { client } = makeMockS3({ initialBody: body });
    sqliteS3._setClientForTests(client, { bucket: 'b', file: 'f' });

    // Try several casings — gateways may pass through arbitrary case.
    const event = {
        headers: {
            'X-Serverlesswp-Sqlite-File': '../wp-sqlite-cache.sqlite',
            'x-serverlesswp-sqlite-file': 'wp-sqlite-cache.sqlite',
            'X-SERVERLESSWP-SQLITE-FILE': 'etag.txt',
        },
    };
    await sqliteS3.preRequest(event);

    // Exactly one header remains, and its value is the per-invocation file
    // (not anything the client supplied).
    const matching = Object.entries(event.headers)
        .filter(([k]) => k.toLowerCase() === 'x-serverlesswp-sqlite-file');
    assert.strictEqual(matching.length, 1, 'one header survives');
    const [, value] = matching[0];
    assert.match(value, /^wp-sqlite-[0-9a-f-]+\.sqlite$/, 'value is the per-invocation working file name');
    assert.notStrictEqual(value, 'wp-sqlite-cache.sqlite');
    assert.notStrictEqual(value, 'etag.txt');

    await sqliteS3.postRequest(event, {});
});

// The sqliteS3 module imports the real @aws-sdk PutObjectCommand /
// GetObjectCommand classes. Our mock dispatches by constructor.name, but the
// production code constructs the *real* SDK command instances. So our mock
// must accept those names. Sanity-check that the SDK class names match.
test('SDK command class names match what the mock dispatches on', () => {
    const sdk = require('@aws-sdk/client-s3');
    assert.strictEqual(new sdk.GetObjectCommand({}).constructor.name, 'GetObjectCommand');
    assert.strictEqual(new sdk.PutObjectCommand({}).constructor.name, 'PutObjectCommand');
});
