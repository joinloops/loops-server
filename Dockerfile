# Node.js stage for building assets
FROM node:26-trixie-slim AS node
# PHP base image — FrankenPHP (includes Caddy built-in)
FROM serversideup/php:8.4-frankenphp

WORKDIR /var/www/html

USER root

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libvips42 \
    unzip \
    zip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    bcmath \
    ctype \
    curl \
    fileinfo \
    gd \
    imagick \
    intl \
    json \
    mbstring \
    openssl \
    pdo_mysql \
    redis \
    tokenizer \
    vips \
    ffi \
    xml \
    zip

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

# Install composer dependencies 
## TODO add "--no-dev" for production. Currently breaks due to Pail.
RUN composer install --no-ansi --no-interaction --optimize-autoloader

# Copy Node.js binaries/libraries from node stage
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /usr/local/lib /usr/local/lib

# Install npm dependencies and build assets
ENV NODE_ENV="production"
RUN npm install --include=dev
RUN npm run build

USER www-data

# FrankenPHP: 8080 HTTP, 8443 HTTPS
EXPOSE 8080
EXPOSE 8443
