exports.name = 'Read Only Mode';

const MUTATION_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

function getMethod(event) {
    return (event.httpMethod || event.requestContext?.http?.method || 'GET').toUpperCase();
}

exports.preRequest = async function(event) {
    if (MUTATION_METHODS.includes(getMethod(event))) {
        return {
            statusCode: 403,
            headers: { 'content-type': 'text/html' },
            body: '<!DOCTYPE html><html><head><title>Read Only</title><style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f9f9f9;}p{color:#555;font-size:16px;}</style></head><body><p>This site is in read-only mode.</p></body></html>',
            _forceResponse: true,
        };
    }
};

exports.postRequest = async function(event, response) {
    if (!response?.headers) {
        return;
    }

    const contentType = response.headers['content-type'] || response.headers['Content-Type'] || '';
    const cacheControl = response.headers['cache-control'] || response.headers['Cache-Control'];
    const setCookie = response.headers['set-cookie'] || response.headers['Set-Cookie'];
    const requestCookies = event.headers?.cookie || event.headers?.Cookie || '';
    if (contentType.includes('text/html') && !cacheControl && !setCookie && !requestCookies) {
        const maxAge = process.env.SERVERLESSWP_READ_ONLY_CACHE_MAX_AGE || 86400;
        response.headers['cache-control'] = `max-age=0, s-maxage=${maxAge}`;
    }
};
