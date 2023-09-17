# WP Offload Media Lite for Amazon S3, DigitalOcean Spaces, and Google Cloud Storage

**Contributors:** wpengine, deliciousbrains, ianmjones, eriktorsner, kevinwhoffman, tysonreeder, dalewilliams, lewisia32, mattshaw, aaemnnosttv, a5hleyrich, polevaultweb, bradt, joetan \
**Tags:** uploads, amazon, s3, amazon s3, digitalocean, digitalocean spaces, google cloud storage, gcs, mirror, admin, media, cdn, cloudfront \
**Requires at least:** 5.5 \
**Tested up to:** 6.3 \
**Requires PHP:** 7.2 \
**Stable tag:** 3.2.5 \
**License:** GPLv3

Copies files to Amazon S3, DigitalOcean Spaces or Google Cloud Storage as they are uploaded to the Media Library. Optionally configure Amazon CloudFront or another CDN for faster delivery.

## Description

FORMERLY WP OFFLOAD S3 LITE

https://www.youtube.com/watch?v=I-wTMXMeFu4

This plugin automatically copies images, videos, documents, and any other media added through WordPress' media uploader to [Amazon S3](http://aws.amazon.com/s3/), [DigitalOcean Spaces](https://www.digitalocean.com/products/spaces/) or [Google Cloud Storage](https://cloud.google.com/storage/). It then automatically replaces the URL to each media file with their respective Amazon S3, DigitalOcean Spaces or Google Cloud Storage URL or, if you have configured [Amazon CloudFront](http://aws.amazon.com/cloudfront/) or another CDN with or without a custom domain, that URL instead. Image thumbnails are also copied to the bucket and delivered through the correct remote URL.

Uploading files *directly* to your Amazon S3, DigitalOcean Spaces or Google Cloud Storage account is not currently supported by this plugin. They are uploaded to your server first, then copied to the bucket. There is an option to automatically remove the files from your server once they are copied to the bucket however.

