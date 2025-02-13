#!/bin/bash

./build-test.sh

docker run -e FQDBDIR=/tmp/db -p 9000:8080 -d --name serverlesswp-test serverlesswp-test

curl -s -o /dev/null -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/installer.php"}'

curl -s -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/"}'


docker stop serverlesswp-test
docker rm serverlesswp-test