<p align="center"><img src="https://serverlesswp.com/wp-content/serverlesswp.png" alt="ServerlessWP"></p>

# ServerlessWP

**Real WordPress, without maintaining a WordPress server.**

ServerlessWP runs a normal WordPress site on Vercel, Netlify, or AWS Lambda. You keep the familiar admin, themes, and plugins, but deploy from Git and let on-demand functions handle requests.

For smaller content sites, it does something unusual: WordPress's database can live as a file in cloud storage instead of on a database server. On Vercel, one click creates that storage automatically, and every preview branch gets its own database. Built-in storage support can also keep uploads and generated files safe after a function shuts down.

Need heavier editing or write traffic? Connect MySQL instead.

Using a fork? Follow upstream releases and fixes at [github.com/mitchmac/serverlesswp](https://github.com/mitchmac/serverlesswp).

![WordPress 7.1](https://img.shields.io/badge/version-7.1-blue?logo=wordpress&labelColor=white&logoColor=black) ![PHP 8.3.33](https://img.shields.io/badge/version-8.3.33-blue?logo=php&labelColor=white)

## Deploy on Vercel

The fastest setup runs everything on Vercel. The deploy button creates and connects a private Vercel Blob store for the WordPress database—no separate database account or credentials to configure.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&project-name=serverlesswp&repository-name=serverlesswp&stores=%5B%7B%22type%22%3A%22blob%22%2C%22access%22%3A%22private%22%2C%22envVarPrefix%22%3A%22SQLITE%22%7D%5D)

After deployment, open the site and complete the normal WordPress installation.

Other options:

- **[Temporary Vercel demo](https://serverlesswp.com/vercel-deploy):** uses a temporary SQLite database on S3 that expires after a few days.
- **[Netlify](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp):** use SQLite on S3 or connect MySQL.
- **AWS Lambda:** run `npm install && serverless deploy` to deploy with the Serverless Framework.

## Is it a good fit?

**ServerlessWP is experimental and is intended primarily for content sites.**

It is a good fit for personal blogs, documentation, portfolios, marketing and small-business sites, development and staging sites, and WordPress used as a headless CMS.

The SQLite option works best for sites that are read often but updated by only one or two people at a time. Use MySQL for ecommerce, memberships, forums, busy forms, or several simultaneous editors.

## How it works

PHP and WordPress are packaged inside a serverless function using [serverlesswp-node](https://github.com/mitchmac/serverlesswp-node). Static assets are served directly by the hosting platform, while dynamic requests run WordPress.

With SQLite, each request works with a local copy of the database. If WordPress changes it, ServerlessWP writes it back to Vercel Blob or S3. Conditional writes prevent an older function from silently overwriting a newer update; a conflicting request fails instead. See the [SQLite and S3 diagram](https://github.com/mitchmac/ServerlessWP/wiki/How-does-SQLite-with-S3-work-with-ServerlessWP%3F) for a deeper look.

The optional stream wrapper applies the same idea to writable files. WordPress and plugins use normal file functions, while selected paths under `wp-content` are transparently stored in Vercel Blob or S3.

## Choose a database

| SQLite in cloud storage | MySQL |
|---|---|
| No database server to provision | Requires a database service |
| Runs on demand and fits free tiers well | Usually runs continuously |
| Limited concurrent updates | Handles many concurrent updates |
| Some plugin incompatibility | Broadest plugin compatibility |
| Best for content sites and previews | Best for busy or application-like sites |

### SQLite with Vercel Blob

This is the easiest option. The Vercel deploy button creates a private store and connects it automatically. Vercel can authenticate with a short-lived OIDC token, so there is no long-lived database password to copy.

For an existing Vercel project, create a **private** Blob store from the Storage tab and connect it. These variables control store selection:

| Variable | Purpose |
|---|---|
| `BLOB_STORE_ID` | Store connected by Vercel |
| `SQLITE_BLOB_STORE_ID` | Optional explicit database store, useful when another Blob store holds uploads |
| `SQLITE_BLOB_READ_WRITE_TOKEN` | Optional static-token fallback |
| `SQLITE_BLOB_PATHNAME` | Optional database name; defaults to `wp-sqlite` |

Keep the database store separate from public media storage. The database must remain private and uncached; uploads normally need public reads and CDN caching.

### SQLite with S3

S3 works on Vercel, Netlify, or AWS Lambda, including with compatible services such as Cloudflare R2. Create a private bucket near the serverless functions and configure:

| Variable | Purpose |
|---|---|
| `SQLITE_S3_BUCKET` | Bucket name |
| `SQLITE_S3_API_KEY` | Access key |
| `SQLITE_S3_API_SECRET` | Secret key |
| `SQLITE_S3_REGION` | Bucket region |
| `SQLITE_S3_ENDPOINT` | Optional endpoint for an S3-compatible service |
| `SQLITE_S3_FORCE_PATH_STYLE` | Optional path-style addressing |

### MySQL

Use MySQL when you need more concurrent writes or maximum plugin compatibility. [TiDB](https://www.pingcap.com/tidb-cloud-serverless/) is one compatible hosted option with a free tier.

| Variable | Purpose |
|---|---|
| `DATABASE` | Database name |
| `USERNAME` | Database user |
| `PASSWORD` | Database password |
| `HOST` | Database host |
| `TABLE_PREFIX` | Optional WordPress table prefix |

ServerlessWP selects the most explicitly configured database:

1. MySQL when `DATABASE`, `USERNAME`, `PASSWORD`, and `HOST` are all set
2. SQLite with S3 when `SQLITE_S3_BUCKET` is set
3. SQLite with Vercel Blob when a Blob store or database token is configured on Vercel
4. Otherwise, the setup page is shown

When changing environment variables in [Vercel](https://vercel.com/docs/concepts/projects/environment-variables) or [Netlify](https://docs.netlify.com/environment-variables/overview/), redeploy the project afterward.

## Persist uploads and generated files

A serverless function's local files do not last forever. ServerlessWP includes an optional PHP stream wrapper that can transparently store writable `wp-content` files in S3 or Vercel Blob. It covers uploads, image sizes, cache assets, and files generated by plugins while keeping deployed plugins, themes, PHP, logs, and databases local by default.

Enable it by selecting a provider:

```text
WP_STREAM_PROVIDER=s3
```

or:

```text
WP_STREAM_PROVIDER=vercel-blob
```

An S3 setup can reuse the credentials already configured for the SQLite database. Vercel Blob media storage should normally use a separate store from the private database. See the [stream-wrapper documentation](packages/serverlesswp-stream-wrapper/README.md) for provider variables, routing rules, serving policy, and limitations.

## Customize WordPress

WordPress lives in [`wp/`](wp/). Add themes or plugins under `wp/wp-content`, commit them, and push to deploy the changes.

Plugins can set CDN-friendly `s-maxage` headers; see the caching documentation for [Vercel](https://vercel.com/docs/edge-network/caching) or [Netlify](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers).

Platform routing lives in [`vercel.json`](vercel.json) and [`netlify.toml`](netlify.toml). The request handler is [`api/index.js`](api/index.js), where ServerlessWP runtime plugins and request or response customization are registered.

## Getting help

Need help getting started? [Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions) or [send a chat](https://serverlesswp.com/chat).

## Contributing

Using ServerlessWP and [reporting problems](https://github.com/mitchmac/ServerlessWP/issues) is a great way to help. Contributions and shared deployment experiences are welcome.

## License

GNU General Public License v3.0
