#!/bin/bash

./build-test.sh

docker network create serverlesswp-test-network

# Start MariaDB container
docker run -d --name mariadb \
    --network serverlesswp-test-network \
    -p 3306:3306 \
    -e "MYSQL_ROOT_PASSWORD=rootpassword" \
    -e "MYSQL_DATABASE=testdb" \
    -e "MYSQL_USER=testuser" \
    -e "MYSQL_PASSWORD=testpass" \
    mariadb:latest

# Wait for MariaDB to initialize
echo "Waiting for MariaDB to initialize..."
sleep 20

# Run the application container with MariaDB environment variables
docker run \
    -e DATABASE=testdb \
    -e USERNAME=testuser \
    -e PASSWORD=testpass \
    -e HOST=mariadb \
    -e SKIP_MYSQL_SSL=1 \
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test serverlesswp-test

sleep 5

curl -s -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/installer.php"}'| jq -e '.statusCode == 200'

curl -s -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/"}' | jq -e '.statusCode == 200'

# Clean up
docker stop serverlesswp-test
#docker logs serverlesswp-test
docker rm serverlesswp-test

docker stop mariadb
#docker logs mariadb
docker rm mariadb

docker network rm serverlesswp-test-network