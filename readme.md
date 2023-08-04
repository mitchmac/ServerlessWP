# Host WordPress On Vercel, Netlify, or AWS
WordPress hosting is silly. Serverless WordPress on Vercel, Netlify, or AWS Lambda.

| Vercel (recommended) | Netlify | AWS with Serverless Framework |
| --- | --- | --- |
| [![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp&env=DATABASE,USERNAME,PASSWORD,HOST&envDescription=Database%20credentials%20from%20PlanetScale%20or%20other%20host&envLink=https%3A%2F%2Fgithub.com%2Fmitchmac%2FServerlessWP%23setup-vercel-or-netlify&project-name=serverlesswp&repository-name=serverlesswp) |  [![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp) | ```npm install && serverless deploy```

### Quick install video

[![](https://markdown-videos.vercel.app/youtube/A1HZB2OqpCY)](https://youtu.be/A1HZB2OqpCY)

## Project goals

✅ Maintaining servers for WordPress can be a pain. Serverless hosting should be less work.

✅ Small WordPress sites shouldn't cost much (or anything) to host. **Vercel, Netlify, AWS, & PlanetScale have free tiers**.

✅ WordPress plugins and themes save time and should be extensively supported.

✅ Edge caching can give us blazing fast websites.

✅ We can reduce the carbon footprint of WordPress websites.

✅ We can create a helpful community. [Share your successes, knowledge, ideas, or struggles](https://github.com/mitchmac/ServerlessWP/discussions) in the discussions.

## Deploying ServerlessWP

**This is currently an experimental project and shouldn't be used when considerable security or stability is required, yet**

1. **Create a MySQL database** that can be accessed from Vercel or Netlify.

The easiest way to do this is with [PlanetScale](https://planetscale.com/) which has a free tier to get started. When using PlanetScale, make sure your database's region matches the region that Vercel or Netlify will use. This is usually ```us-east-1```.

2. **Deploy this repository to Vercel, Netlify, or AWS.** One of the links above will get you started. You'll just need a GitHub account.

If deploying to AWS with the Serverless Framework for the first time, check the [Serverless Framework docs](https://www.serverless.com/framework/docs/getting-started) to get up to speed and run ```serverless deploy``` when ready.

3. **Update the environment variables** for your project in Vercel or Netlify with the database credentials from PlanetScale or wherever you host the MySQL database. The WordPress config file ```wp-config.php``` uses these values to connect to the database. The environment variables are:
- DATABASE
- USERNAME
- PASSWORD
- HOST

For more information about creating environment variables, see [here for Vercel](https://vercel.com/docs/concepts/projects/environment-variables) and [here for Netlify](https://docs.netlify.com/environment-variables/overview/). Remember to redeploy your project after updating the environment variables if you update them after initially deploying your project.

4. (optional, can be done later) File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
- S3_KEY_ID
- S3_ACCESS_KEY

## Customizing WordPress
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content```
- Plugins like [Cache-Control](https://wordpress.org/plugins/cache-control/) can enable CDN caching with the s-maxage directive and make your site super fast. Refer to [Vercel Edge Caching](https://vercel.com/docs/concepts/edge-network/caching) or [Netlfiy Cache Headers](https://docs.netlify.com/edge-functions/optional-configuration/#supported-headers)

## Project structure
- `netlify.toml` or `vercel.json` are where we configure ```/api/index.js``` to handle all requests
- [mitchmac/serverlesswp-node](https://github.com/mitchmac/serverlesswp-node) is used to run PHP and handle the request
- You can modify the incoming request through the ```event``` object in api/index.js. You can also modify the WordPress ```response``` object there.

## Getting help
Need help getting ServerlessWP installed? [Start a discussion](https://github.com/mitchmac/ServerlessWP/discussions) or [e-mail Mitch](mailto:wp@mitchmac.dev)

## How can you help?
- Just using ServerlessWP and [reporting any problems you experience](https://github.com/mitchmac/ServerlessWP/issues) is a fantastic way to help!
- Spread the word! Let's try to make WordPress hosting better.

## License
GNU General Public License v3.0
