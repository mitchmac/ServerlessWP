FROM amazonlinux:latest

RUN yum -y groups install "Development tools" \
  && yum -y install \
    libxml2-devel \
    libzip-devel \
    libcurl-devel \
    openssl-devel \
    bzip2-devel \
    gd-devel \
    mysql-devel \
    libjpeg-devel \
    libexif \
    libexif-devel \
    wget

RUN curl -L http://ca3.php.net/get/php-7.1.12.tar.bz2/from/this/mirror --create-dirs -o /work/php.tar.bz2

WORKDIR /work

RUN tar -jxvf php.tar.bz2 \
  && mkdir php-7-bin

WORKDIR /work/php-7.1.12

RUN ./configure \
  --prefix=/work/php-7-bin/ \
  --enable-shared=no \
  --enable-static=yes \
  --without-pear \
  --enable-json \
  --with-openssl \
  --with-curl \
  --enable-libxml \
  --enable-simplexml \
  --enable-xml \
  --with-mhash \
  --with-gd \
  --enable-exif \
  --with-freetype-dir \
  --enable-mbstring \
  --enable-sockets \
  --enable-pdo \
  --with-pdo-mysql \
  --enable-tokenizer \
  --enable-zip \
  --with-mysqli \
  --with-bz2 \
  --with-zlib \
  --with-gettext

RUN make install
