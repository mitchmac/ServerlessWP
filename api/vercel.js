// Vercel entry point, referenced by vercel.json. No Vercel-specific setup left
// here - util/storage.js reads the VERCEL_* variables itself.

const core = require('./index.js');

module.exports = core.handler;
module.exports.handler = core.handler;
