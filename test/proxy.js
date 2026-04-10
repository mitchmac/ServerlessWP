const https = require('https');
const http = require('http');
const zlib = require('zlib');
const fs = require('fs');
const path = require('path');

const ssl = {
    cert: fs.readFileSync(path.join(__dirname, 'test-cert.pem')),
    key: fs.readFileSync(path.join(__dirname, 'test-key.pem')),
};

const WP_DIR = path.resolve(__dirname, '../wp');

const STATIC_MIME = {
    '.js':   'application/javascript',
    '.css':  'text/css',
    '.png':  'image/png',
    '.jpg':  'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif':  'image/gif',
    '.svg':  'image/svg+xml',
    '.ico':  'image/x-icon',
    '.webp': 'image/webp',
    '.woff': 'font/woff',
    '.woff2':'font/woff2',
    '.ttf':  'font/ttf',
    '.otf':  'font/otf',
    '.eot':  'application/vnd.ms-fontobject',
    '.txt':  'text/plain',
    '.xml':  'text/xml',
    '.map':  'application/json',
};

function serveStatic(urlPath, res) {
    const ext = path.extname(urlPath).toLowerCase();
    const filePath = path.join(WP_DIR, urlPath);
    // Prevent path traversal outside WP_DIR
    if (!filePath.startsWith(WP_DIR)) {
        res.statusCode = 403;
        res.end();
        return;
    }
    fs.readFile(filePath, (err, data) => {
        if (err) {
            res.statusCode = 404;
            res.end();
            return;
        }
        res.setHeader('content-type', STATIC_MIME[ext] || 'application/octet-stream');
        res.end(data);
    });
}

// Lambda RIE only handles one invocation at a time — serialize all requests
let tail = Promise.resolve();

function invokeLambda(event) {
    const p = tail.then(() => new Promise((resolve, reject) => {
        const opts = {
            hostname: 'localhost', port: 9000,
            path: '/2015-03-31/functions/function/invocations',
            method: 'POST',
            headers: { 'content-type': 'application/json', 'content-length': Buffer.byteLength(event) },
        };
        http.request(opts, r => {
            let data = '';
            r.on('data', c => data += c);
            r.on('end', () => resolve(JSON.parse(data)));
        }).on('error', reject).end(event);
    }));
    tail = p.catch(() => {});
    return p;
}

function decompress(buf, encoding) {
    return new Promise((resolve, reject) => {
        if (encoding === 'gzip') zlib.gunzip(buf, (e, r) => e ? reject(e) : resolve(r));
        else if (encoding === 'deflate') zlib.inflate(buf, (e, r) => e ? reject(e) : resolve(r));
        else resolve(buf);
    });
}

https.createServer(ssl, (req, res) => {
    const [urlPath, qs] = req.url.split('?');
    const ext = path.extname(urlPath).toLowerCase();

    // Serve static files directly — no Lambda needed
    if (ext in STATIC_MIME && !urlPath.endsWith('.php')) {
        serveStatic(urlPath, res);
        return;
    }

    const chunks = [];
    req.on('data', c => chunks.push(c));
    req.on('end', () => {
        const body = Buffer.concat(chunks);
        // Strip accept-encoding so PHP returns plain (uncompressed) responses.
        const { 'accept-encoding': _ae, ...forwardHeaders } = req.headers;
        const event = JSON.stringify({
            path: urlPath,
            httpMethod: req.method,
            headers: forwardHeaders,
            rawQueryString: qs || '',
            queryStringParameters: qs ? Object.fromEntries(new URLSearchParams(qs)) : null,
            body: body.length ? body.toString('base64') : null,
            isBase64Encoded: !!body.length,
        });
        const t0 = Date.now();
        invokeLambda(event)
            .then(async ({ statusCode = 200, headers = {}, multiValueHeaders = {}, cookies = [], body: b = '', isBase64Encoded }) => {
                console.log(`${req.method} ${req.url} -> ${statusCode} (${Date.now()-t0}ms)`);
                let bodyBuf = isBase64Encoded ? Buffer.from(b, 'base64') : Buffer.from(b);
                // Decompress and strip content-encoding — we forward raw bytes to the browser.
                const ce = (headers['content-encoding'] || headers['Content-Encoding'] || '').toLowerCase();
                if (ce === 'gzip' || ce === 'deflate') {
                    bodyBuf = await decompress(bodyBuf, ce);
                    delete headers['content-encoding'];
                    delete headers['Content-Encoding'];
                }
                res.statusCode = statusCode;
                const skip = new Set(['content-length', 'transfer-encoding', 'connection', 'keep-alive']);
                for (const [k, v] of Object.entries(headers)) {
                    if (!skip.has(k.toLowerCase())) res.setHeader(k, v);
                }
                for (const [k, v] of Object.entries(multiValueHeaders)) {
                    if (!skip.has(k.toLowerCase())) res.setHeader(k, v);
                }
                // AWS HTTP API v2 format puts Set-Cookie in `cookies` array
                if (cookies.length) res.setHeader('set-cookie', cookies);
                res.end(bodyBuf);
            })
            .catch(e => { res.statusCode = 502; res.end(e.message); });
    });
}).listen(3000, () => console.log('proxy ready'));
