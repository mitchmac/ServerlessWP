const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');

// This is where all requests to WordPress are routed through. See vercel.json or netlify.toml for the redirection rules.
exports.handler = async function (event, context, callback) {
    // Move the /wp directory to /tmp/wp so that it is writeable.
    setup();

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