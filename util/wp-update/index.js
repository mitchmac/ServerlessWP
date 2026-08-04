// Keeps the bundled WordPress up to date.
//
//   node util/wp-update                     update wp/ to the latest release
//   node util/wp-update --plugins           update bundled wordpress.org plugins
//   node util/wp-update --themes            report on themes, change nothing
//   node util/wp-update --dry-run           report what would change, touch nothing
//
// All three are safe to run in a copy of this repository: a file is
// only written when wordpress.org's checksums prove the copy on disk still
// holds what was published. The two run separately and produce separate pull
// requests, so a plugin update never rides along with a core one.
//
//   core.js      the WordPress files themselves
//   plugins.js   bundled plugins from wordpress.org
//   themes.js    themes, which are only ever reported on
//   plan.js      the rules deciding what gets written or deleted
//   files.js     reading and writing the working copy
//   versions.js  comparing header versions
//   api.js       where every checksum comes from

const fs = require('fs');
const path = require('path');

const REPO_ROOT = path.resolve(__dirname, '..', '..');

function parseArgs(argv) {
    const options = {
        root: path.join(REPO_ROOT, 'wp'),
        mode: 'core',
        target: undefined,
        dryRun: false,
        report: undefined,
    };

    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i];
        if (arg === '--plugins') {
            options.mode = 'plugins';
        } else if (arg === '--themes') {
            options.mode = 'themes';
        } else if (arg === '--dry-run') {
            options.dryRun = true;
        } else if (arg === '--root') {
            options.root = path.resolve(argv[++i]);
        } else if (arg === '--target') {
            options.target = argv[++i];
        } else if (arg === '--report') {
            options.report = path.resolve(argv[++i]);
        } else {
            throw new Error(`unknown argument: ${arg}`);
        }
    }

    return options;
}

function setOutputs(values) {
    if (!process.env.GITHUB_OUTPUT) {
        return;
    }

    const lines = Object.entries(values).map(([key, value]) => `${key}=${value}`);
    fs.appendFileSync(process.env.GITHUB_OUTPUT, lines.join('\n') + '\n');
}

const MODES = {
    core: './core.js',
    plugins: './plugins.js',
    themes: './themes.js',
};

async function main(argv) {
    const options = parseArgs(argv);
    const result = await require(MODES[options.mode]).run(options);

    if (result.report) {
        console.log('\n' + result.report + '\n');
        if (options.report) {
            fs.writeFileSync(options.report, result.report + '\n');
        }
        // The theme report produces no commit and so no pull request. Writing to
        // the job summary is the only way its findings reach anyone in CI.
        if (process.env.GITHUB_STEP_SUMMARY) {
            fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, result.report + '\n');
        }
    }

    if (options.dryRun) {
        console.log('--dry-run: nothing was written.');
    }

    setOutputs({ updated: String(result.updated && !options.dryRun), ...result.outputs });
}

if (require.main === module) {
    main(process.argv.slice(2)).catch((error) => {
        console.error(error.message);
        process.exit(1);
    });
}
