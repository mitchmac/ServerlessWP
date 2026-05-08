// Test-only Lambda handler that applies the undici fetch patch before the real
// handler loads the Vercel Blob SDK. Used as the CMD for the blob-test image.
require('./undici-patch.js');
exports.handler = require('../api/vercel.js').handler;
