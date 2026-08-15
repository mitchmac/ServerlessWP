const fs = require('fs');
const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');
const storage = require('../util/storage.js');
const sandbox = require('../util/sandbox.js');
const readOnly = require('../util/readOnly.js');

const pathToWP = '/tmp/wp';
const wpContentPath = pathToWP + '/wp-content';
const sqlitePluginPath = wpContentPath + '/plugins/sqlite-database-integration';

const database = storage.resolve();

// Load executable bootstrap only from the read-only bundle.
const streamWrapperPrepend = '/var/task/wp/wp-content/mu-plugins/serverlesswp-stream-wrapper/bootstrap/prepend.php';

const requestRouter = '/var/task/wp/router.php';

const streamWrapperActive = !!process.env['WP_STREAM_PROVIDER']
    && fs.existsSync(streamWrapperPrepend);

if (streamWrapperActive && !process.env['WP_STREAM_WP_CONTENT_DIR']) {
    process.env['WP_STREAM_WP_CONTENT_DIR'] = wpContentPath;
}

// Refuse to fall back to ephemeral writes.
if (streamWrapperActive && typeof serverlesswp.buildPhpArgs !== 'function') {
    console.log('WP_STREAM_PROVIDER is set but the installed serverlesswp package does not support autoPrependFile. Upgrade it, or wp-content writes will not reach object storage.');
}

const readOnlyActive = !!process.env['SERVERLESSWP_READ_ONLY_MODE']
    && !['false', '0', 'no'].includes(process.env['SERVERLESSWP_READ_ONLY_MODE'].toLowerCase());

let initDone = false;

setup();

exports.handler = async function (event, context, callback) {
    if (!initDone) {
        // Block mutations before opening SQLite.
        if (readOnlyActive) {
            serverlesswp.registerPlugin(readOnly);
        }
        if (database.plugin) {
            await database.plugin.prepPlugin(wpContentPath, sqlitePluginPath);
            database.plugin.config(database.config);
            serverlesswp.registerPlugin(database.plugin);
        }
        if (process.env['SERVERLESSWP_DATA_SECRET']) {
            serverlesswp.registerPlugin(sandbox);
        }
        initDone = true;
    }

    const options = { docRoot: pathToWP, event: event };
    if (fs.existsSync(requestRouter)) {
        options.routerScript = requestRouter;
    }
    if (streamWrapperActive) {
        options.autoPrependFile = streamWrapperPrepend;
    }

    const response = await serverlesswp(options);
    const checkInstall = validate(response);
    return checkInstall || response;
};
