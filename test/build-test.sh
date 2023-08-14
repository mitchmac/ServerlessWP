#!/bin/bash

cd ..
mkdir temp
cd temp
git clone git@github.com:WordPress/sqlite-database-integration.git
cd ..
docker build -t serverlesswp-test -f test/Dockerfile .
rm -rf temp