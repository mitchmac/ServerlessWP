'use strict';

var AWS = require('aws-sdk');

module.exports.dbsleep = (event, context, callback) => {
    var rds = new AWS.RDS();
    var cloudwatch = new AWS.CloudWatch();
    var sleepTimeout = parseInt(process.env.WP_RDS_SLEEP_TIMEOUT);

    var now = new Date();
    var startTime = new Date();
    startTime.setMinutes(startTime.getMinutes() - sleepTimeout);
    var cloudwatchParams = {
        EndTime: now.toISOString(),
        MetricName: 'Invocations',
        Namespace: 'AWS/Lambda',
        Period: sleepTimeout * 60,
        StartTime: startTime.toISOString(),
        Dimensions: [
            {
                Name: 'FunctionName',
                Value: process.env.WP_WEB_FUNCTION_NAME
            }
        ],
        Statistics: [
            "Sum"
        ]
    };

    cloudwatch.getMetricStatistics(cloudwatchParams, function(err, data) {
        if (err) {
            console.log(err, err.stack);
        }
        else {
            if (!data.Datapoints.length) {
                var RDSParams = {
                    DBInstanceIdentifier: process.env.WP_DB_INSTANCE
                };
                rds.describeDBInstances(RDSParams, function(err, data) {
                    if (!err && sleepTimeout) {
                        data.DBInstances.forEach(function (instance) {
                            if (instance.DBInstanceStatus == 'available') {
                                rds.stopDBInstance(RDSParams, function (err, data) {
                                    if (err) {
                                        console.log(err, err.stack);
                                    }
                                    else {
                                        console.log(data);
                                    }
                                });
                            }
                        });
                    }
                });
            }
        }
    });
};

module.exports.dbwake = (event, context, callback) => {
    var rds = new AWS.RDS();
    var RDSParams = {
        DBInstanceIdentifier: process.env.WP_DB_INSTANCE
    };
    rds.describeDBInstances(RDSParams, function(err, data) {
        if (!err) {
            data.DBInstances.forEach(function (instance) {
                if (instance.DBInstanceStatus == 'stopped') {
                    rds.startDBInstance(RDSParams, function (err, data) {
                        if (err) {
                            console.log(err, err.stack);
                        }
                        else {
                            var response = {
                                statusCode: 200,
                                headers: {'Content-Type': 'text/html'},
                                body: '<html><head><meta http-equiv="refresh" content="10" ></head><body>Database is starting. This page will refresh every 10 seconds until the database is ready.</body></html>'
                            };
                            callback(null, response);
                        }

                    });
                }
                else if(instance.DBInstanceStatus == 'available') {
                    var response = {
                        statusCode: 307,
                        headers: {'Location': 'https://' + process.env['WP_EDITOR_CUSTOM_DOMAIN'] + '/' },
                        body: ''
                    };
                    callback(null, response);
                }
                else {
                    var response = {
                        statusCode: 200,
                        headers: {'Content-Type': 'text/html'},
                        body: '<html><head><meta http-equiv="refresh" content="10" ></head><body>Database is ' + instance.DBInstanceStatus + '. This page will refresh every 10 seconds until the database is ready.</body></html>'
                    };
                    callback(null, response);
                }
            });
        }
    });
};
