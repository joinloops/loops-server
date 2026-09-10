# Node.js stage for building assets
FROM node:26-trixie-slim AS node

# FFmpeg build stage - compile the latest FFmpeg from source (shared/dynamic).
# Built on the SAME base image as the final stage so the compiled shared
# libraries and the codec libraries they link against are ABI-compatible when
# copied/installed into the final image.
# ffmpeg version to compile, change with [--build-arg FFMPEG_VERSION="9.0.1"]
FROM serversideup/php:8.5-frankenphp AS ffmpeg
ARG FFMPEG_VERSION=9.0.1
ARG FFMPEG_URL=https://ffmpeg.org/releases
# x264 has no numbered releases; it is built from its rolling `stable` branch.
ARG X264_URL=https://code.videolan.org/videolan/x264.git
ARG X264_BRANCH=stable
# x265 version to compile, change with [--build-arg X265_VERSION="4.2"]
ARG X265_VERSION=4.2
ARG X265_URL=https://bitbucket.org/multicoreware/x265_git/get

USER root
SHELL ["/bin/bash", "-o", "pipefail", "-o", "errexit", "-c"]

# Install build tools and codec development libraries.
RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf \
    automake \
    build-essential \
    ca-certificates \
    cmake \
    git \
    libtool \
    meson \
    nasm \
    ninja-build \
    pkg-config \
    wget \
    xz-utils \
    yasm \
    # codec dev libraries.
    # x264 (H.264) and x265 (HEVC) are Loops' primary transcoding codecs and are
    # compiled from upstream source below (not from Debian's older packages).
    # AV1 uses Debian's libraries for now (libaom encode/decode, libdav1d for
    # fast decode). AV1 is not yet the default codec for Loops; when it becomes
    # the default these will be compiled from source (SVT-AV1 + dav1d) too.
    # Subtitle rendering libs (libass/fontconfig/freetype) are omitted as they
    # are not needed for transcoding.
    libaom-dev \
    libdav1d-dev \
    libmp3lame-dev \
    libnuma-dev \
    libopus-dev \
    libvorbis-dev \
    libvpx-dev \
    libwebp-dev \
    zlib1g-dev \
    liblzma-dev \
    libbz2-dev \
    && rm -rf /var/lib/apt/lists/*

# Build x264 (H.264 encoder) from its upstream `stable` branch as shared libs.
# Installs into /usr/local so FFmpeg's configure finds it via pkg-config ahead
# of any Debian-packaged version.
WORKDIR /usr/local/x264/src
RUN git clone --depth 1 --branch ${X264_BRANCH} ${X264_URL} . \
    && ./configure \
    --prefix=/usr/local \
    --enable-shared \
    --enable-pic \
    --disable-cli \
    && make -j"$(nproc)" \
    && make install \
    && ldconfig

# Build x265 (HEVC encoder) from upstream source as shared libs (uses CMake).
WORKDIR /usr/local/x265/src
ADD ${X265_URL}/${X265_VERSION}.tar.gz /usr/local/x265/src/x265.tar.gz
RUN tar xf x265.tar.gz --strip-components=1
WORKDIR /usr/local/x265/src/build/linux
RUN cmake -G "Unix Makefiles" \
    -DCMAKE_INSTALL_PREFIX=/usr/local \
    -DENABLE_SHARED=ON \
    -DENABLE_CLI=OFF \
    ../../source \
    && make -j"$(nproc)" \
    && make install \
    && ldconfig

WORKDIR /usr/local/ffmpeg/src
# Download and extract FFmpeg source
ADD ${FFMPEG_URL}/ffmpeg-${FFMPEG_VERSION}.tar.xz /usr/local/ffmpeg/src/
RUN tar xf ffmpeg-${FFMPEG_VERSION}.tar.xz

WORKDIR /usr/local/ffmpeg/src/ffmpeg-${FFMPEG_VERSION}

# Configure, compile and install FFmpeg into /usr/local/ffmpeg.
# Built with --enable-shared: the ffmpeg/ffprobe binaries link dynamically
# against the codec libraries. The FFmpeg shared libs are copied into the final
# image and the codec runtime packages are installed there via apt.
# PKG_CONFIG_PATH / extra flags ensure the source-built x264 & x265 in
# /usr/local are discovered ahead of any Debian-packaged versions.
#   --toolchain=hardened : compiler hardening (stack protector, FORTIFY, RELRO)
#                          for a tool that parses untrusted user-uploaded media.
#   --enable-lto         : link-time optimization for a small runtime speedup.
ENV PKG_CONFIG_PATH="/usr/local/lib/pkgconfig"
RUN ./configure \
    --prefix=/usr/local/ffmpeg \
    --extra-cflags="-I/usr/local/include" \
    --extra-ldflags="-L/usr/local/lib" \
    --toolchain=hardened \
    --enable-lto \
    --disable-debug \
    --disable-doc \
    --disable-ffplay \
    --disable-static \
    --enable-shared \
    --enable-ffmpeg \
    --enable-ffprobe \
    --enable-gpl \
    --enable-version3 \
    --enable-pthreads \
    --enable-libaom \
    --enable-libdav1d \
    --enable-libmp3lame \
    --enable-libopus \
    --enable-libvorbis \
    --enable-libvpx \
    --enable-libwebp \
    --enable-libx264 \
    --enable-libx265 \
    --enable-zlib \
    --enable-lzma \
    ; \
    make -j"$(nproc)"; \
    make install

# PHP base image — FrankenPHP (includes Caddy built-in)
FROM serversideup/php:8.5-frankenphp

WORKDIR /var/www/html

USER root

# Install system dependencies and PHP extensions.
# NOTE: ffmpeg itself is NOT installed from apt; it is compiled from source in
# the `ffmpeg` build stage above and copied in below. The codec runtime shared
# libraries that the source-built FFmpeg links against ARE installed here via
# apt (unversioned -dev package names so apt resolves the correct runtime
# dependency for the base image's Debian release, avoiding brittle version
# suffixes like libx265-215 that change between Debian releases).
RUN apt-get update && apt-get install -y \
    libvips42 \
    unzip \
    zip \
    git \
    curl \
    # runtime codec libraries required by the source-built FFmpeg.
    # NOTE: x264 and x265 are NOT installed here; the source-built shared
    # libraries are copied in from the build stage below.
    libaom-dev \
    libdav1d-dev \
    libmp3lame0 \
    libnuma1 \
    libopus0 \
    libvorbis0a \
    libvorbisenc2 \
    libvpx-dev \
    libwebp7 \
    libwebpmux3 \
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

# Copy the source-built FFmpeg binaries and shared libraries into the final image.
COPY --from=ffmpeg /usr/local/ffmpeg/bin/ffmpeg /usr/local/bin/ffmpeg
COPY --from=ffmpeg /usr/local/ffmpeg/bin/ffprobe /usr/local/bin/ffprobe
COPY --from=ffmpeg /usr/local/ffmpeg/lib /usr/local/lib
# Copy the source-built x264 and x265 shared libraries that FFmpeg links against.
COPY --from=ffmpeg /usr/local/lib/libx264.so* /usr/local/lib/
COPY --from=ffmpeg /usr/local/lib/libx265.so* /usr/local/lib/

# Refresh the dynamic linker cache and smoke-test the media processors
RUN ldconfig \
    && ffmpeg -version \
    && ffprobe -version

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
