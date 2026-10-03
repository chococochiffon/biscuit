# Biscuit の本番用の PHP(php-fpm)。開発用の docker/php/Dockerfile と違い、起動時に composer install をしない
# (install.sh が 1 回だけ composer install --no-dev を動かす)。ビルドの起点は docker/。OPcache は PHP 8.5 から本体に組み込まれているため入れない
FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git zip unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd bcmath pdo_mysql mysqli exif \
    && rm -rf /var/lib/apt/lists/*

COPY php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/app

CMD ["php-fpm"]
