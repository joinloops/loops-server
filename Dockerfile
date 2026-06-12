# Node.js stage for building assets
FROM node:24-trixie-slim AS node
# PHP base image
FROM serversideup/php:8.4-fpm-nginx

# Set working directory
WORKDIR /var/www/html

# Switch to root to install packages
USER root

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libvips42 \
    unzip \
    zip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions using the built-in helper
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
    xml \
    zip

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

# Install composer dependencies
RUN composer install --no-ansi --no-interaction --optimize-autoloader --ignore-platform-req=ext-ffi --ignore-platform-req=ext-vips

# Copy Node.js binaries/libraries from node stage
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /usr/local/lib /usr/local/lib

# Install npm dependencies and build assets
RUN npm install
ENV NODE_ENV="production"
RUN npm run build

# ── CDN: Remove cache/security headers from nginx (Caddy handles at edge) ──
RUN sed -i '/add_header Cache-Control/d' /etc/nginx/server-opts.d/performance.conf \
    && sed -i '/add_header X-Frame-Options/d' /etc/nginx/server-opts.d/security.conf \
    && sed -i '/add_header X-Content-Type-Options/d' /etc/nginx/server-opts.d/security.conf \
    && sed -i '/add_header Referrer-Policy/d' /etc/nginx/server-opts.d/security.conf \
    && sed -i '/add_header Strict-Transport-Security/d' /etc/nginx/server-opts.d/security.conf

# Switch back to www-data user
USER www-data

# Expose port 8080 (default for serversideup/php)
EXPOSE 8080
