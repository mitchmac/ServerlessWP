#!/bin/bash

./build-test.sh

docker run -p 9000:8080 -d -e FQDBDIR='/tmp/db' --name serverlesswp-test serverlesswp-test

curl -s -o /dev/null -w "%{http_code}" -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/installer.php"}'

curl -s -o /dev/null -w "%{http_code}" -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/"}'

docker stop serverlesswp-test
docker rm serverlesswp-test