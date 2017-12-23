'use strict';

module.exports.handler =  function(event, context, callback) {
    var token = event.authorizationToken;

    var user = process.env.WP_BASIC_AUTH_USER;
    var pass = process.env.WP_BASIC_AUTH_PASSWORD;
    var expected = 'Basic ' + new Buffer(user + ':' + pass).toString('base64');

    var arnParts = event.methodArn.split(process.env.WP_STAGE);
    var arn = arnParts[0] + '*/*/*';

    if (typeof token != 'undefined' && token == expected) {
        callback(null, generatePolicy('user', 'Allow', arn));
    }

    callback('Unauthorized');
};

var generatePolicy = function(principalId, effect, resource) {
    var authResponse = {};

    authResponse.principalId = principalId;
    if (effect && resource) {
        var policyDocument = {};
        policyDocument.Version = '2012-10-17';
        policyDocument.Statement = [];
        var statementOne = {};
        statementOne.Action = 'execute-api:Invoke';
        statementOne.Effect = effect;
        statementOne.Resource = resource;
        policyDocument.Statement[0] = statementOne;
        authResponse.policyDocument = policyDocument;
    }

    return authResponse;
};
