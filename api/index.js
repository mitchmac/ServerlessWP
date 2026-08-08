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

// Which database this deployment uses. See util/storage.js.
const database = storage.resolve();

// wp-alt-streamwrapper routes wp-content writes to object storage. Its stream
// wrapper has to be registered before any WordPress code runs, which means
// auto_prepend_file.
//
// The prepend is loaded from the read-only bundle, never the /tmp copy: it
// executes on every request, and /tmp/wp/wp-content is exactly the tree the
// wrapper makes writable, so sourcing it from there would turn any write into
// code execution. /var/task is read-only, so it can't.
const streamWrapperPrepend = '/var/task/wp/wp-content/mu-plugins/wp-alt-streamwrapper/bootstrap/prepend.php';

// Same reasoning for the router script: it runs on every request, so it comes
// from the read-only bundle too. Without it `php -S` answers any URI with a
// file extension itself, 404ing files that live only in object storage before
// WordPress can serve them. Only passed when the wrapper is active, so request
// routing is unchanged for every other deployment.
const streamWrapperRouter = '/var/task/wp/router.php';

// The prepend infers wp-content from its own location, which resolves to the
// read-only bundle. WordPress runs from /tmp/wp, so tell it explicitly —
// otherwise the router matches nothing and every write silently lands on the
// ephemeral disk.
const streamWrapperActive = !!process.env['WP_STREAM_PROVIDER']
    && fs.existsSync(streamWrapperPrepend);

if (streamWrapperActive && !process.env['WP_STREAM_WP_CONTENT_DIR']) {
    process.env['WP_STREAM_WP_CONTENT_DIR'] = wpContentPath;
}

// autoPrependFile arrived in serverlesswp alongside buildPhpArgs. Older
// versions ignore unknown options, which would leave the wrapper unregistered
// and every wp-content write landing on the ephemeral disk with no error.
if (streamWrapperActive && typeof serverlesswp.buildPhpArgs !== 'function') {
    console.log('WP_STREAM_PROVIDER is set but the installed serverlesswp package does not support autoPrependFile. Upgrade it, or wp-content writes will not reach object storage.');
}

const readOnlyActive = !!process.env['SERVERLESSWP_READ_ONLY_MODE']
    && !['false', '0', 'no'].includes(process.env['SERVERLESSWP_READ_ONLY_MODE'].toLowerCase());

let initDone = false;

// Move the /wp directory to /tmp/wp so that it is writeable.
setup();

// This is where all requests to WordPress are routed through.
// See vercel.json, netlify.toml, or serverless.yml for the redirection rules.
exports.handler = async function (event, context, callback) {
    if (!initDone) {
        // Register readOnly first so blocked mutations short-circuit before the
        // sqlite plugin tries to hit storage.
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
    if (streamWrapperActive) {
        options.autoPrependFile = streamWrapperPrepend;
        if (fs.existsSync(streamWrapperRouter)) {
            options.routerScript = streamWrapperRouter;
        }
    }

    const response = await serverlesswp(options);
    const checkInstall = validate(response);
    return checkInstall || response;
};
