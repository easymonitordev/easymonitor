FROM php:8.4-fpm-alpine

# ––––– system deps –––––
RUN apk add --no-cache \
      $PHPIZE_DEPS \
      linux-headers shadow \
      supervisor bash postgresql-dev postgresql-client redis nodejs npm iputils \
 && docker-php-ext-install -j$(nproc) \
      pdo_pgsql pgsql opcache sockets bcmath pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis pcntl sockets \
 && apk del $PHPIZE_DEPS

# ––––– Composer –––––
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# ––––– Match www-data to the host user that owns the bind mount –––––
# The repo is bind-mounted from the host where files are owned by UID 1000.
# Retarget www-data to UID 1000 so php-fpm (and anything else running as
# www-data) can read/write the mounted files without chown gymnastics.
# Override at build time: --build-arg PUID=1001 --build-arg PGID=1001
ARG PUID=1000
ARG PGID=1000
RUN groupmod -o -g "${PGID}" www-data \
 && usermod  -o -u "${PUID}" -g "${PGID}" www-data

# ––––– Set working directory –––––
WORKDIR /var/www/html

# ––––– Entrypoint: ensure writable dirs on every start –––––
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ––––– Supervisord –––––
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisord.conf

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
