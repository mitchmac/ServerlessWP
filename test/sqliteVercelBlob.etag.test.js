// ETag handling for util/sqliteVercelBlob.js.
//
// Vercel Blob serves downloads with a weak validator (`W/"abc"`) but compares
// x-if-match against the strong form (`"abc"`) that put and head report. An
// ETag cached from a download therefore has to be canonicalized, or every
// conditional write from that instance is rejected with a 412 - which is what
// happens on a cold start, since a cold instance always does a full download.

const test = require('node:test');
const assert = require('node:assert');

const { _normalizeEtag: normalizeEtag } = require('../util/sqliteVercelBlob.js');

test('a weak download ETag becomes the strong form used for conditional writes', () => {
    assert.strictEqual(
        normalizeEtag('W/"5eee2d609d153b6ba014d93d93637e3d"'),
        '"5eee2d609d153b6ba014d93d93637e3d"'
    );
});

test('a strong ETag from put or head is left alone', () => {
    assert.strictEqual(
        normalizeEtag('"5eee2d609d153b6ba014d93d93637e3d"'),
        '"5eee2d609d153b6ba014d93d93637e3d"'
    );
});

test('only a leading weak prefix is stripped', () => {
    // A W/ inside the value is part of the entity tag, not a validator prefix.
    assert.strictEqual(normalizeEtag('"abcW/def"'), '"abcW/def"');
    assert.strictEqual(normalizeEtag('W/"W/abc"'), '"W/abc"');
});

test('a missing ETag stays falsy so no ifMatch is sent', () => {
    assert.strictEqual(normalizeEtag(''), '');
    assert.strictEqual(normalizeEtag(undefined), undefined);
});
