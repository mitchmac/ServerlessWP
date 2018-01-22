#!/bin/bash
read -p "Are you sure? This will copy new files into the wp directory. " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    curl -O https://wordpress.org/latest.zip
    unzip latest.zip
    rm latest.zip
    mv wordpress/* wp/
    rm -rf wordpress

    curl -O https://downloads.wordpress.org/plugin/amazon-web-services.latest-stable.zip
    unzip amazon-web-services.latest-stable.zip
    rm amazon-web-services.latest-stable.zip
    mv amazon-web-services wp/wp-content/plugins/

    curl -O https://downloads.wordpress.org/plugin/amazon-s3-and-cloudfront.latest-stable.zip
    unzip amazon-s3-and-cloudfront.latest-stable.zip
    rm amazon-s3-and-cloudfront.latest-stable.zip
    mv amazon-s3-and-cloudfront wp/wp-content/plugins/

    curl -OL https://github.com/mitchmac/ServerlessWP-plugin/archive/master.zip
    unzip master.zip
    rm master.zip
    mv ServerlessWP-plugin-master wp/wp-content/plugins/serverlesswp

    cp wp/wp-content/plugins/serverlesswp/assets/wp-config-base.php wp/wp-config.php

   php -r '$salts = file_get_contents("https://api.wordpress.org/secret-key/1.1/salt/");
    $config = file_get_contents("wp/wp-config.php");
    $config = str_replace("// replace-salt", $salts, $config);
    file_put_contents("wp/wp-config.php", $config);'
fi
