// Reading a plugin straight out of a GitHub repository. Some plugins are bundled
// from GitHub, following the repo's default branch; with nothing to verify a
// copy against, wp/ mirrors the branch and the PR diff is the review.
//
// A monorepo plugin can span directories: sqlite-database-integration symlinks
// wp-includes/database to ../../mysql-on-sqlite/src/, turning 20 files into the
// 46 a site runs. Git stores that symlink as a blob holding the target path, so
// it's followed here rather than written out as a text file.

const fs = require('fs');
const path = require('path');
const posix = path.posix;
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const API = 'https://api.github.com';
const SYMLINK_MODE = '120000';

function headers() {
    const value = { accept: 'application/vnd.github+json' };
    // Actions provides a token that raises the rate limit; unauthenticated is
    // fine for the few calls made here.
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

// Git's name for a file's contents, so a local file compares to a tree entry
// without downloading anything.
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

// Every file the plugin is made of, keyed by path within the plugin, valued by
// git's content hash. Symlinked directories are spliced in from where they
// point, so the result is the unpacked plugin, not just its own directory.
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

// Checks the repo out and returns the plugin directory with symlinks turned into
// real files, ready to land in wp/. Clones rather than downloading a tarball:
// GitHub's tarballs honour export-ignore, and sqlite-database-integration's
// .gitattributes export-ignores the whole plugin, leaving a 15KB readme. A clone
// ignores that and gets the files.
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

    // -L follows symlinks instead of copying them, so the result stands alone
    // once the rest of the repo is gone.
    fs.mkdirSync(materialized, { recursive: true });
    execFileSync('cp', ['-rL', source + '/.', materialized], { stdio: 'pipe' });

    return materialized;
};
