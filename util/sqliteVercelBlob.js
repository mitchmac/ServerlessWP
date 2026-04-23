const sqlite3 = require('sqlite3').verbose();
const fs = require('fs').promises;
const fsSync = require('fs');
const { Readable } = require('node:stream');
const { pipeline } = require('node:stream/promises');
const { get, put, BlobPreconditionFailedError, BlobNotFoundError } = require('@vercel/blob');

const ETAG_CACHE = '/tmp/etag-vercel-blob.txt';
let sqliteFilePath = '/tmp/wp-sqlite-s3.sqlite';

let init = false;
let db;
let dataVersion;
let _config;

exports.name = 'ServerlessWP sqlite Vercel Blob';

exports.config = function(config) {
    _config = config;
}

exports.preRequest = async function(event) {
    if (!_config?.pathname) {
        throw new Error("Vercel Blob pathname is required");
    }

    const cachedEtag = await getEtag();

    const options = { access: 'private' };
    if (_config.token) {
        options.token = _config.token;
    }
    if (cachedEtag) {
        options.ifNoneMatch = cachedEtag;
    }

    try {
        const response = await get(_config.pathname, options);

        if (!response) {
            // Blob doesn't exist yet - behave like a new site.
            return;
        }

        if (response.statusCode === 304) {
            // No need to download, just use existing local file.
            db = new sqlite3.Database(sqliteFilePath);
            dataVersion = await getDataVersion();
            return;
        }

        if (response.statusCode === 200 && response.stream) {
            await pipeline(
                Readable.fromWeb(response.stream),
                fsSync.createWriteStream(sqliteFilePath)
            );
            db = new sqlite3.Database(sqliteFilePath);
            dataVersion = await getDataVersion();
            if (response.blob?.etag) {
                await setEtag(response.blob.etag);
            }
        }
    }
    catch (err) {
        if (err instanceof BlobNotFoundError) {
            console.log('Database blob not found');
            return;
        }
        console.error('Error fetching database blob:', err);
    }
}

exports.postRequest = async function(event, response) {
    try {
        const dbExists = await exists(sqliteFilePath);
        if (!db) {
            if (dbExists) {
                db = new sqlite3.Database(sqliteFilePath);
                dataVersion = null;
            } else {
                return;
            }
        }
        const versionNow = await getDataVersion();

        // See if the db has been mutated, if so, send the changes to the blob store.
        const readOnly = process.env['SERVERLESSWP_READ_ONLY_MODE'];
        const readOnlyActive = readOnly && !['false', '0', 'no'].includes(readOnly.toLowerCase());
        if (!readOnlyActive && dataVersion !== versionNow) {
            if (dbExists) {
                try {
                    await dbClose();

                    const sqliteContent = await fs.readFile(sqliteFilePath);
                    const currentEtag = await getEtag();

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
                    if (putResponse?.etag) {
                        await setEtag(putResponse.etag);
                    }
                    return;
                }
                catch (err) {
                    console.log(err);
                    const errResponse = {
                        statusCode: 500,
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

        await dbClose();
    }
    catch (err) {
        console.log(err);
    }
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

async function getDataVersion() {
    return new Promise((resolve, reject) => {
        if (!db) { reject('No db') }
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

async function dbClose() {
    return new Promise((resolve, reject) => {
        if (!db) { reject('No db') }
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
