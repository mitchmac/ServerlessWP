// Preference: MySQL, S3 SQLite, Vercel Blob SQLite, none.
const sandbox = require('./sandbox.js');

function has(...names) {
    return names.every((name) => !!process.env[name]);
}

function branchSlug() {
    const ref = process.env['VERCEL_GIT_COMMIT_REF'];
    return ref ? '-' + encodeURIComponent(ref) : '';
}

function sqliteS3Config() {
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

function blobStoreId() {
    return process.env['SQLITE_BLOB_STORE_ID'] || process.env['BLOB_STORE_ID'];
}

function sqliteBlobConfig() {
    return {
        pathname: `${process.env['SQLITE_BLOB_PATHNAME'] || 'wp-sqlite'}${branchSlug()}.sqlite`,
        storeId: blobStoreId(),
        // Never reuse an uploads-store token for the database.
        token: process.env['SQLITE_BLOB_READ_WRITE_TOKEN'],
    };
}

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

    if (has('VERCEL') && (has('SQLITE_BLOB_READ_WRITE_TOKEN') || !!blobStoreId())) {
        return {
            mode: 'sqlite-vercel-blob',
            plugin: require('./sqliteVercelBlob.js'),
            config: sqliteBlobConfig(),
        };
    }

    return { mode: 'none' };
};
