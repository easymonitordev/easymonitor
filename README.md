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
```bash
git clone https://github.com/easymonitordev/easymonitor.git
cd easymonitor

cp .env.example .env          # tweak DB/REDIS passwords if you like
docker compose up -d --build  # first run: ~3-4 min

# seed demo checks
docker compose exec php php artisan migrate --seed
open http://localhost
```

## 🛠 Adding a probe in another region

```bash
docker run -d --restart=unless-stopped \
  -e REDIS_URL="rediss://probe:<token>@your-domain.com:6380" \
  -e NODE_ID="ams-1" \
  easymonitor/probe-node:latest
```
The probe auto-registers via JWT and starts sending results within ~5 sec.

## 🤝 Contributing
* Fork the repo & create your branch.
* Run tests: docker compose exec php vendor/bin/pest.
* Open a PR — GitHub Actions will lint (pint, phpstan) and run CI.

## 📝 License

EasyMonitor is released under the MIT License — see [LICENSE](LICENSE) for details.

© 2025 EasyMonitor — Monitoring made easy, wherever you deploy.
