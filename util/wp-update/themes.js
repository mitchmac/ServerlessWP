// Reports on themes. This file never writes anything.
//
// wordpress.org publishes no theme-checksums endpoint -- the plugin equivalent
// answers 404 for every theme -- so there is no way to prove a theme on disk is
// still the release it claims to be. Without that proof nothing here writes,
// deletes or downloads anything. A theme update is the owner's to make.
//
// Themes bundled with WordPress are a separate matter: their files are in the
// core checksums, so the core update already covers them. Reporting those as
// outdated would be wrong as well as noisy, and which themes those are comes
// from the same place as everything else -- wordpress.org, for the exact
// WordPress version on disk.

const fs = require('fs');
const path = require('path');

const api = require('./api.js');
const core = require('./core.js');
const versions = require('./versions.js');

// A theme is a directory with a style.css carrying a Theme Name header.
exports.readHeader = function (themeDir) {
    let style;
    try {
        style = fs.readFileSync(path.join(themeDir, 'style.css'), 'utf8').slice(0, 8192);
    } catch {
        return null;
    }

    if (!versions.headerField(style, 'Theme Name')) {
        return null;
    }

    return { version: versions.headerField(style, 'Version') };
};

exports.discover = function (themesRoot) {
    let entries;
    try {
        entries = fs.readdirSync(themesRoot, { withFileTypes: true });
    } catch {
        return [];
    }

    const found = [];
    for (const entry of entries.filter((e) => e.isDirectory()).sort((a, b) => a.name.localeCompare(b.name))) {
        const header = exports.readHeader(path.join(themesRoot, entry.name));
        if (header) {
            found.push({ slug: entry.name, installed: header.version });
        }
    }

    return found;
};

// The theme directories a WordPress release ships, read out of its own file
// list rather than a list of names kept here.
exports.bundledSlugs = function (checksums) {
    const slugs = new Set();

    for (const filePath of Object.keys(checksums)) {
        const parts = filePath.split('/');
        if (parts[0] === 'wp-content' && parts[1] === 'themes' && parts.length > 3) {
            slugs.add(parts[2]);
        }
    }

    return slugs;
};

exports.inspect = async function (theme, bundled) {
    if (bundled.has(theme.slug)) {
        return { ...theme, status: 'core' };
    }

    if (!theme.installed) {
        return { ...theme, status: 'no-version' };
    }

    const info = await api.themeInfo(theme.slug);
    if (!info) {
        return { ...theme, status: 'not-on-org' };
    }

    if (versions.compareVersions(info.version, theme.installed) <= 0) {
        return { ...theme, latest: info.version, status: 'current' };
    }

    return { ...theme, latest: info.version, status: 'outdated' };
};

exports.report = function (results) {
    const outdated = results.filter((r) => r.status === 'outdated');
    const custom = results.filter((r) => r.status === 'not-on-org' || r.status === 'no-version');
    const bundled = results.filter((r) => r.status === 'core');
    const current = results.filter((r) => r.status === 'current');

    const lines = ['## Themes', ''];

    if (outdated.length) {
        lines.push(`**${outdated.length} theme(s) have a newer release on wordpress.org.**`, '');
        lines.push('Themes are never updated automatically: wordpress.org publishes no');
        lines.push('checksums for them, so there is no way to tell an untouched copy from');
        lines.push('one that has been edited. Updating these is a manual step.', '');

        for (const theme of outdated) {
            lines.push(`- \`${theme.slug}\` ${theme.installed} → ${theme.latest} — https://wordpress.org/themes/${theme.slug}/`);
        }
    } else {
        lines.push('No theme has a newer release on wordpress.org.');
    }

    const notes = [];
    if (bundled.length) {
        notes.push(`${bundled.length} shipped with WordPress and covered by the core update`);
    }
    if (current.length) {
        notes.push(`${current.length} already up to date`);
    }
    if (custom.length) {
        notes.push(`${custom.length} not published on wordpress.org (${custom.map((t) => t.slug).join(', ')})`);
    }

    if (notes.length) {
        lines.push('', notes.join('; ') + '.');
    }

    return lines.join('\n');
};

exports.run = async function (options) {
    const themesRoot = path.join(options.root, 'wp-content', 'themes');
    const themes = exports.discover(themesRoot);

    console.log(`Found ${themes.length} theme(s) in ${themesRoot}.`);

    // Which themes this WordPress ships, for the version actually on disk.
    const wpVersion = core.installedVersion(options.root);
    const bundled = exports.bundledSlugs(await api.checksums(wpVersion));

    const results = [];
    for (const theme of themes) {
        const result = await exports.inspect(theme, bundled);
        console.log(`  ${theme.slug} ${theme.installed || '?'} — ${result.status}`);
        results.push(result);
    }

    // Nothing to commit, ever, so this never reports an update.
    return {
        updated: false,
        report: exports.report(results),
        outputs: { outdated: results.filter((r) => r.status === 'outdated').length },
    };
};
