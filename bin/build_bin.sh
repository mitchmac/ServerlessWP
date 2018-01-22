#!/bin/sh

docker build -t lambda-php7 .
container=$(docker create lambda-php7)
docker -D cp $container:/work/php-7-bin/bin/php-cgi ./php-cgi
docker -D cp $container:/usr/bin/wget ./wget
docker -D cp $container:/usr/lib64/libcrypto.so.1.0.2k ./lib/libcrypto.so.10
docker rm $container
