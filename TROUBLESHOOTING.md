# Troubleshooting

Quick reference for issues that can happen during install, upgrade, or normal operation. Each section starts with the symptom you'd see, then the commands that diagnose and fix it.

All commands assume you're in the project directory (`cd ~/easymonitor`).

---

## Where to look first

| Problem | First command to run |
|---------|---------------------|
| 500 error on any page | `docker compose exec php tail -n 80 storage/logs/laravel.log` |
| Page loads blank (no output) | Set `APP_DEBUG=true` in `.env`, then `docker compose exec php php artisan config:clear` |
| Container won't stay up | `docker compose logs <service> --tail 50` |
| Monitors never change from "Pending" | `docker compose exec php tail -n 40 storage/logs/laravel.log \| grep -iE "dispatched\|processed"` |
| App-level system check | Log in, then visit `/healthz` — shows DB, Redis, monitoring loop, probe status |

---

## Install & first run

### Setup.sh gets stuck on "Waiting for PostgreSQL"

**Cause:** the wait-loop's `psql` call is masking the real error, or the DB started but credentials don't match.

**Diagnose:**

```bash
# What does the DB container actually say?
docker compose logs db --tail 30

# Is PHP-FPM seeing the right env?
docker compose exec php env | grep -E 'DB_'

# Try the connection manually (without silencing errors)
docker compose exec php bash -c 'PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c "SELECT 1;"'
```

The last command will show the actual authentication/connection error.

**Fix options:**

- `password authentication failed` — DB volume has old password. `docker compose down -v && ./setup.sh` (destroys DB data).
- `could not translate host name "db"` — Docker network broken. `docker compose down && docker compose up -d`.
- `role "easymonitor" does not exist` — DB env vars weren't set. Verify `.env` has `DB_USERNAME` and `DB_DATABASE`.

### Probe container keeps restarting right after install

**Symptom:**
```
probe | Failed to load configuration: JWT_TOKEN environment variable is required
```

**Cause:** the probe container started before `setup.sh` wrote `PROBE_JWT_TOKEN` to `.env`. Docker only reads env vars at container start.

**Fix:**

```bash
# Verify the token is in .env
grep '^PROBE_JWT_TOKEN=eyJ' .env && echo "token present"

# Recreate the probe container so it picks up the new env
docker compose up -d --force-recreate probe

# Verify
docker compose logs probe --tail 10
```

If the token isn't in `.env`, regenerate it:

```bash
docker compose exec php php artisan probe:generate-token \
  --node-id=local-node-1 --expires=365 --no-interaction
docker compose up -d --force-recreate probe
```

---

## 500 / blank page

### Blank page with `APP_DEBUG=true`

**Cause:** Laravel can't even start — usually missing/corrupt cache, bad config, or missing dependency.

**Diagnose:**

```bash
# Run public/index.php via CLI so errors print to terminal
docker compose exec php php -d display_errors=1 public/index.php 2>&1 | tail -30

# Check for missing/unreadable autoload files
docker compose exec php composer install --no-interaction --optimize-autoloader
```

### Mixed content / HTTP asset URLs on an HTTPS page

**Symptom:** page loads but CSS/JS blocked by browser with "mixed content" warning. Links show `http://your-domain/build/assets/...` instead of `https://`.

**Cause:** Laravel doesn't trust the reverse proxy, so it thinks the request came over HTTP.

