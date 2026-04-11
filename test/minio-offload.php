<?php
/*
 * Test-only mu-plugin: routes WP Offload Media through MinIO.
 * Activated only when S3_OFFLOAD_ENDPOINT / S3_OFFLOAD_PUBLIC_DOMAIN env vars are set.
 */

if (!empty($_ENV['S3_OFFLOAD_ENDPOINT'])) {
    add_filter('as3cf_aws_s3_client_args', function ($args) {
        $args['endpoint'] = $_ENV['S3_OFFLOAD_ENDPOINT'];
        $args['use_path_style_endpoint'] = true;
        return $args;
    });
    // MinIO test server is plain HTTP, not HTTPS.
    add_filter('as3cf_use_ssl', '__return_false');
}

if (!empty($_ENV['S3_OFFLOAD_PUBLIC_DOMAIN'])) {
    add_filter('as3cf_aws_s3_url_domain', function ($domain, $bucket) {
        return $_ENV['S3_OFFLOAD_PUBLIC_DOMAIN'] . '/' . $bucket;
    }, 10, 2);
}
