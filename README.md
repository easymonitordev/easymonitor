# EasyMonitor

> **Open-source, multi-region uptime & performance monitoring you can run in one `docker compose up`.**

![GitHub tag](https://img.shields.io/github/v/tag/easymonitordev/easymonitor?label=version)
![License](https://img.shields.io/github/license/easymonitordev/easymonitor)
![Docker pulls](https://img.shields.io/docker/pulls/easymonitor/probe-node)

---

## ✨ Why EasyMonitor?

* **Zero-friction install** — drop the stack on any Docker host and get a full web UI, probes, TimescaleDB and Redis Streams in minutes.
* **Multi-region accuracy** — add lightweight probe containers (`<30 MB`) in any VPS or edge network; quorum logic kills false positives.
* **Built for OSS **and** SaaS** — self-host for free.
* **Laravel + Livewire** UI — hackable PHP you probably already know, plus DaisyUI-powered Tailwind v4 components.

---

## 🏗 Architecture

```text
┌────────────┐     XADD checks        ┌──────────────────┐
│  Laravel & │ ───────────────────▶   │  Redis Streams   │
│  Livewire  │                        └─────────▲────────┘
└──────▲─────┘           XADD results           │ (consumer group)
       │                                        │
 TimescaleDB &               pull/ack           │
 ClickHouse       ┌─────────────────────────────┴───────┐
                  │    Probe Containers (Go)            │
                  │  ping / HTTP / TLS / DNS checks     │
                  └─────────────────────────────────────┘
```

* PostgreSQL 18 + TimescaleDB 2.22 — fast hypertables for raw metrics.
* Redis 7 Streams — simple, reliable job bus (no extra Kafka).
* Caddy ➜ Nginx ➜ PHP-FPM 8.4 — automatic HTTPS & FastCGI.
* Supervisor inside the PHP image — keeps Horizon / workers alive.

## 🚀 Quick start (local)

### One-Command Setup (Recommended)
```bash
git clone https://github.com/easymonitordev/easymonitor.git
cd easymonitor
./setup.sh
```

The setup script will automatically:
- ✅ Create `.env` from `.env.example` (if needed)
- ✅ Generate application key
- ✅ **Generate JWT secret and probe token** (NEW!)
- ✅ Build and start all Docker containers (including local probe)
- ✅ Install dependencies (Composer + NPM)
- ✅ Build frontend assets
- ✅ Run database migrations

**That's it!** Your monitoring system is ready at: **http://localhost**

The local probe node starts automatically and begins monitoring your checks immediately.

### Manual Setup
```bash
git clone https://github.com/easymonitordev/easymonitor.git
cd easymonitor

cp .env.example .env                                  # Edit and set DB password
docker compose up -d --build                          # Start all containers (~3-4 min)
docker exec php bash /var/www/html/docker/scripts/setup.sh  # Setup app

# Optional: seed demo checks
docker exec php php artisan db:seed
```

**Note:** The local probe is enabled by default. To disable it (e.g., on tiny VPSes):
```bash
docker compose up -d --scale probe=0
```

📖 **For detailed setup instructions, see [PROBE_NODE_SETUP.md](PROBE_NODE_SETUP.md)**

## 🛠 Adding a probe in another region

### 1. Generate a token on your server:
```bash
docker exec php php artisan probe:generate-token \
  --node-id=us-east-1 \
  --tags=us-east-1,production
```

### 2. Deploy the probe on a remote server:
```bash
docker run -d --restart=unless-stopped \
  -e NODE_ID="us-east-1" \
  -e REDIS_URL="rediss://your-redis-host.com:6380/0" \
  -e REDIS_PASSWORD="your-redis-password" \
  -e JWT_TOKEN="<token-from-step-1>" \
  easymonitor/probe-node:latest
```

The probe authenticates via JWT and starts monitoring within ~5 seconds.

📖 **For external probe deployment, see [PROBE_NODE_SETUP.md](PROBE_NODE_SETUP.md)**

## 🤝 Contributing
* Fork the repo & create your branch.
* Run tests: docker compose exec php vendor/bin/pest.
* Open a PR — GitHub Actions will lint (pint, phpstan) and run CI.

## 📝 License

EasyMonitor is released under the MIT License — see [LICENSE](LICENSE) for details.

© 2025 EasyMonitor — Monitoring made easy, wherever you deploy.
