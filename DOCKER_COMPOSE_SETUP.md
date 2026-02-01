# Loops Docker Compose Setup with serversideup/php container

This setup uses `serversideup/php:8.4-fpm-nginx` as the base image and is designed to work behind a reverse proxy like Cloudflare Tunnel, or Nginx (Proxy Manager) for HTTPS termination.

## Prerequisites

- Docker and Docker Compose installed
- A reverse proxy (e.g., Nginx Proxy Manager) for HTTPS
- Domain name
- Email Provider for sending emails

## Quick Start

1. **Clone and prepare the privledges**
    ```bash
    git clone https://github.com/joinloops/loops-server
    cd loops-server
    sudo chown -R www-data:www-data storage/ bootstrap/cache/
    sudo find storage/ -type d -exec chmod 755 {} \; # set all directories to rwx by user/group
    sudo find storage/ -type f -exec chmod 644 {} \; # set all files to rw by user/group
    ```

2. **Copy the environment file, and Generate secure `.env` secrets:**
   ```bash
   cp .env.docker.example .env
   ```

   ```bash
   sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=\"$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)\"|" .env
   sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=\"$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)\"|" .env
   sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=\"$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)\"|" .env
   ```

3. **Update `.env` with your configuration:**
   - Set APP_KEY ( generate with https://laravel-encryption-key-generator.vercel.app/ )
   - Update `APP_URL` with your domain
   - Configure mail settings
   - Configure S3 (object storage)
   - Configure CAPTCHA

4. **Build container**
   ```bash
   docker compose build
   ```
   See "Container Build Troubleshooting" below if you have permission errors.

5. **Build and start the containers:**
   ```bash
   docker compose up -d mysqldb redis  # Bootstrap the database and Redis.
   # Wait 30 seconds for them to complete first boot.
   docker compose up -d
   # Monitor for any errors.
   docker compose logs -f
   ```
   
6. **Generate application "admin defaults":**
   ```bash
   docker compose exec loops php artisan db:seed --class=AdminSettingsSeeder
   ```

7. **Generate application keys:**
   ```bash
   docker compose exec loops php artisan passport:keys
   ```

8. **Ensure boot-time environment:**
   ```bash
   docker compose exec loops php artisan app:ensure-boottime
   ```

9. **Create admin user:**
   ```bash
   docker compose exec loops php artisan create-admin-account
   ```

## Reverse Proxy Configuration

### Cloudflare Tunnel

1. Doco coming soon

### Nginx Proxy Manager

1. Add a new Proxy Host in Nginx Proxy Manager
2. Set the following:
   - **Domain Names:** Your domain (e.g., `loops.yourdomain.com`)
   - **Scheme:** `http`
   - **Forward Hostname/IP:** `loops-app` (or the Docker host IP)
   - **Forward Port:** `8080`
   - **Enable:** Websockets Support, Block Common Exploits
3. Configure SSL certificate (Let's Encrypt recommended)
4. Add custom Nginx configuration if needed:
   ```nginx
   client_max_body_size 500M;
   proxy_read_timeout 300s;
   ```

### Manual Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    client_max_body_size 500M;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
    }
}
```

## Upgrade

```bash
git pull
docker compose build --pull
docker compose up -d
```
See "Container Build Troubleshooting" below if you have permission errors.

## Container Build Troubleshooting ##

`open /home/username/loops/storage/app/public/m/_v2/xxxxxxxxxxxxxxxxxx/xxxxxxxxxxx-xxxxxxxxxx/xxxxxxxxxxxx: permission denied` or similar might require fixing local permissions.
```bash
sudo find storage/ -type d -exec chmod 755 {} \; # set all directories to rwx by user/group
sudo find storage/ -type f -exec chmod 644 {} \; # set all files to rw by user/group
```

## Useful Commands

```bash
# View logs
docker compose logs -f

# Run artisan commands
docker compose exec loops php artisan [command]

# Access container shell
docker compose exec loops bash

# Restart services
docker compose restart

# Stop services
docker compose down

# Stop and remove volumes (WARNING: deletes data)
docker compose down -v
```
