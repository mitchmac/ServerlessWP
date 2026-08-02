// Single source of truth for which database a deployment uses.
//
// The most explicitly configured option wins, so connecting a Blob store for
// uploads can't take over a site that already has a database:
//
//   1. MySQL          DATABASE + USERNAME + PASSWORD + HOST
//   2. SQLite + S3    SQLITE_S3_BUCKET, or SERVERLESSWP_DATA_SECRET (sandbox)
//   3. SQLite + Blob  BLOB_STORE_ID (or a SQLITE_BLOB_* equivalent) on Vercel
//   4. none           show the install page
//
// wp-config.php doesn't repeat this: the active plugin tells it which file to
// open via the x-serverlesswp-sqlite-file header.

const sandbox = require('./sandbox.js');

function has(...names) {
    return names.every((name) => !!process.env[name]);
}

// Each Vercel branch gets its own database. Empty off Vercel.
function branchSlug() {
    const ref = process.env['VERCEL_GIT_COMMIT_REF'];
    return ref ? '-' + encodeURIComponent(ref) : '';
}

function sqliteS3Config() {
    // The sandbox flow uses the project id as both bucket name and API key.
    const vercelFallback = process.env['VERCEL'] ? process.env['VERCEL_PROJECT_ID'] : undefined;

    const config = {
        bucket: process.env['SQLITE_S3_BUCKET'] || vercelFallback,
        file: `wp-sqlite-s3${branchSlug()}.sqlite`,
        S3Client: {
            credentials: {
                accessKeyId: process.env['SQLITE_S3_API_KEY'] || vercelFallback,
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

// A connected Blob store authenticates with OIDC: Vercel injects BLOB_STORE_ID
// and mints a short-lived VERCEL_OIDC_TOKEN per deployment, which the SDK picks
// up on its own. A store created with an envVarPrefix of SQLITE gets the
// prefixed name, so accept either.
function blobStoreId() {
    return process.env['SQLITE_BLOB_STORE_ID'] || process.env['BLOB_STORE_ID'];
}

function sqliteBlobConfig() {
    return {
        // Optional override.
        pathname: `${process.env['SQLITE_BLOB_PATHNAME'] || 'wp-sqlite'}${branchSlug()}.sqlite`,
        storeId: blobStoreId(),
        // A static read-write token, for a store that has one. The SDK
        // prefers it over OIDC. The unprefixed BLOB_READ_WRITE_TOKEN is
        // deliberately not accepted: that's the token a store connected for
        // uploads gets, and those stores are public, so every private write
        // here would fail.
        token: process.env['SQLITE_BLOB_READ_WRITE_TOKEN'],
    };
}

// Returns { mode, plugin, config }; plugin and config are absent for 'mysql'
// and 'none', which need no request-time handling.
exports.resolve = function () {
    if (has('DATABASE', 'USERNAME', 'PASSWORD', 'HOST')) {
        return { mode: 'mysql' };
    }

    if (has('SQLITE_S3_BUCKET') || has('SERVERLESSWP_DATA_SECRET')) {
        return {
            mode: 'sqlite-s3',
            plugin: require('./sqliteS3.js'),
            config: sqliteS3Config(),
        };
    }

    // Only wired up on Vercel, even if credentials exist elsewhere: OIDC needs
    // the token Vercel mints for the deployment.
    //
    // An unprefixed BLOB_STORE_ID counts, which is all a store connected for
    // uploads leaves behind too. That store can't be told apart from a database
    // store, but a site using Blob for uploads has a database already, and both
    // database options above win here.
    if (has('VERCEL') && (has('SQLITE_BLOB_READ_WRITE_TOKEN') || !!blobStoreId())) {
        return {
            mode: 'sqlite-vercel-blob',
            plugin: require('./sqliteVercelBlob.js'),
            config: sqliteBlobConfig(),
        };
    }

    return { mode: 'none' };
};
