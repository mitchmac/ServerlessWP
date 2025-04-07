#!/bin/bash

./build-test.sh

VERCEL=${VERCEL:-1}
VERCEL_GIT_COMMIT_REF=${VERCEL_GIT_COMMIT_REF:-test_branch}

if ! command -v mc &> /dev/null
then
    wget https://dl.min.io/client/mc/release/linux-amd64/mc -O /usr/local/bin/mc
    chmod +x /usr/local/bin/mc
fi

docker network create serverlesswp-test-network

docker run -d --name minio \
    --network serverlesswp-test-network \
    -p 9010:9000 -p 9011:9011 \
    -e "MINIO_ROOT_USER=minioadmin" -e "MINIO_ROOT_PASSWORD=minioadmin" \
    minio/minio server /data  --console-address ":9011"

sleep 5

mc alias set local-minio http://localhost:9010 minioadmin minioadmin
mc mb local-minio/test-bucket
mc admin user add local-minio testuser testpass
mc admin policy attach local-minio readwrite --user testuser

docker run \
    -e SQLITE_S3_BUCKET=test-bucket \
    -e SQLITE_S3_API_KEY=testuser -e SQLITE_S3_API_SECRET=testpass \
    -e SQLITE_S3_REGION=us-east-1  -e SQLITE_S3_ENDPOINT=http://minio:9000 -e SQLITE_S3_FORCE_PATH_STYLE=1 \
    -e VERCEL=$VERCEL -e VERCEL_GIT_COMMIT_REF=$VERCEL_GIT_COMMIT_REF \
    -p 9000:8080 \
    --network serverlesswp-test-network \
    -d --name serverlesswp-test serverlesswp-test

curl -s -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/installer.php"}' | jq -e '.statusCode == 200'

curl -s -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d '{"path":"/"}' | jq -e '.statusCode == 200'

docker stop serverlesswp-test
#docker logs serverlesswp-test
docker rm serverlesswp-test

docker stop minio
#docker logs minio
docker rm minio

docker network rm serverlesswp-test-network
