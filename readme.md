# ServerlessWP

WordPress hosting is silly.

**Low maintenance** and **low cost/free** WordPress hosting on Vercel, Netlify, or AWS Lambda.

ServerlessWP puts WordPress in serverless functions and the database in a file. Deploy this repository to give it a try.

Stay up-to-date at the ServerlessWP repository: [github.com/mitchmac/serverlesswp](https://github.com/mitchmac/serverlesswp)

![WordPress 7.0.4](https://img.shields.io/badge/version-7.0.4-blue?logo=wordpress&labelColor=white&logoColor=black) ![PHP 8.3.33](https://img.shields.io/badge/version-8.3.33-blue?logo=php&labelColor=white)

## Use Cases

**This is currently an experimental project.** It's built for content sites rather than applications:

✅ **Great fit:** personal blogs, documentation, portfolios, marketing and small business sites, dev and staging sites — anything that isn't heavily updated by more than one person at a time.

✅ **Also great: headless/decoupled WordPress.** run WordPress purely as the editing backend and content API (REST or GraphQL) for a separate frontend.

⚠️ **Use MySQL, not SQLite when:** sites with several people publishing at once, take a lot of form submissions,  ecommerce, membership sites, forums. SQLite+S3 and SQLite+Blob have [limited write concurrency](#sqlite--object-storage).


## Quick Deploy

**The easiest way to run WordPress with ServerlessWP is entirely on Vercel.** This button creates a private [Vercel Blob](https://vercel.com/docs/vercel-blob) store during setup, and WordPress runs on a SQLite database kept in it. No database to host, no credentials to copy, no other accounts to sign up for — and each git branch gets its own database.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&project-name=serverlesswp&repository-name=serverlesswp&stores=%5B%7B%22type%22%3A%22blob%22%2C%22access%22%3A%22private%22%2C%22envVarPrefix%22%3A%22SQLITE%22%7D%5D)

More on [how SQLite + Vercel Blob works](#sqlite--vercel-blob) and [when to use MySQL instead](#mysql-database-option).

Other ways to deploy (click to deploy):

- **[Just kicking the tires?](https://serverlesswp.com/vercel-deploy)** Deploy on Vercel with a temporary SQLite database on S3 that expires after a few days.
- **[Netlify](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp)** with your own database (SQLite+S3 or MySQL). Trade-offs vs. Vercel: 10 second max request duration instead of 60, manual branch config, and analytics/firewall are paid add-ons.
- **AWS Lambda** with the Serverless Framework: `npm install && serverless deploy`

## Project goals

🌴 WordPress hosting made easy. Lower maintenance with serverless functions instead of servers.

💲 Small WordPress sites shouldn't cost much to host. **Vercel, Netlify, & AWS have free tiers**.

🔓 WordPress plugins and themes are extensively supported. No arbitrary limitations here.

⚡ Blazing fast websites that take advantage of caching and content delivery networks.

🌎 Lower the carbon footprint of WordPress websites.

🤝 A helpful community. [Share your successes, ideas, or struggles](https://github.com/mitchmac/ServerlessWP/discussions) in the discussions.

## Deploy ServerlessWP

### 1. Deploy this repository to Vercel, Netlify, or AWS.
One of the links above will get you started. You'll just need a GitHub account.

### 2. Setup a database.
**Vercel Blob or S3 are recommended because it's the quickest to get running and the least to maintain: nothing to provision, nothing running 24/7, and on Vercel no credentials to copy at all.** MySQL is equally supported and stays the better choice for the sites called out above — see [MySQL](#mysql-database-option).

If you used the Vercel button above, you're already done: the Blob store it created is your database. Skip to step 3.

Otherwise, pick your database below — [SQLite + object storage](#sqlite--object-storage) or [MySQL](#mysql-database-option) — then come back for uploads.

Whichever you choose, you set it up with environment variables. See [here for Vercel](https://vercel.com/docs/concepts/projects/environment-variables) and [here for Netlify](https://docs.netlify.com/environment-variables/overview/) for how to manage them. **Remember to redeploy** your project if you change environment variables after the initial deploy.

### 3. File and media uploads with S3 (optional, can be done later) 
File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
- S3_KEY_ID
- S3_ACCESS_KEY

## SQLite + object storage
WordPress usually runs with a MySQL (or MariaDB) database. That means hosting a database that runs 24/7.

A [SQLite database](https://github.com/WordPress/sqlite-database-integration) option has been developed by members of the WordPress community. With the recent ability to *conditionally write* to object storage - Vercel Blob, or S3 and S3-compatible buckets - a decentralized and serverless data layer for ServerlessWP is possible.

Check out the [diagram of the SQLite+S3 logic](https://github.com/mitchmac/ServerlessWP/wiki/How-does-SQLite-with-S3-work-with-ServerlessWP%3F) if you're interested in how it works.

ServerlessWP supports both SQLite and MySQL as database options. Some of the trade-offs:

| SQLite + object storage | MySQL |
|---|---|
| 🕑 on demand   | 24/7 hosting |
| 💲 usage based (free tiers) | monthly fees (some limited free tiers) |
| 🧩 some plugin incompatibility | full plugin compatibility |
| ♾️ limited database update concurrency | few concurrency limitations |
| ✔️ blogs, dev sites, documentation, single editor sites | any site |

The main trade-off of using SQLite with ServerlessWP is:
- if requests are handled by multiple underlying serverless functions at the same time and make a change to the database, the competing requests may fail. Sites with multiple editors working at the same time or receiving many form submissions aren't a great fit for SQLite.

### SQLite + Vercel Blob

The easiest option. On Vercel, the deploy button above creates a private [Vercel Blob](https://vercel.com/docs/vercel-blob) store for you during setup - no bucket or IAM credentials to create. The git branch is added to the name, so preview deployments each get their own database.

| SQLite+Vercel Blob | |
|---|---|
| BLOB_STORE_ID | id of the store holding the database - Vercel adds this when it connects a store |
| SQLITE_BLOB_STORE_ID | optional: store id to use instead, for a store created with an env var prefix of `SQLITE` |
| SQLITE_BLOB_READ_WRITE_TOKEN | optional: static read-write token, for a store that has one |
| SQLITE_BLOB_PATHNAME | optional: base name for the database - defaults to `wp-sqlite` |

Connecting a store is all the setup there is. Vercel adds `BLOB_STORE_ID` to the project and mints a short-lived `VERCEL_OIDC_TOKEN` for each deployment, and the Blob SDK pairs the two to authenticate. To set this up on an existing project, create the store from the Storage tab with **private** access and connect it - there's nothing to copy.

Stores that hand out a static `BLOB_READ_WRITE_TOKEN` work too, as `SQLITE_BLOB_READ_WRITE_TOKEN`. The unprefixed name isn't used - that's the token a store connected for media uploads gets, and those stores are public, so every private write against one would fail.

Keep the database store separate from any store you use for media uploads: the database has to stay private and uncached, while uploads want public reads and CDN caching. A store connected for uploads sets `BLOB_STORE_ID` as well, so name the database store's id `SQLITE_BLOB_STORE_ID` if you connect both.

### SQLite + S3

Works anywhere - Netlify, AWS, or Vercel - with any S3-compatible bucket, including Cloudflare R2. Setup a **private** bucket and use these environment variables:

| SQLite+S3 | |
|---|---|
| SQLITE_S3_BUCKET | bucket name you created |
| SQLITE_S3_API_KEY | API key to access the bucket |
| SQLITE_S3_API_SECRET | API secret key to access the bucket |
| SQLITE_S3_REGION | region where the bucket lives - create it near your serverless functions |
| SQLITE_S3_ENDPOINT | optional: to update where the bucket is, like a Cloudflare R2 address |

## MySQL database option

The right call when you need full plugin compatibility or more than a couple of people writing at once. [TiDB](https://www.pingcap.com/tidb-cloud-serverless/) provides a cloud MySQL database with a generous free tier.

After creating your database, set these environment variables with the credentials. ```wp-config.php``` is automatically configured to use them to connect.

|  |  |
|---|---|
| DATABASE | database name you created |
| USERNAME | database user to access the database |
| PASSWORD | database user's password |
| HOST |  address to access the database |
| TABLE_PREFIX | optional: to use a prefix on the database tables |

## Which database gets used

The most explicitly configured option wins, so adding a Blob store for media won't take over an existing database:

1. **MySQL** - `DATABASE`, `USERNAME`, `PASSWORD`, and `HOST` all set
2. **SQLite + S3** - `SQLITE_S3_BUCKET` set
3. **SQLite + Vercel Blob** - `BLOB_STORE_ID` (or `SQLITE_BLOB_STORE_ID`, or `SQLITE_BLOB_READ_WRITE_TOKEN`) set on Vercel
4. otherwise the setup page is shown

## Customizing WordPress
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content``` then commit the files to your repository so it will re-deploy.
- Plugins like [Cache-Control](https://wordpress.org/plugins/cache-control/) can enable CDN caching with the s-maxage directive and make your site super fast. Refer to [Vercel Edge Caching](https://vercel.com/docs/concepts/edge-network/caching) or [Netlfiy Cache Headers](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers)

## Customizing ServerlessWP
- `netlify.toml` or `vercel.json` are where we configure ```/api/index.js``` to handle all requests
- [mitchmac/serverlesswp-node](https://github.com/mitchmac/serverlesswp-node) is used to run PHP and handle the request
- You can modify the incoming request through the ```event``` object in api/index.js. You can also modify the WordPress ```response``` object there. ServerlessWP has a basic plugin system to do this. Checkout out ```/api/index.js``` for hints.

## Getting help
Need help getting ServerlessWP installed? [Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions) or [send me a chat](https://serverlesswp.com/chat).

## Contributing
- Using ServerlessWP and [reporting any problems you experience](https://github.com/mitchmac/ServerlessWP/issues) is a great way to help.
- Spread the word!

## License
GNU General Public License v3.0
