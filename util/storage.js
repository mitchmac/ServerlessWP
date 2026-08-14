// Database precedence; explicit configurations win over connected Blob stores:
//   1. MySQL          DATABASE + USERNAME + PASSWORD + HOST
//   2. SQLite + S3    SQLITE_S3_BUCKET, or SERVERLESSWP_DATA_SECRET (sandbox)
//   3. SQLite + Blob  BLOB_STORE_ID (or a SQLITE_BLOB_* equivalent) on Vercel
//   4. none           show the install page

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

// Accept both the default and SQLITE-prefixed names used by connected stores.
function blobStoreId() {
    return process.env['SQLITE_BLOB_STORE_ID'] || process.env['BLOB_STORE_ID'];
}

function sqliteBlobConfig() {
    return {
        // Optional override.
        pathname: `${process.env['SQLITE_BLOB_PATHNAME'] || 'wp-sqlite'}${branchSlug()}.sqlite`,
        storeId: blobStoreId(),
        // Do not accept an upload store's unprefixed token for the private DB.
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

    // OIDC requires Vercel. Database options above take precedence when the
    // unprefixed store ID belongs to an uploads store.
    if (has('VERCEL') && (has('SQLITE_BLOB_READ_WRITE_TOKEN') || !!blobStoreId())) {
        return {
            mode: 'sqlite-vercel-blob',
            plugin: require('./sqliteVercelBlob.js'),
            config: sqliteBlobConfig(),
        };
    }

    return { mode: 'none' };
};
