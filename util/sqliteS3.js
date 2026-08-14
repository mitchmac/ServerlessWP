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
        // Cloudflare R2 rejects optional SDK checksums.
        if (config.S3Client.endpoint && config.S3Client.endpoint.includes('cloudflarestorage.com')) {
            config.S3Client.requestChecksumCalculation = "WHEN_REQUIRED";
            config.S3Client.responseChecksumValidation = "WHEN_REQUIRED";
        }
        client = new S3Client(config.S3Client);
    }
}

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
        etag: null,
        blobMissing: false,
    };
    event[CONTEXT_KEY] = ctx;

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

    // A 304 is usable only when its body is cached.
    if (cachedEtag && await exists(CACHE_FILE)) {
        getCommandParams.IfNoneMatch = cachedEtag;
    }

    const get = new GetObjectCommand(getCommandParams);

    try {
        const response = await client.send(get);

        if (response) {
            const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
            await fs.writeFile(tmp, response.Body);
            await fs.rename(tmp, CACHE_FILE);
            await setEtag(response.ETag);
            ctx.etag = response.ETag;
        }
        else {
            console.log('db file not found');
            ctx.blobMissing = true;
        }
    }
    catch (err) {
        if (err.$metadata && err.$metadata.httpStatusCode === 304) {
            ctx.etag = cachedEtag;
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
        else if (err.name === 'NoSuchKey' || err.$metadata?.httpStatusCode === 404) {
            console.log('Database file not found on server');
            ctx.blobMissing = true;
            return;
        }
        else {
            console.error('Error fetching database:', err);
            return readError();
        }
    }

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
        const workingExists = await exists(ctx.workingPath);
        if (!workingExists) {
            console.error('Database persistence failed: the per-request SQLite working file is missing.');
            return persistenceError();
        }
        if (!ctx.db) {
            ctx.db = new sqlite3.Database(ctx.workingPath);
            ctx.dataVersion = null;
        }

        let versionNow = await getDataVersion(ctx.db);

        const readOnly = process.env['SERVERLESSWP_READ_ONLY_MODE'] && !['false', '0', 'no'].includes(process.env['SERVERLESSWP_READ_ONLY_MODE'].toLowerCase());
        if (!readOnly && ctx.dataVersion !== versionNow && workingExists) {
            if (!ctx.etag && !ctx.blobMissing) {
                console.log('Refusing to save database without a bound ETag.');
                return persistenceError(true);
            }

            try {
                await dbClose(ctx.db);
                ctx.db = null;

                const sqliteContent = await fs.readFile(ctx.workingPath);
                // Bind the write to this request's source version.
                let currentEtag = ctx.etag;

                let putCommandParams = {
                    Bucket: _config.bucket,
                    Key: _config.file,
                    Body: sqliteContent,
                }

                if (currentEtag) {
                    putCommandParams.IfMatch = currentEtag;
                }
                else if (ctx.blobMissing) {
                    putCommandParams.IfNoneMatch = '*';
                }
                const command = new PutObjectCommand(putCommandParams);

                const putResponse = await client.send(command);

                const tmp = CACHE_FILE + '.' + randomUUID() + '.tmp';
                await fs.copyFile(ctx.workingPath, tmp);
                await fs.rename(tmp, CACHE_FILE);
                await setEtag(putResponse.ETag);
                return;
            }
            catch (err) {
                console.log(err);
                let errResponse = persistenceError();
                const statusCode = err.$metadata?.httpStatusCode;
                if (statusCode === 412 || (ctx.blobMissing && statusCode === 409)) {
                    if (ctx.blobMissing) {
                        console.log('Database creation was rejected because another request created it first.');
                    }
                    else {
                        errResponse.retry = true;
                        console.log('Retrying database save to s3 because of a conflicting update.');
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
            try { await dbClose(ctx.db); } catch (e) { }
            ctx.db = null;
        }
        try { await fs.unlink(ctx.workingPath); } catch (e) { }
        delete event[CONTEXT_KEY];
    }
}

// Never continue with an unknown database state.
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
      if (error?.code === 'ENOENT') {
        return false;
      }
      throw error;
    }
}

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
