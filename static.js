'use strict';

const AWS = require('aws-sdk');
const execSync = require("child_process").execSync;
const fs = require('fs');
const md5File = require('md5-file');
const mime = require('mime-types');
const recursive = require('recursive-readdir');
const spawn = require('child_process').spawn;

const s3 = new AWS.S3({httpOptions: {timeout: 10000}});

module.exports.crawlEndpoint = (event, context, callback) => {
    var rds = new AWS.RDS();
    var RDSParams = {
        DBInstanceIdentifier: process.env.WP_DB_INSTANCE
    };
    rds.describeDBInstances(RDSParams, function(err, data) {
        if (!err) {
            data.DBInstances.forEach(function (instance) {
                if (instance.DBInstanceStatus == 'available') {
                    var lambda = new AWS.Lambda({region: process.env['WP_AWS_REGION']});

                    var params = {
                        FunctionName: process.env['WP_STATIC_FUNCTION_NAME'],
                        InvocationType: 'Event',
                        Payload: ''
                    };

                    lambda.invoke(params, function(error, data) {
                        var response = {
                            statusCode: 200,
                            headers: {},
                            body: 'Crawl started'
                        };
                        if (error) {
                            console.error(error);
                            var response = {
                                statusCode: 500,
                                headers: {},
                                body: 'Crawl error'
                            };
                        } else if (data) {
                            console.log(data);
                        }
                        callback(null, response);
                    });
                }
                else {
                    var response = {
                        statusCode: 500,
                        headers: {},
                        body: 'Site not ready'
                    };
                    callback(null, response);
                }
            });
        }
    });

};

module.exports.crawl = (event, context, callback) => {
    process.env['LD_LIBRARY_PATH'] = '/var/task/bin/lib:' + process.env['LD_LIBRARY_PATH'];

    var startUrl = process.env['WP_BACKEND_URL'];
    var user = "--user=" + process.env['WP_BASIC_AUTH_USER'];
    var password = "--password=" + process.env['WP_BASIC_AUTH_PASSWORD'];

    // @TODO: delete any previous leftover files.

    var crawlerArgs = [
        '-r',
        '-np',
        '--wait=0',
        '-nH',
        '-x',
        '-k',
        '-l0',
        '-T10',
        '-t3',
        '--trust-server-names',
        '--no-http-keep-alive',
        '--reject-regex', '"(.*)\.html\?(.*)"',
        user,
        password,
        '--directory-prefix=/tmp/crawl',
        startUrl
    ];
    try {
        var crawler = spawn('./bin/wget', crawlerArgs);
    }
    catch (e) {
        console.log(e);
    }

    var output = '';
    var err = '';

    crawler.stdout.on('data', function(data) {
        output += data.toString('utf-8');
    });

    crawler.stderr.on('data', function(data) {
        err += data.toString('utf-8');
    });

    crawler.on('close', function() {
        console.log(output);
        console.log(err);

        var baseDir = '/tmp/crawl/' + process.env['WP_STAGE'] + '/';
        if (fs.existsSync(baseDir)) {

            // Try to remove any references to the API Gateway URL from HTML.
            var urlSearch = process.env['WP_BACKEND_URL'].replace('https://', '');
            var urlReplace = process.env['WP_PUBLIC_DOMAIN'];
            execSync("find " + baseDir + " -type f -exec sed -i 's#" + urlSearch + "#" + urlReplace + "#g' {} +");

            recursive(baseDir, function (error, items) {
                var uploadCounter = 0;
                var changedFiles = [];
                if (items.length > 0) {
                    // @TODO: Handle more than 1000 files.
                    var listParams = {
                        Bucket: process.env.WP_S3_BUCKET
                    };
                    s3.listObjectsV2(listParams, function(err, data) {

                        for (var i = 0; i < items.length; i++) {
                            var pathParts = items[i].split('?');
                            var filePath = pathParts[0];
                            var fileKey = filePath.replace(baseDir, '');

                            var hash = md5File.sync(items[i]);
                            var upload = shouldUpload(fileKey, hash, data);

                            if (upload) {
                                var contentType = mime.lookup(filePath);
                                if (!contentType || contentType == 'application/x-httpd-php') {
                                    contentType = 'text/html';
                                }

                                var fileData = fs.createReadStream(items[i]);
                                var params = {
                                    Bucket: process.env.WP_S3_BUCKET,
                                    Key: fileKey,
                                    ACL: 'public-read',
                                    Body: fileData,
                                    ContentType: contentType
                                };
                                s3.putObject(params, function (error, dat) {
                                    uploadCounter++;
                                    if (error) {
                                        console.log(error);
                                    }
                                    if (uploadCounter === items.length) {
                                        uploadsComplete(data['Contents'], items, callback);
                                    }
                                });
                            }
                            else {
                                uploadCounter++;
                                if (uploadCounter === items.length) {
                                    uploadsComplete(data['Contents'], items, callback);
                                }
                            }
                        }
                    });
                }
            });
        }
    });
};

var uploadsComplete = function (s3Files, crawlFiles, callback) {
    var findFile = function (file, source) {
        return source.find(function (obj) { return obj.startsWith('/tmp/crawl/' + process.env['WP_STAGE'] + '/' + file); });
    };

    for (var i = 0; i < s3Files.length; i++) {
        var fileKey = s3Files[i].Key;
        var found = findFile(fileKey, crawlFiles);
        if (typeof found === 'undefined' && !fileKey.startsWith('wp-content/uploads')) {
            var params = {
                Bucket: process.env.WP_S3_BUCKET,
                Key: fileKey
            };
            s3.deleteObject(params, function(error, data) {
                if (error) {
                    console.log(error);
                }
            });
        }
    }

};

var shouldUpload = function (fileKey, hash, data) {
    if (!data['Contents'].length) {
        return true;
    }

    var existing = data['Contents'].find(function (obj) { return obj.Key === fileKey; });
    return !(typeof existing !== 'undefined' && existing.ETag === '"' + hash + '"');
};
