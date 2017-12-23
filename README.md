# ServerlessWP
*WordPress hosting for cheap perfectionists*

Many WordPress websites could be replaced by static HTML. Static websites are cheaper, faster, and more secure to host.
Why not use WordPress as a static website generator so we can still edit content via our web browser and use many of the great WordPress themes and plugins?

## Learn more

ServerlessWP enables hosting the backend WordPress installation, where we add and manage content, on AWS Serverless products so that we don't have to worry about maintaining a server. Serverless means we only pay for what we use, and most websites don't get edited too often. Combined with AWS free tier offerings, we can host the backend for next to nothing in cost.

ServerlessWP puts Basic Authentication in front of the backend WordPress installation to limit its exposure to bots and other sources of unwanted traffic. The backend WordPress website is crawled to generate the static website. The static HTML is uploaded to Amazon S3 for storage and hosting.

## Installation

1. Install the Serverless Framework for AWS - [Serverless installation guide](https://serverless.com/framework/docs/providers/aws/guide/installation/)
2. Clone or download this repository

## Authors

* **Mitch MacKenzie**  - [mitchmac](https://github.com/mitchmac)

## Acknowledgments

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
