// The file operations in util/wp-update/files.js, against real directories.
//
// The plan tests cover which files get touched; these cover what touching them
// does on disk -- that a write lands, a delete removes only its own file, and
// that a directory holding anything else survives. Core and plugin updates share
// these, so a plugin update is subject to the same rules as a core one.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const updater = require('../util/wp-update/files.js');
const core = require('../util/wp-update/core.js');

let workDir;
let wpRoot;
let releaseRoot;

test.beforeEach(() => {
    workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-update-test-'));
    wpRoot = path.join(workDir, 'wp');
    releaseRoot = path.join(workDir, 'release');
    fs.mkdirSync(wpRoot, { recursive: true });
    fs.mkdirSync(releaseRoot, { recursive: true });
});

test.afterEach(() => {
    fs.rmSync(workDir, { recursive: true, force: true });
});

function write(root, filePath, contents) {
    const file = path.join(root, filePath);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);
}

function read(filePath) {
    return fs.readFileSync(path.join(wpRoot, filePath), 'utf8');
}

function exists(filePath) {
    return fs.existsSync(path.join(wpRoot, filePath));
}

test('a write creates missing directories and overwrites the old file', () => {
    write(wpRoot, 'wp-login.php', 'old');
    write(releaseRoot, 'wp-login.php', 'new');
    write(releaseRoot, 'wp-includes/blocks/new.php', 'added');

    updater.apply(wpRoot, releaseRoot, {
        writes: ['wp-login.php', 'wp-includes/blocks/new.php'],
        deletes: [],
    });

    assert.strictEqual(read('wp-login.php'), 'new');
    assert.strictEqual(read('wp-includes/blocks/new.php'), 'added');
});

test('a delete removes the file and prunes the directories it emptied', () => {
    write(wpRoot, 'wp-includes/old/deep/gone.php', 'x');

    updater.apply(wpRoot, releaseRoot, { writes: [], deletes: ['wp-includes/old/deep/gone.php'] });

    assert.ok(!exists('wp-includes/old/deep/gone.php'));
    assert.ok(!exists('wp-includes/old'));
    // Pruning stops at the WordPress root even when everything under it went.
    assert.ok(fs.existsSync(wpRoot));
});

// The reason nothing here uses rsync --delete: a deleted core file must not
// take a sibling the owner added with it.
test('a delete leaves a file the owner added in the same directory', () => {
    write(wpRoot, 'wp-content/plugins/akismet/akismet.php', 'x');
    write(wpRoot, 'wp-content/plugins/akismet/my-notes.txt', 'mine');

    updater.apply(wpRoot, releaseRoot, {
        writes: [],
        deletes: ['wp-content/plugins/akismet/akismet.php'],
    });

    assert.ok(!exists('wp-content/plugins/akismet/akismet.php'));
    assert.strictEqual(read('wp-content/plugins/akismet/my-notes.txt'), 'mine');
});

test('hashing covers only the paths asked for, and skips what is not there', () => {
    write(wpRoot, 'wp-login.php', 'x');
    write(wpRoot, 'wp-config.php', 'secrets');

    const disk = updater.hashDisk(wpRoot, ['wp-login.php', 'wp-settings.php']);

    // 9dd4e461268c8034f5c8564e155c67a6 is md5('x').
    assert.deepStrictEqual(disk, { 'wp-login.php': '9dd4e461268c8034f5c8564e155c67a6' });
});

// A directory sitting where WordPress ships a file has no md5, and must not
// read as "unmodified core file" and get deleted.
test('a directory where a file is expected is never mistaken for that file', () => {
    fs.mkdirSync(path.join(wpRoot, 'wp-login.php'));

    const disk = updater.hashDisk(wpRoot, ['wp-login.php']);

    assert.strictEqual(disk['wp-login.php'], 'not-a-file');
});

test('the installed version comes from wp-includes/version.php', () => {
    write(wpRoot, 'wp-includes/version.php', "<?php\n$wp_version = '7.0.2';\n");

    assert.strictEqual(core.installedVersion(wpRoot), '7.0.2');
});

test('ignored paths are found through git', () => {
    execFileSync('git', ['init', '-q'], { cwd: workDir });
    write(workDir, '.gitignore', 'package-lock.json\n');
    write(wpRoot, 'wp-content/themes/twentytwentyfive/package-lock.json', '{}');
    write(wpRoot, 'wp-login.php', 'x');

    const ignored = updater.ignoredPaths(wpRoot, [
        'wp-content/themes/twentytwentyfive/package-lock.json',
        'wp-login.php',
    ]);

    assert.deepStrictEqual([...ignored], ['wp-content/themes/twentytwentyfive/package-lock.json']);
});

test('nothing is ignored when the copy is not a git repository', () => {
    assert.deepStrictEqual([...updater.ignoredPaths(wpRoot, ['wp-login.php'])], []);
});
