#!/usr/bin/env bash
#
# EasyMonitor — unified installer.
#
# Run this from the project root. It will:
#   1. Ask whether this is a Local (dev) or Production install
#   2. Collect required (and optional) configuration interactively
#   3. Generate or update .env
#   4. Patch docker/caddy/Caddyfile.production for production installs
#   5. Build and start the docker stack
#   6. Run migrations, generate keys, build assets, set up storage
#
# Re-running is safe: it will detect existing config and offer to keep it.

set -e

# ── colors ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()    { printf "${BLUE}ℹ${NC}  %s\n" "$1"; }
ok()      { printf "${GREEN}✓${NC}  %s\n" "$1"; }
warn()    { printf "${YELLOW}⚠${NC}  %s\n" "$1"; }
fail()    { printf "${RED}✗${NC}  %s\n" "$1"; exit 1; }
header()  { printf "\n${BOLD}━━━ %s ━━━${NC}\n\n" "$1"; }

# ── helpers ─────────────────────────────────────────────────────────────────
require_cmd() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

ask() {
    # ask <prompt> <default>  → echoes the answer
    local prompt="$1"
    local default="$2"
    local answer

    if [ -n "$default" ]; then
        read -p "  $prompt [$default]: " answer
        echo "${answer:-$default}"
    else
        read -p "  $prompt: " answer
        echo "$answer"
    fi
}

ask_secret() {
    # ask_secret <prompt>  → echoes the typed secret (hidden), no default
    local prompt="$1"
    local answer
    read -s -p "  $prompt: " answer
    echo "" >&2
    echo "$answer"
}

confirm() {
    # confirm <prompt>  → returns 0 for y, 1 for n (default n)
    local prompt="$1"
    local answer
    read -p "  $prompt [y/N]: " answer
    [[ "$answer" =~ ^[Yy]$ ]]
}

random_string() {
    # 32 chars, base64-safe-ish
    openssl rand -base64 32 | tr -d '/+=\n' | cut -c1-32
}

detect_public_ip() {
    # Try a few services; first one that responds wins. Empty if all fail.
    # Try a few IPv4-only services; first one that responds wins.
    # -4 forces IPv4 on the local end, and these services return IPv4
    # so we don't accidentally grab an AAAA address on dual-stack hosts.
    for svc in "https://api.ipify.org" "https://ipv4.icanhazip.com" "https://v4.ident.me" "https://ifconfig.me" "https://ifconfig.net"; do
        local ip
        ip=$(curl -4 -fsS --max-time 5 "$svc" 2>/dev/null | tr -d '[:space:]')
        if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "$ip"
            return 0
        fi
    done
    echo ""
}

resolve_domain() {
    # Resolve <domain> to an IP. Tries 'dig' then 'host' then 'getent'.
    local domain="$1"
    local ip
    if command -v dig >/dev/null 2>&1; then
        ip=$(dig +short "$domain" A | head -n1)
    elif command -v host >/dev/null 2>&1; then
        ip=$(host -t A "$domain" 2>/dev/null | awk '/has address/ {print $4; exit}')
    elif command -v getent >/dev/null 2>&1; then
        ip=$(getent hosts "$domain" | awk '{print $1; exit}')
    fi
    echo "$ip"
}

set_env() {
    # set_env <key> <value>  — adds or updates a key in .env
    local key="$1"
    local value="$2"
    # escape chars dangerous to sed
    local escaped
    escaped=$(printf '%s\n' "$value" | sed -e 's/[\/&]/\\&/g')

    if grep -q "^${key}=" .env 2>/dev/null; then
        sed -i.bak "s/^${key}=.*/${key}=${escaped}/" .env && rm -f .env.bak
    else
        echo "${key}=${value}" >> .env
    fi
}

# ── checks ──────────────────────────────────────────────────────────────────
require_cmd docker
require_cmd openssl
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 not available (run 'docker compose version' to check)"

cat <<'BANNER'

╔══════════════════════════════════════════════════════════════╗
║               EasyMonitor — Installer                        ║
╚══════════════════════════════════════════════════════════════╝

BANNER

# ── mode ────────────────────────────────────────────────────────────────────
header "Install mode"
echo "  1) Local development"
echo "  2) Production (auto-HTTPS via Let's Encrypt + on-demand TLS for status pages)"
echo ""
MODE=""
while [[ "$MODE" != "1" && "$MODE" != "2" ]]; do
    read -p "  Choose [1/2]: " MODE
done

IS_PROD=false
[ "$MODE" = "2" ] && IS_PROD=true