**Fix:** already handled by `bootstrap/app.php` shipping `trustProxies()`. If you modified that file, restore:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: ['0.0.0.0/0', '::/0']);
})
```

Then rebuild assets (asset URLs are baked in at build time):

```bash
docker compose exec php sh -c 'rm -rf public/build && npm run build'
docker compose exec php php artisan optimize:clear
```

### Permission denied on `storage/` or `bootstrap/cache/`

**Symptom:** 500 after running `artisan ... clear` or any command via `docker compose exec`. The log (if it exists) mentions `failed to open stream: Permission denied`.

**Cause:** files created by `docker compose exec` (running as root) can't be written by PHP-FPM (running as `www-data`).

**Fix:** the Dockerfile sets `www-data` to UID 1000 on build and the container entrypoint chowns on start. If perms drifted anyway:

```bash
docker compose exec php chown -R www-data:www-data storage bootstrap/cache
docker compose exec php chmod -R 775 storage bootstrap/cache
docker compose exec php php artisan optimize:clear
```

Or restart the container — the entrypoint script re-chowns automatically:

```bash
docker compose restart php
```

---

## Monitor stuck on "Pending"

### Pipeline diagnosis

Runs each link in the chain. Anywhere it breaks, that's your bug.

```bash
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)

# 1. Is Horizon running?
docker compose exec php php artisan horizon:status

# 2. Any checks dispatched to Redis?
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XLEN checks

# 3. Probe consumer groups present?
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XINFO GROUPS checks

# 4. Any results published back?
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XLEN results

# 5. Is the result-processors group reading them?
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XINFO GROUPS results

# 6. Is the monitoring loop alive?
docker compose exec php tail -n 40 storage/logs/laravel.log | grep -iE "dispatched|processed|bootstrapped"
```

You should see recurring log entries like `DispatchMonitorChecks: Dispatched 1 monitor check(s)` every 30s and `ProcessMonitorResults: Processed N result(s)` every few seconds.

### Missing: "Monitoring dispatcher bootstrapped" in logs

**Cause:** the `MonitoringServiceProvider` didn't kick off the self-requeuing jobs. Usually because Horizon failed to start the first time and the one-shot bootstrap was skipped.

**Fix:**

```bash
# Clear the bootstrap flag so the provider re-dispatches on next Horizon boot
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning DEL easymonitor_cache:monitoring:dispatcher:initialized

# Restart php so Horizon reboots and triggers the provider
docker compose restart php
sleep 15

# Confirm
docker compose exec php tail -n 30 storage/logs/laravel.log | grep -iE "bootstrapped|dispatched"
```

### Manually kick off the jobs

If the automatic bootstrap keeps failing, dispatch the initial pair by hand:

```bash
docker compose exec php php artisan tinker --execute='
  dispatch(new \App\Jobs\MonitoringEngine\DispatchMonitorChecks);
  dispatch(new \App\Jobs\MonitoringEngine\ProcessMonitorResults);
  echo "dispatched\n";
'
```

These are self-requeuing — once started they'll keep running on their own.

### Force an immediate check on a specific monitor

```bash
docker compose exec php php artisan tinker --execute='
  $m = \App\Models\Monitor::find(1);   // adjust ID
  app(\App\Services\MonitoringEngine\CheckDispatcher::class)->dispatchCheck($m);
  echo "dispatched check for " . $m->name . "\n";
'
```

---

## Probe-specific issues

### Probe is up but not running checks

```bash
# Confirm probe is subscribed to the stream
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XINFO GROUPS checks
# Should list probe-<NODE_ID> as a consumer group with recent last-delivered-id

# Probe health endpoint
docker compose exec probe wget -qO- http://localhost:8080/health

# Probe logs
docker compose logs probe --tail 30
```

### "AUTH failed" in probe logs

Probe's `REDIS_PASSWORD` env doesn't match Redis's. On the server:

```bash
# What the probe thinks the password is
docker compose exec probe printenv REDIS_PASSWORD

# What the .env says
grep '^REDIS_PASSWORD=' .env

