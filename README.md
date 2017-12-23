# ServerlessWP
*WordPress hosting for cheap perfectionists*

Many WordPress websites could be replaced by static HTML. Static websites are cheaper, faster, and more secure to host.
Why not use WordPress as a static website generator so we can still edit content via our web browser and use many of the great WordPress themes and plugins?

## Learn more

ServerlessWP enables hosting the backend WordPress installation, where we add and manage content, on AWS Serverless products so that we don't have to worry about maintaining a server. Serverless means we only pay for what we use, and most websites don't get edited too often. Combined with AWS free tier offerings, we can host the backend for next to nothing in cost.

ServerlessWP puts Basic Authentication in front of the backend WordPress installation to limit its exposure to bots and other sources of unwanted traffic.

The backend WordPress website is crawled to generate the static website. The static HTML is uploaded to Amazon S3 for storage and hosting. AWS CloudFront is used to provide CDN hosting and SSL for the public-facing website.

*Disclaimer: This is a proof of concept and involves lots of hackery.*

## Installation

1. Install the Serverless Framework for AWS - [Serverless installation guide](https://serverless.com/framework/docs/providers/aws/guide/installation/)
2. Clone or download this repository
3. Place necessary binary files in the "bin" directory.
   * "php-cgi" and "wget" are the currently necessary binaries. They must be compiled to run in the Lambda environment.
   * This can be handled by running the "./build_bin.sh" script if you have Docker installed.
   * The "lib" directory in "./bin" requires a library noted in [bin/lib/readme.txt](bin/lib/readme.txt). It will be put in place if "./build_bin.sh" is used.
4. Place a WordPress installation directly in the "wp" directory so that "index.php" is found in the root of "wp".
5. Modify "wp-config.php" for database config:
```php
/** The name of the database for WordPress */
define( 'DB_NAME', getenv('WP_DB_NAME') );

/** MySQL database username */
define( 'DB_USER', getenv('WP_DB_USER') );

/** MySQL database password */
define( 'DB_PASSWORD', getenv('WP_DB_PASS') );

/** MySQL hostname */
define( 'DB_HOST', getenv('WP_DB_HOST') );
```
6. Modify "wp-config.php" for general ServerlessWP config:
```php
define('WP_HOME','https://' . getenv('WP_EDITOR_CUSTOM_DOMAIN'));
define('WP_SITEURL','https://' . getenv('WP_EDITOR_CUSTOM_DOMAIN'));
define('FORCE_SSL_ADMIN', true);
define('FORCE_SSL_CONTENT', true);
define('CONCATENATE_SCRIPTS', false);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('DISALLOW_FILE_MODS', true);
define('WP_AUTO_UPDATE_CORE', false);

/* That's all, stop editing! Happy blogging. */
```

## Authors

* **Mitch MacKenzie**  - [mitchmac](https://github.com/mitchmac)

## Acknowledgments

The following articles and repositories provided ideas, examples, and best practices for various parts of the project:

* [Chris White](https://github.com/cwhite92) - [Hosting a Laravel application on AWS Lambda](http://cwhite.me/hosting-a-laravel-application-on-aws-lambda/)
* [Danny Linden](https://github.com/dannylinden) - [aws-lambda-php](https://github.com/dannylinden/aws-lambda-php)
* [Peter Tilsen](https://github.com/petertilsen) - [basicAuthApiGateway](https://github.com/petertilsen/basicAuthApiGateway)
* [hemanth.hm](https://github.com/hemanth) - [Copying shared library dependencies](https://h3manth.com/content/copying-shared-library-dependencies)
* [Ivan Perevernykhata](https://github.com/perevernihata) - [Start/Stop RDS instances on schedule](https://www.codeproject.com/Articles/1190194/Start-Stop-RDS-instances-on-schedule)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