# ── .env ────────────────────────────────────────────────────────────────────
if [ -f .env ]; then
    warn ".env already exists."
    if ! confirm "Continue and update existing .env in place?"; then
        info "Aborting. Move .env aside and re-run if you want a clean start."
        exit 0
    fi
else
    cp .env.example .env
    ok "Created .env from .env.example"
fi

# ── basics ──────────────────────────────────────────────────────────────────
header "Basics"

APP_NAME=$(ask "App name" "EasyMonitor")
set_env APP_NAME "$APP_NAME"

if $IS_PROD; then
    info "Detecting this server's public IP address..."
    PUBLIC_IP=$(detect_public_ip)
    if [ -n "$PUBLIC_IP" ]; then
        ok "Server public IP: ${PUBLIC_IP}"
    else
        warn "Could not auto-detect public IP. You'll need to know it for DNS setup."
    fi
    echo ""

    DOMAIN=$(ask "Primary domain (e.g. monitor.example.com)" "")
    [ -z "$DOMAIN" ] && fail "Primary domain is required for production"

    # DNS sanity check — warn early if the domain doesn't already point here.
    DOMAIN_IP=$(resolve_domain "$DOMAIN")
    if [ -n "$DOMAIN_IP" ] && [ -n "$PUBLIC_IP" ]; then
        if [ "$DOMAIN_IP" = "$PUBLIC_IP" ]; then
            ok "DNS check: ${DOMAIN} resolves to ${DOMAIN_IP} (matches this server)."
        else
            warn "DNS mismatch: ${DOMAIN} resolves to ${DOMAIN_IP}, but this server is ${PUBLIC_IP}."
            warn "Update your DNS A record to ${PUBLIC_IP} before HTTPS will work."
            confirm "Continue anyway?" || fail "Aborting. Fix DNS and re-run."
        fi
    elif [ -z "$DOMAIN_IP" ]; then
        warn "${DOMAIN} doesn't resolve to any IP yet."
        warn "Add a DNS A record:  ${DOMAIN}  →  ${PUBLIC_IP:-<this-server-ip>}"
        confirm "Continue anyway? (TLS will fail until DNS propagates)" || fail "Aborting. Fix DNS and re-run."
    fi

    ADMIN_EMAIL=$(ask "Admin email (for Let's Encrypt notifications)" "")
    [ -z "$ADMIN_EMAIL" ] && fail "Admin email is required for production"

    set_env APP_ENV "production"
    set_env APP_DEBUG "false"
    set_env APP_URL "https://${DOMAIN}"
    ok "App URL set to https://${DOMAIN}"
else
    set_env APP_ENV "local"
    set_env APP_DEBUG "true"
    set_env APP_URL "http://localhost"
    ok "App URL set to http://localhost"
fi

# ── database ────────────────────────────────────────────────────────────────
header "Database"

if $IS_PROD; then
    DB_PASSWORD=$(random_string)
    info "Generated a strong random DB password."
else
    DB_PASSWORD=$(ask "Database password" "secret")
fi
set_env DB_PASSWORD "$DB_PASSWORD"

# ── redis ───────────────────────────────────────────────────────────────────
header "Redis"

if $IS_PROD; then
    REDIS_PASSWORD=$(random_string)
    set_env REDIS_PASSWORD "$REDIS_PASSWORD"
    ok "Generated Redis password (required for production)."
else
    info "Leaving Redis without password (local dev)."
    REDIS_PASSWORD=""
fi

# ── remote probes ───────────────────────────────────────────────────────────
REMOTE_PROBES=false
REMOTE_REDIS_URL=""

header "Remote probe nodes"

