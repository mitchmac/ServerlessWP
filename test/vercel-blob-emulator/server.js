// Minimal Vercel Blob mock for e2e tests.
// Implements the endpoints our sqliteVercelBlob plugin relies on:
//   PUT  /?pathname=<name>   upload (honors x-if-match, x-allow-overwrite)
//   GET  /?url=<url>         head metadata
//   GET  /<pathname>         download (honors If-None-Match)
//   POST /delete             delete (honors x-if-match for single URL)
//
// ETags use SHA-1 of the body, wrapped in double quotes (RFC 7232).

const http = require('node:http');
const crypto = require('node:crypto');
const { URL } = require('node:url');

const PORT = parseInt(process.env.PORT || '7000', 10);
const STORE_ID = process.env.STORE_ID || 'test';
const ACCESS = process.env.ACCESS || 'private';
const BASE_HOST = `${STORE_ID}.${ACCESS}.blob.vercel-storage.com`;

const store = new Map();

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

function extractPathname(input) {
    try {
        return new URL(input).pathname.slice(1);
    } catch {
        return input.startsWith('/') ? input.slice(1) : input;
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
            const pathname = url.pathname.slice(1);
            const entry = store.get(pathname);
            if (!entry) {
                res.statusCode = 404;
                res.end();
                return;
            }
            const lastModified = new Date(entry.uploadedAt).toUTCString();
            if (req.headers['if-none-match'] === entry.etag) {
                res.statusCode = 304;
                res.setHeader('etag', entry.etag);
                res.setHeader('last-modified', lastModified);
                res.end();
                return;
            }
            res.statusCode = 200;
            res.setHeader('etag', entry.etag);
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
