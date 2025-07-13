<p align="center"><img src="https://serverlesswp.com/wp-content/serverlesswp.png"></p>

WordPress hosting is silly.

**Low maintenance** and **low cost/free** WordPress hosting on Vercel, Netlify, or AWS Lambda.

ServerlessWP puts PHP & WordPress in serverless functions. Deploy this repository to give it a try.

Stay up-to-date at the ServerlessWP repository: [github.com/mitchmac/serverlesswp](https://github.com/mitchmac/serverlesswp)

![PHP 8.3.23](https://img.shields.io/badge/version-8.3.23-blue?logo=php&labelColor=white) ![WordPress 6.8.1](https://img.shields.io/badge/version-6.8.1-blue?logo=wordpress&labelColor=white&logoColor=black)

## Quick Deploy

Click one of the options below to deploy your serverless WordPress site:

| Vercel (recommended)  | Netlify  |
|---|---|
| [![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&project-name=serverlesswp&repository-name=serverlesswp)  | [![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp)  |
| 🕑 60 second max request duration   | 10 second max request duration  |
| &nbsp;⎇&nbsp; automatic branch deploy config   | manual branch config  |
| 🗲 [Fluid compute](https://vercel.com/fluid) | - |
| 📈 [Web analytics](https://vercel.com/docs/analytics) | paid add-on |
| 🛡️ [Firewall](https://vercel.com/docs/vercel-firewall/vercel-waf) | paid add-on |

Want to use AWS Lambda with the Serverless Framework instead? `npm install && serverless deploy`

## Project goals

🌴 WordPress hosting made easy. Lower maintenance with serverless functions instead of servers.

💲 Small WordPress sites shouldn't cost much to host. **Vercel, Netlify, & AWS have free tiers**.

🔓 WordPress plugins and themes are extensively supported. No arbitrary limitations here.

⚡ Blazing fast websites that take advantage of caching and content delivery networks.

🌎 Lower the carbon footprint of WordPress websites.

🤝 A helpful community. [Share your successes, ideas, or struggles](https://github.com/mitchmac/ServerlessWP/discussions) in the discussions.

## Deploy ServerlessWP

**This is currently an experimental project.**

It's a good fit for development, personal blogs, documentation sites, and small business sites. It shouldn't be used when considerable security or stability is required, yet.

### 1. Deploy this repository to Vercel, Netlify, or AWS.
One of the links above will get you started. You'll just need a GitHub account.

### 2. Setup a database.
You'll need to create a database for your site's content.

[TiDB](https://www.pingcap.com/tidb-cloud-serverless/) provides a cloud database with a generous free tier.

Wouldn't it be great to skip hosting a database? [Skip below](#sqlite--s3-database-option) if you want to try something different with SQLite & S3.

### 3. Update the environment variables.
After creating your database you'll need to update environment variables for your project with the credentials. The WordPress config file ```wp-config.php``` is automatically configured to use these values to connect to the database.

Update the environment variables in Vercel/Netlify:

|  |  |
|---|---|
| DATABASE | database name you created |
| USERNAME | database user to access the database |
| PASSWORD | database user's password |
| HOST |  address to access the database |
| TABLE_PREFIX | optional: to use a prefix on the database tables |

See [here for Vercel](https://vercel.com/docs/concepts/projects/environment-variables) and [here for Netlify](https://docs.netlify.com/environment-variables/overview/) for more about managing environment variables. **Remember to redeploy** your project after updating the environment variables if you update them after initially deploying your project.

### 4. File and media uploads with S3 (optional, can be done later) 
File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
- S3_KEY_ID
- S3_ACCESS_KEY

## SQLite + S3 database option
WordPress usually runs with a MySQL (or MariaDB) database. That means hosting a database that runs 24/7.

A [SQLite database](https://github.com/WordPress/sqlite-database-integration) option has been developed by members of the WordPress community. With the recent ability to *conditionally write* to S3-compatible object storage a decentralized and serverless data layer for ServerlessWP is possible.

Check out the [diagram of the SQLite+S3 logic](https://github.com/mitchmac/ServerlessWP/wiki/How-does-SQLite-with-S3-work-with-ServerlessWP%3F) if you're interested in how it works.

ServerlessWP supports both SQLite+S3 and MySQL as database options. Some of the trade-offs:

| SQLite+S3 | MySQL |
|---|---|
| 🕑 on demand   | 24/7 hosting |
| 💲 usage based (free tiers) | monthly fees (some limited free tiers) |
| 🧩 some plugin incompatibility | full plugin compatibility |
| ♾️ limited database update concurrency | few concurrency limitations |
| ✔️ blogs, dev sites, documentation, single editor sites | any site |

The main trade-off of using SQLite+S3 with ServerlessWP is:
- if requests are handled by multiple underlying serverless functions at the same time and make a change to the database, the competing requests may fail. Sites with multiple editors working at the same time or receiving many form submissions aren't a great fit for SQLite+S3.

Want to give it a try? Setup a private S3 bucket and use these environment variables:

| SQLite+S3 | |
|---|---|
| SQLITE_S3_BUCKET | bucket name you created |
| SQLITE_S3_API_KEY | API key to access the bucket |
| SQLITE_S3_API_SECRET | API secret key to access the bucket |
| SQLITE_S3_REGION | region where the bucket lives - create it near your serverless functions |
| SQLITE_S3_ENDPOINT | optional: to update where the bucket is, like a Cloudflare R2 address |

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
