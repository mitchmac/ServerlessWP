#!/bin/bash

cd ..
mkdir temp
cd temp
wget https://wordpress.org/latest.zip
unzip latest.zip
cp ../wp/wp-config.php wordpress/
mkdir wordpress/wp-content/mu-plugins
cp ../wp/wp-content/mu-plugins/serverlesswp.php wordpress/wp-content/mu-plugins/
rm -rf wordpress/wp-content/plugins/akismet wordpress/wp-content/plugins/hello.php
rm -rf wordpress/wp-content/themes/twentytwentytwo wordpress/wp-content/themes/twentytwentyone
wget https://downloads.wordpress.org/plugin/amazon-s3-and-cloudfront.zip
unzip amazon-s3-and-cloudfront.zip
mv amazon-s3-and-cloudfront wordpress/wp-content/plugins/
git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git
cp -rL sqlite-database-integration/packages/plugin-sqlite-database-integration wordpress/wp-content/plugins/sqlite-database-integration
rm -rf sqlite-database-integration
git clone https://github.com/pingcap/wordpress-tidb-plugin.git
wget https://downloads.wordpress.org/plugin/tidb-compatibility.zip
unzip tidb-compatibility
mv tidb-compatibility wordpress/wp-content/plugins/
rm -rf ../wp
mv wordpress ../wp
cd ..
rm -rf temp