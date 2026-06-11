FROM php:8.3-fpm-bookworm

ENV MOODLE_VERSION=MOODLE_502_STABLE
ENV MOODLE_DIR=/var/www/moodle
ENV MOODLE_DATAROOT=/var/www/moodledata

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    composer \
    nginx \
    supervisor \
    postgresql-client \
    gosu \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libsodium-dev \
    libxslt1-dev \
    graphviz \
    aspell \
    ghostscript \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pgsql \
        pdo_pgsql \
        zip \
        intl \
        soap \
        gd \
        exif \
        opcache \
        sodium \
        xsl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 --branch ${MOODLE_VERSION} https://github.com/moodle/moodle.git ${MOODLE_DIR}
RUN cd ${MOODLE_DIR} \
    && composer install --no-dev --classmap-authoritative --no-interaction --no-progress --prefer-dist

COPY php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY nginx/default.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY moodle-cron.sh /usr/local/bin/moodle-cron.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/moodle-cron.sh \
    && mkdir -p /run/nginx ${MOODLE_DATAROOT} \
    && chown -R www-data:www-data ${MOODLE_DIR} ${MOODLE_DATAROOT}

WORKDIR ${MOODLE_DIR}

ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