If you're adding this plugin to a site that's been around for a while, your existing media files will not be copied to or served from Amazon S3, DigitalOcean Spaces or Google Cloud Storage. Only newly uploaded files will be copied to and served from the bucket. [The pro upgrade](https://deliciousbrains.com/wp-offload-media/upgrade/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) has an upload tool to handle existing media files.

**Image Optimization**

Although WP Offload Media doesn't include image optimization features, we work closely with the author of [EWWW Image Optimizer](https://wordpress.org/plugins/ewww-image-optimizer/) to ensure they always work well together. Not only do we recommend EWWW Image Optimizer but we officially support its integration with WP Offload Media.

**PRO Upgrade with Email Support and More Features**

* Upload existing Media Library to Amazon S3, DigitalOcean Spaces or Google Cloud Storage
* Control offloaded files from the Media Library
* [Assets Pull addon](https://deliciousbrains.com/wp-offload-media/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting&utm_content=assets%2Baddon#addons) - Serve your CSS, JS and fonts via CloudFront or another CDN
* [WooCommerce integration](https://deliciousbrains.com/wp-offload-media/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting&utm_content=woocommerce%2Baddon#integrations)
* [Easy Digital Downloads integration](https://deliciousbrains.com/wp-offload-media/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting&utm_content=edd%2Baddon#integrations)
* Priority email support

[Compare pro vs free &rarr;](https://deliciousbrains.com/wp-offload-media/upgrade/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)

The video below runs through the pro upgrade features...

https://www.youtube.com/watch?v=I-wTMXMeFu4

## Installation

1. Install this plugin using WordPress' built-in installer
2. Access the *Offload Media* option under *Settings*
3. Follow the instructions to set up your AWS or DigitalOcean access keys and configure

Check out the [Quick Start Guide](https://deliciousbrains.com/wp-offload-media/doc/quick-start-guide/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) for more information on configuring WP Offload Media.

## Frequently Asked Questions

### What are the minimum requirements?

You can see the minimum requirements [here](https://deliciousbrains.com/wp-offload-media/pricing/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting&utm_content=requirements#requirements).

### Do you offer email support?

If you upgrade to the pro version of [WP Offload Media](https://deliciousbrains.com/wp-offload-media/upgrade/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting), we will gladly provide you with email support. We take pride in delivering exceptional customer support. We do not provide email support for the free version.

## Screenshots

### 1. Select Cloud Storage Provider

![Select Cloud Storage Provider](https://ps.w.org/amazon-s3-and-cloudfront/assets/screenshot-1.png)

### 2. Select or Create Bucket

![Select or Create Bucket](https://ps.w.org/amazon-s3-and-cloudfront/assets/screenshot-2.png)

### 3. Settings Screen

![Settings Screen](https://ps.w.org/amazon-s3-and-cloudfront/assets/screenshot-3.png)

### 4. Custom Domain Used With CDN

![Custom Domain Used With CDN](https://ps.w.org/amazon-s3-and-cloudfront/assets/screenshot-4.png)


## Upgrade Notice

### 2.6

This is a major upgrade that updates the format of information stored about offloaded Media Library items. Once upgraded you will not be able to downgrade without restoring data from a backup.
This version requires PHP 5.6+

### 2.3

This is a major upgrade that switches to using a custom table for storing data about offloaded Media Library items. Once upgraded you will not be able to downgrade without restoring data from a backup.

### 2.0

This is a major upgrade that introduces support for DigitalOcean Spaces, renames the plugin to WP Offload Media Lite, and coincidentally upgrades some of its database settings. You may not be able to downgrade to WP Offload S3 Lite 1.x after upgrading to WP Offload Media Lite 2.0+.

### 1.1

This is a major change, which ensures S3 URLs are no longer saved in post content. Instead, local URLs are filtered on page generation and replaced with the S3 version. If you depend on the S3 URLs being stored in post content you will need to make modifications to support this version.

### 0.6

This version requires PHP 5.3.3+ and the Amazon Web Services plugin

## Changelog

### WP Offload Media Lite 3.2.5 - 2023-08-24

* New: WordPress 6.3 compatible
* New: PHP 8.2 compatible
* New: AWS PHP SDK has been updated to v3.279.0
* New: Google Cloud Storage SDK has been updated to v1.33.0 (requires PHP 7.4+)
* New: Links to plugin documentation, support, feedback, and changelog are now available in the footer of WP Admin
* Bug fix: Delivery settings check no longer logs false message about not being able to remove test object from bucket
* Bug fix: Delivery settings check no longer logs a "Could not get Block All Public Access status" message when using DigitalOcean Spaces

### WP Offload Media Lite 3.2.4 - 2023-06-26

* Bug fix: Newly added rotated images no longer fail to automatically offload

### WP Offload Media Lite 3.2.3 - 2023-06-13

* Bug fix: Broken legacy amazonS3_info metadata no longer causes upgrade routines to fail

### WP Offload Media Lite 3.2.2 - 2023-05-16

* Security: Updated AWS SDK and Google Cloud SDK to address a vulnerability in `guzzlehttp/psr7` as reported in [CVE-2023-29197](https://nvd.nist.gov/vuln/detail/CVE-2023-29197)

### WP Offload Media Lite 3.2.1 - 2023-03-27

* Bug fix: Saving settings no longer prevented when access credentials are missing but bucket is set
* Bug fix: Changing delivery provider no longer prevented when delivery settings are incomplete

### WP Offload Media Lite 3.2.0 - 2023-03-16

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-3-2-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Offload status can be seen in the navigation bar
* New: Diagnostic Info now includes media counts by item type
* New: Validation status for storage and delivery providers displayed on Media tab
* New: WordPress 6.2 compatible
* Improvement: Amazon S3 buckets are now created in a way compatible with AWS API changes due in April 2023
* Bug fix: Confirming set up of an Origin Access Identity for Object Ownership Enforcement in Bucket Security now works as expected
* Bug fix: Using an invalid storage provider identifier in defined settings no longer causes a fatal error
* Bug fix: Cropping offloaded images in the standard image block no longer fails
* Bug fix: Using Server Roles no longer needs a define in wp-config.php
* Bug fix: SVGs now automatically offload when added to the Media Library
* Bug fix: Legacy defines no longer show as keys used in the AS3CF_SETTINGS define

### WP Offload Media Lite 3.1.0 - 2022-12-07

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-3-1-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: PHP 8.1 compatibility is now improved
* New: Amazon S3 regions Asia Pacific (Hyderabad), EU (Spain), EU (Zurich) and Middle East (UAE) are now selectable
* New: Google Cloud Storage regions Middle East (Tel Aviv) and Dual-Region (Asia) are now selectable
* New: DigitalOcean region Sydney (SYD1) is now selectable
* New: AWS PHP SDK has been updated to v3.238.6
* New: Google Cloud Storage SDK has been updated to v1.28.1
* Improvement: Storage Provider regions are now listed alphabetically
* Improvement: Google Cloud Storage regions are now grouped by geographic area
* Bug fix: Pathless external URLs entered in a post no longer cause an error when saved
* Bug fix: Media upload time no longer degrades as the Media Library grows
* Bug fix: Periodic updates of media offload statistics no longer time out in large multisite installs
* Bug fix: The clear post meta cache upgrade routine now works properly on multisite subsites
* Bug fix: Completed upgrade routines no longer create redundant cron schedule entries
* Bug fix: Upgrade routines no longer try to run on non-primary subsites
* Bug fix: Custom table format upgrades no longer run unnecessarily
* Bug fix: Settings REST API endpoints are no longer enabled if unusable
* Bug fix: Only offloaded items with valid attachment IDs are retrieved from external cache
* Bug fix: Legacy bucket names that include uppercase letters or underscores can now be selected
* Bug fix: Offloading already-edited files no longer causes issues with filename uniqueness checks

### WP Offload Media Lite 3.0.2 - 2022-10-05

* Bug fix: ACF integration no longer breaks sites using closures in ACF field config (edge case)
* Bug fix: The Offload metabox for attachments no longer has issues for some browser and screen resolution combinations

### WP Offload Media Lite 3.0.0 - 2022-09-29

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-3-0-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Fresh new settings page UI! 🎉
* New: Advanced Custom Fields Lite & Pro now supported
* New: Authenticated WP-REST-API endpoints added for managing settings
* New: Amazon S3 Object Ownership controls are now supported
* New: Added Asia Pacific (Jakarta) Amazon S3 region
* New: Added Columbus, Dallas, Toronto, Santiago, Warsaw, Milan, Paris, Madrid, Delhi, Jakarta and Melbourne Google Cloud Storage regions
* New: AWS PHP SDK updated to v3.222.1
* New: Google Cloud Storage SDK updated to v1.26.3
* New: PHP 7.2 or later required
* New: WordPress 6.1 compatible
* Improvement: Settings changes by another user or in wp-config.php are detected and reflected in UI
* Improvement: A page refresh is requested if settings changes by another user conflict with unsaved changes
* Improvement: Important error notices on the settings page are less easy to miss when displayed
* Improvement: The Custom Domain (CNAME) can no longer be left empty if enabled
* Improvement: The format of the entered Custom Domain (CNAME) for a CDN is now validated before being able to save settings
* Improvement: It is no longer possible to mistake the example URL preview for an offloaded media item
* Improvement: The relationship between settings and the example URL preview is now easier to understand
* Improvement: Some common AWS S3 errors are caught and shown with easier to understand messages
* Improvement: Media settings can now be used even if previously defined Storage Provider credentials have been removed but bucket still set (useful for development or test installs cloned from large production sites)
* Bug fix: Saving posts with serialized data in post_content that include offloaded URLs now have correct string length values
* Bug fix: EC2 hosted sites using IAM Roles no longer get an error when retrieving credentials from the instance profile metadata service in some instances
* Bug fix: URLs are no longer rewritten for media offloaded to a different storage provider than currently configured
* Bug fix: Creating a bucket on DigitalOcean Spaces no longer fails with 403 Forbidden error
* Bug fix: Rewrite Media URLs can now be turned off for installs updated from pre WP Offload S3 1.0
* Bug fix: Legacy AWS access key defines no longer cause issues with upgrade routines
* Bug fix: Getting Block All Public Access status no longer fails for recently created buckets
* Bug fix: Block All Public Access warning notice no longer shows even though CloudFront set up
* Bug fix: Images with non-numeric width or height values no longer cause fatal errors

### WP Offload Media Lite 2.6.2 - 2022-04-04

* Bug fix: Upgrade routine no longer risks breaking items when external object cache is in use

### WP Offload Media Lite 2.6.1 - 2022-03-21

* Bug fix: Local files are no longer removed if as3cf_pre_upload_attachment filter is used to abort upload

### WP Offload Media Lite 2.6 - 2022-03-09

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-6-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: WP Offload Media is now compatible with WordPress 5.9 and Full Site Editing
* Improvement: Offloaded thumbnail sizes are now tracked for better handling of changes to registered sizes
* Improvement: Offloads and other storage provider actions are faster
* Bug fix: URL rewriting now works in the Full Site Editor
* Bug fix: Offloaded images are now shown when re-editing a Block Template or Template Part
* Bug fix: URL rewriting now works for Widgets migrated to a Widget Sidebar Block
* Bug fix: Objects are no longer left in the bucket when deleting a Media Library item with many changes to its thumbnail sizes

### WP Offload Media Lite 2.5.5 - 2021-07-19

* Bug fix: Signed GCS URLs broken when updating a post
* Bug fix: Incorrect mime type set on scaled image's bucket object when thumbnail format differs from original file's format
* Tested: WordPress 5.8

### WP Offload Media Lite 2.5.3 - 2021-03-03

* New: Added DigitalOcean region San Francisco 3
* Bug fix: Domain mapping not handled correctly when the local URL includes a port number
* Bug fix: In some unusual configurations the upgrade routine uses incorrect name for multisite blogs table
* Tested: WordPress 5.7

### WP Offload Media Lite 2.5.2 - 2020-12-14

* New: AWS PHP SDK 3.168.0
* New: Google Cloud Storage SDK 1.23.0
* Improvement: Faster saving of posts with many external links
* Improvement: Faster URL rewriting when Force HTTPS setting is being used but is not needed
* Bug fix: PHP Fatal error on the settings page when using PHP 8.0

### WP Offload Media Lite 2.5.1 - 2020-11-25

* New: WordPress 5.6 compatible
* New: PHP 8.0 compatible
* Bug fix: Unexpectedly asked to select bucket after saving settings when legacy access key named constants defined
* Bug fix: srcset missing for some images
* Bug fix: Error saving item during Metadata upgrade in some cases

### WP Offload Media Lite 2.5 - 2020-11-11

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-5-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* Improvement: [Error notice shown](https://deliciousbrains.com/wp-offload-media/doc/missing-table-error-notice/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) when plugin's required custom table(s) missing
* Improvement: [Diagnostic Info](https://deliciousbrains.com/wp-offload-media/doc/missing-table-error-notice/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting#diagnostic-info) shows status of plugin's required custom tables
* Bug fix: wp_get_original_image_path function does not return provider URL when local files removed
* Bug fix: File missing notices recorded in debug.log when regenerating thumbnails and Remove Files From Server turned on

### WP Offload Media Lite 2.4.4 - 2020-09-08

* Improvement: Updated AWS PHP SDK to v3.151.6
* Bug fix: Files for duplicate thumbnail sizes not removed from server after initial offload
* Bug fix: PHP Fatal error: Class 'DeliciousBrains\WP_Offload_Media\Aws3\Symfony\Polyfill\Intl\Idn\Idn' not found
* Bug fix: PHP Recoverable fatal error: Object of class WP_Error could not be converted to string in .../wp-includes/post.php on line 504
* Bug fix: PHP message: PHP Warning: is_readable(): open_basedir restriction in effect
* Bug fix: URLs not rewritten for RSS feed enclosures

### WP Offload Media Lite 2.4.3 - 2020-09-01

* Improvement: Updated AWS PHP SDK to v3.151.3
* Bug fix: PHP Fatal error: Class 'DeliciousBrains\WP_Offload_Media\Aws3\Symfony\Polyfill\Intl\Idn\Idn' not found

### WP Offload Media Lite 2.4.2 - 2020-08-27

* Improvement: Updated AWS PHP SDK to v3.150.1
* Bug fix: Image thumbnail URLs in custom HTML not rewritten to delivery provider URLs
* Bug fix: Background processes do not start when PHP memory limit in gigabytes
* Bug fix: PHP Fatal error: require(): Failed opening required '.../vendor/Aws3/Aws/Sts/StsClient.php'
* Bug fix: AWS SDK "Warning: is_readable(): open_basedir restriction in effect" message from Regional Endpoint check
* Bug fix: Bottom and right button borders in settings page are clipped when focused

### WP Offload Media Lite 2.4.1 - 2020-07-21

* Bug fix: Fatal Error with EWWW Image Optimizer 5.5 or earlier installed
* Bug fix: AWS SDK "Warning: is_readable(): open_basedir restriction in effect" message when Use ARN Region in effect
* Bug fix: "Data you have entered may not be saved" notice shown incorrectly when leaving settings page

### WP Offload Media Lite 2.4 - 2020-07-14

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-4-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Setting to choose a Delivery Provider (i.e. S3, CloudFront, another CDN)
* New: Support for Block All Public Access setting on S3 buckets
* New: Raw S3 URLs use bucket in domain rather than path where possible as per changes required by AWS
* New: Raw S3 URLs use dot rather than dash between s3 and region name as per changes required by AWS
* New: Added S3 regions for Africa (Cape Town), Asia Pacific (Hong Kong), EU (Milan), EU (Stockholm), and Middle East (Bahrain)
* New: Added GCS regions for Salt Lake City, Las Vegas, Zürich, Osaka, Seoul, EUR4 (dual-region), and NAM4 (dual-region)
* Improvement: Updated AWS PHP SDK to v3.133.40
* Improvement: Updated Google Cloud Storage SDK to v1.18.0
* Improvement: S3 regions for China no longer hidden (configuration via AWS Console still required)
* Bug fix: Private images not showing in grid mode Media page overlay
* Bug fix: Public image thumbnails not showing in grid mode Media page when original is private
* Bug fix: URL rewriting sometimes fails for sites hosted on Windows
* Bug fix: URL rewrite fails for image when original upload has size in file name
* Bug fix: External URLs rewritten to local URLs when they shouldn't
* Bug fix: StreamWrappers don't work with private buckets
* Bug fix: Database error when inserting media library item and using HyperDB
* Bug fix: S3 bucket link in settings no longer goes direct to bucket contents
* Bug fix: New uploads slow with very large Media Library
* Bug fix: Migration to custom table very slow with large postmeta tables
* Bug fix: Signed GCS URLs have incorrect expires value
* Bug fix: The use-server-roles AS3CF_SETTINGS value is not properly reflected in Diagnostic Info
* Bug fix: Unknown column '2019/12/some-file-name.pdf' in 'where clause' when using managed MySQL on DigitalOcean
* Bug fix: WordPress database error Expression #1 of ORDER BY clause is not in SELECT list when using MySQL8
* Bug fix: WordPress forces HTTP in Edit Media page if site is not HTTPS, breaking remote URLs that require HTTPS
* Tested: WordPress 5.5

### WP Offload Media Lite 2.3.2 - 2019-12-09

* Improvement: Reduced database queries when external object cache available
* Bug fix: Uncaught Error: Call to undefined function DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\choose_handler()
* Bug fix: SVG files not automatically offloaded
* Tested: PHP 7.4

### WP Offload Media Lite 2.3.1 - 2019-11-19

* Bug fix: Uncaught Error: Cannot use object of type Media_Library_Item as array in wp-includes/media.php:217
* Bug fix: Image not automatically offloaded if subsizes not expected

### WP Offload Media Lite 2.3 - 2019-11-12

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-3-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Upgrade routine to migrate offload data to custom table
* New: Support for changed Media Library upload process introduced with WordPress 5.3
* New: Support for new "-scaled" and "-rotated" images introduced with WordPress 5.3
* New: Support for customizer changes introduced with WordPress 5.3
* New: Offload new "original_image" file introduced with WordPress 5.3
* Improvement: Performance boost during both page display and save
* Improvement: Better detection of offloaded media URLs during page display
* Bug fix: New Media Library upload given same local file name as offloaded and removed file after Remove Files From Server turned off
* Bug fix: PHP message: PHP Deprecated: strpos(): Non-string needles will be interpreted as strings in the future

### WP Offload Media Lite 2.2.1 - 2019-07-18

* Improvement: Menu option and settings page title now include "Lite"
* Improvement: Remove Files From Server option now warns about media backups when switched on
* Bug fix: Undefined index in file amazon-s3-and-cloudfront/classes/filters/as3cf-local-to-s3.php at line 286

### WP Offload Media Lite 2.2 - 2019-06-10

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-2-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Use IAM Roles without having to update wp-config.php
* New: Frankfurt (FRA1) region now supported on DigitalOcean Spaces
* Improvement: WP dashboard performance
* Bug fix: Uploaded media files with uppercase extensions get second extension added

### WP Offload Media Lite 2.1.1 - 2019-04-29

* New: Multisite domain mapping via WordPress MU Domain Mapping plugin is now supported
* Improvement: Local to Provider content filtering performance improvements
* Improvement: Warning notice shown when changing storage provider and media already offloaded
* Bug fix: Media title not retaining characters stripped from filename
* Bug fix: Warning: is_readable(): open_basedir restriction in effect. File(~/.aws/config) is not within the allowed path(s)
* Bug fix: Fatal error when GCS Key File not accessible
* Bug fix: Non-image offloads on subsites with 4 digit IDs get duplicate subsite ID in bucket path
* Bug fix: No srcset added to img tag if filename includes non-ASCII characters
* Bug fix: Full size image URL saved to img tag src attribute when thumbnail picked if filename includes non-ASCII characters

### WP Offload Media Lite 2.1 - 2019-03-05

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-media-2-1-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: Google Cloud Storage is now supported
* Improvement: AWS PHP SDK updated
* Improvement: Diagnostic Info shows more complete settings information
* Bug fix: Year/Month path prefix incorrectly set in bucket for non-image media files
* Bug fix: PHP Fatal error: Class 'XMLWriter' not found
* Bug fix: PHP Fatal error: Uncaught Error: Call to undefined method ...\Aws3\Aws\S3\Exception\S3Exception::search() in .../classes/providers/aws-provider.php:439
* Bug fix: PHP Warning: filesize(): stat failed for [file-path] in classes/amazon-s3-and-cloudfront.php on line 1309

### WP Offload Media Lite 2.0.1 - 2018-12-17

* Improvement: Streamlined UI for setting Storage Provider and Bucket
* Bug fix: On/Off switches in settings look reversed
* Bug fix: Latest upgrade routine runs on fresh install
* Bug fix: Defined settings still found in database
* Bug fix: More Info links in Storage Provider settings incorrect
* Tested: WordPress 5.0

### WP Offload Media Lite 2.0 - 2018-09-24

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-s3-is-now-wp-offload-media-and-adds-support-for-digitalocean-spaces/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: DigitalOcean Spaces is now supported
* New: Plugin name updated from WP Offload S3 Lite to WP Offload Media Lite
* Improvement: More logical UI layout and better description of each setting

### WP Offload S3 Lite 1.4.3 - 2018-08-02

* Bug fix: Images remotely edited via stream wrapper sometimes set as private on S3

### WP Offload S3 Lite 1.4.2 - 2018-07-03

* Bug fix: Error getting bucket region
* Bug fix: Child themes with missing or broken style.css break diagnostic info

### WP Offload S3 Lite 1.4.1 - 2018-06-21

* Bug fix: Incorrect filesize saved to metadata when image removed from local server

### WP Offload S3 Lite 1.4 - 2018-06-12

* New: Using AWS PHP SDK v3
* New: Requires PHP 5.5+
* Improvement: Supported AWS S3 regions updated and names changed to match current AWS nomenclature
* Bug fix: PHP Warning: Declaration of AS3CF_Stream_Wrapper::register should be compatible with Aws\S3\StreamWrapper::register
* Bug fix: File size not stored in _wp_attachment_metadata for audio/video files
* Bug fix: Image srcset uses full size image if metadata size is stored as string
* Bug fix: PHP Warning: preg_match() expects parameter 2 to be string, array given
* Bug fix: SQL syntax error when using `attachment_url_to_postid()` with non-ascii file name
* Tested: WordPress 4.9.6
* Tested: Gutenberg 3.0

### WP Offload S3 Lite 1.3.2 - 2018-02-22

* Bug fix: Fatal error: Uncaught Error: Call to undefined method Composer\Autoload\ClassLoader::setClassMapAuthoritative()
* Bug fix: AWS keys stored in database by Amazon Web Services plugin are not being migrated to new settings record
* Bug fix: Notice in settings page that Amazon Web Services plugin no longer required is not being shown when Amazon Web Services active

### WP Offload S3 Lite 1.3.1 - 2018-02-20

* Bug fix: Fatal error in stream wrapper setup when AWS Keys not set

### WP Offload S3 Lite 1.3 - 2018-02-20

* [Release Summary Blog Post](https://deliciousbrains.com/wp-offload-s3-1-6-released/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* New: [Amazon Web Services plugin](https://wordpress.org/plugins/amazon-web-services/) is no longer required to run WP Offload S3 Lite
* New: Added [`as3cf_local_domains`](https://deliciousbrains.com/wp-offload-s3/doc/filtering-urls-for-multiple-domains/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting) filter for when site content is updated through multiple domains
* Improvement: AWS keys can be set in new "Settings" tab if not defined in wp-config.php
* Improvement: Minor tweaks to settings page UI including right aligning tabs and consistent title across Lite and Pro plugins
* Improvement: cURL version, theme version and parent theme information added to diagnostics
* Bug fix: Incompatible with plugins that include AWS PHP SDK v3
* Bug fix: Regenerate Thumbnails v3.0+ does not work with Remove Files From Server option
* Bug fix: "Warning: count(): Parameter must be an array or an object that implements Countable" error with PHP 7.2
* Bug fix: Force HTTPS not being applied to non-srcset S3 URLs in pages served over HTTP
* Bug fix: Content URLs not filtered to S3 URLs if AWS keys not set
* Bug fix: URL preview can be coerced to display invalid URL
* Bug fix: Changes to upload made via `as3cf_object_meta` filter are not reflected in amazonS3_info records
* Bug fix: Settings link not showing in network admin plugins page
* Bug fix: License in composer.json fails Packagist validation

### WP Offload S3 Lite 1.2.1 - 2017-11-20

* New: Compatibility with HTML Widget
* New: Dismissible admin notice that WP Offload S3 Lite will soon require PHP 5.5+
* Improvement: Compatibility with WordPress 4.9
* Bug fix: Incorrect region used when changing bucket by defining it in WPOS3_SETTINGS
* Bug fix: Media library notices render inside the upload tool
* Bug fix: Save notices disappear on settings page
* Bug fix: Improper use of jQuery.attr logged to browser console
* Bug fix: "Content Filtering Upgrade" URL in notice incorrect
* Bug fix: "More info" links can be broken across two lines

### WP Offload S3 Lite 1.2 - 2017-06-19

* New: Compatibility with WordPress 4.8
* New: Support for WP CLI `wp media regenerate`
* Improvement: Intermediate image sizes are now passed through the `as3cf_object_meta` filter
* Improvement: Content filtering cache now uses the external object when available
* Bug fix: Timeouts on large multisite installs due to excessive database queries on upgrade routines
* Bug fix: Video files with private ACL not working with WordPress's default media player
* Bug fix: Bucket permissions check not using configured path
* Bug fix: WordPress image editor sometimes shows a 404 when 'Remove Files From Server' enabled
* Bug fix: Notice: Undefined index: region

### WP Offload S3 Lite 1.1.6 - 2017-03-13

* New: Compatibility with [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)
* New: `as3cf_filter_post_local_to_s3` and `as3cf_filter_post_s3_to_local` filters added for filtering S3 URLs in custom content
* Improvement: Ensure files uploaded using `media_handle_sideload` have unique filename on S3 when 'Remove Files From Server' enabled
* Bug fix: Files uploaded to S3 with empty filenames when the filename started with non-latin characters
* Bug fix: Audio files with private ACL not working with WordPress's default media player
* Bug fix: S3 API version not passed to S3 client
* Bug fix: Content added to text widgets via the Customizer not saved
* Bug fix: Original file not removed locally when cropped via the Customizer and 'Remove Files From Server' enabled
* Bug fix: Incorrect Media Library URLs saved to the database when WordPress installed in a subdirectory

### WP Offload S3 Lite 1.1.5 - 2017-01-12

* Improvement: Filter custom CSS - S3 URLs will no longer be saved to the database
* Bug fix: PDF previews have incorrect MIME type
* Bug fix: Original PDF not removed from S3 on attachment delete when image previews exist

### WP Offload S3 Lite 1.1.4 - 2016-12-13

* New: Upgrade routine to replace all S3 URLs in post excerpts with local URLs
* Improvement: Performance improvements
* Improvement: Allow expires time to be filtered for private content using the `as3cf_expires` filter
* Bug fix: Image `srcset` not correctly applied when file names contain special characters

### WP Offload S3 Lite 1.1.3 - 2016-11-28

* Bug fix: Private URL signing params stripped in some circumstances
* Improvement: Performance improvements for URL filtering, especially on large sites

### WP Offload S3 Lite 1.1.2 - 2016-11-02

* Improvement: Better content filtering support for third party plugins and themes
* Bug fix: PHP Warning: Division by zero

### WP Offload S3 Lite 1.1.1 - 2016-10-17

* New: Filter post excerpts - S3 URLs will no longer be saved to the database
* Bug fix: PHP 5.3 Fatal error: Using $this when not in object context
* Bug fix: Query string parameters incorrectly encoded for Media Library items

### WP Offload S3 Lite 1.1 - 2016-09-29

* New: Filter post content. S3 URLs will no longer be saved to the database
* New: Upgrade routine to replace all S3 URLs in content with local URLs
* New: Support for theme custom logos
* New: Control the ACL for intermediate image sizes using the `as3cf_upload_acl_sizes` filter
* Bug fix: File names containing special characters double encoded
* Bug fix: `srcset` not working for file names containing special characters
* Bug fix: Incorrect placeholder text for 'Path' option
* Bug fix: Objects in root of bucket not deleted when removed from the Media Library
* Bug fix: No longer use deprecated functions in WordPress 4.6
* Bug fix: Don't delete local file when 'Remove Files From Server' enabled and upload to S3 fails

### WP Offload S3 Lite 1.0.5 - 2016-09-01

* New: Compatibility with WordPress 4.6
* Improvement: No longer delete plugin data on uninstall. Manual removal possible, as per this [doc](https://deliciousbrains.com/wp-offload-s3/doc/uninstall/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)

### WP Offload S3 Lite 1.0.4 - 2016-05-30

* New: Now using simpler Force HTTPS setting, removed redundant Always Use HTTP setting
* New: `as3cf_cloudfront_path_parts` filter allows changing served CloudFront path (useful when distribution pulls subdirectory)
* Improvement: Better compatibility with non-standard notices from other plugins and themes
* Improvement: Added basic auth and proxy info to diagnostic info
* Improvement: Added `allow_url_fopen` status to diagnostic info
* Improvement: Added memory usage to diagnostic info
* Improvement: Ensure notice text is 800px or less in width
* Improvement: Reduced database queries on settings screen
* Bug fix: Properly handle _wp_attachment_data metadata when it is a serialized WP_Error

### WP Offload S3 Lite 1.0.3 - 2016-03-23

* Bug fix: Don't replace srcset URLs when Rewrite File URLs option disabled
* Bug fix: Fatal error: Cannot redeclare as3cf_get_secure_attachment_url()

### WP Offload S3 Lite 1.0.2 - 2016-03-08

* Bug fix: Uninstall would run even if pro plugin installed

### WP Offload S3 Lite 1.0.1 - 2016-03-08

* Bug fix: Fatal error on plugin activation
* Bug fix: Unable to activate Pro upgrade

### WP Offload S3 Lite 1.0 - 2016-03-07

* New: Plugin renamed to "WP Offload S3 Lite"
* New: Define any and all settings with a constant in wp-config.php
* New: Documentation links for each setting
* Improvement: Simplified domain setting UI
* Improvement: Far future expiration header set by default
* Improvement: Newly created bucket now immediately appears in the bucket list
* Improvement: Cleanup user meta on uninstall
* Improvement: WP Retina 2x integration removed
* Bug fix: Year/Month folder structure on S3 not created if the 'Organise my uploads into month and year-based folders' WordPress setting is disabled
* Bug fix: Responsive srcset PHP notices
* Bug fix: Compatibility addon notices displayed to non-admin users
* Bug fix: Potential PHP fatal error in MySQL version check in diagnostic log
* Bug fix: Missing image library notices displaying before plugin is setup

### WP Offload S3 0.9.12 - 2016-02-03

* Improvement: Compatibility with WP Offload S3 Assets 1.1
* Bug fix: Object versioned responsive images in post content not working when served from S3 on WordPress 4.4+

### WP Offload S3 0.9.11 - 2015-12-19

* Bug fix: Responsive images in post content not working when served from S3
* Bug fix: Responsive images using wrong image size when there are multiple images with the same width

### WP Offload S3 0.9.10 - 2015-11-26

* Improvement: Support for responsive images in WP 4.4
* Bug fix: Incorrect file path for intermediate image size files uploaded to S3 with no prefix
* Bug fix: Thumbnail previews return 404 error during image edit screen due to character encoding

### WP Offload S3 0.9.9 - 2015-11-12

* Improvement: Improve wording of compatibility notices
* Improvement: Compatibility with Easy Digital Downloads 1.0.1 and WooCommerce 1.0.3 addons
* Improvement: Better determine available memory for background processes
* Bug fix: URL previews incorrect due to stripping `/` characters
* Bug fix: PHP Warning: stream_wrapper_register(): Protocol s3:// is already defined
* Bug fix: PHP Fatal error:  Call to undefined method WP_Error::get()

### WP Offload S3 0.9.8 - 2015-11-02

* Bug fix: Attachment URLs containing query string parameters incorrectly encoded

### WP Offload S3 0.9.7 - 2015-10-26

* Improvement: Improve compatibility with third party plugins when the _Remove Files From Server_ option is enabled
* Improvement: Fix inconsistent spacing on the WP Offload S3 settings screen
* Improvement: Validate _CloudFront or custom domain_ input field
* Improvement: Link to current S3 bucket added to WP Offload S3 settings screen
* Improvement: Show notice when neither GD or Imagick image libraries are not installed
* Improvement: Supply Cache-Control header to S3 when the _Far Future Expiration Header_ option is enabled
* Improvement: Additional information added to _Diagnostic Information_
* Improvement: Added warning when _Remove Files From Server_ option is enabled
* Improvement: Filter added to allow additional image versions to be uploaded to S3
* Bug fix: File size not stored in _wp_attachment_metadata_ when _Remove Files From Server_ option is enabled
* Bug fix: Uploads on Multisite installs allowed after surpassing upload limit
* Bug fix: Site icon in WordPress customizer returns 404
* Bug fix: Image versions remain locally and on S3 after deletion, when the file name contains characters which require escaping
* Bug fix: Files with the same file name overwritten when __Remove Files From Server_ option is enabled
* Bug fix: Cron tasks incorrectly scheduled due to passing the wrong time to `wp_schedule_event`
* Bug fix: Default options not shown in the UI after first install

### WP Offload S3 0.9.6 - 2015-10-01

* Improvement: Update text domains for translate.wordpress.org integration

### WP Offload S3 0.9.5 - 2015-09-01

* Bug fix: Fatal error: Cannot use object of type WP_Error as array

### WP Offload S3 0.9.4 - 2015-08-27

* New: Update all existing attachments with missing file sizes when the 'Remove Files From Server' option is enabled (automatically runs in the background)
* Improvement: Show when constants are used to set bucket and region options
* Improvement: Don't show compatibility notices on plugin update screen
* Improvement: On Multisite installs don't call `restore_current_blog()` on successive loop iterations
* Bug fix: 'Error getting URL preview' alert shown when enter key pressed on settings screen
* Bug fix: Unable to crop header images when the 'Remove Files From Server' option is enabled
* Bug fix: Incorrect storage space shown on Multisite installs when the 'Remove Files From Server' option is enabled
* Bug fix: Upload attempted to non existent bucket when defined by constant
* Bug fix: 'SignatureDoesNotMatch' error shown when using signed URLs with bucket names containing '.' characters

### WP Offload S3 0.9.3 - 2015-08-17

* New: Pro upgrade sidebar
* Bug fix: Create buckets in US standard region causing S3 URLs to 404 errors

### WP Offload S3 0.9.2 - 2015-07-29

* Bug fix: Accidentally released the sidebar for after we launch the pro version

### WP Offload S3 0.9.1 - 2015-07-29

* Improvement: Access denied sample IAM policy replaced with link to [Quick Start Guide](https://deliciousbrains.com/wp-offload-s3/doc/quick-start-guide/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* Improvement: Access denied messages on bucket selection or bucket creation now link to [Quick Start Guide](https://deliciousbrains.com/wp-offload-s3/doc/quick-start-guide/?utm_campaign=changelogs&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting)
* Improvement: Object expires time can now be filtered using the `as3cf_object_meta` filter
* Bug fix: Error not always shown when S3 bucket inaccessible due to incorrect permissions
* Bug fix: Permission checks fail when S3 bucket is in a non-default region and defined by `AS3CF_BUCKET` constant
* Bug fix: Restore `as3cf_get_attached_file_copy_back_to_local` filter
* Bug fix: Image versions not uploaded to S3 when an edited image is restored
* Bug fix: Original image version not deleted from server when _Remove Files From Server_ option enabled
* Bug fix: Media library items with non-ascii characters in the file name are not removed from S3
* Bug fix: Compatibility notices shown on plugin install pages
* Bug fix: WordPress footer overlaps WP Offload S3 sidebar
* Bug fix: Upon initial setup the settings changed alert shows when no settings have changed

### WP Offload S3 0.9 - 2015-07-08

* New: Plugin rebranded to WP Offload S3
* New: Support tab added to _Offload S3_ screen containing diagnostic information
* New: Compatibility with the [Media Replace](https://wordpress.org/plugins/enable-media-replace/) plugin
* New: Select bucket region when creating a new bucket
* New: Toggle switches redesigned
* Improvement: Compatibility with release candidate of Pro plugin
* Improvement: Example IAM policy more secure
* Improvement: Set default bucket region using the `AS3CF_REGION` constant
* Improvement: Added `as3cf_object_meta` filter for developers
* Improvement: Bucket selection moved to modal window
* Improvement: Don't allow bucket names to contain invalid characters on creation
* Improvement: More verbose error messages on bucket selection
* Improvement: Settings link added to plugin row on _Plugins_ screen
* Improvement: Object versioning enabled by default
* Improvement: Uninstall routines added
* Improvement: JavaScript coding standards
* Improvement: Cache result when checking S3 bucket permissions
* Bug fix: Bucket region errors result in blank WP Offload S3 screen
* Bug fix: Editing an image when _Remove Files From Server_ option is enabled results in error
* Bug fix: Metadata upgrade procedure triggered on new installs
* Bug fix: File URLs when uploaded to a subdirectory result in incorrect S3 URLs
* Bug fix: Errors logged when trying to delete non-existent HiDPI images
* Bug fix: SignatureDoesNotMatch errors on regions with v4 authentication
* Bug fix: Customizer background image not editable
* Bug fix: Error when creating buckets with US Standard region
* Bug fix: Notices appearing incorrectly on some admin screens
* Bug fix: Subsite upload paths repeated on multisite installs
* Bug fix: Handle multisite installs where `BLOG_ID_CURRENT_SITE` is not 1

### WP Offload S3 0.8.2 - 2015-01-31

* New: Input bucket in settings to avoid listing all buckets
* New: Specify bucket with 'AS3CF_BUCKET' constant
* Improvement: Compatibility with beta release of Pro plugin
* Bug Fix: Incorrect file prefix in S3 permission check

### WP Offload S3 0.8.1 - 2015-01-19

* Bug Fix: Permission problems on installs running on EC2s
* Bug Fix: Blank settings page due to WP_Error on S3 permission check
* Bug Fix: Warning: strtolower() expects parameter 1 to be string, object given
* Bug Fix: Region post meta update running on subsites of Multisite installs

### WP Offload S3 0.8 - 2015-01-10

* New: Redesigned settings UI
* Improvement: SSL setting can be fully controlled, HTTPS for urls always, based on request or never
* Improvement: Download files from S3 that are not found on server when running Regenerate Thumbnails plugin
* Improvement: When calling `get_attached_file()` and file is missing from server, return S3 URL
* Improvement: Code cleanup to WordPress coding standards
* Bug Fix: Files for all subsites going into the same S3 folder on multisite installs setup prior to WP 3.5
* Bug Fix: 'attempting to access local file system' error for some installs

### WP Offload S3 0.7.2 - 2014-12-11

* Bug: Some buckets in the EU region causing permission and HTTP errors
* Bug: Undefined variable: message in view/error.php also causing white screens

### WP Offload S3 0.7.1 - 2014-12-05

* Bug: Read-only error on settings page sometimes false positive

### WP Offload S3 0.7 - 2014-12-04

* New: Proper S3 region subdomain in URLs for buckets not in the US Standard region (e.g. https://s3-us-west-2.amazonaws.com/...)
* New: Update all existing attachment meta with bucket region (automatically runs in the background)
* New: Get secure URL for different image sizes (iamzozo)
* New: S3 bucket can be set with constant in wp-config.php (dberube)
* New: Filter for allowing/disallowing file types: `as3cf_allowed_mime_types`
* New: Filter to cancel upload to S3 for any reason: `as3cf_pre_update_attachment_metadata`
* New: Sidebar with email opt-in
* Improvement: Show warning when S3 policy is read-only
* Improvement: Tooltip added to clarify option
* Improvement: Move object versioning option to make it clear it does not require CloudFront
* Improvement: By default only allow file types in `get_allowed_mime_types()` to be uploaded to S3
* Improvement: Compatibility with WPML Media plugin
* Bug Fix: Edited images not removed on S3 when restoring image and IMAGE_EDIT_OVERWRITE true
* Bug Fix: File names with certain characters broken not working
* Bug Fix: Edited image uploaded to incorrect month folder
* Bug Fix: When creating a new bucket the bucket select box appears empty on success
* Bug Fix: SSL not working in regions other than US Standard
* Bug Fix: 'Error uploading' and 'Error removing local file' messages when editing an image
* Bug Fix: Upload and delete failing when bucket is non-US-region and bucket name contains dot
* Bug Fix: S3 file overwritten when file with same name uploaded and local file removed (dataferret)
* Bug Fix: Manually resized images not uploaded (gmauricio)

### WP Offload S3 0.6.1 - 2013-09-21

* WP.org download of Amazon Web Services plugin is giving a 404 Not Found, so directing people to download from Github instead

### WP Offload S3 0.6 - 2013-09-20

* Complete rewrite
* Now requires PHP 5.3.3+
* Now requires the [Amazon Web Services plugin](http://wordpress.org/extend/plugins/amazon-web-services/) which contains the latest PHP libraries from Amazon
* Now works with multisite
* New Option: Custom S3 object path
* New Option: Always serve files over https (SSL)
* New Option: Enable object versioning by appending a timestamp to the S3 file path
* New Option: Remove uploaded file from local filesystem once it has been copied to S3
* New Option: Copy any HiDPI (@2x) images to S3 (works with WP Retina 2x plugin)

### WP Offload S3 0.5 - 2013-01-29

* Forked [Amazon S3 for WordPress with CloudFront](http://wordpress.org/extend/plugins/tantan-s3-cloudfront/)
* Cleaned up the UI to fit with today's WP UI
* Fixed issues causing error messages when WP_DEBUG is on
* [Delete files on S3 when deleting WP attachment](https://github.com/deliciousbrains/wp-amazon-s3-and-cloudfront/commit/e777cd49a4b6999f999bd969241fb24cbbcece60)
* [Added filter to the get_attachment_url function](https://github.com/deliciousbrains/wp-amazon-s3-and-cloudfront/commit/bbe1aed5c2ae900e9ba1b16ba6806c28ab8e2f1c)
* [Added function to get a temporary, secure download URL for private files](https://github.com/deliciousbrains/wp-amazon-s3-and-cloudfront/commit/11f46ec2714d34907009e37ad3b97f4421aefed3)

