# php-crc32

![Build Status](https://github.com/google/php-crc32/actions/workflows/test.yml/badge.svg)

by [Andrew Brampton](https://bramp.net)

**Deprecated**: Since PHP 8.0, the crc32 algorithms within PHP are using [hardware accelerated instructions](https://github.com/php/php-src/commit/c3299d7dab15aeed52a34535f1967d426f5327de), and are surprisingly fast. Thus this extension is not needed anymore!

CRC32 implementations, that support all crc32 polynomials, as well as (if you
install the pecl extension) hardware accelerated versions of CRC32C (Castagnoli).

Supports PHP 7.4 though PHP 8.2. Older PHP versions are supported with "v0.1.0".

# Usage

```php
require 'vendor/autoload.php';

use Google\CRC32\CRC32;

$crc = CRC32::create(CRC32::CASTAGNOLI);
$crc->update('hello');
echo $crc->hash();
```

Depending on the environment and the polynomial, `CRC32::create` will choose
the fastest available version, and return one of the following classes:

* `Google\CRC32\PHP` - A pure PHP implementation.
* `Google\CRC32\Builtin` - A [PHP Hash framework](http://php.net/manual/en/book.hash.php) implementation.
* `Google\CRC32\Google` - A hardware accelerated implementation (using [google/crc32c](https://github.com/google/crc32c)).

When reading 1M byte chunks, using `CRC32::CASTAGNOLI` with PHP 7.4 on a 2014 Macbook Pro we get the following performance (higher is better):

```
Google\CRC32\PHP           12.27 MB/s
Google\CRC32\Builtin       468.74 MB/s (available since PHP 7.4)
Google\CRC32\Google        24,684.46 MB/s (using crc32c.so)
```

# Install

```shell
$ composer require google/crc32
```

# crc32c.so

To use the hardware accelerated, a custom PHP extension must be installed. This makes use of [google/crc32c](https://github.com/google/crc32c) which provides a highly optomised `CRC32C` (Castagnoli) implementation using the SSE 4.2 instruction set of Intel CPUs.

The extension can be installed from pecl, or compiled from stratch.

```shell
TODO pecl install crc32c
```

Once installed or compiled, you'll need to add `extension=crc32c.so` to your php.ini file.

## Compile (Linux / Mac)

Ensure that [composer](https://getcomposer.org), build tools (e.g [build-essential](https://packages.debian.org/sid/devel/build-essential), [cmake](https://packages.debian.org/sid/devel/cmake), etc), and php dev headers (e.g [php-dev](https://packages.debian.org/sid/php/php-dev)) are installed.

Simple (using Makefile):

```shell
make test
```

Alternatively (manually):

```shell
cd ext

# Install the google/crc32c library
./install_crc32c.sh # From source (recommended)

# or use your favorite package manager, e.g.
# brew install crc32c

# Prepare the build environment
phpize
./configure

# or if using a custom crc32c
# ./configure --with-crc32c=$(brew --prefix crc32c)

## Build and test
make test
```

The extension will now be at `ext/modules/crc32c.so`. This file should be copied to your [extension directory](https://php.net/extension-dir) and reference in your php.ini.

```
# php.ini
extension=crc32c.so
```

## Testing

`make test` will test with the current PHP. `make test_all` will search for available
PHP installs, and test with all of them.

## Benchmark

To compare the performance of the different `CRC32C` implementations, run `make benchmark`.

# Related

* https://bugs.php.net/bug.php?id=71890

# TODO

- [ ] Test if this works on 32 bit machine.
- [x] Add php unit (or similar) testing.
- [x] Publish to packagist
- [ ] Publish to pecl (https://pecl.php.net/account-request.php)
- [x] Update instructions for linux.


# Licence (Apache 2)

*This is not an official Google product (experimental or otherwise), it is just code that happens to be owned by Google.*

```
Copyright 2023 Google Inc. All Rights Reserved.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```
