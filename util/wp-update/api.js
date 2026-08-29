// Every checksum comes from wordpress.org. Its checksums endpoint lists exactly
// the files a release ships, so it doubles as the manifest: a path it lists is
// WordPress's, a path it doesn't belongs to whoever cloned this repo.
// wp-config.php isn't listed (only wp-config-sample.php ships), so it's out of
// scope without any special-casing.

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

// The one version stable-check marks 'latest'. Anything but exactly one means
// the response shape changed, so stop rather than guess.
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

// Downloads and unpacks a release, returning its wordpress/ directory. Shells
// out to unzip (on ubuntu-latest; Node has no equivalent).
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

// Latest published info for a wordpress.org plugin, or null for anything not
// carried there (which answers 200 with an error body, not a status code).
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

// Per-file md5 for one published plugin release, keyed by path within the
// plugin. Null when wordpress.org never published that version -- the signal
// that a bundled plugin came from elsewhere. A value is a string, or an array
// when a re-tagged release accepts more than one build; see acceptedHashes in
// plugins.js.
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

// The published release of a theme, or null if wordpress.org doesn't carry it
// (404 for an unknown slug, unlike the plugin endpoint). There's no theme
// equivalent of pluginChecksums, which is why themes are only reported on.
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
