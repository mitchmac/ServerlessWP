// Database selection in util/storage.js.
//
// The Vercel Blob cases matter most: a store connected through the deploy
// button authenticates with OIDC, so BLOB_STORE_ID and a platform-minted
// VERCEL_OIDC_TOKEN are all the deployment gets. Requiring a read-write token
// there leaves the site on the setup page with a working store attached.

const test = require('node:test');
const assert = require('node:assert');

const storage = require('../util/storage.js');

const MANAGED = [
    'DATABASE', 'USERNAME', 'PASSWORD', 'HOST',
    'SQLITE_S3_BUCKET', 'SERVERLESSWP_DATA_SECRET',
    'VERCEL', 'VERCEL_GIT_COMMIT_REF',
    'BLOB_STORE_ID', 'SQLITE_BLOB_STORE_ID',
    'BLOB_READ_WRITE_TOKEN', 'SQLITE_BLOB_READ_WRITE_TOKEN',
    'SQLITE_BLOB_PATHNAME',
];

let saved;

test.beforeEach(() => {
    saved = {};
    for (const name of MANAGED) {
        saved[name] = process.env[name];
        delete process.env[name];
    }
});

test.afterEach(() => {
    for (const name of MANAGED) {
        if (saved[name] === undefined) {
            delete process.env[name];
        } else {
            process.env[name] = saved[name];
        }
    }
});

test('no credentials means the setup page', () => {
    assert.strictEqual(storage.resolve().mode, 'none');
});

test('a store connected on Vercel is used through its store id', () => {
    process.env.VERCEL = '1';
    process.env.BLOB_STORE_ID = 'store_abc123';

    const database = storage.resolve();
    assert.strictEqual(database.mode, 'sqlite-vercel-blob');
    assert.strictEqual(database.config.storeId, 'store_abc123');
    assert.strictEqual(database.config.token, undefined);
});

test('a store id from an envVarPrefix of SQLITE wins over the unprefixed one', () => {
    process.env.VERCEL = '1';
    process.env.BLOB_STORE_ID = 'store_uploads';
    process.env.SQLITE_BLOB_STORE_ID = 'store_database';

    assert.strictEqual(storage.resolve().config.storeId, 'store_database');
});

test('a read-write token still selects and configures the blob database', () => {
    process.env.VERCEL = '1';
    process.env.SQLITE_BLOB_READ_WRITE_TOKEN = 'vercel_blob_rw_abc123_secret';

    const database = storage.resolve();
    assert.strictEqual(database.mode, 'sqlite-vercel-blob');
    assert.strictEqual(database.config.token, 'vercel_blob_rw_abc123_secret');
});

test('the database name carries the branch so previews get their own', () => {
    process.env.VERCEL = '1';
    process.env.BLOB_STORE_ID = 'store_abc123';
    process.env.VERCEL_GIT_COMMIT_REF = 'feature/blob';

    assert.strictEqual(storage.resolve().config.pathname, 'wp-sqlite-feature%2Fblob.sqlite');
});

test('blob is not used off Vercel, where there is no OIDC token to pair', () => {
    process.env.BLOB_STORE_ID = 'store_abc123';

    assert.strictEqual(storage.resolve().mode, 'none');
});

test('an unprefixed read-write token is ignored', () => {
    // That's the token a store connected for uploads gets. Those stores are
    // public, so every private write against one would fail.
    process.env.VERCEL = '1';
    process.env.BLOB_READ_WRITE_TOKEN = 'vercel_blob_rw_uploads_secret';

    assert.strictEqual(storage.resolve().mode, 'none');
});

test('MySQL wins over a connected blob store', () => {
    process.env.VERCEL = '1';
    process.env.BLOB_STORE_ID = 'store_abc123';
    process.env.DATABASE = 'wordpress';
    process.env.USERNAME = 'wp';
    process.env.PASSWORD = 'secret';
    process.env.HOST = 'db.example.com';

    assert.strictEqual(storage.resolve().mode, 'mysql');
});

test('SQLite + S3 wins over a connected blob store', () => {
    process.env.VERCEL = '1';
    process.env.BLOB_STORE_ID = 'store_abc123';
    process.env.SQLITE_S3_BUCKET = 'wp-database';

    assert.strictEqual(storage.resolve().mode, 'sqlite-s3');
});
