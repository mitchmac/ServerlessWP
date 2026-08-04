// Theme reporting in util/wp-update/themes.js.
//
// This never writes anything -- wordpress.org publishes no theme
// checksums, so an untouched theme can't be told from an edited one. The tests
// that matter are about what it says: a theme bundled with WordPress must not
// be reported as the owner's problem, because the core update already covers it.

const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');

const themes = require('../util/wp-update/themes.js');
const api = require('../util/wp-update/api.js');

let themesRoot;

test.beforeEach(() => {
    themesRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-themes-test-'));
});

test.afterEach(() => {
    fs.rmSync(themesRoot, { recursive: true, force: true });
});

function writeTheme(slug, style) {
    const dir = path.join(themesRoot, slug);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, 'style.css'), style);
    return dir;
}

test('the version comes from the style.css header', () => {
    writeTheme('twentytwentyfive', '/*\nTheme Name: Twenty Twenty-Five\nVersion: 1.5\n*/\n');

    assert.strictEqual(themes.readHeader(path.join(themesRoot, 'twentytwentyfive')).version, '1.5');
});

test('a stylesheet without a Theme Name is not a theme', () => {
    writeTheme('styles', '/* just some css */\nbody { color: red; }\n');

    assert.strictEqual(themes.readHeader(path.join(themesRoot, 'styles')), null);
});

test('a directory with no style.css is not a theme', () => {
    fs.mkdirSync(path.join(themesRoot, 'not-a-theme'));
    fs.writeFileSync(path.join(themesRoot, 'not-a-theme', 'readme.txt'), 'stray');

    assert.strictEqual(themes.readHeader(path.join(themesRoot, 'not-a-theme')), null);
});

test('discovery finds themes and skips everything else', () => {
    writeTheme('one', '/*\nTheme Name: One\nVersion: 1.0\n*/\n');
    writeTheme('two', '/*\nTheme Name: Two\nVersion: 2.0\n*/\n');
    fs.mkdirSync(path.join(themesRoot, 'empty'));
    fs.writeFileSync(path.join(themesRoot, 'index.php'), '<?php // Silence is golden.');

    assert.deepStrictEqual(themes.discover(themesRoot).map((t) => t.slug), ['one', 'two']);
});

// Which themes ship with WordPress is read out of the release's own file list,
// so it stays right when WordPress swaps its bundled themes.
test('bundled theme slugs come out of the core checksums', () => {
    const bundled = themes.bundledSlugs({
        'wp-content/themes/twentytwentyfive/style.css': 'a',
        'wp-content/themes/twentytwentyfive/theme.json': 'b',
        'wp-content/themes/twentytwentyfour/style.css': 'c',
        'wp-content/themes/index.php': 'd',
        'wp-login.php': 'e',
    });

    assert.deepStrictEqual([...bundled].sort(), ['twentytwentyfive', 'twentytwentyfour']);
});

function stubThemeInfo(byslug) {
    const real = api.themeInfo;
    api.themeInfo = async (slug) => byslug[slug] ?? null;
    return () => {
        api.themeInfo = real;
    };
}

// The case that would otherwise produce a wrong and repeating finding: a theme
// WordPress ships is the core update's job, and .org's standalone listing for it
// says nothing about the copy in wp/.
test('a theme shipped with WordPress is never reported as outdated', async () => {
    const restore = stubThemeInfo({ twentytwentyfive: { version: '99.0' } });

    try {
        const result = await themes.inspect(
            { slug: 'twentytwentyfive', installed: '1.5' },
            new Set(['twentytwentyfive']),
        );
        assert.strictEqual(result.status, 'core');
        assert.ok(!result.latest);
    } finally {
        restore();
    }
});

test('a theme wordpress.org does not publish is the owner to maintain', async () => {
    const restore = stubThemeInfo({});

    try {
        const result = await themes.inspect({ slug: 'my-theme', installed: '0.1' }, new Set());
        assert.strictEqual(result.status, 'not-on-org');
    } finally {
        restore();
    }
});

test('a theme behind its wordpress.org release is reported', async () => {
    const restore = stubThemeInfo({ astra: { version: '4.13.8' } });

    try {
        const result = await themes.inspect({ slug: 'astra', installed: '4.0.0' }, new Set());
        assert.strictEqual(result.status, 'outdated');
        assert.strictEqual(result.latest, '4.13.8');
    } finally {
        restore();
    }
});

test('a theme at or ahead of the published release is current', async () => {
    const restore = stubThemeInfo({ astra: { version: '4.13.8' } });

    try {
        assert.strictEqual((await themes.inspect({ slug: 'astra', installed: '4.13.8' }, new Set())).status, 'current');
        assert.strictEqual((await themes.inspect({ slug: 'astra', installed: '5.0' }, new Set())).status, 'current');
    } finally {
        restore();
    }
});

test('a theme with no version header is not guessed at', async () => {
    const restore = stubThemeInfo({ astra: { version: '4.13.8' } });

    try {
        const result = await themes.inspect({ slug: 'astra', installed: undefined }, new Set());
        assert.strictEqual(result.status, 'no-version');
    } finally {
        restore();
    }
});

test('the report links outdated themes and counts the rest', () => {
    const report = themes.report([
        { slug: 'astra', installed: '4.0.0', latest: '4.13.8', status: 'outdated' },
        { slug: 'twentytwentyfive', installed: '1.5', status: 'core' },
        { slug: 'my-theme', installed: '0.1', status: 'not-on-org' },
    ]);

    assert.match(report, /1 theme\(s\) have a newer release/);
    assert.match(report, /`astra` 4\.0\.0 → 4\.13\.8/);
    assert.match(report, /https:\/\/wordpress\.org\/themes\/astra\//);
    assert.match(report, /1 shipped with WordPress/);
    assert.match(report, /my-theme/);
});

test('a report with nothing outdated says so plainly', () => {
    const report = themes.report([{ slug: 'twentytwentyfive', installed: '1.5', status: 'core' }]);

    assert.match(report, /No theme has a newer release/);
});