echo "  EasyMonitor runs a local probe out of the box. If you want probes in"
echo "  other regions, they need a network path to this server's Redis."
echo ""
echo "  Recommended approach: a VPN-style tunnel (Tailscale or Cloudflare"
echo "  Tunnel). Never expose Redis directly on the public internet."
echo ""
if confirm "Will you run probes on other machines?"; then
    REMOTE_PROBES=true

    echo ""
    echo "  1) Tailscale  — simplest. Free for up to 100 devices."
    echo "  2) Cloudflare Tunnel — free with any Cloudflare account, more setup."
    echo "  3) I'll configure networking manually (SSH, WireGuard, etc.)"
    echo ""
    TUNNEL_CHOICE=""
    while [[ ! "$TUNNEL_CHOICE" =~ ^[1-3]$ ]]; do
        read -p "  Choose [1/2/3]: " TUNNEL_CHOICE
    done

    case "$TUNNEL_CHOICE" in
        1)
            # Tailscale
            if command -v tailscale >/dev/null 2>&1; then
                ok "Tailscale already installed."
            else
                info "Tailscale is not installed."
                if confirm "Install Tailscale now? (runs the official installer)"; then
                    curl -fsSL https://tailscale.com/install.sh | sh || fail "Tailscale install failed."
                    ok "Tailscale installed."
                else
                    warn "Skipping Tailscale install. Follow https://tailscale.com/download when ready."
                fi
            fi

            if command -v tailscale >/dev/null 2>&1; then
                if ! tailscale status >/dev/null 2>&1; then
                    info "Starting Tailscale — a browser-based login URL will open."
                    sudo tailscale up || warn "Tailscale up failed. Run 'sudo tailscale up' manually."
                fi

                TAILSCALE_IP=$(tailscale ip -4 2>/dev/null | head -n1 || true)
                if [ -n "$TAILSCALE_IP" ]; then
                    ok "Tailscale IP: $TAILSCALE_IP"
                    set_env REDIS_BIND_HOST "$TAILSCALE_IP"
                    REMOTE_REDIS_URL="redis://${TAILSCALE_IP}:6379/0"
                else
                    warn "Couldn't read Tailscale IP. Run 'tailscale ip -4' after setup and update REDIS_BIND_HOST in .env."
                    set_env REDIS_BIND_HOST "127.0.0.1"
                    REMOTE_REDIS_URL="redis://<tailscale-ip>:6379/0"
                fi
            else
                set_env REDIS_BIND_HOST "127.0.0.1"
                REMOTE_REDIS_URL="redis://<tailscale-ip>:6379/0 (after Tailscale install)"
            fi
            ;;
        2)
            info "Cloudflare Tunnel setup — see PROBE_NODE_SETUP.md for the full guide."
            info "cloudflared will run on this host and bridge a hostname to localhost:6379."
            set_env REDIS_BIND_HOST "127.0.0.1"
            REMOTE_REDIS_URL="redis://<your-tunnel-hostname>:6379/0"
            ;;
        3)
            info "Manual networking. Redis will bind to 127.0.0.1:6379 by default —"
            info "edit REDIS_BIND_HOST in .env if you need a different interface."
            set_env REDIS_BIND_HOST "127.0.0.1"
            REMOTE_REDIS_URL="redis://<your-tunnel-endpoint>:6379/0"
            ;;
    esac

    # Append the remote-probes compose override so REDIS_BIND_HOST takes effect.
    CURRENT_CF=$(grep -E '^COMPOSE_FILE=' .env 2>/dev/null | head -n1 | cut -d= -f2- || echo "")
    [ -z "$CURRENT_CF" ] && CURRENT_CF="docker-compose.yml"
    if [[ "$CURRENT_CF" != *"remote-probes"* ]]; then
        CURRENT_CF="${CURRENT_CF}:docker-compose.remote-probes.yml"
    fi
    set_env COMPOSE_FILE "$CURRENT_CF"
fi

# ── registration ────────────────────────────────────────────────────────────
header "User registration"
if confirm "Allow open user registration? (No = only the very first user can register)"; then
    set_env REGISTRATION_ENABLED "true"
else
    set_env REGISTRATION_ENABLED "false"
    info "Registration locked. The first user to sign up will be the admin."
fi

# ── mail ────────────────────────────────────────────────────────────────────
header "Email (alerts)"
echo "  1) Skip — log emails to storage/logs (no real delivery)"
echo "  2) Amazon SES"
echo "  3) Generic SMTP (Mailgun, Postmark, custom, etc.)"
echo ""
MAIL_CHOICE=""
while [[ ! "$MAIL_CHOICE" =~ ^[1-3]$ ]]; do
    read -p "  Choose [1/2/3]: " MAIL_CHOICE
done

