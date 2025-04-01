const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');
const sqliteS3 = require('../util/sqliteS3.js');

const pathToWP = '/tmp/wp';

// Move the /wp directory to /tmp/wp so that it is writeable.
setup();

if (process.env['SQLITE_S3_BUCKET']) {
    // Configure the sqliteS3 plugin.
    let sqliteS3Config = {
        bucket: process.env['SQLITE_S3_BUCKET'],
        file: 'wp-sqlite-s3.sqlite',
        S3Client: {
            credentials: {
                "accessKeyId": process.env['SQLITE_S3_API_KEY'],
                "secretAccessKey": process.env['SQLITE_S3_API_SECRET']
            },
            region: process.env['SQLITE_S3_REGION'],
        }
    };

    if (process.env['SQLITE_S3_ENDPOINT']) {
        sqliteS3Config.S3Client.endpoint = process.env['SQLITE_S3_ENDPOINT'];
    }

    if (process.env['SQLITE_S3_FORCE_PATH_STYLE']) {
        sqliteS3Config.S3Client.forcePathStyle = true;
    }

    sqliteS3.config(sqliteS3Config);

    // Register the sqlite serverlesswp plugin.
    serverlesswp.registerPlugin(sqliteS3);
}

// This is where all requests to WordPress are routed through.
// See vercel.json or netlify.toml for the redirection rules.
exports.handler = async function (event, context, callback) {
    if (process.env['SQLITE_S3_BUCKET']) {
        let wpContentPath = pathToWP + '/wp-content';
        let sqlitePluginPath = wpContentPath + '/plugins/sqlite-database-integration';
        await sqliteS3.prepPlugin(wpContentPath, sqlitePluginPath);
    }

    // Send the request (event object) to the serverlesswp library.
    // It includes the PHP server that allows WordPress to handle the request.
    let response = await serverlesswp({docRoot: pathToWP, event: event});
    
    // Check to see if the database environment variables are in place.
    let checkInstall = validate(response);
    
    if (checkInstall) {
        return checkInstall;
    }
    else {
        // Return the response for serving.
        return response;
    }
}
