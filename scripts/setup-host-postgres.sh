#!/usr/bin/env bash
# One-time host setup for "host PostgreSQL" mode: lets Docker containers reach
# the PostgreSQL server running on this machine.
#
#   sudo ./scripts/setup-host-postgres.sh
#
# What it does (idempotent - safe to run twice):
#   1. Makes PostgreSQL listen on the Docker gateway as well as localhost.
#   2. Adds a pg_hba rule allowing Docker networks (172.16.0.0/12) with password auth.
#   3. Restarts PostgreSQL.
#   4. Creates the role and databases used by the app (values read from .env).
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Run me with sudo: sudo $0" >&2
    exit 1
fi

ENV_FILE="$(dirname "$0")/../.env"
[[ -f "$ENV_FILE" ]] || { echo ".env not found - run: cp .env.example .env" >&2; exit 1; }

env_get() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"'; }
DB_DATABASE="$(env_get DB_DATABASE)"; DB_USERNAME="$(env_get DB_USERNAME)"; DB_PASSWORD="$(env_get DB_PASSWORD)"
: "${DB_DATABASE:?DB_DATABASE missing in .env}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}"

PG_CONF_DIR=$(ls -d /etc/postgresql/*/main | sort -V | tail -1)
PG_CONF="$PG_CONF_DIR/postgresql.conf"
PG_HBA="$PG_CONF_DIR/pg_hba.conf"
echo ">> Using cluster config: $PG_CONF_DIR"

# 1) listen_addresses: localhost + Docker bridge gateway.
if ! grep -Eq "^listen_addresses\s*=.*(172\.17\.0\.1|\*)" "$PG_CONF"; then
    cp "$PG_CONF" "$PG_CONF.bak.$(date +%s)"
    sed -i -E "s/^#?listen_addresses\s*=\s*'[^']*'/listen_addresses = 'localhost,172.17.0.1'/" "$PG_CONF"
    echo ">> listen_addresses set to 'localhost,172.17.0.1'"
else
    echo ">> listen_addresses already OK"
fi

# 2) pg_hba rule for Docker networks (compose networks live in 172.16.0.0/12).
HBA_RULE="host    all             all             172.16.0.0/12           scram-sha-256"
if ! grep -q "172.16.0.0/12" "$PG_HBA"; then
    cp "$PG_HBA" "$PG_HBA.bak.$(date +%s)"
    printf '\n# Docker containers (added by setup-host-postgres.sh)\n%s\n' "$HBA_RULE" >> "$PG_HBA"
    echo ">> pg_hba rule added"
else
    echo ">> pg_hba rule already present"
fi

# 3) Restart the cluster.
systemctl restart postgresql
echo ">> PostgreSQL restarted"

# 4) Role + databases (app + test), idempotent.
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$ BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USERNAME}') THEN
      CREATE ROLE "${DB_USERNAME}" LOGIN PASSWORD '${DB_PASSWORD}';
   ELSE
      ALTER ROLE "${DB_USERNAME}" LOGIN PASSWORD '${DB_PASSWORD}';
   END IF;
END \$\$;
SELECT 'CREATE DATABASE "${DB_DATABASE}" OWNER "${DB_USERNAME}"'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${DB_DATABASE}')\gexec
SELECT 'CREATE DATABASE "${DB_DATABASE}_test" OWNER "${DB_USERNAME}"'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${DB_DATABASE}_test')\gexec
SQL
echo ">> role '${DB_USERNAME}' and databases '${DB_DATABASE}', '${DB_DATABASE}_test' ready"

cat <<EOF

Done. To switch the app to host-PostgreSQL mode, edit .env:
    DB_HOST=host.docker.internal
    COMPOSE_PROFILES=
then: make down && make up

Verify from inside a container:
    docker compose exec app php artisan db:show
EOF
