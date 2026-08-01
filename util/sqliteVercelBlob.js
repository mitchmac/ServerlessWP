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
        // The blob version this request's working copy came from. Bound here,
        // per request, because the shared etag file moves under concurrent
        // requests: reading it again at write time would let a request whose
        // copy predates another's committed write pass its ifMatch and
        // silently revert that write.
        etag: null,
        // Set when the read established the blob doesn't exist. Only then may
        // postRequest write without ifMatch.
        blobMissing: false,
    };
    event[CONTEXT_KEY] = ctx;

    // Tell PHP (wp-config.php) which DB file to open for this request.
    // Strip any inbound variant first so a client can't point WP at the
    // cache file or another request's working file. wp-config.php also
    // passes the value through basename() defensively.
    if (!event.headers) event.headers = {};
    for (const k of Object.keys(event.headers)) {
        if (k.toLowerCase() === 'x-serverlesswp-sqlite-file') {
            delete event.headers[k];
        }
    }
    event.headers['x-serverlesswp-sqlite-file'] = workingFileName;

    const cachedEtag = await getEtag();

    // useCache: false reads from origin instead of the CDN cache. Reads have to
    // be consistent here: a cached read can serve the previous version of the
    // blob for up to 60 seconds after a write, which both hands WordPress a
    // stale database and hands us a stale ETag - so the next conditional write
    // fails its ifMatch and the request 500s.
    // https://vercel.com/docs/vercel-blob/private-storage#consistent-reads
    const options = { access: 'private', useCache: false };
    if (_config.token) {
        options.token = _config.token;
    }
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
        if (!ctx.db) {
            if (workingExists) {
                ctx.db = new sqlite3.Database(ctx.workingPath);
                ctx.dataVersion = null;
            } else {
                return;
            }
        }

        const versionNow = await getDataVersion(ctx.db);

        // See if the db has been mutated, if so, send the changes to the blob store.
        const readOnly = process.env['SERVERLESSWP_READ_ONLY_MODE'];
        const readOnlyActive = readOnly && !['false', '0', 'no'].includes(readOnly.toLowerCase());
        if (!readOnlyActive && ctx.dataVersion !== versionNow && workingExists) {
            // The blob exists but we don't know which version the working
            // copy came from (e.g. a download without an ETag). Writing
            // anyway would be unconditional and could overwrite another
            // instance's committed changes - fail and let the retry re-fetch.
            if (!ctx.etag && !ctx.blobMissing) {
                console.log('Refusing to save database without a bound ETag.');
                return {
                    statusCode: 500,
                    headers: { 'content-type': 'text/plain', 'cache-control': 'no-store' },
                    body: 'Database error. This can happen when simultaneous database updates happen. Re-try your request.',
                    retry: true,
                };
            }

            try {
                await dbClose(ctx.db);
                ctx.db = null;

                const sqliteContent = await fs.readFile(ctx.workingPath);
                // The version this request started from, captured in
                // preRequest - never the shared etag file, which a concurrent
                // request may have advanced since.
                const currentEtag = ctx.etag;

                const putOptions = {
                    access: 'private',
                    allowOverwrite: true,
                    addRandomSuffix: false,
                };
                if (_config.token) {
                    putOptions.token = _config.token;
                }
                if (currentEtag) {
                    putOptions.ifMatch = currentEtag;
                }

                const putResponse = await put(_config.pathname, sqliteContent, putOptions);

                // Refresh the local cache before writing the ETag so etag.txt
                // never describes content newer than CACHE_FILE. If the copy
                // fails, the old ETag stays on disk and the next request's
                // ifNoneMatch will miss, triggering a clean re-fetch.
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
                const errResponse = {
                    statusCode: 500,
                    headers: { 'content-type': 'text/plain', 'cache-control': 'no-store' },
                    body: 'Database error. This can happen when simultaneous database updates happen. Re-try your request.'
                }
                if (err instanceof BlobPreconditionFailedError) {
                    errResponse.retry = true;
                    console.log('Retrying database save to Vercel Blob because of a conflicting update.');
                }
                return errResponse;
            }
        }
    }
    catch (err) {
        console.log(err);
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

// Fail the request when the blob can't be read. A blob that doesn't exist yet
// is a new site and returns null instead, so this is only reached when the read
// itself failed. Letting the request through would hand WordPress an empty
// database, and postRequest would then save that over the real one.
// _forceResponse stops the plugin chain, otherwise a later plugin returning
// nothing would drop this response and WordPress would run anyway.
function readError() {
    return {
        statusCode: 500,
        headers: { 'content-type': 'text/plain', 'cache-control': 'no-store' },
        body: 'Database error. The database could not be read. Re-try your request.',
        _forceResponse: true,
    };
}

// Downloads carry a weak validator (`W/"abc"`) while put and head report the
// strong form (`"abc"`), and x-if-match is compared against the strong one. So
// an ETag taken from a download can't be used for a conditional write as-is:
// the store sees a mismatch even though it's the same database. Any instance
// that did a full download - every cold start - could never write again.
// Canonicalize on the way in and out so both sources agree.
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
        return false;
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
