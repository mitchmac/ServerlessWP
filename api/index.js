const serverlesswp = require('serverlesswp');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');
const sqliteS3 = require('../util/sqliteS3.js');
const sandbox = require('../util/sandbox.js');
const readOnly = require('../util/readOnly.js');

const pathToWP = '/tmp/wp';
const wpContentPath = pathToWP + '/wp-content';
const sqlitePluginPath = wpContentPath + '/plugins/sqlite-database-integration';

const hasSqliteS3 = !!(process.env['SQLITE_S3_BUCKET'] || process.env['SERVERLESSWP_DATA_SECRET']);
const readOnlyActive = !!process.env['SERVERLESSWP_READ_ONLY_MODE']
    && !['false', '0', 'no'].includes(process.env['SERVERLESSWP_READ_ONLY_MODE'].toLowerCase());

let sqliteSelection = null;
let initDone = false;

function buildSqliteS3Config(overrides = {}) {
    const config = {
        bucket: overrides.bucket || process.env['SQLITE_S3_BUCKET'],
        file: overrides.file || 'wp-sqlite-s3.sqlite',
        S3Client: {
            credentials: {
                accessKeyId: overrides.accessKeyId || process.env['SQLITE_S3_API_KEY'],
                secretAccessKey: process.env['SQLITE_S3_API_SECRET'] || process.env['SERVERLESSWP_DATA_SECRET'],
            },
            region: process.env['SQLITE_S3_REGION'],
        }
    };

    if (process.env['SQLITE_S3_ENDPOINT']) {
        config.S3Client.endpoint = process.env['SQLITE_S3_ENDPOINT'];
    }

    if (process.env['SQLITE_S3_FORCE_PATH_STYLE'] || process.env['SERVERLESSWP_DATA_SECRET']) {
        config.S3Client.forcePathStyle = true;
    }

    if (process.env['SERVERLESSWP_DATA_SECRET']) {
        config.S3Client.endpoint = 'https://data.serverlesswp.com';
        config.onAuthError = () => sandbox.register(config.bucket, process.env['SERVERLESSWP_DATA_SECRET']);
    }

    return config;
}

// Platform entry points (api/vercel.js) may call this before the first request
// to override which sqlite plugin gets used or pass a platform-tuned config.
function useSqlitePlugin(plugin, config) {
    sqliteSelection = { plugin, config };
}

exports.useSqlitePlugin = useSqlitePlugin;
exports.buildSqliteS3Config = buildSqliteS3Config;

// Default: use sqliteS3 when its env vars are present.
if (hasSqliteS3) {
    useSqlitePlugin(sqliteS3, buildSqliteS3Config());
}

// Move the /wp directory to /tmp/wp so that it is writeable.
setup();

// This is where all requests to WordPress are routed through.
// See netlify.toml or serverless.yml for the redirection rules.
// On Vercel, requests come through api/vercel.js which then calls this handler.
exports.handler = async function (event, context, callback) {
    if (!initDone) {
        // Register readOnly first so blocked mutations short-circuit before the
        // sqlite plugin tries to hit storage.
        if (readOnlyActive) {
            serverlesswp.registerPlugin(readOnly);
        }
        if (sqliteSelection) {
            const { plugin, config } = sqliteSelection;
            await plugin.prepPlugin(wpContentPath, sqlitePluginPath);
            plugin.config(config);
            serverlesswp.registerPlugin(plugin);
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
