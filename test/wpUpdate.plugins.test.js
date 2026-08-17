// Plugin updating in util/wp-update/plugins.js.
//
// The case driving most of this is sqlite-database-integration: it is bundled
// from its GitHub repository at 3.0.0-rc.7, while wordpress.org publishes
// 2.2.23 under the same slug. Code that trusted the slug alone would quietly
// downgrade it. Two independent guards stop that -- the version comparison and
// the absence of published checksums -- and both are tested here.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const plugins = require('../util/wp-update/plugins.js');

let pluginsRoot;

test.beforeEach(() => {
    pluginsRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-plugins-test-'));
});

test.afterEach(() => {
    fs.rmSync(pluginsRoot, { recursive: true, force: true });
});

function writePlugin(slug, fileName, contents) {
    const dir = path.join(pluginsRoot, slug);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, fileName), contents);
    return dir;
}

test('the real bundled plugin is older than the one wordpress.org publishes', () => {
    // The exact versions in this repository, and the reason the slug alone
    // cannot be trusted.
    assert.strictEqual(plugins.compareVersions('2.2.23', '3.0.0-rc.7'), -1);
    assert.strictEqual(plugins.compareVersions('3.0.0-rc.7', '2.2.23'), 1);
});

test('a release outranks its own release candidate', () => {
    assert.strictEqual(plugins.compareVersions('3.0.0', '3.0.0-rc.7'), 1);
    assert.strictEqual(plugins.compareVersions('3.0.0-rc.7', '3.0.0-rc.8'), -1);
    assert.strictEqual(plugins.compareVersions('1.0.0-beta.1', '1.0.0-rc.1'), -1);
    assert.strictEqual(plugins.compareVersions('1.0.0-alpha', '1.0.0-beta'), -1);
});

test('version parts compare as numbers, not as text', () => {
    assert.strictEqual(plugins.compareVersions('1.0.10', '1.0.9'), 1);
    assert.strictEqual(plugins.compareVersions('3.3.1', '3.3.1'), 0);
    assert.strictEqual(plugins.compareVersions('1.10', '1.9.9'), 1);
});

// An unrecognised suffix must not read as an upgrade, or a plugin bundled from
// a fork could talk the update into overwriting it.
test('an unknown suffix ranks below a plain release', () => {
    assert.strictEqual(plugins.compareVersions('1.0.0-mybuild', '1.0.0'), -1);
    assert.strictEqual(plugins.compareVersions('1.0.0', '1.0.0-mybuild'), 1);
});

