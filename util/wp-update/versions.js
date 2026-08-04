// Comparing the version strings plugins and themes put in their headers.
//
// These are not semver and can't be assumed to be. What matters most here is
// that nothing ever reads as an upgrade unless it clearly is: a wrong answer
// sends the plugin update looking to overwrite files with an older release.

// Ranks below a plain release, so 3.0.0-rc.7 sorts under 3.0.0. An unknown
// word ranks lowest: if we can't place it, we must not call it an upgrade.
const TAG_RANK = { dev: -4, alpha: -3, a: -3, beta: -2, b: -2, rc: -1, pl: 1, p: 1 };

function chunks(version) {
    return String(version)
        .toLowerCase()
        .replace(/[-_+]/g, '.')
        .replace(/([a-z]+)/g, '.$1.')
        .split('.')
        .filter((chunk) => chunk !== '');
}

function isPreRelease(chunk) {
    return !/^\d+$/.test(chunk) && (TAG_RANK[chunk] ?? -5) < 0;
}

// Returns 1 if a is newer than b, -1 if older, 0 if equal.
exports.compareVersions = function (a, b) {
    const left = chunks(a);
    const right = chunks(b);

    for (let i = 0; i < Math.max(left.length, right.length); i++) {
        const x = left[i];
        const y = right[i];

        // One version ran out of chunks. A trailing pre-release tag makes it
        // the older of the two; a trailing number makes it the newer.
        if (x === undefined) {
            return isPreRelease(y) ? 1 : -1;
        }
        if (y === undefined) {
            return isPreRelease(x) ? -1 : 1;
        }

        const xNumeric = /^\d+$/.test(x);
        const yNumeric = /^\d+$/.test(y);

        if (xNumeric && yNumeric) {
            if (Number(x) !== Number(y)) {
                return Number(x) > Number(y) ? 1 : -1;
            }
            continue;
        }

        // A number outranks any tag, so 3.0.0 beats 3.0.0-rc.7.
        if (xNumeric !== yNumeric) {
            return xNumeric ? 1 : -1;
        }

        const xRank = TAG_RANK[x] ?? -5;
        const yRank = TAG_RANK[y] ?? -5;
        if (xRank !== yRank) {
            return xRank > yRank ? 1 : -1;
        }
        if (x !== y) {
            return x > y ? 1 : -1;
        }
    }

    return 0;
};

// Reads one field out of a WordPress file header, which may be laid out as a
// plain comment or a doc block:
//
//   Version: 1.0.2
//    * Version:           1.0.2
exports.headerField = function (text, field) {
    const match = new RegExp(`^[ \\t/*#@]*${field}:(.*)$`, 'mi').exec(text);
    return match ? match[1].trim() : undefined;
};
