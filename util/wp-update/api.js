// Everything the core update knows about WordPress comes from
// wordpress.org. The checksums endpoint returns every file a release ships --
// 3,945 paths for 7.0.2, bundled themes included -- and that list is byte-exact
// against the release zip. So it is the manifest: a path wordpress.org lists is
// WordPress's, and a path it doesn't list belongs to whoever cloned this repo.
//
// wp-config.php is absent from it (only wp-config-sample.php ships), so the
// file most worth protecting is out of scope without special-casing.

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const STABLE_CHECK = 'https://api.wordpress.org/core/stable-check/1.0/';
const CHECKSUMS = 'https://api.wordpress.org/core/checksums/1.0/';
const RELEASE = 'https://downloads.wordpress.org/release/';
const PLUGIN_INFO = 'https://api.wordpress.org/plugins/info/1.2/';
const PLUGIN_CHECKSUMS = 'https://downloads.wordpress.org/plugin-checksums/';
const PLUGIN_DOWNLOAD = 'https://downloads.wordpress.org/plugin/';
const THEME_INFO = 'https://api.wordpress.org/themes/info/1.2/';

async function getJson(url) {
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }
    return response.json();
}

async function download(url, file) {
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }
    fs.writeFileSync(file, Buffer.from(await response.arrayBuffer()));
}

function unzip(zipPath, into) {
    try {
        execFileSync('unzip', ['-q', '-o', zipPath, '-d', into], { stdio: 'pipe' });
    } catch (error) {
        throw new Error(`could not unzip ${zipPath}: ${error.message}`);
    }
}

// The one version stable-check marks 'latest'. It lists all 800+ releases with
// a status each, so anything but exactly one 'latest' means the shape changed
// and we should stop rather than guess.
exports.latestVersion = async function () {
    const versions = await getJson(STABLE_CHECK);
    const latest = Object.keys(versions).filter((version) => versions[version] === 'latest');

    if (latest.length !== 1) {
        throw new Error(`stable-check named ${latest.length} latest versions, expected 1`);
    }

    return latest[0];
};

// Map of release-relative path -> md5.
exports.checksums = async function (version, locale = 'en_US') {
    const body = await getJson(`${CHECKSUMS}?version=${encodeURIComponent(version)}&locale=${encodeURIComponent(locale)}`);

    // A version wordpress.org never published answers 200 with checksums: false.
    if (!body || typeof body.checksums !== 'object' || body.checksums === null) {
        throw new Error(`no checksums published for WordPress ${version} (${locale})`);
    }

    return body.checksums;
};

// Downloads and unpacks a release, returning the path to its wordpress/
// directory. Extraction shells out to unzip, which ubuntu-latest has and Node
// has no equivalent of.
exports.downloadRelease = async function (version, workDir) {
    const zipPath = path.join(workDir, `wordpress-${version}.zip`);
    const url = `${RELEASE}wordpress-${version}.zip`;

    fs.mkdirSync(workDir, { recursive: true });
    await download(url, zipPath);
    unzip(zipPath, workDir);

    const root = path.join(workDir, 'wordpress');
    if (!fs.existsSync(root)) {
        throw new Error(`${url} did not contain a wordpress/ directory`);
    }

    return root;
};

// Tells a wordpress.org plugin from a custom or premium one: the latter answer
// 200 with {"error":"Plugin not found."} rather than a status code. Returns
// null for anything not published there.
exports.pluginInfo = async function (slug) {
    const url = `${PLUGIN_INFO}?action=plugin_information&request[slug]=${encodeURIComponent(slug)}`;
    const response = await fetch(url);

    if (response.status === 404) {
        return null;
    }
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }

    const body = await response.json();
    if (!body || body.error || !body.version) {
        return null;
    }

    return body;
};

// Per-file md5 for one published plugin release, keyed by path relative to the
// plugin directory. Null when wordpress.org has never published that version,
// which is the signal that a bundled plugin came from somewhere else.
//
// A value is a string, or an array of them when a release was re-tagged and
// more than one build is accepted -- three of tidb-compatibility 1.0.2's four
// files are like that. Callers resolve arrays against what is on disk; see
// acceptedHashes in plugins.js.
exports.pluginChecksums = async function (slug, version) {
    const url = `${PLUGIN_CHECKSUMS}${encodeURIComponent(slug)}/${encodeURIComponent(version)}.json`;
    const response = await fetch(url);

    if (response.status === 404) {
        return null;
    }
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }

    const body = await response.json();
    if (!body || typeof body.files !== 'object' || body.files === null) {
        return null;
    }

    const sums = {};
    for (const [filePath, hashes] of Object.entries(body.files)) {
        if (typeof hashes.md5 === 'string' || Array.isArray(hashes.md5)) {
            sums[filePath] = hashes.md5;
        }
    }

    return sums;
};

// The published release of a theme, or null if wordpress.org doesn't carry it.
// Unlike the plugin endpoint this answers 404 for an unknown slug rather than
// 200 with an error body.
//
// There is no theme equivalent of pluginChecksums: theme-checksums answers 404
// for every theme, bundled or not. That absence is why themes are only ever
// reported and never written.
exports.themeInfo = async function (slug) {
    const url = `${THEME_INFO}?action=theme_information&request[slug]=${encodeURIComponent(slug)}`;
    const response = await fetch(url);

    if (response.status === 404) {
        return null;
    }
    if (!response.ok) {
        throw new Error(`${url} returned ${response.status}`);
    }

    const body = await response.json();
    if (!body || body.error || !body.version) {
        return null;
    }

    return body;
};

// Unpacks a plugin release and returns the directory holding its files.
exports.downloadPlugin = async function (slug, version, workDir) {
    const into = path.join(workDir, `${slug}-${version}`);
    const zipPath = path.join(workDir, `${slug}.${version}.zip`);
    const url = `${PLUGIN_DOWNLOAD}${encodeURIComponent(slug)}.${encodeURIComponent(version)}.zip`;

    fs.mkdirSync(into, { recursive: true });
    await download(url, zipPath);
    unzip(zipPath, into);

    // The zip wraps everything in a directory named after the plugin.
    const root = path.join(into, slug);
    if (!fs.existsSync(root)) {
        throw new Error(`${url} did not contain a ${slug}/ directory`);
    }

    return root;
};
