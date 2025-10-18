# Docker Setup Guide

This project uses Docker for local development with the following services:
- **Caddy** - Web server and reverse proxy
- **Nginx** - Application server
- **PHP 8.4** - PHP-FPM with supervisor, Node.js, and monitoring extensions
- **PostgreSQL 16 + TimescaleDB** - Database with time-series support
- **Redis 7** - Cache, sessions, queue backend, and Redis Streams

## Quick Start

### One-Command Setup (Recommended)

```bash
./setup.sh
```

This automated script will:
1. Check for `.env` file (creates from `.env.example` if missing)
2. Build and start all Docker containers
3. Wait for services to be ready
4. Install Composer dependencies
5. Generate application key
6. Install NPM dependencies and build assets
7. Run database migrations

**First time setup:** The script will prompt you to create a `.env` file. After creating it, edit `.env` to set your database password, then run `./setup.sh` again.

### Manual Setup (Alternative)

If you prefer manual control:

#### 1. Copy and Configure Environment File

```bash
cp .env.example .env
```

**Important:** Edit `.env` and customize your database credentials **before** running Docker:

```env
DB_DATABASE=easymonitor          # Database name
DB_USERNAME=easymonitor          # Database user
DB_PASSWORD=your_secure_password # Change this!
```

#### 2. Start Docker Containers

```bash
docker compose up -d --build
```

This will:
- Build the PHP container with all necessary extensions (including Node.js/NPM)
- Start all services (Caddy, Nginx, PHP, PostgreSQL, Redis)
- Automatically create the database and user with your credentials
- Enable the TimescaleDB extension

#### 3. Run Application Setup

```bash
docker exec php bash /var/www/html/docker/scripts/setup.sh
```

This will:
- Wait for database and Redis to be ready
- Install Composer dependencies
- Generate application key (if needed)
- Install NPM dependencies and build assets
- Run database migrations

### Access the Application

- **HTTP:** http://localhost
- **HTTPS:** Not enabled for local development (to avoid certificate warnings)

To use `http://easymonitor.local`, add this to your `/etc/hosts` file:
```
127.0.0.1 easymonitor.local
```

## Useful Commands

### Development Workflow

#### Rebuild Frontend Assets
```bash
docker exec php npm run build          # Production build
docker exec php npm run dev            # Development build (watch mode)
```

#### Run Artisan Commands
```bash
docker exec php php artisan migrate
docker exec php php artisan make:model Post
docker exec php php artisan queue:work
```

#### Install New Dependencies
```bash
docker exec php composer require vendor/package
docker exec php npm install package-name
```

### Container Management

#### View Logs
```bash
docker compose logs -f              # All services
docker compose logs -f php          # PHP only
docker compose logs -f db           # Database only
docker compose logs -f nginx        # Nginx only
```

#### Restart Services
```bash
docker compose restart              # All services
docker restart php                  # PHP only
docker restart nginx                # Nginx only
```

#### Access Container Shells
```bash
docker exec -it php sh                                        # PHP container
docker exec -it db psql -U easymonitor -d easymonitor        # Database
docker exec -it redis redis-cli                               # Redis
```

#### Stop & Remove Containers
```bash
docker compose down                 # Stop containers
docker compose down -v              # Stop and remove volumes (deletes data!)
```

### Complete Reset

To start fresh (deletes all data):
```bash
docker compose down -v
./setup.sh
```

## Changing Database Credentials

If you want to change database credentials after initial setup:

1. Stop and remove containers with volumes:
   ```bash
   docker compose down -v
   ```

2. Update your `.env` file with new credentials

3. Start containers again:
   ```bash
   docker compose up -d
   ```

## Production Deployment

For production, use the production Caddyfile:

1. Update `docker/caddy/Caddyfile.production` with your domain
2. Mount it in docker-compose.yml:
   ```yaml
   - ./docker/caddy/Caddyfile.production:/etc/caddy/Caddyfile:ro
   ```
3. Caddy will automatically obtain and renew SSL certificates

## Troubleshooting

### "File not found" Error
Restart the containers:
```bash
docker compose restart
```

### Database Connection Failed
Check that the database credentials in `.env` match the ones used when you first started Docker.

### Port Already in Use
If ports 80 or 443 are already in use, stop other services or change the ports in `docker-compose.yml`:
```yaml
ports:
    - "8080:80"
    - "8443:443"
```

Then access via http://localhost:8080
