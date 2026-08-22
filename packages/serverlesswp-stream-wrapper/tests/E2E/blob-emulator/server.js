
const http = require('node:http');
const crypto = require('node:crypto');
const { URL } = require('node:url');

const PORT = parseInt(process.env.PORT || '7000', 10);
const STORE_ID = process.env.STORE_ID || 'test';
const ACCESS = process.env.ACCESS || 'private';
const BASE_HOST = `${STORE_ID}.${ACCESS}.blob.vercel-storage.com`;
const CACHE_STALE_MS = parseInt(process.env.BLOB_CACHE_STALE_MS || '60000', 10);
const WEAK_DOWNLOAD_ETAG = process.env.BLOB_WEAK_DOWNLOAD_ETAG !== '0';
const NO_CONDITIONAL_READS = process.env.BLOB_NO_CONDITIONAL_READS === '1';

function etagsWeaklyEqual(a, b) {
    const strip = (v) => (v || '').replace(/^W\//, '');
    return !!a && strip(a) === strip(b);
}

const store = new Map();
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

function decodePath(path) {
    try {
        return decodeURIComponent(path);
    } catch {
        return path;
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
            // Real Vercel Blob rejects pathnames ending in a slash, so a
            // trailing-slash "directory marker" upload cannot succeed here.
            if (pathname === '' || pathname.endsWith('/')) {
                return jsonError(res, 400, 'bad_request', 'pathname cannot end with a slash');
            }
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
