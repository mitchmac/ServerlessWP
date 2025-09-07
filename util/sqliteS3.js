const sqlite3 = require('sqlite3').verbose();
const fs = require('fs').promises;
const { S3Client, GetObjectCommand, PutObjectCommand } = require('@aws-sdk/client-s3');

const ETAG_CACHE = '/tmp/etag.txt';
let sqliteFilePath = '/tmp/wp-sqlite-s3.sqlite';

let init = false;
let db;
let dataVersion;
let client;
let etag;
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

    let etag = await getEtag();

    let getCommandParams = {
        Bucket: _config.bucket,
        Key: _config.file
    }
    
    if (etag) {
        getCommandParams.IfNoneMatch = etag;
    }

    const get = new GetObjectCommand(getCommandParams);

    try {
        const response = await client.send(get);

        if (response) {
            await fs.writeFile(sqliteFilePath, response.Body);
            db = new sqlite3.Database(sqliteFilePath);
            dataVersion = await getDataVersion();
            await setEtag(response.ETag);
        }
        else {
            // @TODO: if it doesn't exist, behave like it's a new site?
            console.log('db file not found');
        }
    }
    catch (err) {
        if (err.$metadata && err.$metadata.httpStatusCode === 304) {
            // No need to download, just use existing file
            db = new sqlite3.Database(sqliteFilePath);
            dataVersion = await getDataVersion();
        } 
        else if (err.name === 'NoSuchKey') {
            // Handle case where the file doesn't exist on S3
            console.log('Database file not found on server');
        }
        else {
            // Handle other errors
            console.error('Error fetching database:', err);
        }
    }
}

exports.postRequest = async function(event, response) {
    try {
        if (!db) {
            db = new sqlite3.Database(sqliteFilePath);
        }
        let versionNow = await getDataVersion();

        // See if the db has been mutated, if so, send the changes to s3
        if (dataVersion !== versionNow) {
            const dbExists = await exists(sqliteFilePath);
            if (dbExists) {
                try {
                    await dbClose();
                    
                    const sqliteContent = await fs.readFile(sqliteFilePath);
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

                    const response = await client.send(command);
                    await setEtag(response.ETag);
                    // should db be closed?
                    return;
                }
                catch (err) {
                    console.log(err);
                    //@TODO: more descriptive message
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

        await dbClose();
    }
    catch (err) {
        console.log(err);
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
    etag = newEtag;
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
// Paths should reference where they've been setup in /tmp
exports.prepPlugin = async function (wpContentPath, sqlitePluginPath) {
    if (!init) {
        try {
            let oldPath = sqlitePluginPath + '/db.copy';
            let newPath = wpContentPath + '/db.php';
            await fs.copyFile(oldPath, newPath);
            const content = await fs.readFile(newPath, 'utf8');
            const modifiedContent = content.replace(new RegExp(/{SQLITE_IMPLEMENTATION_FOLDER_PATH}/, 'g'), sqlitePluginPath);

            await fs.writeFile(newPath, modifiedContent)
        }
        catch (err) {
            console.log(err);
        }
    }
}
