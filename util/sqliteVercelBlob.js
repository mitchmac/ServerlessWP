const sqlite3 = require('sqlite3').verbose();
const fs = require('fs').promises;
const fsSync = require('fs');
const { randomUUID } = require('crypto');
const { Readable } = require('node:stream');
const { pipeline } = require('node:stream/promises');
const { BlobPreconditionFailedError, BlobNotFoundError } = require('@vercel/blob');
let { get, put } = require('@vercel/blob');

const ETAG_CACHE = '/tmp/etag-vercel-blob.txt';
const CACHE_FILE = '/tmp/wp-sqlite-cache.sqlite';
const CONTEXT_KEY = Symbol.for('serverlesswp.sqliteVercelBlob.context');

let init = false;
let _config;

exports.name = 'ServerlessWP sqlite Vercel Blob';

exports.config = function(config) {
    _config = config;
}

exports.preRequest = async function(event) {
    if (!_config?.pathname) {
        throw new Error("Vercel Blob pathname is required");
    }

    const workingFileName = 'wp-sqlite-' + randomUUID() + '.sqlite';
    const ctx = {
        workingPath: '/tmp/' + workingFileName,
        db: null,
        dataVersion: null,
        // Bind the source ETag to this request; the shared cache may advance
        // before this working copy is written back.
        etag: null,
        // Set when the read established the blob doesn't exist. Only then may
        // postRequest write without ifMatch.
        blobMissing: false,
        // The OIDC credential for this request, taken off the headers below.
        oidcToken: null,
    };
    event[CONTEXT_KEY] = ctx;

    // Replace any client-supplied DB header with this request's working file.
    if (!event.headers) event.headers = {};
    for (const k of Object.keys(event.headers)) {
        const name = k.toLowerCase();
        if (name === 'x-serverlesswp-sqlite-file') {
            delete event.headers[k];
        }
        // AWS handler mode lacks the request context the Blob SDK normally uses,
        // so pass Vercel's per-request OIDC token explicitly.
        // https://vercel.com/docs/oidc#in-vercel-functions
        else if (name === 'x-vercel-oidc-token') {
            ctx.oidcToken = event.headers[k];
            // WordPress has no use for a store credential.
            delete event.headers[k];
        }
    }
    event.headers['x-serverlesswp-sqlite-file'] = workingFileName;

    // Local development and the test suite get the token from the environment.
    if (!ctx.oidcToken) {
        ctx.oidcToken = process.env['VERCEL_OIDC_TOKEN'] || null;
    }

    // Report which supported credential sources are missing.
    if (_config.storeId && !ctx.oidcToken && !_config.token) {
        console.error('No Vercel Blob credentials: no x-vercel-oidc-token header on the request '
            + '(OIDC federation is per project, under Settings > Security) and no '
            + 'SQLITE_BLOB_READ_WRITE_TOKEN set.');
        return readError();
    }

    const cachedEtag = await getEtag();

    // Read from origin: a cached body and ETag could make the next conditional
    // database write fail or start WordPress from stale state.
    // https://vercel.com/docs/vercel-blob/private-storage#consistent-reads
    const options = { access: 'private', useCache: false };
    applyAuth(options, ctx);
    // Only send ifNoneMatch if we actually have the cache file locally.
    // Otherwise a 304 leaves us with no file to copy.
    if (cachedEtag && await exists(CACHE_FILE)) {
        options.ifNoneMatch = cachedEtag;
    }

    try {
        const response = await get(_config.pathname, options);

        if (!response) {
            // Blob doesn't exist yet - behave like a new site.
            ctx.blobMissing = true;
            return;
        }

        if (response.statusCode === 304) {
            // Cache is up to date; fall through to copy below. The 304 was
            // earned by ifNoneMatch, so cachedEtag is the version we hold.
            ctx.etag = cachedEtag;
        }
        else if (response.statusCode === 200 && response.stream) {
            // Stream to a tmp path then atomically rename into place.
            // Existing open fds against the old inode keep working.
            const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
            await pipeline(
                Readable.fromWeb(response.stream),
                fsSync.createWriteStream(tmp)
            );
            await fs.rename(tmp, CACHE_FILE);
            const downloadedEtag = normalizeEtag(response.blob?.etag);
            if (downloadedEtag) {
                await setEtag(downloadedEtag);
                ctx.etag = downloadedEtag;
            }
        }
    }
    catch (err) {
        if (err instanceof BlobNotFoundError) {
            console.log('Database blob not found');
            ctx.blobMissing = true;
            return;
        }
        console.error('Error fetching database blob:', err);
        return readError();
    }

    // If we have a cache file (from this request or a previous one), copy it
    // to a per-invocation working file and open SQLite against that copy.
    // This isolates concurrent requests on the same warm instance.
    if (await exists(CACHE_FILE)) {
        await fs.copyFile(CACHE_FILE, ctx.workingPath);
        ctx.db = new sqlite3.Database(ctx.workingPath);
        ctx.dataVersion = await getDataVersion(ctx.db);
    }
}

