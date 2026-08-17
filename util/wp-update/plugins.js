// Updates bundled plugins that came from wordpress.org.
//
// The rule is stricter than it is for core, because a half-updated plugin is
// worse than one left alone: a plugin is only touched when wordpress.org can
// prove, file by file, that what's on disk is exactly the release it claims to
// be. Anything else -- a plugin that isn't on .org, a build .org has never
// published, a single edited file -- is reported and skipped whole.
//
// That is what protects sqlite-database-integration, which is bundled from its
// GitHub repository at a version wordpress.org doesn't carry. It needs no
// entry on any exclusion list: .org publishes no checksums for the installed
// build, so nothing here can prove anything about it and it is left alone.

const fs = require('fs');
const path = require('path');
const os = require('os');

const api = require('./api.js');
const files = require('./files.js');
const github = require('./github.js');
const planner = require('./plan.js');
const versions = require('./versions.js');

// WordPress reads plugin headers from the first 8KB of a file, and so does
// this. The main file is rarely named after the plugin -- WP Offload Media
// lives in wordpress-s3.php, SQLite Database Integration in load.php -- so
// every top-level PHP file is checked for the header rather than guessed at.
const HEADER_BYTES = 8192;

exports.readHeader = function (pluginDir) {
    let entries;
    try {
        entries = fs.readdirSync(pluginDir);
    } catch {
        return null;
    }

    for (const entry of entries.filter((name) => name.endsWith('.php')).sort()) {
        const file = path.join(pluginDir, entry);
        if (!fs.statSync(file).isFile()) {
            continue;
        }

        const head = fs.readFileSync(file).subarray(0, HEADER_BYTES).toString('utf8');
        if (!/^[ \t\/*#@]*Plugin Name:/mi.test(head)) {
            continue;
        }

        return { mainFile: entry, version: versions.headerField(head, 'Version') };
    }

    return null;
};

exports.compareVersions = versions.compareVersions;

// Every directory under wp-content/plugins holding a plugin header. The slug
// is the directory name, which is what wordpress.org keys plugins by.
exports.discover = function (pluginsRoot) {
    let entries;
    try {
        entries = fs.readdirSync(pluginsRoot, { withFileTypes: true });
    } catch {
        return [];
    }

    const found = [];
    for (const entry of entries.filter((e) => e.isDirectory()).sort((a, b) => a.name.localeCompare(b.name))) {
        const dir = path.join(pluginsRoot, entry.name);
        const header = exports.readHeader(dir);
        if (header) {
            found.push({ slug: entry.name, dir, installed: header.version });
        }
    }

    return found;
};

// Flattens the multi-hash entries wordpress.org publishes for re-tagged
// releases down to the one hash that matters here.
//
// plan.js compares one checksum per path, so an array has to collapse before
// it gets there. A file matching any accepted build is the official file, so
// resolving to the hash already on disk is what tells the plan it is untouched.
// With no match, the first accepted hash stands in and the file reads as
// locally modified -- which is what it is.
exports.acceptedHashes = function (sums, disk) {
    const resolved = {};

    for (const [filePath, hashes] of Object.entries(sums)) {
        if (!Array.isArray(hashes)) {
            resolved[filePath] = hashes;
        } else {
            resolved[filePath] = hashes.includes(disk[filePath]) ? disk[filePath] : hashes[0];
        }
    }

    return resolved;
};

// Plugins bundled from GitHub instead of wordpress.org. These follow the
// repository's default branch: whatever it holds is what a site runs, and the
// pull request diff is where that gets reviewed.
//
// wordpress.org carries a plugin under this slug too, at an older version, so
// leaving it out of this list would not merely stop updates -- it would offer a
// downgrade. It is here because the plugin is bundled from source, and it has
// to be listed because nothing in the plugin's own headers points at its
// repository.
exports.TRACKED = {
    'sqlite-database-integration': {
        repo: 'WordPress/sqlite-database-integration',
        path: 'packages/plugin-sqlite-database-integration',
    },
};

// Compares the copy on disk against the repository's default branch. Unlike
// the wordpress.org path there is no proof to be had, so anything differing
// from the branch is replaced and anything the branch doesn't have is removed.
exports.inspectTracked = async function (plugin, tracked) {
    const branch = await github.defaultBranch(tracked.repo);
    const wanted = await github.effectiveTree(tracked.repo, branch, tracked.path);

    const writes = [];
    for (const [filePath, sha] of wanted) {
        const file = path.join(plugin.dir, filePath);
        let onDisk;
        try {
            onDisk = fs.readFileSync(file);
        } catch {
            writes.push(filePath);
            continue;
        }
        if (github.blobSha(onDisk) !== sha) {
            writes.push(filePath);
        }
    }

    const deletes = exports.filesUnder(plugin.dir).filter((filePath) => !wanted.has(filePath));

    if (!writes.length && !deletes.length) {
        return { ...plugin, source: `${tracked.repo}@${branch}`, status: 'current' };
    }

    return {
        ...plugin,
        source: `${tracked.repo}@${branch}`,
        tracked,
        branch,
        plan: { writes, deletes },
        status: 'track',
    };
};

exports.filesUnder = function (root, prefix = '') {
    let entries;
    try {
        entries = fs.readdirSync(root, { withFileTypes: true });
    } catch {
        return [];
    }

    return entries.flatMap((entry) =>
        entry.isDirectory()
            ? exports.filesUnder(path.join(root, entry.name), prefix + entry.name + '/')
            : [prefix + entry.name],
    );
};

// Decides what happens to one plugin, without touching it. Every path out of
// here that isn't 'update' or 'track' leaves the plugin exactly as it was.
exports.inspect = async function (plugin) {
    const tracked = exports.TRACKED[plugin.slug];
    if (tracked) {
        return exports.inspectTracked(plugin, tracked);
    }

    if (!plugin.installed) {
        return { ...plugin, status: 'no-version' };
    }

    const info = await api.pluginInfo(plugin.slug);
    if (!info) {
        return { ...plugin, status: 'not-on-org' };
    }

    const latest = info.version;
    if (exports.compareVersions(latest, plugin.installed) <= 0) {
        // Equal is the common case. Newer-than-.org happens when a plugin is
        // bundled from somewhere else, and must never be "updated" backwards.
        return { ...plugin, latest, status: exports.compareVersions(plugin.installed, latest) > 0 ? 'ahead' : 'current' };
    }

    // Checksums for what's installed. Their absence is the whole safety net
    // for plugins bundled from outside wordpress.org: no proof, no update.
    const oldSums = await api.pluginChecksums(plugin.slug, plugin.installed);
    if (!oldSums) {
        return { ...plugin, latest, status: 'unverifiable' };
    }

    const newSums = await api.pluginChecksums(plugin.slug, latest);
    if (!newSums) {
        return { ...plugin, latest, status: 'unverifiable' };
    }

    const paths = [...new Set([...Object.keys(oldSums), ...Object.keys(newSums)])];
    const disk = files.hashDisk(plugin.dir, paths);

    const plan = planner.plan({
        oldSums: exports.acceptedHashes(oldSums, disk),
        newSums: exports.acceptedHashes(newSums, disk),
        disk,
        ignored: files.ignoredPaths(plugin.dir, paths),
    });

    // All or nothing. Updating the files that happen to be clean would leave a
    // plugin running a mix of two releases, which is worse than not updating.
    if (plan.conflicts.length || plan.localEdits.length || plan.absent.length) {
        return { ...plugin, latest, status: 'modified', plan };
    }

    return { ...plugin, latest, status: 'update', plan };
};

const SKIP_TEXT = {
    'not-on-org': 'not published on wordpress.org, so there is nothing to compare it against',
    'no-version': 'no Version header, so the installed release is unknown',
    ahead: 'newer than the version wordpress.org publishes, so it is left alone',
    unverifiable: 'wordpress.org publishes no checksums for the installed build',
    modified: 'differs from the release wordpress.org published, so it was not replaced',
};

exports.report = function (results) {
    const updated = results.filter((r) => r.status === 'update');
    const followed = results.filter((r) => r.status === 'track');
    const current = results.filter((r) => r.status === 'current');
    const skipped = results.filter((r) => SKIP_TEXT[r.status]);

    const lines = ['Updates bundled plugins.', ''];

    for (const plugin of updated) {
        lines.push(`- **${plugin.slug}** ${plugin.installed} → ${plugin.latest}`);
    }

    for (const plugin of followed) {
        const changed = plugin.plan.writes.length;
        const removed = plugin.plan.deletes.length;
        lines.push(
            `- **${plugin.slug}** follows \`${plugin.source}\` — ${changed} file(s) changed` +
            (removed ? `, ${removed} removed` : ''),
        );
    }

    if (!updated.length && !followed.length) {
        lines.push('No plugin updates were available.');
    }

    if (followed.length) {
        lines.push('', 'Plugins that follow a branch are not published on wordpress.org, so');
        lines.push('there are no checksums to check them against. The diff below is the');
        lines.push('review.');
    }

    if (skipped.length) {
        lines.push('', `### ${skipped.length} plugin(s) left untouched`, '');
        lines.push('These are not updated automatically. Nothing here is applied.', '');

        for (const plugin of skipped) {
            lines.push(`- \`${plugin.slug}\` (${plugin.installed || 'unknown'}) — ${SKIP_TEXT[plugin.status]}`);
        }
    }

    if (current.length) {
        lines.push('', `${current.length} plugin(s) already up to date.`);
    }

    return lines.join('\n');
};

exports.run = async function (options) {
    const pluginsRoot = path.join(options.root, 'wp-content', 'plugins');
    const plugins = exports.discover(pluginsRoot);

    console.log(`Found ${plugins.length} plugin(s) in ${pluginsRoot}.`);

    const results = [];
    for (const plugin of plugins) {
        const result = await exports.inspect(plugin);
        console.log(`  ${plugin.slug} ${plugin.installed || '?'} — ${result.status}`);
        results.push(result);
    }

    const report = exports.report(results);
    const changing = results.filter((result) => result.status === 'update' || result.status === 'track');

    if (!changing.length || options.dryRun) {
        return { updated: false, report, outputs: { plugins: changing.length } };
    }

    const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-plugins-'));
    try {
        for (const plugin of changing) {
            const source = plugin.status === 'track'
                ? await github.materialize(plugin.tracked.repo, plugin.branch, plugin.tracked.path, fs.mkdtempSync(path.join(workDir, 'repo-')))
                : await api.downloadPlugin(plugin.slug, plugin.latest, workDir);

            files.apply(plugin.dir, source, plugin.plan);
            console.log(`Updated ${plugin.slug} from ${plugin.source || 'wordpress.org'}.`);
        }
    } finally {
        fs.rmSync(workDir, { recursive: true, force: true });
    }

    return { updated: true, report, outputs: { plugins: changing.length } };
};