case "$MAIL_CHOICE" in
    2)
        info "Amazon SES — make sure the from-address is on a verified identity."
        set_env MAIL_MAILER "ses"
        FROM=$(ask "From email" "alerts@${DOMAIN:-example.com}")
        FROM_NAME=$(ask "From name" "$APP_NAME")
        set_env MAIL_FROM_ADDRESS "\"$FROM\""
        set_env MAIL_FROM_NAME "\"$FROM_NAME\""
        AWS_KEY_INPUT=$(ask "AWS Access Key ID" "")
        AWS_SECRET_INPUT=$(ask_secret "AWS Secret Access Key")
        AWS_REGION_INPUT=$(ask "AWS region (e.g. us-east-1, eu-west-1)" "us-east-1")
        # AWS_* covers every AWS service (SES, S3, etc).
        # Cloudflare R2 has its own R2_* namespace below.
        set_env AWS_ACCESS_KEY_ID "$AWS_KEY_INPUT"
        set_env AWS_SECRET_ACCESS_KEY "$AWS_SECRET_INPUT"
        set_env AWS_DEFAULT_REGION "$AWS_REGION_INPUT"
        ok "SES configured."
        ;;
    3)
        set_env MAIL_MAILER "smtp"
        SMTP_HOST=$(ask "SMTP host" "smtp.example.com")
        SMTP_PORT=$(ask "SMTP port" "587")
        SMTP_USER=$(ask "SMTP username" "")
        SMTP_PASS=$(ask_secret "SMTP password")
        FROM=$(ask "From email" "alerts@${DOMAIN:-example.com}")
        FROM_NAME=$(ask "From name" "$APP_NAME")
        set_env MAIL_HOST "$SMTP_HOST"
        set_env MAIL_PORT "$SMTP_PORT"
        set_env MAIL_USERNAME "$SMTP_USER"
        set_env MAIL_PASSWORD "$SMTP_PASS"
        set_env MAIL_SCHEME "tls"
        set_env MAIL_FROM_ADDRESS "\"$FROM\""
        set_env MAIL_FROM_NAME "\"$FROM_NAME\""
        ok "SMTP configured."
        ;;
    *)
        set_env MAIL_MAILER "log"
        info "Emails will be written to storage/logs/laravel.log."
        ;;
esac

# ── pushover ────────────────────────────────────────────────────────────────
header "Pushover (push notifications)"
echo "  Pushover delivers instant push alerts to your phone/desktop. Each user"
echo "  still supplies their own user key from the Notifications settings page."
echo "  You only need the application token here (one per EasyMonitor install)."
echo ""
echo "  Create an app at: https://pushover.net/apps/build"
echo ""
if confirm "Do you already have a Pushover application token?"; then
    PUSHOVER_TOKEN=$(ask_secret "Pushover application token")
    if [ -n "$PUSHOVER_TOKEN" ]; then
        set_env PUSHOVER_APP_TOKEN "$PUSHOVER_TOKEN"
        ok "Pushover configured. Users can now add their user key at /settings/notifications."
    else
        info "Empty token — skipping. You can set PUSHOVER_APP_TOKEN in .env later."
    fi
else
    info "Skipping. Email alerts still work; add PUSHOVER_APP_TOKEN to .env later to enable Pushover."
fi

# ── object storage (R2 / S3) ────────────────────────────────────────────────
header "Object storage (status page logos)"
echo "  1) Local disk (default)"
echo "  2) Cloudflare R2"
echo "  3) Amazon S3"
echo ""
STORE_CHOICE=""
while [[ ! "$STORE_CHOICE" =~ ^[1-3]$ ]]; do
    read -p "  Choose [1/2/3]: " STORE_CHOICE
done

case "$STORE_CHOICE" in
    2)
        info "Cloudflare R2 (S3-compatible)."
        R2_ACCOUNT_INPUT=$(ask "Cloudflare account ID" "")
        R2_BUCKET_INPUT=$(ask "R2 bucket name" "")
        R2_KEY_INPUT=$(ask "R2 access key" "")
        R2_SECRET_INPUT=$(ask_secret "R2 secret key")
        R2_PUBLIC_INPUT=$(ask "Public URL (e.g. https://cdn.example.com — leave blank if private)" "")

        # R2 has its own dedicated namespace so it never collides with AWS
        # (SES, S3, etc.) credentials. The 'r2' filesystem disk in
        # config/filesystems.php reads these R2_* env vars.
        set_env FILESYSTEM_DISK "r2"
        set_env R2_ACCESS_KEY "$R2_KEY_INPUT"
        set_env R2_SECRET_KEY "$R2_SECRET_INPUT"
        set_env R2_BUCKET "$R2_BUCKET_INPUT"
        set_env R2_ENDPOINT "https://${R2_ACCOUNT_INPUT}.r2.cloudflarestorage.com"
        [ -n "$R2_PUBLIC_INPUT" ] && set_env R2_URL "$R2_PUBLIC_INPUT"
        ok "R2 configured."
        ;;
    3)
        info "Amazon S3."
        S3_BUCKET_INPUT=$(ask "S3 bucket name" "")
        # Reuse the AWS_* credentials already written by the SES flow (if any).
        # If not set yet, prompt now; otherwise just ask for the bucket.
        if ! grep -q '^AWS_ACCESS_KEY_ID=.' .env 2>/dev/null; then
            S3_KEY_INPUT=$(ask "AWS Access Key ID" "")
            S3_SECRET_INPUT=$(ask_secret "AWS Secret Access Key")
            S3_REGION_INPUT=$(ask "AWS region" "us-east-1")
            set_env AWS_ACCESS_KEY_ID "$S3_KEY_INPUT"
            set_env AWS_SECRET_ACCESS_KEY "$S3_SECRET_INPUT"
            set_env AWS_DEFAULT_REGION "$S3_REGION_INPUT"
        else
            info "Reusing AWS credentials already configured."
        fi
        set_env FILESYSTEM_DISK "s3"
        set_env AWS_BUCKET "$S3_BUCKET_INPUT"
        set_env AWS_USE_PATH_STYLE_ENDPOINT "false"
        ok "S3 configured."
        ;;
    *)
        set_env FILESYSTEM_DISK "local"
        info "Using local disk for uploads."
        ;;
