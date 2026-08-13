#!/bin/bash

cd ..
mkdir temp
cd temp
wget https://cn.wordpress.org/latest-zh_CN.zip
unzip latest-zh_CN.zip
cp ../wp/wp-config.php wordpress/
cp ../wp/wp-content/languages/plugins/asgaros-forum-zh_CN.po wordpress/
cp ../wp/wp-content/languages/plugins/asgaros-forum-zh_CN.mo wordpress/
cp ../wp/wp-content/languages/plugins/asgaros-forum-zh_CN.l10n.php wordpress/
mkdir wordpress/wp-content/mu-plugins
cp ../wp/wp-content/mu-plugins/serverlesswp.php wordpress/wp-content/mu-plugins/
wget https://downloads.wordpress.org/plugin/amazon-s3-and-cloudfront.zip
unzip amazon-s3-and-cloudfront.zip
mv amazon-s3-and-cloudfront wordpress/wp-content/plugins/
git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git
cp -rL sqlite-database-integration/packages/plugin-sqlite-database-integration wordpress/wp-content/plugins/sqlite-database-integration
rm -rf sqlite-database-integration
wget https://downloads.wordpress.org/plugin/tidb-compatibility.zip
unzip tidb-compatibility
mv tidb-compatibility wordpress/wp-content/plugins/
rm -rf wordpress/wp-content/plugins/hello.php
rm -rf wordpress/wp-content/themes/twentytwentytwo wordpress/wp-content/themes/twentytwentyone
rm -rf wordpress/wp-content/themes/twentytwentythree wordpress/wp-content/themes/twentytwentyfour
rm -rf wordpress/wp-content/themes/twentytwenty wordpress/wp-content/themes/twentytwentyfive

wget https://downloads.wordpress.org/plugin/simple-cloudflare-turnstile.zip
unzip simple-cloudflare-turnstile.zip
mv simple-cloudflare-turnstile wordpress/wp-content/plugins/

wget https://downloads.wordpress.org/plugin/yctvn-media-offload-cloudflare-r2.1.0.2.zip
unzip yctvn-media-offload-cloudflare-r2.1.0.2.zip
mv yctvn-media-offload-cloudflare-r2 wordpress/wp-content/plugins/

wget https://downloads.wordpress.org/plugin/asgaros-forum.3.4.0.zip
unzip asgaros-forum.3.4.0.zip
mv asgaros-forum wordpress/wp-content/plugins/

wget https://github.com/solstice23/argon-theme/releases/download/v1.3.5/argon.zip
unzip argon.zip
mv argon wordpress/wp-content/themes/

rm -rf ../wp
mv wordpress ../wp
cd ..
rm -rf temp
