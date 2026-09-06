# ServerlessWP

WordPress hosting is silly.

Why should a small WordPress site need a server running around the clock?

ServerlessWP [[github.com/mitchmac/serverlesswp](https://github.com/mitchmac/serverlesswp)] enables **low maintenance** and **low cost/free** WordPress hosting on Vercel, Netlify, or AWS Lambda.

WordPress runs on demand in serverless functions, with a SQLite database stored in S3 or Vercel Blob. No always-on server or separate database hosting to manage.

**Just want to try?** Deploy on Vercel with a private [Vercel Blob](https://vercel.com/docs/vercel-blob) store for your SQLite database and media uploads. No separate database hosting or credentials to copy.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&project-name=serverlesswp&repository-name=serverlesswp&stores=%5B%7B%22type%22%3A%22blob%22%2C%22access%22%3A%22private%22%2C%22envVarPrefix%22%3A%22SQLITE%22%7D%5D&env=SERVERLESSWP_STREAM_PROVIDER,SERVERLESSWP_STREAM_VERCEL_ACCESS&envDefaults=%7B%22SERVERLESSWP_STREAM_PROVIDER%22%3A%22vercel-blob%22%2C%22SERVERLESSWP_STREAM_VERCEL_ACCESS%22%3A%22private%22%7D&envDescription=Stores%20media%20uploads%20in%20the%20same%20private%20Blob%20store.%20Leave%20these%20as%20they%20are.&envLink=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp%23media-uploads-on-vercel-blob)

Leave the two pre-filled deploy settings as they are to enable [media uploads](#media-uploads-on-vercel-blob). Once deployed, open your site’s URL and complete the WordPress setup.

## Use cases

Use the familiar WordPress admin to write posts, edit pages, and upload media.

**This is an experimental project**, best suited to blogs, portfolios, documentation, marketing and small business sites, and dev or staging sites.

SQLite keeps setup simple for sites with light database activity, but some plugins are incompatible and competing database writes can fail. For multiple active editors, frequent form submissions, ecommerce, membership sites, or forums, use [MySQL/MariaDB](#mysql-database-option).

## Why serverless WordPress?

- **Less to maintain:** no server to manage; [WordPress updates](#keeping-wordpress-updated) arrive as pull requests you can review and merge.
- **On-demand compute:** run WordPress when requests need it, without keeping your own server running around the clock.
- **No database server required:** store SQLite in S3 or Vercel Blob.
- **Branch previews:** try changes with a fresh SQLite database per branch, configured automatically on Vercel.

The deploy button uses Vercel for the simplest setup, with Blob storage, [CDN delivery and automatic HTTPS](https://vercel.com/docs/cdn), and [DDoS protection and firewall tools](https://vercel.com/docs/vercel-firewall) in one place. Optional [Web Analytics](https://vercel.com/docs/analytics/quickstart) adds visitor insights once enabled with a tracking script in WordPress. Features and usage limits vary by plan; the [free Hobby plan](https://vercel.com/pricing) is for personal, non-commercial projects. You can also deploy on Netlify or AWS Lambda.

## Other deployment options

- **[Vercel with an S3 demo database](https://serverlesswp.com/vercel-deploy):** try SQLite + S3 with a temporary database that expires after a few days.
- **[Netlify](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp):** bring your own [SQLite + S3](#sqlite--s3) or [MySQL](#mysql-database-option) database.
- **AWS Lambda:** deploy with the Serverless Framework using `npm install && serverless deploy`.

## Customization

- **Plugins and themes:** WordPress lives in `wp/`. Add plugins to `wp/wp-content/plugins/` or themes to `wp/wp-content/themes/`, then commit and push to redeploy. See [Keeping WordPress updated](#keeping-wordpress-updated) for updates through pull requests.
- **Uploads and generated files:** media uploads and supported plugin-generated files persist in S3 or Vercel Blob. See [the stream wrapper reference](#media-uploads-on-vercel-blob) for file-storage limitations.
- **Caching:** use cache headers such as `s-maxage` to enable CDN caching. See [Vercel Edge Caching](https://vercel.com/docs/concepts/edge-network/caching) or [Netlify Cache Headers](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers).
- **Request handling:** [api/index.js](api/index.js) runs PHP through [serverlesswp-node](https://github.com/mitchmac/serverlesswp-node) and provides hooks to modify the incoming `event` and WordPress `response`. Routing is configured in [vercel.json](vercel.json) or [netlify.toml](netlify.toml).

## Getting help

[Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions) for setup help or to share your successes and ideas.

## Contributing

Try ServerlessWP, [report problems](https://github.com/mitchmac/ServerlessWP/issues), and spread the word!

## License

GNU General Public License v3.0

## Reference

### Database options

ServerlessWP supports MySQL or a [SQLite database](https://github.com/WordPress/sqlite-database-integration) stored in Vercel Blob or an S3-compatible bucket. SQLite runs on demand, but some plugins are incompatible and competing database writes can fail. Use MySQL for sites with multiple active editors or frequent submissions. See [how SQLite + S3 works](https://github.com/mitchmac/ServerlessWP/wiki/How-does-SQLite-with-S3-work-with-ServerlessWP%3F).

Database selection follows this order:

1. **MySQL:** all four connection variables below are set.
2. **SQLite + S3:** `SQLITE_S3_BUCKET` is set.
3. **SQLite + Vercel Blob:** `BLOB_STORE_ID`, `SQLITE_BLOB_STORE_ID`, or `SQLITE_BLOB_READ_WRITE_TOKEN` is set on Vercel.
4. Otherwise, the setup page appears.

### SQLite + Vercel Blob

Connect a **private** Blob store in your Vercel project's Storage tab. Vercel supplies the store ID and OIDC authentication; each new git branch starts with its own fresh SQLite database. The deploy button handles this setup and shares the store with media uploads.

| Environment variable | Purpose / default |
|---|---|
| `BLOB_STORE_ID` | Connected store ID; supplied by Vercel. |
| `SQLITE_BLOB_STORE_ID` | Optional database-specific store ID; overrides `BLOB_STORE_ID`. |
| `SQLITE_BLOB_READ_WRITE_TOKEN` | Optional static token instead of OIDC. The database does **not** use `BLOB_READ_WRITE_TOKEN`. |
| `SQLITE_BLOB_PATHNAME` | Database base name; default: `wp-sqlite`. |

For separate database and upload stores, connect the private database store with the `SQLITE` environment variable prefix and the upload store without a prefix.

### SQLite + S3

Create a **private** S3-compatible bucket (including Cloudflare R2), preferably near your functions. Works on Netlify, AWS, and Vercel.

| Environment variable | Purpose |
|---|---|
| `SQLITE_S3_BUCKET` | Bucket name. |
| `SQLITE_S3_API_KEY` | API access key. |
| `SQLITE_S3_API_SECRET` | API secret key. |
| `SQLITE_S3_REGION` | Bucket region. |
| `SQLITE_S3_ENDPOINT` | Optional custom endpoint, e.g. for Cloudflare R2. |

### MySQL database option

Create a MySQL-compatible database and set the following; `wp-config.php` connects automatically. [TiDB](https://www.pingcap.com/tidb-cloud-serverless/) is one hosted option.

| Environment variable | Purpose |
|---|---|
| `DATABASE` | Database name. |
| `USERNAME` | Database user. |
| `PASSWORD` | Database password. |
| `HOST` | Database host. |
| `TABLE_PREFIX` | Optional table prefix. |

### Media uploads on Vercel Blob

The stream wrapper persists uploads in object storage because local writes do not survive redeploys. The deploy button enables it; for an existing project, configure:

| Environment variable | Purpose / default |
|---|---|
| `SERVERLESSWP_STREAM_PROVIDER` | `vercel-blob` for Vercel Blob, or `s3` for a bucket. |
| `SERVERLESSWP_STREAM_VERCEL_ACCESS` | `private` or `public`; must match the store's access setting. |
| `SERVERLESSWP_STREAM_VERCEL_STORE_ID` | Optional store ID; falls back to `BLOB_STORE_ID`, then `SQLITE_BLOB_STORE_ID`. |
| `SERVERLESSWP_STREAM_CACHE_CONTROL` | Served-file cache header; default: `public, max-age=3600, s-maxage=86400`. |
| `SERVERLESSWP_STREAM_CDN_BASE_URL` | Optional public Blob CDN URL to serve files directly. |

Vercel OIDC authenticates writes without manually added credentials. Private uploads are served through the function and cached at the edge. Plugins, themes, mu-plugins, and languages stay local; `.php`, `.log`, `.sqlite`, and `.htaccess` files are never routed. See the [stream wrapper README](packages/serverlesswp-stream-wrapper/README.md) for all settings, S3 configuration, and limitations.

### Keeping WordPress updated

The **Update WordPress** GitHub Action checks daily and opens pull requests; merging them redeploys your site. Enable it in your repository:

1. **Settings → Actions → General → Workflow permissions → Allow GitHub Actions to create and approve pull requests.** Without this, the action still pushes a branch for you to open a PR manually.
2. **Actions → Update WordPress → Enable workflow**, if disabled. GitHub disables scheduled workflows after 60 days without a push. **Run workflow** starts a check manually.

| Component | Update behavior |
|---|---|
| WordPress core | Replaces files verified against wordpress.org checksums; skips edited or deleted files and preserves your plugins, themes, uploads, and `wp-config.php`. The PR lists skipped and modified core files. |
| Bundled plugins | Separate PR; updates only plugins whose entire installed release matches wordpress.org checksums. Modified, premium, custom, and other unverified plugins are skipped and listed. |
| SQLite Database Integration | Mirrors its GitHub default branch; review the PR diff. Updates remove files you add inside this plugin's directory, so keep custom code in a separate plugin. |
| Themes | Reports available updates for manual installation; no automatic updates because wordpress.org provides no theme checksums. Themes bundled with WordPress are excluded from the report and covered by core updates. |

Check without changing files:

```bash
node util/wp-update --dry-run
node util/wp-update --plugins --dry-run
node util/wp-update --themes
```
