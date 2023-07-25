# ServerlessWP WordPress Starter
Serverless WordPress on Vercel, Netlify, or AWS Lambda.

World class affordable (free) hosting for your WordPress blog, portfolio or anything you can imagine.

| Netlify | Vercel |
| --- | --- |
| [![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/serverlesswp) |[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fserverlesswp) |

## Setup (Vercel or Netlify)
1. Deploy this repository to Vercel or Netlify. The links above will get you started.
2. Setup a MySQL database for WordPress to use. [PlanetScale](https://planetscale.com/) is a great option with a free tier.
3. Update environment variables for your project in Vercel or Netlify with the database credentials. These are used by wp-config.php. The environment variables are:
```
DATABASE
USERNAME
PASSWORD
HOST
```
4. (optional) File and media uploads can be enabled using the included WP Offload Media Lite for Amazon S3 plugin. S3 setup details can be found [here](https://deliciousbrains.com/wp-offload-media/doc/amazon-s3-quick-start-guide/). The wp-config.php file is setup to use the following environment variables for use by the plugin:
```
S3_KEY_ID
S3_ACCESS_KEY
```

## Setup (Serverless Framework)
1. Install and setup the serverless framework ([docs](https://www.serverless.com/framework/docs/getting-started))
2. Run `serverless deploy` to confirm that the Lambda is created
3. Like step 2 above, create a MySQL database and update the environment variables. They can be updated in the `serverless.yml` file and then run `serverless deploy` again.


## Structure
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content```
- `netlify.toml` or `vercel.json` are what directs all requests to be served by the file in `api/index.js`


## License
GNU General Public License v3.0
