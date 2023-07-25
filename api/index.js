const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');

exports.handler = async function (event, context, callback) {
    setup();

    let response = await serverlesswp({docRoot: '/tmp/wp', event: event});
    let checkInstall = validate(response);
    
    if (checkInstall) {
        return checkInstall;
    }
    else {
        return response;
    }
}