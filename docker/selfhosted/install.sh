#!/usr/bin/env bash
# First-time setup for the self-hosted stack: writes .env with fresh secrets,
# builds the image, starts everything and prints the link to create the owner
# account. Safe to run again - an existing .env is kept, so secrets never change.
#
#   ./install.sh                       ask for the address
#   ./install.sh shop.example.com      https with an automatic certificate
#   ./install.sh 192.168.1.20          plain http on your network
#   ./install.sh http://localhost:8088 plain http on another port (testing)
set -euo pipefail
cd "$(dirname "$0")"

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "Docker is not installed. Get it from https://docs.docker.com/get-docker/ and run this again."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 is missing ('docker compose'). Update Docker and run this again."
command -v openssl >/dev/null 2>&1 || die "openssl is needed to generate secrets."

# Replace (or append) KEY=value in .env without sed -i, which differs on macOS/Linux.
set_env() {
    local key=$1 value=$2 tmp
    tmp=$(mktemp)
    awk -v k="$key" -v v="$value" 'BEGIN{done=0} $0 ~ "^"k"=" {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' .env > "$tmp"
    cat "$tmp" > .env && rm -f "$tmp"
}
get_env() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }

if [ -f .env ]; then
    say "Keeping the existing .env (delete it to start over - this does not touch your data)"
else
    address="${1:-}"
    if [ -z "$address" ]; then
        printf 'What address will people use to open the shop?\n'
        printf '  a domain (shop.example.com) for https, an IP/name on your network, or Enter for this computer only: '
        read -r address || true
    fi
    address="${address:-http://localhost}"

    host="${address#http://}"; host="${host#https://}"; host="${host%%/*}"
    hostname="${host%%:*}"
    port=""; [[ "$host" == *:* ]] && port="${host##*:}"

    # A real domain gets https (Caddy fetches the certificate); localhost, IPs
    # and *.local names can't, so they stay on plain http.
    if [[ "$address" == http://* ]] || [[ "$hostname" == "localhost" ]] || [[ "$hostname" =~ ^[0-9.]+$ ]] || [[ "$hostname" == *.local ]] || [[ "$hostname" != *.* ]]; then
        site=":80"; url="http://$host"; secure=false
    else
        site="$hostname"; url="https://$hostname"; secure=true
    fi

    cp .env.example .env
    chmod 600 .env
    set_env SITE_ADDRESS "$site"
    set_env APP_URL "$url"
    set_env SESSION_SECURE_COOKIE "$secure"
    [ -n "$port" ] && [ "$site" = ":80" ] && set_env HTTP_PORT "$port"
    set_env APP_KEY "base64:$(openssl rand -base64 32)"
    set_env SELFHOSTED_SETUP_KEY "$(openssl rand -hex 12)"
    set_env DB_PASSWORD "$(openssl rand -hex 24)"
    set_env REVERB_APP_ID "$(od -An -N4 -tu4 /dev/urandom | tr -d ' ')"
    set_env REVERB_APP_KEY "$(openssl rand -hex 10)"
    set_env REVERB_APP_SECRET "$(openssl rand -hex 24)"
    say "Wrote .env (secrets generated). Keep a copy of it somewhere safe."
fi

say "Building and starting (the first build takes a few minutes)"
docker compose up -d --build

say "Waiting for the app to come up"
for _ in $(seq 1 90); do
    state=$(docker compose ps --format '{{.Health}}' web 2>/dev/null | head -1 || true)
    [ "$state" = "healthy" ] && break
    sleep 4
done
[ "${state:-}" = "healthy" ] || die "The app did not become healthy. See: docker compose logs web"

url=$(get_env APP_URL); key=$(get_env SELFHOSTED_SETUP_KEY)
cat <<MSG

All set. Open this link to create the owner account and your shop:

    ${url}/setup?key=${key}

The link only works until the first account exists. Then sign in at ${url}.
Also worth doing now: set up email in .env (see README.md) and run ./backup.sh.
MSG
