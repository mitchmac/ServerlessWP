// Reading and writing the working copy, shared by the core and plugin updates.
//
// Nothing here decides what should happen -- plan.js does that -- so every
// function takes an explicit list of paths and never walks a directory looking
// for work. That is what keeps a plugin or theme the owner added invisible to
// an update.

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

// Stands in for a path occupied by something that isn't a plain file. It can
// never equal a real md5, so plan.js treats it as content it doesn't own.
const NOT_A_FILE = 'not-a-file';

exports.NOT_A_FILE = NOT_A_FILE;

function md5(file) {
    return crypto.createHash('md5').update(fs.readFileSync(file)).digest('hex');
}

exports.md5 = md5;

// Hashes only the paths given. Everything else under root is never read, let
// alone written.
exports.hashDisk = function (root, paths) {
    const disk = {};

    for (const filePath of paths) {
        const file = path.join(root, filePath);
        let stat;
        try {
            stat = fs.lstatSync(file);
        } catch {
            continue;
        }
        disk[filePath] = stat.isFile() && !stat.isSymbolicLink() ? md5(file) : NOT_A_FILE;
    }

    return disk;
};

// Paths git won't carry into a pull request. Writing them would produce
// invisible changes and a report that repeats on every run.
exports.ignoredPaths = function (root, paths) {
    if (!paths.length) {
        return new Set();
    }

    let stdout;
    try {
        stdout = execFileSync('git', ['check-ignore', '-z', '--stdin'], {
            cwd: root,
            input: paths.join('\0'),
            maxBuffer: 64 * 1024 * 1024,
        }).toString();
    } catch (error) {
        // Exit 1 is "nothing was ignored", the common case. Anything else --
        // no git, not a repository -- means we can't tell. The update then
        // treats every path as tracked, which is the behaviour without a
        // .gitignore at all; the checksum rules still guard each file.
        if (error.status !== 1) {
            console.warn(`git check-ignore did not run (${error.message.trim()}); assuming nothing is ignored.`);
        }
        return new Set();
    }

    return new Set(stdout.split('\0').filter(Boolean));
};

// Directories are only removed once the update has emptied them, so a
// directory still holding anything -- including a file the owner added --
// stays.
function removeEmptyParents(root, filePath) {
    let dir = path.dirname(path.join(root, filePath));

    while (dir.startsWith(root + path.sep) && fs.readdirSync(dir).length === 0) {
        fs.rmdirSync(dir);
        dir = path.dirname(dir);
    }
}

exports.apply = function (root, sourceRoot, plan) {
    for (const filePath of plan.writes) {
        const destination = path.join(root, filePath);
        fs.mkdirSync(path.dirname(destination), { recursive: true });
        fs.copyFileSync(path.join(sourceRoot, filePath), destination);
    }

    for (const filePath of plan.deletes) {
        fs.rmSync(path.join(root, filePath));
        removeEmptyParents(root, filePath);
    }
};
