# ServerlessWP
*WordPress as a static website generator on AWS Serverless*

Many WordPress websites could be replaced by static HTML. Static websites are cheaper, faster, and more secure to host.
Why not use WordPress as a static website generator so we can still edit content via our web browser and use many of the great WordPress themes and plugins?

:white_check_mark: Fast & secure static website hosting

:white_check_mark: Easy browser based content management

:white_check_mark: Compatible with most WordPress themes and many plugins

:white_check_mark: WordPress backend isolated from the web

:white_check_mark: Pay only for what is used

## Learn more

ServerlessWP enables hosting the backend WordPress installation, where we add and manage content, on AWS Serverless products so that we don't have to worry about maintaining a server. Serverless means we only pay for what we use, and most websites don't get edited too often. Combined with AWS free tier offerings, we can host the backend for next to nothing in cost.

ServerlessWP puts Basic Authentication in front of the backend WordPress installation to limit its exposure to bots and other sources of unwanted traffic.

The backend WordPress website is crawled to generate the static website. The static HTML is uploaded to Amazon S3 for storage and hosting. AWS CloudFront is used to provide CDN hosting and SSL for the public-facing website.

**A typical blog will cost around $1 per month to run (mainly depending on CloudFront data transfer and database uptime for content management).** The RDS-based MySQL database for WordPress will shut down automatically after 2 hours of inactivity to reduce costs, since it is not necessary for the static frontend.

*Disclaimer: This is a proof of concept! Breaking changes may be made if/when Aurora Serverless becomes a preferable database option versus the current RDS usage.*

## Installation

1. Install the Serverless Framework for AWS - [Serverless installation guide](https://serverless.com/framework/docs/providers/aws/guide/installation/)
2. Clone or download this repository
3. Place necessary binary files in the "bin" directory.
   * **This can be handled by running the "./build_bin.sh" script if you have Docker installed.**
   * "php-cgi" and "wget" are the currently necessary binaries. They must be compiled to run in the Lambda environment.
   * The "./bin/lib" directory requires a library noted in [bin/lib/readme.txt](bin/lib/readme.txt). It will be put in place if "./build_bin.sh" is used.
4. Place a WordPress installation directly in the "wp" directory so that "index.php" is found in the root of "wp".
   * **This can be handled by executing the "./build_wp.sh" script.**
5. Modify "wp-config.php" for ServerlessWP friendly configuration.
   * **This is handled by the "./build_wp.sh" script if used.**
   * Otherwise, use this [wp-config-base.php](https://github.com/mitchmac/ServerlessWP-plugin/blob/master/assets/wp-config-base.php) as a guide.
6. To handle file uploads in the WordPress backend, install the necessary WordPress plugins:
   * **This is handled by the "./build_wp.sh" script if used.**
   * [ServerlessWP](https://github.com/mitchmac/ServerlessWP-plugin/)
   * [Amazon Web Services](https://en-ca.wordpress.org/plugins/amazon-web-services/)
   * [WP Offload S3 Lite](https://wordpress.org/plugins/amazon-s3-and-cloudfront/)
7. Place any other WordPress themes or plugins in the respective wp-content directories like a standard WordPress installation.
8. Edit serverless.yml
   * The "custom" section at the top of the file has variables that should be reviewed.
9. Run "npm install"
10. Run "severless deploy" -- may take 30-60 minutes for AWS to create the necessary resources the first time.
11. Point your domain's DNS at the created CloudFront distribution.

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