# What Redis is actually configured for
docker compose exec redis redis-cli CONFIG GET requirepass
```

All three should match. If they don't, fix `.env` and `docker compose up -d --force-recreate probe redis`.

### "failed to validate token" in probe logs

JWT_TOKEN is expired or was generated with a different JWT_SECRET. Regenerate:

```bash
docker compose exec php php artisan probe:generate-token --node-id=local-node-1 --expires=365 --no-interaction
docker compose up -d --force-recreate probe
```

### Stale consumer group from an earlier install

If you see an old `probes` group (pre-Phase 2) alongside the new `probe-<node-id>` groups:

```bash
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XGROUP DESTROY checks probes
```

---

## Email / alerts not sending

### Check which driver is configured

```bash
grep '^MAIL_MAILER=' .env
```

- `log` — emails written to `storage/logs/laravel.log`. Not actually sent. Change to `ses` or `smtp` and set credentials.
- `ses` — make sure `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` are set, and `MAIL_FROM_ADDRESS` is on a verified SES identity. If in sandbox, can only send to verified recipients.
- `smtp` — test with:
  ```bash
  docker compose exec php php artisan tinker --execute='
    Mail::raw("test", fn(\$m) => \$m->to("you@example.com")->subject("test"));
    echo "sent\n";
  '
  ```
  If it throws, the output shows the actual SMTP error.

### Monitor went down but no email arrived

Check the queued notifications actually ran:

```bash
# Any failed notifications?
docker compose exec php php artisan queue:failed

# Check Horizon dashboard (requires login)
# https://your-domain/horizon (admin users only)

# Retry failed
docker compose exec php php artisan queue:retry all
```

---

## DB / Redis issues

### Connect to PostgreSQL directly

```bash
docker compose exec db psql -U easymonitor -d easymonitor

# Common queries
SELECT COUNT(*) FROM monitors;
SELECT name, status, consecutive_failures FROM monitors;
SELECT * FROM probe_nodes;
```

### Connect to Redis directly

```bash
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning

# Useful commands once connected
XLEN checks
XLEN results
XINFO GROUPS checks
KEYS easymonitor_cache:*
LLEN queues:monitoring
```

### Reset the monitoring loop without losing data

Sometimes the loop gets "stuck" (e.g. after a long container pause). Hard reset:

```bash
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)

# Clear bootstrap flag and any stale locks
docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning DEL \
  easymonitor_cache:monitoring:dispatcher:initialized \
  easymonitor_laravel_cache_monitor:dispatch-checks:lock \
  easymonitor_laravel_cache_monitor:process-results:lock

# Restart php
docker compose restart php
```

---

## Upgrades

### Standard upgrade flow

```bash
cd ~/easymonitor
git pull

# Pull any new images (if using published ones)
docker compose pull

# Rebuild containers (if Dockerfiles changed)
docker compose up -d --build

# Run migrations
docker compose exec php php artisan migrate --force

# Clear caches
docker compose exec php php artisan optimize:clear
```

### Hard reset (destroys all data)

```bash
docker compose down -v      # removes containers + volumes (DB, Redis, Caddy certs)
docker compose rm -f
docker rmi easymonitor-php easymonitor-probe 2>/dev/null
rm -f .env
git pull
./setup.sh
```

---

## Diagnostic bundle

Paste this when asking for help — it's a single block that captures the most useful state:

```bash
cd ~/easymonitor
REDIS_PW=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2)

echo "=== git ===" && git log --oneline -3
echo "=== env (redacted) ===" && grep -E '^(APP_|DB_|REDIS_|QUEUE_|MAIL_|COMPOSE_FILE)' .env | sed -E 's/(PASSWORD|KEY|TOKEN|SECRET)=.*/\1=REDACTED/'
echo "=== containers ===" && docker compose ps
echo "=== horizon ===" && docker compose exec php php artisan horizon:status
echo "=== redis streams ===" && \
  docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XLEN checks && \
  docker compose exec redis redis-cli -a "$REDIS_PW" --no-auth-warning XLEN results
echo "=== recent laravel errors ===" && docker compose exec php tail -n 40 storage/logs/laravel.log | grep -iE "error|exception" || echo "(none)"
echo "=== probe logs ===" && docker compose logs probe --tail 10
```
