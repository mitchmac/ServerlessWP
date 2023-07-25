/**
 * Copyright 2019 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * crc32c hash extension for PHP
 * 
 * This file contains the crc32c hash function for
 * http://php.net/manual/en/function.hash.php
 */
#include "php_crc32c.h"

#include "ext/hash/php_hash.h"
#include "ext/hash/php_hash_crc32.h"

#include "crc32c/crc32c.h"

PHP_HASH_API void CRC32CInit(PHP_CRC32_CTX *context
#if PHP_API_VERSION >= 20201213
, ZEND_ATTRIBUTE_UNUSED HashTable *args
#endif
)
{
	context->state = 0;
}

PHP_HASH_API void CRC32CUpdate(PHP_CRC32_CTX *context, const unsigned char *input, size_t len)
{
	context->state = crc32c_extend(context->state, input, len);
}

PHP_HASH_API void CRC32CFinal(uint8_t crc[4], PHP_CRC32_CTX *context)
{
	int2byte(context->state, crc);
	context->state = 0;
}

PHP_HASH_API int CRC32CCopy(const php_hash_ops *ops, PHP_CRC32_CTX *orig_context, PHP_CRC32_CTX *copy_context)
{
	copy_context->state = orig_context->state;
	return SUCCESS;
}

const php_hash_ops crc32_ops = {
#if PHP_API_VERSION >= 20200620
    "crc32c",
#endif
	(php_hash_init_func_t) CRC32CInit,
	(php_hash_update_func_t) CRC32CUpdate,
	(php_hash_final_func_t) CRC32CFinal,
	(php_hash_copy_func_t) CRC32CCopy,
#if PHP_API_VERSION >= 20200620
    php_hash_serialize,
    php_hash_unserialize,
    PHP_CRC32_SPEC,
#endif
	4, /* what to say here? */
	4,
	sizeof(PHP_CRC32_CTX),
#if PHP_API_VERSION >= 20170718
	0
#endif
};