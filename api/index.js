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

// Register the wrapper before WordPress through auto_prepend_file. Load it from
// read-only /var/task: executing the remotely writable /tmp copy would allow
// stored content to become code.
const streamWrapperPrepend = '/var/task/wp/wp-content/mu-plugins/wp-alt-streamwrapper/bootstrap/prepend.php';

// The bundled router enforces upload policy and sends remote-file misses to WP.
const requestRouter = '/var/task/wp/router.php';

// WordPress runs from /tmp, not beside the bundled prepend; give the wrapper
// the runtime path or it will route nothing.
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
