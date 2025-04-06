# Host WordPress On Vercel, Netlify, or AWS
WordPress hosting is silly. Serverless WordPress deployed to Vercel, Netlify, or AWS Lambda.

**April 2025 - ServerlessWP now supports SQLite combined with S3 as a truly serverless database alternative!**

## Quick Deploy

Choose one of the following platforms to deploy your serverless WordPress site:

### Vercel (recommended)

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&env=DATABASE,USERNAME,PASSWORD,HOST&envDescription=Database%20credentials%20from%20PlanetScale%20or%20other%20host&envLink=https%3A%2F%2Fgithub.com%2Fmitchmac%2FServerlessWP%23setup-vercel-or-netlify&project-name=serverlesswp&repository-name=serverlesswp)

Vercel provides the best experience for ServerlessWP: requests can run for up to 60 seconds, branch-based deployments are automatic and well-supported, and Fluid compute optimizes serverless function resource usage.

### Netlify

[![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp)

Netlify is another good option but requests can only run up to 10 seconds. ServerlessWP currently doesn't support branch-based data access with Netlify.

### Serverless Framework & AWS Lambda

```npm install && serverless deploy```

## Project goals

✅ Maintaining servers for WordPress can be a pain. Serverless hosting should make it so much easier.

✅ Small WordPress sites shouldn't cost much (or anything) to host. **Vercel, Netlify, & AWS have free tiers**.

✅ WordPress plugins and themes are extensively supported.

✅ Blazing fast websites that take advantage of caching and Content Delivery Networks.

✅ Mindful consideration of the carbon footprint of WordPress websites.

✅ Create a helpful community. [Share your successes, knowledge, ideas, or struggles](https://github.com/mitchmac/ServerlessWP/discussions) in the discussions.

## Deploy ServerlessWP

**This is currently an experimental project. It's probably a good fit for personal blogs, documentation sites, and small org or business sites.** It shouldn't be used when considerable security or stability is required, yet

### 1. Deploy this repository to Vercel, Netlify, or AWS.
One of the links above will get you started. You'll just need a GitHub account.

### 2. Select a database solution.
WordPress usually runs with a MySQL or MariaDB database. That means hosting a database that runs 24/7.

A [SQLite database](https://github.com/WordPress/sqlite-database-integration) option has been developed by members of the WordPress community. Combining SQLite with the recent ability to *conditionally write* to S3-compatible object storage allows for decentralized and serverless data layer for ServerlessWP.

ServerlessWP supports both SQLite+S3 and MySQL/MariaDB as database options. For blogs and sites that don't receive many simultaneous updates, SQLite+S3 may be a much lower cost and zero maintenance option.

The main trade-offs of using SQLite+S3 with ServerlessWP are:
- some plugins may not be completely compatible with SQLite
- if requests are handled by multiple underlying serverless functions at the same time and make a change to the database, the last request may fail.

### 3. **Update the environment variables.**
After selecting your database solution you'll need to update environment variables for your project with the S3 or database credentials. The WordPress config file ```wp-config.php``` is configured to use these values to connect to the database. 

If using **SQLite and S3**, create a private S3 bucket and get access credentials for these environment variables:
- SQLITE_S3_BUCKET
- SQLITE_S3_API_KEY
- SQLITE_S3_API_SECRET
- SQLITE_S3_REGION
- SQLITE_S3_ENDPOINT (optional: use with Cloudflare R2 for example)

If using a **MySQL or MariaDB** database, setup a database and fill in these variables:
- DATABASE
- USERNAME
- PASSWORD
- HOST

See [here for Vercel](https://vercel.com/docs/concepts/projects/environment-variables) and [here for Netlify](https://docs.netlify.com/environment-variables/overview/) for more about managing environment variables. Remember to redeploy your project after updating the environment variables if you update them after initially deploying your project.

### 4. File and media uploads with S3 (optional, can be done later) 
File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
- S3_KEY_ID
- S3_ACCESS_KEY

## Quick install video

[![](https://markdown-videos.vercel.app/youtube/A1HZB2OqpCY)](https://youtu.be/A1HZB2OqpCY)

## Customizing WordPress
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content``` then commit the files to your repository so it will re-deploy.
- Plugins like [Cache-Control](https://wordpress.org/plugins/cache-control/) can enable CDN caching with the s-maxage directive and make your site super fast. Refer to [Vercel Edge Caching](https://vercel.com/docs/concepts/edge-network/caching) or [Netlfiy Cache Headers](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers)

## Project structure
- `netlify.toml` or `vercel.json` are where we configure ```/api/index.js``` to handle all requests
- [mitchmac/serverlesswp-node](https://github.com/mitchmac/serverlesswp-node) is used to run PHP and handle the request
- You can modify the incoming request through the ```event``` object in api/index.js. You can also modify the WordPress ```response``` object there.

## Getting help
Need help getting ServerlessWP installed? [Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions)

## How can you help?
- Just using ServerlessWP and [reporting any problems you experience](https://github.com/mitchmac/ServerlessWP/issues) is a great way to help.
- Spread the word! Let's try to make WordPress hosting better.

## License
GNU General Public License v3.0
