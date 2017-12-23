#!/bin/sh

docker build -t lambda-php7 .
container=$(docker create lambda-php7)
docker -D cp $container:/work/php-7-bin/bin/php-cgi ./php-cgi
docker rm $container
