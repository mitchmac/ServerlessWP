// Reading and writing the working copy, for the core and plugin updates. Nothing
// here decides what happens (plan.js does); every function takes an explicit
// path list and never walks a directory, which keeps owner-added files invisible.

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

// Marks a path occupied by something that isn't a plain file. Never equals a
// real md5, so plan.js treats it as content it doesn't own.
const NOT_A_FILE = 'not-a-file';

exports.NOT_A_FILE = NOT_A_FILE;

function md5(file) {
    return crypto.createHash('md5').update(fs.readFileSync(file)).digest('hex');
}

exports.md5 = md5;

// Hashes only the given paths; everything else under root is never read.
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

// Paths git won't carry into a PR; writing them makes invisible changes.
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
        // Exit 1 means nothing ignored (the common case). Anything else -- no
        // git, not a repo -- and we treat every path as tracked; the checksum
        // rules still guard each file.
        if (error.status !== 1) {
            console.warn(`git check-ignore did not run (${error.message.trim()}); assuming nothing is ignored.`);
        }
        return new Set();
    }

    return new Set(stdout.split('\0').filter(Boolean));
};

// Removes parent directories only once emptied, so one still holding anything
// -- including an owner-added file -- stays.
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
