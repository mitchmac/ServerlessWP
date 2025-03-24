const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');
const sqliteS3 = require('../util/sqliteS3.js');

// Move the /wp directory to /tmp/wp so that it is writeable.
setup();

if (process.env['SQLITE_S3']) {
    // Configure the sqliteS3 plugin.
    sqliteS3.config({
        bucket: '',
        file: 'file.sqlite',
        S3Client: {
            credentials: {
                "accessKeyId": '',
                "secretAccessKey": ''
            },
            region: 'us-east-1'
        }
    });

    // Register the sqlite serverlesswp plugin.
    serverlesswp.registerPlugin(sqliteS3);
}

// This is where all requests to WordPress are routed through. See vercel.json or netlify.toml for the redirection rules.
exports.handler = async function (event, context, callback) {
    // Send the request (event object) to the serverlesswp library. It includes the PHP server that allows WordPress to handle the request.
    let response = await serverlesswp({docRoot: '/tmp/wp', event: event});
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