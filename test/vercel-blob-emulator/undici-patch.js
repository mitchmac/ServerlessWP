// Test-only monkey patch: redirects fetch() calls to `*.blob.vercel-storage.com`
// to the Vercel Blob emulator at VERCEL_BLOB_MOCK_URL.
//
// The Vercel Blob SDK hardcodes download URLs (`constructBlobUrl` in
// @vercel/blob/dist/index.cjs) and validates URL inputs to get() against
// `.blob.vercel-storage.com`, so we can't override the host via env vars. The
// SDK's compiled CJS does `_undici.fetch.call(...)` against the shared undici
// module exports, so replacing `undici.fetch` after it's been required is
// picked up by every subsequent SDK call.
//
// Put/head/list/delete go through VERCEL_BLOB_API_URL (set separately).

const undici = require('undici');

const MOCK_URL = process.env.VERCEL_BLOB_MOCK_URL;
if (MOCK_URL) {
    const mockBase = new URL(MOCK_URL);
    const originalFetch = undici.fetch;

    undici.fetch = function patchedFetch(input, opts) {
        let urlStr;
        if (typeof input === 'string') {
            urlStr = input;
        } else if (input instanceof URL) {
            urlStr = input.toString();
        } else if (input && typeof input.url === 'string') {
            urlStr = input.url;
        }

        if (urlStr) {
            try {
                const parsed = new URL(urlStr);
                if (parsed.hostname.endsWith('.blob.vercel-storage.com')) {
                    const rewritten = new URL(parsed.pathname + parsed.search, mockBase).toString();
                    return originalFetch.call(this, rewritten, opts);
                }
            } catch {}
        }

        return originalFetch.call(this, input, opts);
    };

    console.log('[undici-patch] redirecting *.blob.vercel-storage.com to', MOCK_URL);
}
