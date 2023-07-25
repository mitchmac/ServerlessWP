#!/bin/sh
# Used to build and install the google/crc32c library.

##
# Copyright 2019 Google Inc. All Rights Reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
##

git clone https://github.com/google/crc32c

cd crc32c
git submodule update --init --recursive

mkdir build
cd build
cmake -DCRC32C_BUILD_TESTS=0 \
      -DCRC32C_BUILD_BENCHMARKS=0 \
      -DCRC32C_USE_GLOG=0 \
      -DBUILD_SHARED_LIBS=0 \
      -DCMAKE_POSITION_INDEPENDENT_CODE=TRUE \
      -DCMAKE_INSTALL_PREFIX:PATH=$PWD \
      ..
cmake --build . --target install
