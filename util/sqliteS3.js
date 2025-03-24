const sqlite3 = require('sqlite3').verbose();
const fs = require('fs').promises;
const { S3Client, GetObjectCommand, PutObjectCommand } = require('@aws-sdk/client-s3');

const ETAG_CACHE = '/tmp/etag.txt';
let sqliteFilePath = '/tmp/db';

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
        client = new S3Client(config.S3Client);
    }
}

exports.preRequest = async function(event) {
    if (!init) {
        // @TODO: is this too much control/knowledge of the setup from the plugin?
        prepPlugin();
    }

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

    const get = new GetObjectCommand( {
        Bucket: _config.bucket,
        Key: _config.file,
        IfNoneMatch: etag
    });

    try {
        const response = await client.send(get);

        if (response) {
            await fs.writeFile(sqliteFilePath, response.Body);
            db = new sqlite3.Database(sqliteFilePath);
            dataVersion = await getDataVersion();
            await setEtag(response.ETag);
            console.log('etag: ' + etag);
        }
        else {
            // @TODO: if it doesn't exist, behave like it's a new site?
            console.log('db file not found');
        }
    }
    catch (err) {
        console.log(err);
    }
}

exports.postRequest = async function(event, response) {
    try {
        if (!db) {
            db = new sqlite3.Database(sqliteFilePath);
        }
        console.log('Data version: ' + dataVersion);
        let versionNow = await getDataVersion();
        console.log('Version now: ' + versionNow);

        // See if the db has been mutated, if so, send the changes to s3
        // @TODO: If the file has changed in s3 in between, fail this request

        if (dataVersion !== versionNow) {
            const dbExists = await exists(sqliteFilePath);
            if (dbExists) {
                try {
                    await dbClose();
                    const sqliteContent = await fs.readFile(sqliteFilePath);
                    let currentEtag = await getEtag();
                    const command = new PutObjectCommand({
                        Bucket: _config.bucket,
                        Key: _config.file,
                        Body: sqliteContent,
                        IfMatch: currentEtag
                    });

                    

                    const response = await client.send(command);
                    await setEtag(response.ETag);
                    return;
                }
                catch (err) {
                    console.log(err);
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
function prepPlugin() {
    //@TODO maybe
}