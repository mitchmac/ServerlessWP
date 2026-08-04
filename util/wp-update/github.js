// Reading a plugin straight out of a GitHub repository.
//
// Some plugins are bundled from GitHub rather than wordpress.org, and follow
// the repository's default branch. There is nothing to verify a copy against --
// no published checksums, no releases to compare -- so the copy in wp/ mirrors
// the branch and the pull request diff is the review.
//
// A plugin inside a monorepo can be assembled out of more than one directory:
// sqlite-database-integration keeps wp-includes/database as a symlink to
// ../../mysql-on-sqlite/src/, so 20 files in the plugin directory become the 46
// a site actually runs. Git records that symlink as a blob holding the target
// path, so it is followed here rather than written out as a file full of text.

const fs = require('fs');
const path = require('path');
const posix = path.posix;
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const API = 'https://api.github.com';
const SYMLINK_MODE = '120000';

function headers() {
    const value = { accept: 'application/vnd.github+json' };
    // Actions provides a token; using it raises the rate limit and costs
    // nothing. Unauthenticated works fine for the handful of calls made here.
    if (process.env.GITHUB_TOKEN) {
        value.authorization = `Bearer ${process.env.GITHUB_TOKEN}`;
    }
    return value;
}

async function getJson(url) {
    const response = await fetch(url, { headers: headers() });
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }
    return response.json();
}

// How git names a file's contents, so a local file can be compared to a tree
// entry without downloading anything.
exports.blobSha = function (buffer) {
    const hash = crypto.createHash('sha1');
    hash.update(`blob ${buffer.length}\0`);
    hash.update(buffer);
    return hash.digest('hex');
};

exports.defaultBranch = async function (repo) {
    const info = await getJson(`${API}/repos/${repo}`);
    if (!info.default_branch) {
        throw new Error(`${repo} reported no default branch`);
    }
    return info.default_branch;
};

// Every file the plugin is made of, keyed by its path inside the plugin and
// valued by git's hash of its contents. Symlinked directories are spliced in
// from wherever they point, so the result is what the plugin looks like once
// unpacked rather than what its own directory holds.
exports.effectiveTree = async function (repo, ref, subdir) {
    const listing = await getJson(`${API}/repos/${repo}/git/trees/${ref}?recursive=1`);

    if (listing.truncated) {
        throw new Error(`the file listing for ${repo}@${ref} was truncated`);
    }

    const files = new Map();

    const expand = async (prefix, relativeTo) => {
        for (const entry of listing.tree) {
            if (entry.type !== 'blob' || !entry.path.startsWith(prefix + '/')) {
                continue;
            }

            const within = posix.join(relativeTo, entry.path.slice(prefix.length + 1));

            if (entry.mode !== SYMLINK_MODE) {
                files.set(within, entry.sha);
                continue;
            }

            // The blob holds the target path, relative to the link's own
            // directory.
            const blob = await getJson(entry.url);
            const target = Buffer.from(blob.content, 'base64').toString().replace(/\/$/, '');
            const resolved = posix.normalize(posix.join(posix.dirname(entry.path), target));
            await expand(resolved, within);
        }
    };

    await expand(subdir, '');

    if (!files.size) {
        throw new Error(`${repo}@${ref} has no files under ${subdir}`);
    }

    return files;
};

// Checks the repository out and returns the plugin directory with its symlinks
// turned into real files, which is what has to land in wp/.
//
// This clones rather than downloading a tarball. GitHub builds tarballs with
// git archive, which honours export-ignore, and sqlite-database-integration's
// .gitattributes carries "/packages export-ignore" -- the whole plugin. Its
// tarball is 15KB of readme. A clone ignores export-ignore and gets the files.
exports.materialize = async function (repo, ref, subdir, workDir) {
    const checkout = path.join(workDir, 'repo');
    const materialized = path.join(workDir, 'plugin');

    execFileSync('git', [
        'clone', '--depth', '1', '--branch', ref, '--quiet',
        `https://github.com/${repo}.git`, checkout,
    ], { stdio: 'pipe' });

    const source = path.join(checkout, subdir);
    if (!fs.existsSync(source)) {
        throw new Error(`${repo}@${ref} has no ${subdir} directory`);
    }

    // -L follows the symlinks rather than copying them, so the result stands
    // on its own once the rest of the repository is gone.
    fs.mkdirSync(materialized, { recursive: true });
    execFileSync('cp', ['-rL', source + '/.', materialized], { stdio: 'pipe' });

    return materialized;
};