// wordpress.org publishes an array of accepted md5s for a re-tagged release --
// three of tidb-compatibility 1.0.0's four files are like that. Dropping those
// entries made the paths look unknown and the whole plugin read as modified,
// so a matching build has to resolve to the hash on disk.
test('a file matching any accepted build counts as unmodified', () => {
    const disk = { 'README.md': 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' };
    const sums = { 'README.md': ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'] };

    assert.deepStrictEqual(plugins.acceptedHashes(sums, disk), {
        'README.md': 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    });
});

test('a file matching no accepted build stays modified', () => {
    const disk = { 'README.md': 'cccccccccccccccccccccccccccccccc' };
    const sums = { 'README.md': ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'] };

    const resolved = plugins.acceptedHashes(sums, disk);

    assert.strictEqual(resolved['README.md'], 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
    assert.notStrictEqual(resolved['README.md'], disk['README.md']);
});

test('a single-hash entry is left as it is', () => {
    assert.deepStrictEqual(
        plugins.acceptedHashes({ LICENSE: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' }, {}),
        { LICENSE: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' },
    );
});

test('the version comes from the plugin header, whatever the file is called', () => {
    // WP Offload Media really does keep its header in wordpress-s3.php.
    writePlugin('amazon-s3-and-cloudfront', 'wordpress-s3.php', '<?php\n/*\nPlugin Name: WP Offload Media Lite\nVersion: 3.3.1\n*/\n');

    const header = plugins.readHeader(path.join(pluginsRoot, 'amazon-s3-and-cloudfront'));

    assert.strictEqual(header.mainFile, 'wordpress-s3.php');
    assert.strictEqual(header.version, '3.3.1');
});

test('a header laid out as a doc block is read the same way', () => {
    writePlugin('tidb-compatibility', 'tidb-compatibility.php', '<?php\n/**\n * Plugin Name:       TiDB Compatibility\n * Version:           1.0.2\n */\n');

    assert.strictEqual(plugins.readHeader(path.join(pluginsRoot, 'tidb-compatibility')).version, '1.0.2');
});

test('a directory with no plugin header is not a plugin', () => {
    writePlugin('not-a-plugin', 'helper.php', '<?php\n// just a file\n');

    assert.strictEqual(plugins.readHeader(path.join(pluginsRoot, 'not-a-plugin')), null);
});

test('discovery finds plugin directories and ignores loose files', () => {
    writePlugin('one', 'one.php', '<?php\n/*\nPlugin Name: One\nVersion: 1.0\n*/\n');
    writePlugin('two', 'two.php', '<?php\n/*\nPlugin Name: Two\nVersion: 2.0\n*/\n');
    writePlugin('not-a-plugin', 'helper.php', '<?php\n');
    fs.writeFileSync(path.join(pluginsRoot, 'index.php'), '<?php // Silence is golden.');

    const found = plugins.discover(pluginsRoot);

    assert.deepStrictEqual(found.map((p) => p.slug), ['one', 'two']);
    assert.deepStrictEqual(found.map((p) => p.installed), ['1.0', '2.0']);
});

test('a plugin with a header but no version is left alone', async () => {
    writePlugin('mystery', 'mystery.php', '<?php\n/*\nPlugin Name: Mystery\n*/\n');

    const found = plugins.discover(pluginsRoot);
    const result = await plugins.inspect(found[0]);

    assert.strictEqual(result.status, 'no-version');
});

// Everything below drives inspect() against a stubbed wordpress.org, so the
// decisions are tested without depending on what .org publishes today.
const api = require('../util/wp-update/api.js');

function stubApi({ info, checksums }) {
    const realInfo = api.pluginInfo;
    const realSums = api.pluginChecksums;
    api.pluginInfo = async (slug) => info[slug] ?? null;
    api.pluginChecksums = async (slug, version) => checksums[`${slug}@${version}`] ?? null;
    return () => {
        api.pluginInfo = realInfo;
        api.pluginChecksums = realSums;
    };
}

test('a plugin wordpress.org does not publish is never touched', async () => {
    const dir = writePlugin('my-plugin', 'my-plugin.php', '<?php\n/*\nPlugin Name: Mine\nVersion: 1.0\n*/\n');
    const restore = stubApi({ info: {}, checksums: {} });

    try {
        const result = await plugins.inspect({ slug: 'my-plugin', dir, installed: '1.0' });
        assert.strictEqual(result.status, 'not-on-org');
    } finally {
        restore();
    }
});

// sqlite-database-integration itself follows a GitHub branch now and never
// reaches this path (see wpUpdate.github.test.js). These two guards still
// protect every other plugin bundled from outside wordpress.org, and the
// version numbers are kept because they are the shape of the real case: .org
// carrying an older release under a slug someone else is bundling from source.
test('a plugin newer than the wordpress.org release is never downgraded', async () => {
    const dir = writePlugin('bundled-from-source', 'load.php', '<?php\n/*\nPlugin Name: Bundled From Source\nVersion: 3.0.0-rc.7\n*/\n');
    const restore = stubApi({
        info: { 'bundled-from-source': { version: '2.2.23' } },
        checksums: {},
    });

    try {
        const result = await plugins.inspect({ slug: 'bundled-from-source', dir, installed: '3.0.0-rc.7' });
        assert.strictEqual(result.status, 'ahead');
        assert.ok(!result.plan);
    } finally {
        restore();
    }
});

// The second guard, independent of the version comparison: even asked to move
// forward, a build wordpress.org never published cannot be verified.
test('a build wordpress.org has no checksums for is not updated', async () => {
    const dir = writePlugin('bundled-from-source', 'load.php', '<?php\n/*\nPlugin Name: Bundled From Source\nVersion: 3.0.0-rc.7\n*/\n');
    const restore = stubApi({
        info: { 'bundled-from-source': { version: '3.1.0' } },
        checksums: { 'bundled-from-source@3.1.0': { 'load.php': 'aaa' } },
    });

    try {
        const result = await plugins.inspect({ slug: 'bundled-from-source', dir, installed: '3.0.0-rc.7' });
        assert.strictEqual(result.status, 'unverifiable');
    } finally {
        restore();
    }
});

test('a plugin already at the published version is left alone', async () => {
    const dir = writePlugin('tidb-compatibility', 'tidb-compatibility.php', '<?php\n');
    const restore = stubApi({ info: { 'tidb-compatibility': { version: '1.0.2' } }, checksums: {} });

    try {
        const result = await plugins.inspect({ slug: 'tidb-compatibility', dir, installed: '1.0.2' });
        assert.strictEqual(result.status, 'current');
    } finally {
        restore();
    }
});

test('a clean plugin is planned for update', async () => {
    const dir = writePlugin('demo', 'demo.php', 'old');
    const md5Old = '149603e6c03516362a8da23f624db945'; // md5('old')
    const restore = stubApi({
        info: { demo: { version: '2.0' } },
        checksums: {
            'demo@1.0': { 'demo.php': md5Old },
            'demo@2.0': { 'demo.php': 'ffffffffffffffffffffffffffffffff' },
        },
    });

    try {
        const result = await plugins.inspect({ slug: 'demo', dir, installed: '1.0' });
        assert.strictEqual(result.status, 'update');
        assert.deepStrictEqual(result.plan.writes, ['demo.php']);
    } finally {
        restore();
    }
});

// All or nothing: one edited file stops the whole plugin, because a plugin
// running a mix of two releases is worse than one that didn't update.
test('a single edited file stops the whole plugin from updating', async () => {
    const dir = writePlugin('demo', 'demo.php', 'old');
    fs.writeFileSync(path.join(dir, 'extra.php'), 'edited by hand');
    const restore = stubApi({
        info: { demo: { version: '2.0' } },
        checksums: {
            'demo@1.0': { 'demo.php': '149603e6c03516362a8da23f624db945', 'extra.php': 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' },
            'demo@2.0': { 'demo.php': 'ffffffffffffffffffffffffffffffff', 'extra.php': 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' },
        },
    });

    try {
        const result = await plugins.inspect({ slug: 'demo', dir, installed: '1.0' });
        assert.strictEqual(result.status, 'modified');
    } finally {
        restore();
    }
});

test('the report names what was updated and what was skipped', () => {
    const report = plugins.report([
        { slug: 'tidb-compatibility', installed: '1.0.2', latest: '1.1.0', status: 'update' },
        { slug: 'sqlite-database-integration', installed: '3.0.0-rc.7', status: 'ahead' },
        { slug: 'my-plugin', installed: '1.0', status: 'not-on-org' },
        { slug: 'amazon-s3-and-cloudfront', installed: '3.3.1', status: 'current' },
    ]);

    assert.match(report, /\*\*tidb-compatibility\*\* 1\.0\.2 → 1\.1\.0/);
    assert.match(report, /2 plugin\(s\) left untouched/);
    assert.match(report, /sqlite-database-integration/);
    assert.match(report, /1 plugin\(s\) already up to date/);
});
