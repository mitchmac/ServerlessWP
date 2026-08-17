// The rules deciding what a WordPress update touches, in util/wp-update/plan.js.
//
// These run in other people's repositories, against working copies holding
// their themes, plugins and edits. The cases that matter are the ones where
// the plan must decline to act: an occupied path, a locally changed file, a
// bundled plugin the owner deleted on purpose.

const test = require('node:test');
const assert = require('node:assert');

const planner = require('../util/wp-update/plan.js');

// Distinct stand-ins for file contents; only equality matters.
const A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const MINE = 'cccccccccccccccccccccccccccccccc';

function plan(parts) {
    return planner.plan({ oldSums: {}, newSums: {}, disk: {}, ...parts });
}

test('an untouched core file is updated', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A },
        newSums: { 'wp-login.php': B },
        disk: { 'wp-login.php': A },
    });

    assert.deepStrictEqual(result.writes, ['wp-login.php']);
    assert.deepStrictEqual(result.deletes, []);
    assert.deepStrictEqual(result.conflicts, []);
});

test('a new core file is added', () => {
    const result = plan({
        newSums: { 'wp-includes/blocks/new.php': B },
    });

    assert.deepStrictEqual(result.writes, ['wp-includes/blocks/new.php']);
});

test('a file the release drops is removed when it is still verbatim', () => {
    const result = plan({
        oldSums: { 'wp-includes/gone.php': A },
        disk: { 'wp-includes/gone.php': A },
    });

    assert.deepStrictEqual(result.deletes, ['wp-includes/gone.php']);
    assert.deepStrictEqual(result.writes, []);
});

test('a locally changed core file is reported, not overwritten', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A },
        newSums: { 'wp-login.php': B },
        disk: { 'wp-login.php': MINE },
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, [{ path: 'wp-login.php', kind: 'modified' }]);
});

test('a locally changed file the release drops is kept, not deleted', () => {
    const result = plan({
        oldSums: { 'wp-includes/gone.php': A },
        disk: { 'wp-includes/gone.php': MINE },
    });

    assert.deepStrictEqual(result.deletes, []);
    assert.deepStrictEqual(result.conflicts, [{ path: 'wp-includes/gone.php', kind: 'modified-removed' }]);
});

// The case that makes this safe to run in a fork: WordPress starts shipping a
// path the owner already uses. There is no previous checksum proving the file
// was ever ours, so it stays theirs.
test('a path the release adds over an existing file is reported, not overwritten', () => {
    const result = plan({
        newSums: { 'wp-content/themes/twentytwentysix/style.css': B },
        disk: { 'wp-content/themes/twentytwentysix/style.css': MINE },
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, [
        { path: 'wp-content/themes/twentytwentysix/style.css', kind: 'occupied' },
    ]);
});

test('a file already at the new version is left alone', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A },
        newSums: { 'wp-login.php': B },
        disk: { 'wp-login.php': B },
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, []);
    assert.strictEqual(result.unchanged, 1);
});

// This repository deletes akismet and hello.php, and clones delete bundled
// themes they don't want. An update must not quietly put them back.
test('a deleted bundled file is not restored', () => {
    const result = plan({
        oldSums: { 'wp-content/plugins/akismet/akismet.php': A },
        newSums: { 'wp-content/plugins/akismet/akismet.php': B },
        disk: {},
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, []);
    assert.deepStrictEqual(result.absent, ['wp-content/plugins/akismet/akismet.php']);
});

// A local edit to a core file is reported whether or not this release touches
// it, so it can't sit unnoticed until some later update collides with it. It
// is not a conflict: nothing was skipped on its account.
test('a locally modified core file is reported even when the release leaves it alone', () => {
    const result = plan({
        oldSums: { 'wp-load.php': A, 'readme.html': A },
        newSums: { 'wp-load.php': A, 'readme.html': A },
        disk: { 'wp-load.php': MINE, 'readme.html': A },
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, []);
    assert.deepStrictEqual(result.localEdits, ['wp-load.php']);
    assert.strictEqual(result.unchanged, 1);
});

// The counterpart: deleting a bundled file this release doesn't change stays
// silent. Clones drop akismet and whole themes on purpose, and naming those
// files every run would bury everything else.
test('a deleted file this release does not change is not reported', () => {
    const result = plan({
        oldSums: { 'wp-content/plugins/akismet/akismet.php': A },
        newSums: { 'wp-content/plugins/akismet/akismet.php': A },
        disk: {},
    });

    assert.deepStrictEqual(result.absent, []);
    assert.deepStrictEqual(result.localEdits, []);
    assert.strictEqual(result.unchanged, 1);
});

test('an ignored path is left out of the plan entirely', () => {
    const result = plan({
        oldSums: { 'wp-content/themes/twentytwentyfive/package-lock.json': A },
        newSums: { 'wp-content/themes/twentytwentyfive/package-lock.json': B },
        disk: { 'wp-content/themes/twentytwentyfive/package-lock.json': MINE },
        ignored: new Set(['wp-content/themes/twentytwentyfive/package-lock.json']),
    });

    assert.deepStrictEqual(result.writes, []);
    assert.deepStrictEqual(result.conflicts, []);
    assert.strictEqual(result.unchanged, 0);
});

// Nothing outside the two checksum lists is a candidate, so a plugin or theme
// the owner added is never named by the plan.
test('files wordpress.org does not list are not considered', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A },
        newSums: { 'wp-login.php': B },
        disk: {
            'wp-login.php': A,
            'wp-config.php': MINE,
            'wp-content/plugins/my-plugin/my-plugin.php': MINE,
        },
    });

    assert.deepStrictEqual(result.writes, ['wp-login.php']);
    assert.deepStrictEqual(result.deletes, []);
    assert.deepStrictEqual(result.conflicts, []);
});

test('the report names every conflict and counts the rest', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A, 'wp-includes/gone.php': A },
        newSums: { 'wp-login.php': B },
        disk: { 'wp-login.php': MINE, 'wp-includes/gone.php': A },
    });

    const report = planner.report('7.0.2', '7.1', result);

    assert.match(report, /from 7\.0\.2 to 7\.1/);
    assert.match(report, /1 file\(s\) removed/);
    assert.match(report, /`wp-login\.php`/);
    assert.match(report, /changed locally/);
});

// Local edits get their own section: folding them into the untouched count
// would claim the update skipped work it never had.
test('the report separates local edits from what the update skipped', () => {
    const result = plan({
        oldSums: { 'wp-login.php': A, 'wp-load.php': A },
        newSums: { 'wp-login.php': B, 'wp-load.php': A },
        disk: { 'wp-login.php': MINE, 'wp-load.php': MINE },
    });

    const report = planner.report('7.0.2', '7.1', result);

    assert.match(report, /### 1 file\(s\) left untouched/);
    assert.match(report, /### 1 locally modified core file\(s\)/);
    assert.ok(report.indexOf('left untouched') < report.indexOf('locally modified'));
});
