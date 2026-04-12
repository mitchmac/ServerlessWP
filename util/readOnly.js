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
    if (contentType.includes('text/html') && !response.headers['cache-control']) {
        response.headers['cache-control'] = 'max-age=86400, s-maxage=86400';
    }
};
