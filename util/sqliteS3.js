const sqlite3 = require('sqlite3').verbose();
const fs = require('fs').promises;
const { randomUUID } = require('crypto');
const { S3Client, GetObjectCommand, PutObjectCommand } = require('@aws-sdk/client-s3');

const ETAG_CACHE = '/tmp/etag.txt';
const CACHE_FILE = '/tmp/wp-sqlite-cache.sqlite';
const CONTEXT_KEY = Symbol.for('serverlesswp.sqliteS3.context');

let init = false;
let client;
let _config;

exports.name = 'ServerlessWP sqlite s3';

exports.config = function(config) {
    _config = config;
    if (config.S3Client) {
        // Cloudflare workaround for https://www.cloudflarestatus.com/incidents/t5nrjmpxc1cj
        if (config.S3Client.endpoint && config.S3Client.endpoint.includes('cloudflarestorage.com')) {
            config.S3Client.requestChecksumCalculation = "WHEN_REQUIRED";
            config.S3Client.responseChecksumValidation = "WHEN_REQUIRED";
        }
        client = new S3Client(config.S3Client);
    }
}

// Test-only: inject a mock S3 client without going through the real
// S3Client constructor.
exports._setClientForTests = function(mockClient, config) {
    client = mockClient;
    _config = config;
}

exports.preRequest = async function(event) {
    if (!_config?.bucket) {
        throw new Error("S3 bucket is required");
    }
    if (!_config?.file) {
        throw new Error("S3 file is required");
    }
    if (!client) {
        throw new Error("S3Client config is required");
    }

    const workingFileName = 'wp-sqlite-' + randomUUID() + '.sqlite';
    const ctx = {
        workingPath: '/tmp/' + workingFileName,
        db: null,
        dataVersion: null,
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

    let cachedEtag = await getEtag();

    let getCommandParams = {
        Bucket: _config.bucket,
        Key: _config.file
    }

    // Only send IfNoneMatch if we actually have the cache file locally.
    // Otherwise a 304 leaves us with no file to copy.
    if (cachedEtag && await exists(CACHE_FILE)) {
        getCommandParams.IfNoneMatch = cachedEtag;
    }

    const get = new GetObjectCommand(getCommandParams);

    try {
        const response = await client.send(get);

        if (response) {
            // Write to a tmp path then atomically rename into place.
            // Existing open fds against the old inode keep working.
            const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
            await fs.writeFile(tmp, response.Body);
            await fs.rename(tmp, CACHE_FILE);
            await setEtag(response.ETag);
        }
        else {
            // @TODO: if it doesn't exist, behave like it's a new site?
            console.log('db file not found');
        }
    }
    catch (err) {
        if (err.$metadata && err.$metadata.httpStatusCode === 304) {
            // Cache is up to date; fall through to copy below.
        }
        else if (err.$metadata?.httpStatusCode === 403) {
            if (_config.onAuthError) {
                try {
                    await _config.onAuthError(event, _config);
                } catch (regErr) {
                    console.error('Auto-registration failed:', regErr.message);
                }
            }
            return;
        }
        else if (err.name === 'NoSuchKey') {
            // Handle case where the file doesn't exist on S3
            console.log('Database file not found on server');
            return;
        }
        else {
            // Handle other errors
            console.error('Error fetching database:', err);
            return;
        }
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

        let versionNow = await getDataVersion(ctx.db);

        // See if the db has been mutated, if so, send the changes to s3
        const readOnly = process.env['SERVERLESSWP_READ_ONLY_MODE'] && !['false', '0', 'no'].includes(process.env['SERVERLESSWP_READ_ONLY_MODE'].toLowerCase());
        if (!readOnly && ctx.dataVersion !== versionNow && workingExists) {
            try {
                await dbClose(ctx.db);
                ctx.db = null;

                const sqliteContent = await fs.readFile(ctx.workingPath);
                let currentEtag = await getEtag();

                let putCommandParams = {
                    Bucket: _config.bucket,
                    Key: _config.file,
                    Body: sqliteContent,
                }

                if (currentEtag) {
                    putCommandParams.IfMatch = currentEtag;
                }
                const command = new PutObjectCommand(putCommandParams);

                const putResponse = await client.send(command);

                // Refresh the local cache before writing the ETag so etag.txt
                // never describes content newer than CACHE_FILE. If the copy
                // fails, the old ETag stays on disk and the next request's
                // IfNoneMatch will miss, triggering a clean re-fetch from S3.
                const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
                await fs.copyFile(ctx.workingPath, tmp);
                await fs.rename(tmp, CACHE_FILE);
                await setEtag(putResponse.ETag);
                return;
            }
            catch (err) {
                console.log(err);
                let errResponse = {
                    statusCode: 500,
                    body: 'Database error. This can happen when simultaneous database updates happen. Re-try your request.'
                }
                if (err.$metadata && err.$metadata.httpStatusCode === 412) {
                   errResponse.retry = true;
                   console.log('Retrying database save to s3 because of a conflicting update.');
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

exports.branchNameToS3file = function(branch) {
    return encodeURIComponent(branch);
}

async function getEtag() {
    try {
        return await fs.readFile(ETAG_CACHE, 'utf8');
      } catch (err) {
        return '';
      }
}

async function setEtag(newEtag) {
    await fs.writeFile(ETAG_CACHE, newEtag);
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
// Paths should reference where they've been setup in /tmp
exports.prepPlugin = async function (wpContentPath, sqlitePluginPath) {
    if (!init) {
        try {
            let pluginPackagePath = sqlitePluginPath;
            let oldPath = pluginPackagePath + '/db.copy';
            let newPath = wpContentPath + '/db.php';
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
