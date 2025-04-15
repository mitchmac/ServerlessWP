# WordPress on Vercel, Netlify, or AWS
WordPress hosting is ridiculously wasteful of time and resouces. Host WordPress on Vercel, Netlify, or AWS Lambda serverless functions with ServerlessWP.

Don't want to run a database server? ServerlessWP experimentally supports SQLite+S3 as a low maintenance alternative.

Stay up-to-date at the ServerlessWP repository: [mitchmac/serverlesswp](https://github.com/mitchmac/serverlesswp)

## Quick Deploy

Choose one of the following platforms to deploy your serverless WordPress site:

| Vercel (recommended)  | Netlify  | Serverless Framework (Lambda)  |
|---|---|---|
| [![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&project-name=serverlesswp&repository-name=serverlesswp)  | [![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp)  |  ```npm install && serverless deploy``` |
| 🕑 60 second requests   | 10 second requests  | 30 second requests  |
| &nbsp;⎇&nbsp; automatic branch deploy config   | manual branch config  | do-it-yourself  |
| 🗲 [Fluid compute](https://vercel.com/fluid) | single concurrency | single concurrency |


## Project goals

🌴 Maintaining servers for WordPress can be a pain. Serverless hosting should make it less time consuming.

💲 Small WordPress sites shouldn't cost much to host. **Vercel, Netlify, & AWS have free tiers**.

🔓 WordPress plugins and themes are extensively supported. No arbitrary limitations here.

⚡ Blazing fast websites that take advantage of caching and Content Delivery Networks.

🌎 Mindful consideration of the carbon footprint of WordPress websites.

🤝 A helpful community. [Share your successes, ideas, or struggles](https://github.com/mitchmac/ServerlessWP/discussions) in the discussions.

## Deploy ServerlessWP

**This is currently an experimental project.**

It's probably a good fit for development/experimentation, personal blogs, documentation sites, and small business sites. It shouldn't be used when considerable security or stability is required, yet.

### 1. Deploy this repository to Vercel, Netlify, or AWS.
One of the links above will get you started. You'll just need a GitHub account.

### 2. Select a database solution.
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

### 3. **Update the environment variables.**
After selecting your database solution you'll need to update environment variables for your project with the S3 or database credentials. The WordPress config file ```wp-config.php``` is automatically configured to use these values to connect to the database. 

If using **SQLite and S3** you'll need to create a private S3 bucket and get access credentials it.

If using a **MySQL or MariaDB** database you'll need to setup a database and make sure it can be accessed by outside servers remotely.

Update the environment variables (choose one):

| SQLite+S3 | MySQL |
|---|---|
| SQLITE_S3_BUCKET <br> the bucket name you created | DATABASE <br> the database name you created |
| SQLITE_S3_API_KEY <br> the API key to access the bucket | USERNAME <br> the database user to access the database |
| SQLITE_S3_API_SECRET <br> the API secret key to access the bucket | PASSWORD <br> the database user's password |
| SQLITE_S3_REGION <br> the region where the bucket lives. Create it near your serverless functions | HOST <br> the address to access the database
| SQLITE_S3_ENDPOINT <br> optional: to update where the bucket is, like a Cloudflare R2 address | TABLE_PREFIX <br> optional: to use a prefix on the database tables |

See [here for Vercel](https://vercel.com/docs/concepts/projects/environment-variables) and [here for Netlify](https://docs.netlify.com/environment-variables/overview/) for more about managing environment variables. **Remember to redeploy** your project after updating the environment variables if you update them after initially deploying your project.

### 4. File and media uploads with S3 (optional, can be done later) 
File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
- S3_KEY_ID
- S3_ACCESS_KEY

## Customizing WordPress
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content``` then commit the files to your repository so it will re-deploy.
- Plugins like [Cache-Control](https://wordpress.org/plugins/cache-control/) can enable CDN caching with the s-maxage directive and make your site super fast. Refer to [Vercel Edge Caching](https://vercel.com/docs/concepts/edge-network/caching) or [Netlfiy Cache Headers](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers)

## Customizing ServerlessWP
- `netlify.toml` or `vercel.json` are where we configure ```/api/index.js``` to handle all requests
- [mitchmac/serverlesswp-node](https://github.com/mitchmac/serverlesswp-node) is used to run PHP and handle the request
- You can modify the incoming request through the ```event``` object in api/index.js. You can also modify the WordPress ```response``` object there. ServerlessWP has a basic plugin system to do this. Checkout out ```/api/index.js``` for hints.

## Getting help
Need help getting ServerlessWP installed? [Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions)

## Contributing
- Using ServerlessWP and [reporting any problems you experience](https://github.com/mitchmac/ServerlessWP/issues) is a great way to help.
- Spread the word!

## License
GNU General Public License v3.0
