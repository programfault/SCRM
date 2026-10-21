FROM docker.1ms.run/library/php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite zip intl pcntl bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