exports.postRequest = async function(event, response) {
    const ctx = event[CONTEXT_KEY];
    if (!ctx) {
        return;
    }

    try {
        // If db wasn't initialized but the working file somehow exists, treat
        // it as a new database (e.g. fresh install path).
        const workingExists = await exists(ctx.workingPath);
        if (!workingExists) {
            console.error('Database persistence failed: the per-request SQLite working file is missing.');
            return persistenceError();
        }
        if (!ctx.db) {
            ctx.db = new sqlite3.Database(ctx.workingPath);
            ctx.dataVersion = null;
        }

        const versionNow = await getDataVersion(ctx.db);

        // See if the db has been mutated, if so, send the changes to the blob store.
        const readOnly = process.env['SERVERLESSWP_READ_ONLY_MODE'];
        const readOnlyActive = readOnly && !['false', '0', 'no'].includes(readOnly.toLowerCase());
        if (!readOnlyActive && ctx.dataVersion !== versionNow && workingExists) {
            // Never overwrite an existing blob without the ETag this copy came
            // from; retrying will fetch a safe base.
            if (!ctx.etag && !ctx.blobMissing) {
                console.log('Refusing to save database without a bound ETag.');
                return persistenceError(true);
            }

            try {
                await dbClose(ctx.db);
                ctx.db = null;

                const sqliteContent = await fs.readFile(ctx.workingPath);
                // Use the request's source ETag, not the shared cache's.
                const currentEtag = ctx.etag;

                const putOptions = {
                    access: 'private',
                    // Vercel Blob's create-only behavior is selected by
                    // disabling overwrite. Existing objects still use ifMatch
                    // below for optimistic concurrency control.
                    allowOverwrite: !ctx.blobMissing,
                    addRandomSuffix: false,
                };
                applyAuth(putOptions, ctx);
                if (currentEtag) {
                    putOptions.ifMatch = currentEtag;
                }

                const putResponse = await put(_config.pathname, sqliteContent, putOptions);

                // Update content before its ETag so the pair cannot describe
                // different blob versions.
                const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
                await fs.copyFile(ctx.workingPath, tmp);
                await fs.rename(tmp, CACHE_FILE);
                if (putResponse?.etag) {
                    await setEtag(putResponse.etag);
                }
                return;
            }
            catch (err) {
                console.error('Error saving database to Vercel Blob:', err);
                const errResponse = persistenceError();
                if (err instanceof BlobPreconditionFailedError) {
                    if (ctx.blobMissing) {
                        // Do not automatically replay an installation request.
                        // The next request will fetch the database that won.
                        console.log('Database creation was rejected because another request created it first.');
                    }
                    else {
                        errResponse.retry = true;
                        console.log('Retrying database save to Vercel Blob because of a conflicting update.');
                    }
                }
                return errResponse;
            }
        }
    }
    catch (err) {
        console.error('Unexpected database persistence error:', err);
        return persistenceError();
    }
    finally {
        if (ctx.db) {
            try { await dbClose(ctx.db); } catch (e) { /* swallow */ }
            ctx.db = null;
        }
        try { await fs.unlink(ctx.workingPath); } catch (e) { /* file may not exist */ }
        delete event[CONTEXT_KEY];
    }
}

