// Updates bundled plugins that came from wordpress.org. Stricter than core,
// because a half-updated plugin is worse than an untouched one: a plugin is only
// touched when .org can prove, file by file, that what's on disk is exactly the
// release it claims. Anything else -- not on .org, an unpublished build, a
// single edited file -- is reported and skipped whole. That's what protects
// sqlite-database-integration (bundled from GitHub) with no exclusion entry:
// .org has no checksums for it, so nothing here can prove anything about it.

const fs = require('fs');
const path = require('path');
const os = require('os');

const api = require('./api.js');
const files = require('./files.js');
const github = require('./github.js');
const planner = require('./plan.js');
const versions = require('./versions.js');

// WordPress reads plugin headers from a file's first 8KB, and so does this. The
// main file is rarely named after the plugin (WP Offload Media is in
// wordpress-s3.php), so every top-level PHP file is checked rather than guessed.
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

// Every directory under wp-content/plugins holding a plugin header. The slug is
// the directory name, which is how wordpress.org keys plugins.
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

// Collapses the multi-hash entries .org publishes for re-tagged releases to one
// hash, since plan.js compares one per path. Matching any accepted build is
// official, so resolving to the on-disk hash reads as untouched; with no match
// the first hash stands in and the file reads as locally modified.
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

// Plugins bundled from GitHub instead of wordpress.org, following the repo's
// default branch; the PR diff is the review. .org carries this slug too, at an
// older version, so omitting it would offer a downgrade rather than just stop
// updates. Listed here because nothing in the plugin's headers names its repo.
exports.TRACKED = {
    'sqlite-database-integration': {
        repo: 'WordPress/sqlite-database-integration',
        path: 'packages/plugin-sqlite-database-integration',
    },
};

// Compares the copy on disk against the repo's default branch. With no checksum
// to prove anything, whatever differs from the branch is replaced and whatever
// the branch lacks is removed.
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

// Decides what happens to one plugin without touching it. Any status but
// 'update' or 'track' leaves the plugin exactly as it was.
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
        // Newer-than-.org means bundled from elsewhere; never downgrade it.
        return { ...plugin, latest, status: exports.compareVersions(plugin.installed, latest) > 0 ? 'ahead' : 'current' };
    }

    // Checksums for what's installed. Their absence is the safety net for
    // plugins bundled from outside .org: no proof, no update.
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

    // All or nothing: updating only the clean files would leave a plugin running
    // a mix of two releases, worse than not updating.
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
