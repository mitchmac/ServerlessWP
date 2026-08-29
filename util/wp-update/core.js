// Updates the WordPress files themselves. Only paths wordpress.org lists for
// the version on disk are candidates, so plugins, themes, wp-config.php and
// anything else added to wp/ are left alone. See plan.js for the rules.

const fs = require('fs');
const path = require('path');
const os = require('os');

const api = require('./api.js');
const files = require('./files.js');
const planner = require('./plan.js');

// The version WordPress reports for itself, which is what the on-disk checksums
// are requested against.
exports.installedVersion = function (wpRoot) {
    const versionFile = path.join(wpRoot, 'wp-includes', 'version.php');
    const match = /\$wp_version\s*=\s*'([^']+)'/.exec(fs.readFileSync(versionFile, 'utf8'));

    if (!match) {
        throw new Error(`could not read a version from ${versionFile}`);
    }

    return match[1];
};

exports.run = async function (options) {
    const from = exports.installedVersion(options.root);
    const to = options.target || (await api.latestVersion());

    if (from === to) {
        console.log(`WordPress ${from} is already the latest release.`);
        return { updated: false, outputs: { from, to } };
    }

    console.log(`Planning update from WordPress ${from} to ${to}...`);

    const [oldSums, newSums] = await Promise.all([api.checksums(from), api.checksums(to)]);

    const paths = [...new Set([...Object.keys(oldSums), ...Object.keys(newSums)])];
    const plan = planner.plan({
        oldSums,
        newSums,
        disk: files.hashDisk(options.root, paths),
        ignored: files.ignoredPaths(options.root, paths),
    });

    const report = planner.report(from, to, plan);

    if (options.dryRun) {
        return { updated: false, report, outputs: { from, to, conflicts: plan.conflicts.length } };
    }

    const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-update-'));
    try {
        const releaseRoot = await api.downloadRelease(to, workDir);
        files.apply(options.root, releaseRoot, plan);
    } finally {
        fs.rmSync(workDir, { recursive: true, force: true });
    }

    console.log(`Updated ${options.root} to WordPress ${to}.`);
    return { updated: true, report, outputs: { from, to, conflicts: plan.conflicts.length } };
};
