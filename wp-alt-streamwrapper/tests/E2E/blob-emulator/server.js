// Minimal Vercel Blob mock for e2e tests.
// Copied from the ServerlessWP repo (test/vercel-blob-emulator/server.js),
// which built it against real @vercel/blob SDK traffic. Keep in sync when
// the upstream copy changes.
//
// Local divergence: this copy percent-decodes pathnames (decodePath below).
// Upstream stores one sqlite file whose name has no characters needing
// encoding, so it compares raw URL paths; wp-content keys routinely contain
// spaces and ampersands, which arrive percent-encoded and must be decoded to
// match the stored key. Worth pushing back upstream.
// Implements the endpoints the plugin relies on:
//   PUT  /?pathname=<name>   upload (honors x-if-match, x-allow-overwrite)
//   GET  /?url=<url>         head metadata
//   GET  /<pathname>         download (honors If-None-Match and cache=0)
//   POST /delete             delete (honors x-if-match for single URL)
//
// ETags use SHA-1 of the body, wrapped in double quotes (RFC 7232).
//
// Downloads go through a simulated CDN cache, because that's what they do on
// Vercel: blobs are cached for up to a month and an overwrite takes up to 60
// seconds to propagate, so a plain get() can return the previous version.
// Passing `useCache: false` (which the SDK turns into `?cache=0`) reads from
// origin instead. See https://vercel.com/docs/vercel-blob#caching

const http = require('node:http');
const crypto = require('node:crypto');
const { URL } = require('node:url');

const PORT = parseInt(process.env.PORT || '7000', 10);
const STORE_ID = process.env.STORE_ID || 'test';
const ACCESS = process.env.ACCESS || 'private';
const BASE_HOST = `${STORE_ID}.${ACCESS}.blob.vercel-storage.com`;
// How long a cached download keeps being served after an overwrite. 0 disables
// the simulated cache entirely.
const CACHE_STALE_MS = parseInt(process.env.BLOB_CACHE_STALE_MS || '60000', 10);
// Downloads carry a weak ETag (`W/"..."`) while the API reports the strong one,
// which is what Vercel Blob does. A client that reuses a download's ETag for a
// conditional write verbatim gets rejected. Set BLOB_WEAK_DOWNLOAD_ETAG=0 to
// serve strong ETags everywhere instead.
const WEAK_DOWNLOAD_ETAG = process.env.BLOB_WEAK_DOWNLOAD_ETAG !== '0';
// Never answer a download with 304, so every read is a full download and the
// client ends up holding a download-sourced ETag. That's what a cold serverless
// instance does on its first request, and a warm single-container test never
// reaches it otherwise.
const NO_CONDITIONAL_READS = process.env.BLOB_NO_CONDITIONAL_READS === '1';

// Weak comparison per RFC 7232: `W/"x"` and `"x"` are the same entity.
function etagsWeaklyEqual(a, b) {
    const strip = (v) => (v || '').replace(/^W\//, '');
    return !!a && strip(a) === strip(b);
}

const store = new Map();
// pathname -> { entry, expiresAt }: what the CDN would still be serving.
const cdnCache = new Map();

function computeEtag(buffer) {
    return `"${crypto.createHash('sha1').update(buffer).digest('hex')}"`;
}

function jsonError(res, status, code, message = '') {
    res.statusCode = status;
    res.setHeader('content-type', 'application/json');
    res.end(JSON.stringify({ error: { code, message } }));
}

function metadata(pathname, entry) {
    const url = `https://${BASE_HOST}/${pathname}`;
    return {
        url,
        downloadUrl: url + '?download=1',
        pathname,
        contentType: entry.contentType,
        contentDisposition: `attachment; filename="${pathname.split('/').pop()}"`,
        cacheControl: 'public, max-age=31536000, must-revalidate',
        size: entry.body.length,
        uploadedAt: entry.uploadedAt,
        etag: entry.etag,
    };
}

function readBody(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        req.on('data', c => chunks.push(c));
        req.on('end', () => resolve(Buffer.concat(chunks)));
        req.on('error', reject);
    });
}

// Percent-decode a URL path into the pathname a blob is stored under. Keys can
// contain spaces and other characters that must be encoded on the wire (a
// WordPress upload named "my file.jpg" travels as my%20file.jpg), and the real
// service decodes them before looking the blob up.
function decodePath(path) {
    try {
        return decodeURIComponent(path);
    } catch {
        return path; // malformed escape sequence: match on the raw form
    }
}

