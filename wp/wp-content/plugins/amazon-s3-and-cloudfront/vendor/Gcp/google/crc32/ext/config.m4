dnl Copyright 2019 Google Inc. All Rights Reserved.
dnl
dnl Licensed under the Apache License, Version 2.0 (the "License");
dnl you may not use this file except in compliance with the License.
dnl You may obtain a copy of the License at
dnl
dnl     http://www.apache.org/licenses/LICENSE-2.0
dnl
dnl Unless required by applicable law or agreed to in writing, software
dnl distributed under the License is distributed on an "AS-IS" BASIS,
dnl WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
dnl See the License for the specific language governing permissions and
dnl limitations under the License.
dnl

PHP_ARG_WITH(crc32c, for crc32c support,
[  --with-crc32c[=DIR]    Include crc32c support. File is the optional path to google/crc32c])

if test "$PHP_CRC32C" != "no"; then
  PHP_REQUIRE_CXX() # The external crc32c library uses C++.

  if test -r $PHP_CRC32C/; then
    SEARCH_PATH=$PHP_CRC32C
  else
    SEARCH_PATH="$PWD/crc32c/build /usr/local /usr"
  fi


  AC_MSG_CHECKING([for crc32c files])
  SEARCH_FOR="include/crc32c/crc32c.h"

  for i in $SEARCH_PATH ; do
    if test -r $i/$SEARCH_FOR; then
      CRC32C_DIR=$i
      AC_MSG_RESULT(found in $i)
    fi
  done

  # --with-crc32c -> check with-path
  if test -z "$CRC32C_DIR"; then
    AC_MSG_RESULT([not found])
    AC_MSG_ERROR([Please install the google/crc32c package, and use --with-crc32c])
  fi

  # --with-crc32c -> add include path
  PHP_ADD_INCLUDE($CRC32C_DIR/include)

  # --with-crc32c -> check for lib and symbol presence
  LIBNAME=crc32c
  LIBSYMBOL=crc32c_extend

  PHP_CHECK_LIBRARY($LIBNAME, $LIBSYMBOL,
  [
    PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $CRC32C_DIR/$PHP_LIBDIR, CRC32C_SHARED_LIBADD)
  ],[
    AC_MSG_FAILURE([wrong crc32c lib version or lib not found])
  ],[
    -L$CRC32C_DIR/$PHP_LIBDIR -lm
  ])
  
  PHP_SUBST(CRC32C_SHARED_LIBADD)
  PHP_NEW_EXTENSION(crc32c, hash_crc32c.c php_crc32c.c, $ext_shared, , -Wall -Werror)
fi
