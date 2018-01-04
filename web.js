'use strict';

const execSync = require("child_process").execSync;
const fs = require('fs');
const httpHeaders = require('http-headers');
const mime = require('mime-types');
const parser = require('http-string-parser');
const spawn = require('child_process').spawn;
const url = require('url').Url;

module.exports.index = (event, context, callback) => {
    process.env['LD_LIBRARY_PATH'] = '/var/task/bin/lib:' + process.env['LD_LIBRARY_PATH'];

    var requestUri = event.path || '';

    setupWPDirectory();

    var rawUrl = 'https://' + event.headers.Host + requestUri;
    var urlParser = new url();
    var parsedUrl = urlParser.parse(rawUrl);
    var path = parsedUrl.pathname;

    var wpPath = '/tmp/wp' + path;
    var pathExists = fs.existsSync(wpPath);
    var isFile = false;
    if (pathExists) {
        isFile = fs.statSync(wpPath).isFile();
    }

    // It's a PHP file, try serving it via PHP.
    if ((pathExists && isFile) && wpPath.substr(wpPath.length - 4) == '.php') {
        handleDynamicRequest(wpPath, event, callback);
    }
    // It's a static file, serve it back.
    else if (pathExists && isFile) {
        handleStaticFile(wpPath, callback);
    }
    // It's a directory with an index.php file, serve that index.php via PHP.
    else if (pathExists && fs.statSync(wpPath).isDirectory() && fs.statSync(wpPath + "index.php").isFile()) {
        handleDynamicRequest(wpPath + "index.php", event, callback);
    }
    // It's something else, let WordPress try to serve it.
    else {
        handleDynamicRequest('/tmp/wp/index.php', event, callback);
    }
};

/**
 * Handle a dynamic request to PHP.
 */
var handleDynamicRequest = function(phpFile, event, callback) {
    // Normalize headers a bit.
    var headers = {};
    if (event.headers) {
        Object.keys(event.headers).map(function (key) {
            headers['HTTP_' + key.toUpperCase()] = event.headers[key];
        });
    }

    // Stringify the query string if there is one.
    var queryString = '';
    if (event.queryStringParameters) {
        Object.keys(event.queryStringParameters).map(function (key) {
            queryString += key + "=" + event.queryStringParameters[key] + "&";
        });
        queryString = queryString.substring(0, queryString.length - 1);
    }

    var scriptName = "/" + process.env.WP_STAGE + phpFile.replace('/tmp/wp', '');
    var body = event.body || '';
    var phpArgs = ["--php-ini", "./bin/php.ini", "--file", phpFile];

    headers['HTTP_X_FORWARDED_FOR'] = headers['HTTP_X-FORWARDED-FOR'];
    var envVars = Object.assign({
        GATEWAY_INTERFACE: "CGI/1.1",
        REDIRECT_STATUS: 200,
        REQUEST_METHOD: event.httpMethod || 'GET',
        SCRIPT_FILENAME: phpFile,
        SCRIPT_NAME: scriptName,
        SERVER_NAME: event.headers.Host,
        SERVER_PROTOCOL: 'HTTP/1.1',
        REQUEST_URI: event.requestContext.path,
        HTTPS: 'on',
        REQUEST_SCHEME: 'https'
    }, headers, process.env);

    if (body) {
        if (event.isBase64Encoded) {
            body = Buffer.from(body, 'base64');
        }
        envVars['CONTENT_LENGTH'] = Buffer.byteLength(body);
        envVars['CONTENT_TYPE'] = event.headers['content-type'];
    }
    envVars['QUERY_STRING'] = queryString;

    try {
        var php = spawn('./bin/php-cgi', phpArgs, {
            env: envVars
        });
    }
    catch (e) {
        console.log(e);
    }

    var output = '';
    var err = '';

    if (body) {
        php.stdin.write(body);
        php.stdin.end();
    }

    php.stdout.on('data', function(data) {
        output += data.toString('utf-8');
    });

    php.stderr.on('data', function(data) {
        err += data.toString('utf-8');
    });

    php.on('close', function() {
        console.log(err);

        // Parser and API Gateway don't work with multiple Set-Cookie headers, hack by changing case.
        var headerData = httpHeaders(output, true);
        if (typeof headerData['set-cookie'] != 'undefined') {
            var responseCookies = headerData['set-cookie'];
            delete headerData['set-cookie'];
            for (var i = 0; i < responseCookies.length; i++) {
                headerData[cookieReplacementKey(headerData)] = responseCookies[i];
            }
        }

        var parsedResponse = parser.parseResponse(output);

        // Does it look like the db is sleeping? Provide a link to restart the DB for convenience if so.
        var dbErrorString = '<h1>Error establishing a database connection</h1>';
        if (parsedResponse.statusCode == 500 && parsedResponse.body.indexOf(dbErrorString) !== -1) {
            var restartUrl = 'https://' + event.headers.Host + "/" +  process.env['WP_STAGE'] + "/serverlesswp-dbwake";
            var dbWakeLink = '<p>The MySQL database has likely been shutdown because of inactivity.</p><br><a href="' + restartUrl + '">Restart Database</a><br>';
            parsedResponse.body = parsedResponse.body.replace('</h1>', '</h1>' + dbWakeLink);
        }

        const response = {
            statusCode: parsedResponse.statusCode || 200,
            headers: headerData,
            body: parsedResponse.body
        };

        callback(null, response);
    });
};


/**
 * Serve a static file.
 */
var handleStaticFile = function(staticFile, callback) {
    var mimeType = mime.lookup(staticFile);
    var encoding = 'utf8';
    // @TODO: Handle more generally.
    if (mimeType.indexOf('text') === -1 && mimeType.indexOf('xml') === -1) {
        encoding = 'base64';
    }
    fs.readFile(staticFile, encoding, function (err, data ) {
        if (err) {
            return console.log(err);
        }
        else {
            var responseHeaders = {};
            responseHeaders['Cache-Control'] = "max-age=" + process.env.WP_LAMBDA_STATIC_CACHE_TIME;
            responseHeaders['Content-Type'] = mimeType;

            var staticResponse = function (body, responseHeaders) {
                var isBase64 = false;
                if (encoding == 'base64') {
                    isBase64 = true;
                    responseHeaders['Content-Type'] = '*/*';
                }
                const response = {
                    statusCode: 200,
                    headers: responseHeaders,
                    body: body,
                    isBase64Encoded: isBase64
                };
                callback(null, response);
            };

            staticResponse(data, responseHeaders);
        }
    });
};

/**
 * Move the WordPress directory to /tmp so files can be written.
 */
var setupWPDirectory = function() {
    if (!fs.existsSync('/tmp/wp')) {
        fs.mkdirSync('/tmp/wp');
        try {
            execSync('cp -R /var/task/wp/* /tmp/wp/');
        }
        catch (err) {
            console.log(err);
        }
    }
};

/**
 * Change case of 'Set-Cookie' header to bypass API Gateway issue.
 */
var cookieReplacementKey = function self(obj) {
    var chars = ['s', 'e', 't', 'c', 'o', 'o', 'k', 'i', 'e'];
    var key = '';
    for (var i = 0; i < chars.length; i++) {
        var upper = Math.round(Math.random());
        var char = chars[i];
        if (upper) {
            char = char.toUpperCase();
        }
        key += char;
    }
    var candidate = key.substr(0, 3) + '-' + key.substr(3);
    if (candidate in obj && Object.keys(obj).length < 512) {
        return self(obj);
    }
    else {
        return candidate;
    }
};
