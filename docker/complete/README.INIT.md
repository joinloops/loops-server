The init script will take care of the entire process up until starting the loops container.
Modify docker-compose.yml and config accordingly if you plan to use a pre-existing Redis or MariaDB instance instead of the two included services.
.env is pre-set to use the built-in redis/mariadb.
Mariadb will auto-initialize itself on first start.
