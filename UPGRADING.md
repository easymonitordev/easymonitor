# Upgrading EasyMonitor

## Standard upgrade

From the project root:

```bash
git pull
./setup.sh --upgrade
```

This rebuilds the Docker images with the new code, restarts the stack, runs
database migrations, rebuilds frontend assets, and re-applies the check
result retention policy. Your `.env` and all data are left untouched.

To upgrade to a specific release instead of the latest `main`:

```bash
git fetch --tags
git checkout v0.1.5
./setup.sh --upgrade
```

## Update notifications

The dashboard shows a banner to the instance owner (the first registered
user) when a newer release is available. A daily background job asks the
GitHub releases API for the latest version — no instance data is sent.

To disable the check entirely (e.g. air-gapped installs), set:

```bash
EASYMONITOR_CHECK_UPDATES=false
```

## Remote probes

The bundled local probe is rebuilt and restarted automatically. Probes
running on other machines must be updated separately — pull the new probe
code/image on each probe host and restart it. Probes ignore check types
they do not understand, so updating the server first is safe.

## Version-specific notes

### v0.3.2

- **Update remote probes to v0.3.2.** A probe that fell behind while running
  kept working through old checks and reported their results against rounds
  dispatched long ago; one production probe was nearly 17 days behind. From
  v0.3.2, probes skip checks older than `MAX_CHECK_AGE` (default `2m`)
  instead. The bundled local probe is rebuilt automatically by
  `./setup.sh --upgrade`; remote probe hosts can use the probe repo's
  `upgrade.sh` or pull and restart manually. Older probes keep working.
- **Duplicate monitoring loops are cleaned up automatically.** The check
  dispatcher and result processor are self-requeuing jobs, and every extra
  dispatch used to leave one more copy running forever. Now only one copy of
  each survives, and leftover copies stop on their next run after upgrading.
  Restarting Horizon (`php artisan horizon:terminate`) is now always a clean
  reset of the loops; the `monitoring:dispatcher:initialized` cache flag is
  no longer used.


- **Security: webhook and ntfy URLs must now resolve to public addresses.**
  Generic webhook URLs and self-hosted ntfy server URLs could previously
  point at loopback, private-network or cloud metadata addresses, letting
  any logged-in user make this server send requests into its own network.
  These URLs are now checked when saved and again on every delivery, and
  deliveries no longer follow redirects. Existing channels that point at a
  private address stop delivering (a warning is logged). If you notify
  services on your own LAN, such as a self-hosted ntfy server, set
  `EASYMONITOR_ALLOW_PRIVATE_NOTIFICATION_TARGETS=true`. Link-local
  addresses, including `169.254.169.254`, stay blocked either way.
- **Redis memory is now bounded.** The `checks` and `results` streams were
  never trimmed and could grow to gigabytes. Redis then takes long enough to
  load on restart that Horizon fails to start and supervisord gives up,
  which stops all monitoring. An hourly `easymonitor:prune-streams` task now
  trims both streams (`EASYMONITOR_STREAM_MAX_LENGTH`, default 10000) and
  removes idle result consumers, including the millions some installs
  accumulated from a per-process consumer leak. The first hourly run frees
  the existing backlog; to free it right away, run
  `docker compose exec php php artisan easymonitor:prune-streams`.
- Horizon now retries for about three and a half minutes when Redis is not
  ready yet, and the php and probe containers wait for Redis to finish
  loading on `docker compose up`.
- The queue-health watchdog now runs inside the scheduler instead of on the
  queue, so it can alert you when Horizon itself is down.
- **Switch to daily log rotation.** New installs default to
  `LOG_STACK=daily` (14 days kept, set with `LOG_DAILY_DAYS`). Existing
  `.env` files keep `LOG_STACK=single`, where `storage/logs/laravel.log`
  grows forever. Change it to `daily` and delete the old `laravel.log`.
  `horizon.log` and `scheduler.log` are now capped at 10 MB × 5 files.

### v0.3.0

- **Update remote probes to v0.3.0 to get keyword assertions.** Assertions
  are evaluated in the probe. Older probes keep working and keep reporting
  up/down normally — they simply ignore the assertion and evaluate only the
  status code, so a monitor's keyword assertion has no effect until every
  probe checking it is updated. The bundled local probe is rebuilt
  automatically by `./setup.sh --upgrade`; remote probe hosts can use the
  probe repo's `upgrade.sh` or pull and restart manually.

### v0.2.1

- **The Laravel scheduler now runs inside the php container.** Previous
  Docker installs had no scheduler process, so scheduled background tasks
  never ran: the update-available banner, the queue-health watchdog,
  certificate expiry alerts (v0.2.0), and Horizon metric snapshots. Upgrading
  fixes this automatically — the rebuilt image starts a `schedule:work`
  process under supervisord. No manual steps are required.
- `./setup.sh --upgrade` now gracefully restarts Horizon after deploying new
  code, so queue workers no longer keep running the previous release when
  the container itself is not recreated.

### v0.2.0

- **Update remote probes when upgrading the server.** TCP checks are new in
  this release; probes older than v0.2.0 do not understand them and would
  report TCP monitors as down. The bundled local probe is rebuilt
  automatically by `./setup.sh --upgrade`; remote probe hosts must pull and
  restart their probe. From v0.2.0 on, probes skip check types they do not
  recognize, so this class of problem cannot recur in future upgrades.
- Certificate expiry monitoring also requires updated probes: only v0.2.0+
  probes report certificate data. Old probes keep working for up/down
  checks — HTTPS monitors just will not show expiry info until the probe
  is updated.

### v0.1.5

- A data retention policy now applies to raw check results (90 days by
  default, compressed after 7). If you want a longer window, set
  `CHECK_RESULT_RETENTION_DAYS` in `.env` **before** upgrading, or re-apply
  afterwards with `docker compose exec php php artisan easymonitor:retention`.
  See "Data Retention & Disk Usage" in DOCKER.md.

## Rollback

Database migrations are not automatically reversed. To roll back a release:

```bash
git checkout <previous-tag>
./setup.sh --upgrade
```

If a release's notes list schema changes, restore the database from the
backup you took before upgrading instead of rolling migrations back.
