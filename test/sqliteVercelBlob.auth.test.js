// Credential forwarding in util/sqliteVercelBlob.js.
//
// A store connected on Vercel authenticates with OIDC: the SDK pairs the store
// id with the VERCEL_OIDC_TOKEN the platform injects. The store id has to reach
// every call - without it the SDK can't build the download URL or name the
// store on a write, so both the read and the save fail.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs').promises;
const path = require('path');
const os = require('os');
const { Readable } = require('node:stream');
const sqlite3 = require('sqlite3').verbose();

const sqliteVercelBlob = require('../util/sqliteVercelBlob.js');

const ETAG_CACHE = '/tmp/etag-vercel-blob.txt';
const CACHE_FILE = '/tmp/wp-sqlite-cache.sqlite';
const CTX_KEY = Symbol.for('serverlesswp.sqliteVercelBlob.context');

async function buildDbBytes() {
    const tmp = path.join(os.tmpdir(), `auth-seed-${Date.now()}-${Math.random()}.sqlite`);
    await new Promise((resolve, reject) => {
        const db = new sqlite3.Database(tmp);
        db.serialize(() => {
            db.run('CREATE TABLE t (v TEXT)');
            db.run('INSERT INTO t VALUES (?)', ['seed'], (err) => err ? reject(err) : null);
            db.close((err) => err ? reject(err) : resolve());
        });
    });
    const bytes = await fs.readFile(tmp);
    await fs.unlink(tmp);
    return bytes;
}

// A write through a second connection, so PRAGMA data_version moves and the
// plugin decides it has changes to save - the same shape as PHP's writes.
function insertRow(dbPath) {
    return new Promise((resolve, reject) => {
        const writer = new sqlite3.Database(dbPath);
        writer.run('INSERT INTO t VALUES (?)', ['written'], (err) => {
            if (err) return reject(err);
            writer.close(() => resolve());
        });
    });
}

// Records the options each call was made with.
function makeRecordingBlobStore(body) {
    const calls = { get: [], put: [] };
    const api = {
        async get(pathname, options = {}) {
            calls.get.push(options);
            return {
                statusCode: 200,
                stream: Readable.toWeb(Readable.from([body])),
                blob: { etag: 'W/"etag-1"' },
            };
        },
        async put(pathname, content, options = {}) {
            calls.put.push(options);
            return { etag: '"etag-2"' };
        },
    };
    return { api, calls };
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

// Runs one request against a recording store and returns what it saw.
async function roundTrip(config) {
    const { api, calls } = makeRecordingBlobStore(await buildDbBytes());
    sqliteVercelBlob._setBlobForTests(api);
    sqliteVercelBlob.config(config);

    const event = {};
    await sqliteVercelBlob.preRequest(event);
    await insertRow(event[CTX_KEY].workingPath);
    const result = await sqliteVercelBlob.postRequest(event, {});

    assert.strictEqual(result, undefined, 'the save succeeds');
    assert.strictEqual(calls.put.length, 1, 'the change is written back');
    return calls;
}

test.beforeEach(async () => {
    await cleanupTmp();
});

test('the store id reaches both the read and the write', async () => {
    const calls = await roundTrip({ pathname: 'wp-sqlite-test.sqlite', storeId: 'store_abc123' });

    assert.strictEqual(calls.get[0].storeId, 'store_abc123');
    assert.strictEqual(calls.put[0].storeId, 'store_abc123');
    assert.strictEqual(calls.get[0].token, undefined, 'no token is invented for OIDC');
    assert.strictEqual(calls.put[0].token, undefined);
});

test('a read-write token reaches both the read and the write', async () => {
    const calls = await roundTrip({ pathname: 'wp-sqlite-test.sqlite', token: 'vercel_blob_rw_abc123_secret' });

    assert.strictEqual(calls.get[0].token, 'vercel_blob_rw_abc123_secret');
    assert.strictEqual(calls.put[0].token, 'vercel_blob_rw_abc123_secret');
});
