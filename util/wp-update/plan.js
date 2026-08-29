// Decides what an update does to each file, with no I/O so the rules can be
// tested directly. This deletes files in other people's repos, so the bias is
// always to leave a file alone and report it. Works on paths, never trees, so
// anything the owner added under wp-content survives by never being named; a
// file is only touched when wordpress.org's checksum for the on-disk version
// proves the copy still holds what WordPress shipped.

// Ignored paths are dropped first: git won't carry them into a PR, so writing
// them would make invisible changes and a report that repeats every run.
exports.plan = function ({ oldSums, newSums, disk, ignored = new Set() }) {
    const result = {
        // Copy from the release into the working copy.
        writes: [],
        // Remove: WordPress dropped the file and the copy still has it verbatim.
        deletes: [],
        // Left alone and reported, { path, kind } each: the update that didn't happen.
        conflicts: [],
        // Core files edited locally that this release doesn't touch. Nothing was
        // skipped on their account, but worth reporting anyway.
        localEdits: [],
        // Files this release ships that the copy lacks. Count only -- dropping
        // bundled plugins and themes is normal and naming all 48 would bury the rest.
        absent: [],
        unchanged: 0,
    };

    const paths = new Set([...Object.keys(oldSums), ...Object.keys(newSums)]);

    for (const filePath of [...paths].sort()) {
        if (ignored.has(filePath)) {
            continue;
        }

        const before = oldSums[filePath];
        const after = newSums[filePath];
        const onDisk = disk[filePath];

        if (after && !before) {
            // A path this release adds. Without a previous checksum an existing
            // file can't be proven ours, so an occupied path is the owner's.
            if (onDisk === undefined) {
                result.writes.push(filePath);
            } else if (onDisk === after) {
                result.unchanged++;
            } else {
                result.conflicts.push({ path: filePath, kind: 'occupied' });
            }
            continue;
        }

        if (after && before) {
            if (onDisk === after) {
                result.unchanged++;
            } else if (onDisk === undefined) {
                // Absent files only count when this release changes them.
                before === after ? result.unchanged++ : result.absent.push(filePath);
            } else if (onDisk === before) {
                result.writes.push(filePath);
            } else if (before === after) {
                result.localEdits.push(filePath);
            } else {
                result.conflicts.push({ path: filePath, kind: 'modified' });
            }
            continue;
        }

        // Dropped by this release.
        if (onDisk === undefined) {
            result.unchanged++;
        } else if (onDisk === before) {
            result.deletes.push(filePath);
        } else {
            result.conflicts.push({ path: filePath, kind: 'modified-removed' });
        }
    }

    return result;
};

const CONFLICT_TEXT = {
    occupied: 'WordPress now ships this path and the copy already has a different file there',
    modified: 'changed locally, so the update was not applied',
    'modified-removed': 'changed locally, so it was kept even though WordPress dropped it',
};

// The pull request body. Conflicts come first: the part that didn't happen and
// the only part needing a decision. Local edits get their own section.
exports.report = function (from, to, plan) {
    const lines = [`Updates the bundled WordPress files from ${from} to ${to}.`, ''];

    lines.push(`- ${plan.writes.length} file(s) added or updated`);
    lines.push(`- ${plan.deletes.length} file(s) removed`);
    if (plan.absent.length) {
        lines.push(`- ${plan.absent.length} file(s) this release changed are not in this copy and were left out`);
    }

    if (plan.conflicts.length) {
        lines.push('', `### ${plan.conflicts.length} file(s) left untouched`, '');
        lines.push('These were not updated, because doing so would overwrite something this');
        lines.push('repository owns. Nothing here is applied automatically.', '');

        for (const conflict of plan.conflicts) {
            lines.push(`- \`${conflict.path}\` — ${CONFLICT_TEXT[conflict.kind]}`);
        }
    }

    if (plan.localEdits.length) {
        lines.push('', `### ${plan.localEdits.length} locally modified core file(s)`, '');
        lines.push(`These differ from what WordPress ${to} ships. This release doesn't change`);
        lines.push('them, so the update skipped nothing, but they will not match upstream and');
        lines.push('a future release touching them will report a conflict.', '');

        for (const filePath of plan.localEdits) {
            lines.push(`- \`${filePath}\``);
        }
    }

    return lines.join('\n');
};
