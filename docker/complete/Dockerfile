FROM debian:13 as builder
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/New_York

#installing some addl tools during development, those will come in handy
RUN apt-get update && apt-get install -y sudo nano git wget curl \
nodejs npm php php-xml php-dom php-redis php-mysql php-fpm php-gd composer ffmpeg

#making sure that the mounted socket file has proper permissions for the nginx container to connect
RUN echo "listen.mode = 0666" >> /etc/php/8.4/fpm/pool.d/www.conf

RUN mkdir /app && \
cd /app
RUN ls -la /app/
ENV CACHEBUST="randomValueHereToUpdateLoopsCode"
RUN git clone https://github.com/joinloops/loops-server.git /app

WORKDIR /app

RUN composer update && composer install --no-dev --optimize-autoloader && \
npm install && \
npm run build && \
chown 33:33 . -R && \
sed -i 's/post_max_size = .*$/post_max_size = 4G/' /etc/php/8.4/fpm/php.ini && \
sed -i 's/upload_max_filesize = .*$/upload_max_filesize = 4G/' /etc/php/8.4/fpm/php.ini && \
sed -i 's/memory_limit = .*$/memory_limit = 8G/' /etc/php/8.4/fpm/php.ini && \
echo "php-fpm8.4 & exec php artisan horizon" > ./start.sh && \
chmod +x start.sh

CMD ["/usr/bin/bash", "./start.sh"]
