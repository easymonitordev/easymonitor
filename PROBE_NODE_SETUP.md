# Probe Node Setup

Probes are small Go binaries that run monitoring checks on behalf of the main EasyMonitor app. They pull work from the server's Redis Streams, execute HTTP/ICMP checks, and publish results back.

A local probe is bundled with the main Docker stack and runs automatically on install. This document covers running **additional probes on other machines** so you get multi-region monitoring.

> The probe binary lives in its own repo: **[github.com/easymonitordev/probe-node](https://github.com/easymonitordev/probe-node)**. Pre-built image: `easymonitor/probe-node:latest` (Docker Hub) or `ghcr.io/easymonitordev/probe-node:latest` (GitHub Container Registry).
>
> This document covers the **server-side tunnel setup** and the minimum `docker run` you need on the probe host. For probe-side details (systemd, Kubernetes, building from source, troubleshooting), see the probe repo's README.

---

## How it works

```
[server]                                     [remote probe host]
┌──────────────────┐    private tunnel    ┌──────────────────┐
│ Laravel + Redis  │◀────────────────────▶│ probe container  │
│ (Docker stack)   │                      │ (docker run)     │
└──────────────────┘                      └──────────────────┘
```

The probe connects to the server's Redis over a **private network tunnel**. Plain Redis is never exposed on the public internet.

**Supported tunnel options:**

- **Tailscale** — simplest. A one-line install on server and probe; both join a mesh network. *Recommended.*
- **Cloudflare Tunnel** — free with any Cloudflare account. Zero ports exposed; traffic proxied through Cloudflare. More steps but no extra daemons on the probe after install.
- **Manual** — SSH tunnel, WireGuard, tailnets, private data center link. Anything that gives the probe TCP access to `<server>:6379`.

The `setup.sh` installer on the server walks you through option 1 or 2 automatically. This document covers what to do on the **probe side**.

---

## Before you start

On the **server**, you should have:

1. EasyMonitor running (`./setup.sh` completed)
2. A `REDIS_PASSWORD` set in `.env` (setup.sh generates one automatically for production)
3. Picked a tunnel option in setup.sh and noted the probe connection URL

Get:

- **Probe Redis URL** — shown at the end of `setup.sh`. Looks like `redis://100.x.y.z:6379/0` for Tailscale, or `redis://tunnel.example.com:6379/0` for Cloudflare.
- **Redis password** — from your server's `.env` file (`grep REDIS_PASSWORD .env`).
- **A probe JWT token** — generate on the server:

  ```bash
  docker compose exec php php artisan probe:generate-token \
    --node-id=us-east-1 \
    --tags=us-east-1,production \
    --expires=365
  ```

  Copy the full token.

---

## Option 1 — Tailscale (recommended)

### 1. Install Tailscale on the probe host

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

Visit the login URL it prints to join the same tailnet your server is on.

Verify you can reach the server's Tailscale IP:

```bash
ping -c 2 <server-tailscale-ip>
```

### 2. Run the probe

Use `--network host` so the container shares the host's Tailscale interface —
without it, the container's own network namespace can't see the tailnet.

```bash
docker run -d \
  --name easymonitor-probe \
  --restart unless-stopped \
  --network host \
  -e NODE_ID="us-east-1" \
  -e REDIS_URL="redis://<server-tailscale-ip>:6379/0" \
  -e REDIS_PASSWORD="<redis password>" \
  -e JWT_TOKEN="<probe jwt token>" \
  easymonitor/probe-node:latest
```

(With `--network host` the probe's health check port 8080 is automatically
on the host — no `-p` flag needed.)

### 3. Verify

On the probe host:

```bash
curl -s http://localhost:8080/health
# should return: ok
```

On the server:

```bash
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning XINFO GROUPS checks
```

You should see the probe listed as an active consumer within ~5 seconds.

### 4. Firewall (optional but recommended)

Block port 6379 from the public internet on the server. With UFW:

```bash
sudo ufw deny 6379
sudo ufw allow in on tailscale0 to any port 6379
```

Tailscale traffic bypasses UFW via the `tailscale0` interface, so probes still connect.

---

## Option 2 — Cloudflare Tunnel

Cloudflare Tunnel exposes a service through Cloudflare's edge without opening any ports. It's free with any Cloudflare account.

### Prerequisites

- A domain in Cloudflare (e.g. `yourdomain.com`)
- Zero Trust dashboard access (default for any Cloudflare account)

### 1. On the server — create the tunnel

Install `cloudflared`:

```bash
curl -fsSL https://pkg.cloudflare.com/install.sh | sudo bash
sudo apt install cloudflared
```

Authenticate and create a tunnel:

```bash
cloudflared tunnel login              # opens a browser
cloudflared tunnel create easymonitor-redis
```

The `create` command prints a tunnel UUID and creates a credentials file at `~/.cloudflared/<uuid>.json`.

Create a config file at `/etc/cloudflared/config.yml`:

```yaml
tunnel: <uuid>
credentials-file: /etc/cloudflared/<uuid>.json

ingress:
  - hostname: monitor-redis.yourdomain.com
    service: tcp://localhost:6379
  - service: http_status:404
```

Route DNS:

```bash
cloudflared tunnel route dns easymonitor-redis monitor-redis.yourdomain.com
```

Start the tunnel as a service:

```bash
sudo cloudflared service install
sudo systemctl start cloudflared
sudo systemctl enable cloudflared
```

Make sure setup.sh configured `REDIS_BIND_HOST=127.0.0.1` in `.env` so cloudflared on the host can reach Redis via localhost.

### 2. On the probe host — connect through Cloudflare Access

Cloudflare Tunnel TCP endpoints are **not** open to the public — they require authentication. Probes connect through a `cloudflared access tcp` proxy.

Install `cloudflared` on the probe host:

```bash
curl -fsSL https://pkg.cloudflare.com/install.sh | sudo bash
sudo apt install cloudflared
```

Create a Cloudflare **service token** in the Zero Trust dashboard:

- Access → Service Auth → Service Tokens → Create
- Copy the `Client ID` and `Client Secret`

Create an Access Application and policy that allows that service token to reach `monitor-redis.yourdomain.com` (Zero Trust → Access → Applications → Add Application → Self-hosted).

Run a local TCP proxy in the background:

```bash
cloudflared access tcp \
  --hostname monitor-redis.yourdomain.com \
  --url 127.0.0.1:6379 \
  --service-token-id <client-id> \
  --service-token-secret <client-secret> &
```

(For production, wrap this in a systemd unit.)

### 3. Run the probe

With the proxy listening on `127.0.0.1:6379`:

```bash
docker run -d \
  --name easymonitor-probe \
  --restart unless-stopped \
  --network host \
  -e NODE_ID="us-east-1" \
  -e REDIS_URL="redis://127.0.0.1:6379/0" \
  -e REDIS_PASSWORD="<redis password>" \
  -e JWT_TOKEN="<probe jwt token>" \
  easymonitor/probe-node:latest
```

`--network host` is used so the container can reach the cloudflared proxy on the host's loopback.

### 4. Verify

```bash
curl -s http://localhost:8080/health
```

On the server:

```bash
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning XINFO GROUPS checks
```

---

## Option 3 — Manual (SSH tunnel, WireGuard, own VPN)

If you already have a private network connecting your probe host to the EasyMonitor server, just point `REDIS_URL` at the appropriate private IP:

```bash
docker run -d \
  --name easymonitor-probe \
  --restart unless-stopped \
  -e NODE_ID="eu-west-1" \
  -e REDIS_URL="redis://10.0.0.5:6379/0" \
  -e REDIS_PASSWORD="<redis password>" \
  -e JWT_TOKEN="<probe jwt token>" \
  -p 8080:8080 \
  easymonitor/probe-node:latest
```

Make sure `REDIS_BIND_HOST` in the server's `.env` matches the interface reachable from the probe (edit and run `docker compose up -d redis` to apply).

### SSH tunnel (quick ad-hoc option)

On the probe host, forward the server's Redis through SSH:

```bash
ssh -L 6379:127.0.0.1:6379 -N user@server.example.com &
```

Then run the probe with `REDIS_URL=redis://127.0.0.1:6379/0 --network host`.

---

## Configuration reference

Environment variables the probe reads:

| Variable | Required | Default | Purpose |
|----------|---------|---------|---------|
| `NODE_ID` | yes | — | Unique identifier for this probe (also shown as `tags` in results) |
| `REDIS_URL` | yes | — | `redis://host:port/db` |
| `REDIS_PASSWORD` | yes (if set on server) | — | Matches the server's `REDIS_PASSWORD` |
| `JWT_TOKEN` | yes | — | Probe authentication token generated on the server |
| `DEFAULT_TIMEOUT` | no | `30s` | Default per-check timeout |
| `BATCH_SIZE` | no | `10` | Max checks pulled per `XREADGROUP` |
| `MAX_CONCURRENCY` | no | `10` | Concurrent in-flight checks |
| `HEALTH_CHECK_PORT` | no | `8080` | Port for the `/health`, `/ready`, `/version` endpoints |

---

## Ops cheat sheet

**Tail probe logs:**
```bash
docker logs -f easymonitor-probe
```

**Restart the probe:**
```bash
docker restart easymonitor-probe
```

**Rotate the probe's JWT token:**

On the server, generate a new one and redeploy the probe with the new token:
```bash
docker compose exec php php artisan probe:generate-token --node-id=us-east-1 --expires=365
```

Recommended: rotate every 90–365 days.

**Check the probe is visible on the server:**

```bash
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning \
  XINFO GROUPS checks
```

Each probe has its own consumer group named `probe-<NODE_ID>`. An active probe's group shows a recent `last-delivered-id`.

You can also list probes registered in the database:

```bash
docker compose exec php php artisan tinker \
  --execute="App\Models\ProbeNode::all()->each(fn(\$p) => print(\$p->node_id.' '.(\$p->last_seen_at?->diffForHumans() ?? 'never').PHP_EOL));"
```

**Pending checks in the queue:**
```bash
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" --no-auth-warning \
  XLEN checks
```

---

## Troubleshooting

**Probe can't connect to Redis**
- Verify connectivity from the probe host: `redis-cli -h <server-ip> -p 6379 -a "$REDIS_PASSWORD" ping` should return `PONG`.
- Check the tunnel is up: `tailscale status` or `systemctl status cloudflared`.
- Confirm `REDIS_BIND_HOST` on the server matches how you're reaching it.

**"AUTH failed" in probe logs**
- `REDIS_PASSWORD` doesn't match the server. Copy fresh from the server's `.env`.

**"failed to validate token" in probe logs**
- Token expired or doesn't match server's `JWT_SECRET`. Regenerate the token.

**Server changed its Tailscale IP**
- Rare but happens on reinstall. Update `REDIS_BIND_HOST` in the server's `.env`, restart Redis (`docker compose up -d redis`), and update `REDIS_URL` on each probe.

**Probe shows up but checks aren't running**
- Check Horizon is running on the server: `docker compose exec php php artisan horizon:status`.
- Check there are active monitors: the probe is idle if nothing is being dispatched.
