#!/bin/bash
echo "Building loops image..."
docker compose build loops || exit "Error during image build. Please check."

mkdir -p mariadb/init || echo "DB init directory already exists."
echo "GRANT ALL PRIVILEGES ON loops_server.* TO 'mariadb'@'%'; FLUSH PRIVILEGES;" > ./mariadb/init/01-init.sql
docker compose up -d db redis
read -p "Press ENTER to continue, but AFTER modifying the .env file with your settings!"

echo "Waiting for DB and Redis to come up..." && sleep 10

echo "Now setting up loops itself..."
docker compose run --rm loops php artisan key:generate
docker compose run --rm loops php artisan storage:link
docker compose run -it --rm loops php artisan migrate
docker compose run -it --rm loops php artisan create-admin-account
docker compose run -it --rm loops php artisan passport:keys
docker compose run -it --rm loops php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"

docker create --name loops_temp loops.org
docker cp loops_temp:/app/public ./app_public
docker rm loops_temp

echo "now you can run 'docker compose up -d loops' to start the server."