// Fail closed: continuing after a read error could replace the real database
// with an empty one. _forceResponse prevents later plugins dropping the error.
function readError() {
    return {
        statusCode: 500,
        headers: { 'content-type': 'text/plain', 'cache-control': 'no-store' },
        body: 'Database error. The database could not be read. Re-try your request.',
        _forceResponse: true,
    };
}

function persistenceError(retry = false) {
    const response = {
        statusCode: 500,
        headers: { 'content-type': 'text/plain', 'cache-control': 'no-store' },
        body: 'Database persistence failed. Your changes may not have been saved. If this is your site, check your function logs for more information.',
    };
    if (retry) {
        response.retry = true;
    }
    return response;
}

// Tell the SDK which store to talk to and how to authenticate: the store id
// pairs with the request's OIDC token. A read-write token, where the store has
// one, takes precedence - passing both is what the SDK expects.
function applyAuth(options, ctx) {
    if (_config.storeId) {
        options.storeId = _config.storeId;
    }
    if (ctx?.oidcToken) {
        options.oidcToken = ctx.oidcToken;
    }
    if (_config.token) {
        options.token = _config.token;
    }
}

// Downloads may return weak ETags while conditional writes require the strong
// form, so normalize every ETag at the boundary.
function normalizeEtag(etag) {
    return typeof etag === 'string' ? etag.replace(/^W\//, '') : etag;
}

// Exported for tests.
exports._normalizeEtag = normalizeEtag;

// Test-only: swap the blob API for a mock without touching the real store.
exports._setBlobForTests = function(mock) {
    get = mock.get;
    put = mock.put;
}

async function getEtag() {
    try {
        return normalizeEtag(await fs.readFile(ETAG_CACHE, 'utf8'));
    } catch (err) {
        return '';
    }
}

async function setEtag(newEtag) {
    await fs.writeFile(ETAG_CACHE, normalizeEtag(newEtag));
}

async function getDataVersion(db) {
    return new Promise((resolve, reject) => {
        if (!db) { return reject('No db') }
        try {
            db.get("PRAGMA data_version", (err, row) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(row['data_version']);
                }
            });
        }
        catch (err) {
            reject(err);
        }
    });
}

async function dbClose(db) {
    return new Promise((resolve, reject) => {
        if (!db) { return reject('No db') }
        try {
            db.close((closeErr) => {
                if (closeErr) {
                    reject(closeErr);
                }
                resolve();
            });
        }
        catch (err) {
            reject(err);
        }
    });
}

async function exists(path) {
    try {
        await fs.access(path);
        return true;
    } catch (error) {
        if (error?.code === 'ENOENT') {
            return false;
        }
        throw error;
    }
}

// Put the sqlite db class in place if not already there.
// Paths should reference where they've been setup in /tmp.
exports.prepPlugin = async function (wpContentPath, sqlitePluginPath) {
    if (!init) {
        try {
            const pluginPackagePath = sqlitePluginPath;
            const oldPath = pluginPackagePath + '/db.copy';
            const newPath = wpContentPath + '/db.php';
            await fs.copyFile(oldPath, newPath);
            const content = await fs.readFile(newPath, 'utf8');
            const modifiedContent = content
                .replace(new RegExp(/{SQLITE_IMPLEMENTATION_FOLDER_PATH}/, 'g'), pluginPackagePath)
                .replace(new RegExp(/{SQLITE_PLUGIN}/, 'g'), 'sqlite-database-integration/load.php');

            await fs.writeFile(newPath, modifiedContent);
            init = true;
        }
        catch (err) {
            console.log(err);
        }
    }
}
