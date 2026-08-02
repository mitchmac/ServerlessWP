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

    const response = await serverlesswp({ docRoot: pathToWP, event: event });
    const checkInstall = validate(response);
    return checkInstall || response;
};
