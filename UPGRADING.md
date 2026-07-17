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
