# ⚠️  PHP 8.4 is still RC as of Oct 2025 – use the RC tag for now
FROM php:8.4-rc-fpm-alpine

# ––––– system deps –––––
RUN apk add --no-cache supervisor bash postgresql-dev nodejs npm iputils $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql opcache sockets pcntl bcmath gmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# ––––– Composer –––––
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ––––– Set working directory –––––
WORKDIR /var/www/html

# ––––– Supervisord –––––
COPY supervisord.conf /etc/supervisord.conf
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
