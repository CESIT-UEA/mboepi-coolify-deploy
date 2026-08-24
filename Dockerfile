FROM php:8.3-fpm-bookworm

ENV MOODLE_VERSION=MOODLE_502_STABLE
ENV MOODLE_DIR=/var/www/moodle
ENV MOODLE_DATAROOT=/var/www/moodledata
ENV LANG=pt_BR.UTF-8
ENV LANGUAGE=pt_BR:pt:en
ENV LC_ALL=pt_BR.UTF-8

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================================================
# PACOTES DO SISTEMA
# =========================================================

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update -o Acquire::Retries=5

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    for pkg in \
        git \
        unzip \
        curl \
        nginx \
        supervisor \
        postgresql-client \
        gosu \
        graphviz \
        aspell \
        ghostscript \
        locales \
    ; do \
        echo "Installing runtime package: $pkg"; \
        apt-get install -y --no-install-recommends "$pkg"; \
    done

# =========================================================
# CONFIGURAÇÃO DE LOCALIDADE
# =========================================================

RUN set -eux; \
    sed -i '/pt_BR.UTF-8/s/^# //g' /etc/locale.gen; \
    sed -i '/en_US.UTF-8/s/^# //g' /etc/locale.gen; \
    sed -i '/en_AU.UTF-8/s/^# //g' /etc/locale.gen; \
    locale-gen

# =========================================================
# DEPENDÊNCIAS DE COMPILAÇÃO DO PHP
# =========================================================
RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    for pkg in \
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
    ; do \
        echo "Installing build dependency: $pkg"; \
        apt-get install -y --no-install-recommends "$pkg"; \
    done

# =========================================================
# EXTENSÕES DO PHP
# =========================================================

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
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

# =========================================================
# INSTALAÇÃO DO MOODLE
# =========================================================

RUN git clone --depth 1 --branch ${MOODLE_VERSION} https://github.com/moodle/moodle.git ${MOODLE_DIR}
RUN cd ${MOODLE_DIR} \
    && composer install --no-dev --classmap-authoritative --no-interaction --no-progress --prefer-dist

# =========================================================
# CONFIGURAÇÃO da PWA
# =========================================================

# Cria o diretório dos arquivos da PWA
RUN mkdir -p ${MOODLE_DIR}/public/pwa

# Adiciona o manifest e o ícone da PWA
COPY manifest.json ${MOODLE_DIR}/public/manifest.json
COPY imagem/monologo-lti-moodle4.svg ${MOODLE_DIR}/public/pwa/monologo-lti-moodle4.svg

# =========================================================
# PERSONALIZAÇÕES DO MOODLE
# =========================================================

COPY --chown=www-data:www-data --chmod=0644 \
    imagem/monologo-lti-moodle4.svg \
    ${MOODLE_DIR}/public/pix/monologo-lti-moodle4.svg

# =========================================================
# CONFIGURAÇÕES DO CONTÊINER
# =========================================================

COPY php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY nginx/default.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY moodle-cron.sh /usr/local/bin/moodle-cron.sh

RUN set -eux; \
    chmod +x \
        /usr/local/bin/docker-entrypoint.sh \
        /usr/local/bin/moodle-cron.sh; \    
    mkdir -p \
        /run/nginx \
        "${MOODLE_DATAROOT}"; \
    chown -R www-data:www-data \
        "${MOODLE_DIR}" \
        "${MOODLE_DATAROOT}"

WORKDIR ${MOODLE_DIR}

ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
