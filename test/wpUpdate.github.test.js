// Following a plugin's GitHub branch, in util/wp-update/github.js and the
// tracked path of util/wp-update/plugins.js.
//
// sqlite-database-integration is bundled from source rather than from
// wordpress.org, so it follows the repository's default branch. There is
// nothing to verify it against: the copy in wp/ mirrors the branch, which means
// this is the one place an update deletes a file it can't account for. The
// tests below pin that behaviour down.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const github = require('../util/wp-update/github.js');
const plugins = require('../util/wp-update/plugins.js');

let pluginDir;

test.beforeEach(() => {
    pluginDir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-track-test-'));
});

test.afterEach(() => {
    fs.rmSync(pluginDir, { recursive: true, force: true });
});

function write(relative, contents) {
    const file = path.join(pluginDir, relative);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);
    return file;
}

// The hash has to be git's, or every file would read as changed and the
// updater would rewrite the whole plugin on every run.
test('the blob hash matches what git computes', () => {
    const file = write('hello.txt', 'hello\n');

    const ours = github.blobSha(fs.readFileSync(file));
    const theirs = execFileSync('git', ['hash-object', file]).toString().trim();

    assert.strictEqual(ours, theirs);
    // Recorded so a change of algorithm can't quietly pass by agreeing with a
    // rewritten helper.
    assert.strictEqual(ours, 'ce013625030ba8dba906f756967f9e9ca394464a');
});

test('an empty file hashes to git\'s empty blob', () => {
    const file = write('empty.txt', '');

    assert.strictEqual(github.blobSha(fs.readFileSync(file)), 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391');
});

test('files are listed relative to the plugin directory', () => {
    write('load.php', 'a');
    write('wp-includes/database/load.php', 'b');
    write('integrations/query-monitor/boot.php', 'c');

    assert.deepStrictEqual(plugins.filesUnder(pluginDir).sort(), [
        'integrations/query-monitor/boot.php',
        'load.php',
        'wp-includes/database/load.php',
    ]);
});

test('listing a directory that is not there is empty, not an error', () => {
    assert.deepStrictEqual(plugins.filesUnder(path.join(pluginDir, 'nope')), []);
});

// Everything below stubs the repository, so the decisions are tested without
// depending on what trunk holds today.
function stubRepo(files) {
    const realBranch = github.defaultBranch;
    const realTree = github.effectiveTree;
    github.defaultBranch = async () => 'trunk';
    github.effectiveTree = async () => new Map(Object.entries(files).map(([p, c]) => [p, github.blobSha(Buffer.from(c))]));
    return () => {
        github.defaultBranch = realBranch;
        github.effectiveTree = realTree;
    };
}

const TRACKED = { repo: 'WordPress/sqlite-database-integration', path: 'packages/plugin-sqlite-database-integration' };

test('a copy matching the branch needs no update', async () => {
    write('load.php', 'same');
    const restore = stubRepo({ 'load.php': 'same' });

    try {
        const result = await plugins.inspectTracked({ slug: 'x', dir: pluginDir }, TRACKED);
        assert.strictEqual(result.status, 'current');
    } finally {
        restore();
    }
});

test('a changed file on the branch is written', async () => {
    write('load.php', 'old');
    const restore = stubRepo({ 'load.php': 'new' });

    try {
        const result = await plugins.inspectTracked({ slug: 'x', dir: pluginDir }, TRACKED);
        assert.strictEqual(result.status, 'track');
        assert.deepStrictEqual(result.plan.writes, ['load.php']);
        assert.deepStrictEqual(result.plan.deletes, []);
    } finally {
        restore();
    }
});

test('a file new on the branch is added', async () => {
    write('load.php', 'same');
    const restore = stubRepo({ 'load.php': 'same', 'capabilities.php': 'new file' });

    try {
        const result = await plugins.inspectTracked({ slug: 'x', dir: pluginDir }, TRACKED);
        assert.deepStrictEqual(result.plan.writes, ['capabilities.php']);
    } finally {
        restore();
    }
});

// The consequence of mirroring a branch, and the one place in this updater
// where a file nobody can account for is removed. Following the branch means
// the directory belongs to the branch.
test('a file the branch does not have is removed, including one added locally', async () => {
    write('load.php', 'same');
    write('MY-NOTE.txt', 'my own note');
    write('old/dropped.php', 'gone upstream');
    const restore = stubRepo({ 'load.php': 'same' });

    try {
        const result = await plugins.inspectTracked({ slug: 'x', dir: pluginDir }, TRACKED);
        assert.strictEqual(result.status, 'track');
        assert.deepStrictEqual(result.plan.deletes.sort(), ['MY-NOTE.txt', 'old/dropped.php']);
    } finally {
        restore();
    }
});

test('the tracked plugin never goes through wordpress.org', async () => {
    // wordpress.org publishes this slug at an older version, so reaching the
    // .org path at all would offer a downgrade.
    assert.ok(plugins.TRACKED['sqlite-database-integration']);

    write('load.php', 'same');
    const restore = stubRepo({ 'load.php': 'same' });

    try {
        const result = await plugins.inspect({
            slug: 'sqlite-database-integration',
            dir: pluginDir,
            installed: '3.0.0-rc.7',
        });
        assert.strictEqual(result.status, 'current');
        assert.match(result.source, /^WordPress\/sqlite-database-integration@/);
    } finally {
        restore();
    }
});

test('the report names the branch a plugin follows', () => {
    const report = plugins.report([
        {
            slug: 'sqlite-database-integration',
            source: 'WordPress/sqlite-database-integration@trunk',
            plan: { writes: ['load.php', 'capabilities.php'], deletes: ['gone.php'] },
            status: 'track',
        },
    ]);

    assert.match(report, /follows `WordPress\/sqlite-database-integration@trunk`/);
    assert.match(report, /2 file\(s\) changed, 1 removed/);
    assert.match(report, /no checksums to check them against/);
});
