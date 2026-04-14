FROM php:8.4-fpm-alpine

# ––––– system deps –––––
RUN apk add --no-cache \
      $PHPIZE_DEPS \
      linux-headers \
      supervisor bash postgresql-dev postgresql-client redis nodejs npm iputils \
 && docker-php-ext-install -j$(nproc) \
      pdo_pgsql pgsql opcache sockets bcmath pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis pcntl sockets \
 && apk del $PHPIZE_DEPS

# ––––– Composer –––––
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# ––––– Set working directory –––––
WORKDIR /var/www/html

# ––––– Supervisord –––––
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisord.conf
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
