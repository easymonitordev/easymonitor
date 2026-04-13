# EasyMonitor

> Open-source, self-hosted uptime and performance monitoring you can run with one `docker compose up`.

![GitHub tag](https://img.shields.io/github/v/tag/easymonitordev/easymonitor?label=version)
![License](https://img.shields.io/github/license/easymonitordev/easymonitor)

EasyMonitor is a full-stack monitoring platform for your websites and APIs. Add a URL, pick an interval, and get alerted when something breaks. Group monitors into projects, share live status with your users via public status pages, and run probes in multiple regions to eliminate false positives.

---

## Features

- **HTTP and ICMP checks** — every 30 seconds to 1 hour per monitor
- **Multi-region probes** — lightweight Go binaries (~10 MB) you can deploy anywhere
- **Consecutive-failure threshold** — configurable per monitor; no alerts on flaky single failures
- **Email alerts** — when a monitor goes down and when it recovers
- **Projects** — group related monitors (e.g. main site + APIs)
- **Teams** — share monitors and projects with collaborators with role-based access
- **Status pages** — public, unlisted (secret link), or private
  - Add projects (live link) or individual monitors
  - Hide specific monitors per page
  - Themes, custom CSS, logo upload
  - Incidents and scheduled maintenance with timeline updates
  - Custom domains with auto-HTTPS via Caddy on-demand TLS
- **TimescaleDB** — efficient time-series storage for check results
- **Redis Streams** — reliable job bus between scheduler, probes, and result consumer
- **Laravel Horizon** — queue dashboard for ops visibility

## Architecture

```
                       ┌──────────────────────────┐
                       │  Laravel + Livewire UI   │
                       │  (admin + public pages)  │
                       └────────────┬─────────────┘
                                    │
                XADD checks         │         consumes results
                ┌───────────────────┴───────────────────┐
                ▼                                       ▲
       ┌────────────────┐                       ┌──────┴────────┐
       │ Redis Streams  │ ◀──── XADD results ───┤ Probe nodes   │
       │ checks/results │       (HTTP, ICMP)    │ (Go, multi-r.)│
       └────────────────┘                       └───────────────┘
                │
                ▼
       ┌──────────────────────────┐
       │ PostgreSQL + TimescaleDB │
       │ (hypertable for checks)  │
       └──────────────────────────┘
```

## Stack

- **Backend:** Laravel 12, PHP 8.4, Livewire 3
- **Frontend:** Tailwind CSS 4, DaisyUI 5, Alpine.js (bundled with Livewire)
- **Probe:** Go 1.24 (separate binary, multi-architecture)
- **Database:** PostgreSQL 18 + TimescaleDB 2.26
- **Message bus:** Redis 7 Streams
- **Web:** Caddy 2.10 (HTTPS) → Nginx → PHP-FPM (with Supervisor + Horizon)

## Quick start

### Prerequisites

- Docker and Docker Compose v2
- ~2 GB free RAM (4 GB comfortable for production)
- Linux, macOS, or WSL2

### One command

```bash
git clone https://github.com/easymonitordev/easymonitor.git
cd easymonitor
./setup.sh
```

The installer is interactive and walks through:

1. **Mode** — local development or production
2. **Domain + admin email** (production only) — auto-detects your server's public IP and verifies DNS
3. **Database** — auto-generates strong password in production
4. **Redis password** — optional
5. **Registration policy** — open or first-user-only
6. **Email driver** — log only, Amazon SES, or generic SMTP
7. **Object storage** — local disk, Cloudflare R2, or Amazon S3

It then:

- Writes `.env`
- Patches `docker/caddy/Caddyfile.production` for production installs
- Builds and starts all containers
- Generates app key, JWT secret, probe token
- Runs migrations
- Builds frontend assets
- Sets up the storage symlink

When it finishes, open the URL it prints. The first user can register and becomes the admin.

## Adding a probe in another region

A local probe runs by default. To add probes in other regions:

### 1. Generate a token on the server

```bash
docker compose exec php php artisan probe:generate-token \
  --node-id=us-east-1 \
  --tags=us-east-1,production \
  --expires=365
```

Copy the token it prints.

### 2. Run the probe on a remote machine

```bash
docker run -d --restart=unless-stopped \
  -e NODE_ID="us-east-1" \
  -e REDIS_URL="rediss://your-redis-host:6380/0" \
  -e REDIS_PASSWORD="your-redis-password" \
  -e JWT_TOKEN="<token-from-step-1>" \
  easymonitor/probe-node:latest
```

The probe authenticates and starts pulling checks within seconds. Use `rediss://` (TLS) for Redis exposed over the public internet.

To disable the bundled local probe:

```bash
docker compose up -d --scale probe=0
```

## Custom domains for status pages

When using the production Caddyfile (configured automatically by `setup.sh` for production installs), customers can point their own domain at your EasyMonitor instance:

1. In the status page settings, they enter `status.theircompany.com`
2. They add the displayed TXT record at their DNS provider for verification
3. They CNAME their domain to your EasyMonitor host (gray cloud / DNS only on Cloudflare)
4. Click **Verify Domain** in the UI

Caddy then provisions a Let's Encrypt certificate automatically on the first request via on-demand TLS. The app gates which domains are allowed via a `/caddy/ask` endpoint that checks the `domain_verified_at` flag.

## Configuration

Most settings live in `.env`. Notable ones beyond the standard Laravel set:

| Variable | Default | Purpose |
|----------|---------|---------|
| `REGISTRATION_ENABLED` | `false` | When false, only the first user can register |
| `JWT_SECRET` | auto-generated | Used to sign probe authentication tokens |
| `PROBE_NODE_ID` | `local-node-1` | Identifier for the bundled probe |
| `PROBE_REDIS_URL` | `redis://redis:6379/0` | Probe Redis connection (use `rediss://` for TLS) |
| `FILESYSTEM_DISK` | `local` | Switch to `s3` for R2 or S3 |
| `MAIL_MAILER` | `log` | Use `ses` or `smtp` for real delivery |

## Development

```bash
docker compose exec php composer install
docker compose exec php php artisan migrate
docker compose exec php npm run dev
```

### Tests

```bash
docker compose exec php php artisan test
```

The test suite uses SQLite in-memory and covers auth, monitors, projects, teams, status pages, custom domains, and the public rendering layer.

### Code style

```bash
docker compose exec php vendor/bin/pint --dirty
```

## Contributing

Contributions welcome. Fork, branch, write tests for your change, run the suite, and open a PR. CI runs `pint` and the test suite on every push.

## License

MIT — see [LICENSE](LICENSE).

---

EasyMonitor — monitoring made easy, wherever you deploy.
