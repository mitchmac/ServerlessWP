// Vercel-specific entry point. Adds Vercel Blob as a storage option and
// layers VERCEL_* env var fallbacks onto the SQLite + S3 config, then delegates
// to the core handler in api/index.js.

const core = require('./index.js');
const sqliteS3 = require('../util/sqliteS3.js');
const sqliteVercelBlob = require('../util/sqliteVercelBlob.js');

const hasSqliteS3 = !!(process.env['SQLITE_S3_BUCKET'] || process.env['SERVERLESSWP_DATA_SECRET']);
const useVercelBlob = !!process.env['BLOB_READ_WRITE_TOKEN'] && !hasSqliteS3;

// Encode the current Vercel git branch so each branch gets its own database.
function branchSlug() {
    if (process.env['VERCEL_GIT_COMMIT_REF']) {
        const branch = encodeURIComponent(process.env['VERCEL_GIT_COMMIT_REF']);
        return branch ? '-' + branch : '';
    }
    return '';
}

if (hasSqliteS3) {
    // Re-configure the S3 plugin with Vercel-aware overrides: the sandbox flow
    // uses VERCEL_PROJECT_ID as the bucket name and the API key fallback, and
    // branch-aware filenames keep preview deploys isolated.
    core.useSqlitePlugin(sqliteS3, core.buildSqliteS3Config({
        bucket: process.env['SQLITE_S3_BUCKET'] || process.env['VERCEL_PROJECT_ID'],
        file: `wp-sqlite-s3${branchSlug()}.sqlite`,
        accessKeyId: process.env['SQLITE_S3_API_KEY'] || process.env['VERCEL_PROJECT_ID'],
    }));
} else if (useVercelBlob) {
    core.useSqlitePlugin(sqliteVercelBlob, {
        pathname: `wp-sqlite${branchSlug()}.sqlite`,
    });
}

exports.handler = core.handler;