function extractPathname(input) {
    try {
        return decodePath(new URL(input).pathname.slice(1));
    } catch {
        return decodePath(input.startsWith('/') ? input.slice(1) : input);
    }
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const method = req.method;

    try {
        if (method === 'PUT' && url.pathname === '/' && url.searchParams.has('pathname')) {
            const pathname = url.searchParams.get('pathname');
            const body = await readBody(req);
            const current = store.get(pathname);
            const ifMatch = req.headers['x-if-match'];
            const allowOverwrite = req.headers['x-allow-overwrite'] === '1';

            if (ifMatch) {
                if (!current || current.etag !== ifMatch) {
                    return jsonError(res, 412, 'precondition_failed', 'ETag mismatch');
                }
            } else if (current && !allowOverwrite) {
                return jsonError(res, 400, 'bad_request', 'Blob exists and overwrite is not allowed');
            }

            const entry = {
                body,
                etag: computeEtag(body),
                contentType: req.headers['x-content-type'] || 'application/octet-stream',
                uploadedAt: new Date().toISOString(),
            };
            store.set(pathname, entry);

            res.statusCode = 200;
            res.setHeader('content-type', 'application/json');
            res.end(JSON.stringify(metadata(pathname, entry)));
            return;
        }

        if (method === 'GET' && url.pathname === '/' && url.searchParams.has('url')) {
            const pathname = extractPathname(url.searchParams.get('url'));
            const entry = store.get(pathname);
            if (!entry) {
                return jsonError(res, 404, 'not_found', 'Blob not found');
            }
            res.statusCode = 200;
            res.setHeader('content-type', 'application/json');
            res.end(JSON.stringify(metadata(pathname, entry)));
            return;
        }

        if (method === 'POST' && url.pathname === '/delete') {
            const body = await readBody(req);
            let urls = [];
            try { ({ urls = [] } = JSON.parse(body.toString() || '{}')); } catch {}
            const ifMatch = req.headers['x-if-match'];
            for (const u of urls) {
                const pathname = extractPathname(u);
                const current = store.get(pathname);
                if (ifMatch && current && current.etag !== ifMatch) {
                    return jsonError(res, 412, 'precondition_failed', 'ETag mismatch');
                }
                store.delete(pathname);
            }
            res.statusCode = 200;
            res.setHeader('content-type', 'application/json');
            res.end('{}');
            return;
        }

        if (method === 'GET') {
            const pathname = decodePath(url.pathname.slice(1));
            const bypassCache = url.searchParams.get('cache') === '0';

            let entry = store.get(pathname);
            let cacheState = bypassCache ? 'BYPASS' : 'MISS';

            if (!bypassCache && CACHE_STALE_MS > 0) {
                const cached = cdnCache.get(pathname);
                if (cached && cached.expiresAt > Date.now()) {
                    // Still within the propagation window: serve what the CDN
                    // has, even if the blob was overwritten since.
                    entry = cached.entry;
                    cacheState = 'HIT';
                } else if (entry) {
                    cdnCache.set(pathname, { entry, expiresAt: Date.now() + CACHE_STALE_MS });
                } else {
                    cdnCache.delete(pathname);
                }
            }

            if (!entry) {
                res.statusCode = 404;
                res.setHeader('x-vercel-blob-cache', cacheState);
                res.end();
                return;
            }
            res.setHeader('x-vercel-blob-cache', cacheState);
            const lastModified = new Date(entry.uploadedAt).toUTCString();
            const downloadEtag = WEAK_DOWNLOAD_ETAG ? 'W/' + entry.etag : entry.etag;
            if (!NO_CONDITIONAL_READS && etagsWeaklyEqual(req.headers['if-none-match'], downloadEtag)) {
                res.statusCode = 304;
                res.setHeader('etag', downloadEtag);
                res.setHeader('last-modified', lastModified);
                res.end();
                return;
            }
            res.statusCode = 200;
            res.setHeader('etag', downloadEtag);
            res.setHeader('content-type', entry.contentType);
            res.setHeader('content-length', entry.body.length);
            res.setHeader('last-modified', lastModified);
            res.end(entry.body);
            return;
        }

        res.statusCode = 405;
        res.end();
    } catch (err) {
        console.error('vercel-blob-emulator error:', err);
        res.statusCode = 500;
        res.end();
    }
});

server.listen(PORT, () => {
    console.log(`vercel-blob-emulator listening on :${PORT} (storeId=${STORE_ID}, access=${ACCESS})`);
});
