const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');
const sqliteS3 = require('../util/sqliteS3.js');

const { register } = require('../util/goldilock.js');

const pathToWP = '/tmp/wp';
let initSqliteS3 = false;

// Move the /wp directory to /tmp/wp so that it is writeable.
setup();

// This is where all requests to WordPress are routed through.
// See vercel.json or netlify.toml for the redirection rules.
exports.handler = async function (event, context, callback) {
    if ((process.env['SQLITE_S3_BUCKET'] || process.env['SERVERLESSWP_DATA_SECRET']) && !initSqliteS3) {
        let wpContentPath = pathToWP + '/wp-content';
        let sqlitePluginPath = wpContentPath + '/plugins/sqlite-database-integration';
        await sqliteS3.prepPlugin(wpContentPath, sqlitePluginPath);

        let branchSlug = '';
        let bucketFallback = '';
        
        // Vercel
        if (process.env['VERCEL']) {
            const branch = sqliteS3.branchNameToS3file(process.env['VERCEL_GIT_COMMIT_REF']);
            branchSlug = branch ? '-' + branch : '';
            bucketFallback = process.env['VERCEL_PROJECT_ID'];
        }

        // Configure the sqliteS3 plugin.
        let sqliteS3Config = {
            bucket: process.env['SQLITE_S3_BUCKET'] || bucketFallback,
            file:`wp-sqlite-s3${branchSlug}.sqlite`,
            S3Client: {
                credentials: {
                    "accessKeyId": process.env['SQLITE_S3_API_KEY'] || process.env['VERCEL_PROJECT_ID'],
                    "secretAccessKey": process.env['SQLITE_S3_API_SECRET'] || process.env['SERVERLESSWP_DATA_SECRET']
                },
                region: process.env['SQLITE_S3_REGION'],
            }
        };

        if (process.env['SQLITE_S3_ENDPOINT']) {
            sqliteS3Config.S3Client.endpoint = process.env['SQLITE_S3_ENDPOINT'];
        }

        if (process.env['SQLITE_S3_FORCE_PATH_STYLE'] || process.env['SERVERLESSWP_DATA_SECRET']) {
            sqliteS3Config.S3Client.forcePathStyle = true;
        }

        if (process.env['SERVERLESSWP_DATA_SECRET']) {
            sqliteS3Config.S3Client.endpoint = 'https://data.serverlesswp.com';
            sqliteS3Config.onAuthError = () => register(
                sqliteS3Config.bucket,
                process.env['SERVERLESSWP_DATA_SECRET']
            );
        }

        sqliteS3.config(sqliteS3Config);
        initSqliteS3 = true;
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

if (process.env['SQLITE_S3_BUCKET'] || process.env['SERVERLESSWP_DATA_SECRET']) {
    // Register the sqlite serverlesswp plugin.
    serverlesswp.registerPlugin(sqliteS3);
}