esac

# ── patch production Caddyfile ──────────────────────────────────────────────
if $IS_PROD; then
    header "Patching Caddyfile.production"

    CADDYFILE="docker/caddy/Caddyfile.production"

    # Replace email and domain placeholders.
    sed -i.bak \
        -e "s/admin@your-domain.com/${ADMIN_EMAIL}/g" \
        -e "s/your-domain.com/${DOMAIN}/g" \
        "$CADDYFILE"
    rm -f "${CADDYFILE}.bak"

    ok "Caddyfile.production updated for ${DOMAIN}"

    # Ensure docker compose uses the production override automatically.
    # Merge with whatever is already set (e.g. remote-probes override added
    # earlier in this script) so we don't clobber previous choices.
    CURRENT_CF=$(grep -E '^COMPOSE_FILE=' .env 2>/dev/null | head -n1 | cut -d= -f2- || echo "")
    [ -z "$CURRENT_CF" ] && CURRENT_CF="docker-compose.yml"
    if [[ "$CURRENT_CF" != *"production.yml"* ]]; then
        # Insert production.yml right after the base compose file so its
        # overrides apply before any later overrides (e.g. remote-probes).
        CURRENT_CF=$(echo "$CURRENT_CF" | sed 's|^docker-compose.yml|docker-compose.yml:docker-compose.production.yml|')
        # Guard: if the base file wasn't first, just prepend production
        [[ "$CURRENT_CF" != *"production.yml"* ]] && CURRENT_CF="docker-compose.yml:docker-compose.production.yml:${CURRENT_CF}"
    fi
    set_env COMPOSE_FILE "$CURRENT_CF"
    ok "COMPOSE_FILE set so production overrides apply by default."
fi

# ── build & start docker ────────────────────────────────────────────────────
header "Starting Docker"

info "Building images and starting containers (this may take a few minutes)..."
docker compose up -d --build
ok "Containers up."

info "Waiting for services to become responsive..."
sleep 6

# ── run inner setup ─────────────────────────────────────────────────────────
header "Application setup"
docker compose exec -T php bash /var/www/html/docker/scripts/setup.sh

# ── recycle probe so it picks up the freshly-generated JWT_TOKEN ────────────
# The bundled probe container started before the inner setup wrote
# PROBE_JWT_TOKEN to .env; recreate it so Docker re-reads the env file.
info "Restarting probe to pick up the new token..."
docker compose up -d --force-recreate probe >/dev/null 2>&1 || true
ok "Probe restarted."

# ── done ────────────────────────────────────────────────────────────────────
header "Done"
if $IS_PROD; then
    ok "EasyMonitor is starting up at https://${DOMAIN}"
    echo ""
    echo "  Next steps:"
    if [ -n "$PUBLIC_IP" ]; then
        echo "    1. DNS A record:  ${DOMAIN}  →  ${PUBLIC_IP}"
    else
        echo "    1. Make sure DNS A record for ${DOMAIN} points to this server."
    fi
    echo "    2. Open https://${DOMAIN} — Caddy will provision the TLS cert on first request."
    echo "    3. Sign up to create the admin user (registration auto-locks after the first user)."
    echo "    4. Tail logs:  docker compose logs -f php  (or caddy / probe)"
else
    ok "EasyMonitor is running at http://localhost"
    echo ""
    echo "  Next steps:"
    echo "    1. Open http://localhost"
    echo "    2. Click Sign In → Sign up to create your account"
    echo "    3. Add your first monitor"
fi

if $REMOTE_PROBES; then
    echo ""
    echo "  Remote probes:"
    echo "    - Probe connection URL:   ${REMOTE_REDIS_URL}"
    echo "    - Generate a probe token: docker compose exec php php artisan probe:generate-token --node-id=<region>"
    echo "    - Full probe setup guide: PROBE_NODE_SETUP.md"
fi
echo ""
