#!/usr/bin/env bash
# Runs NATS + Consul (Docker), the three Mpcium nodes, and the HTTP signer that Laravel talks to.
set -euo pipefail
umask 077

DIR="$(cd "$(dirname "$0")" && pwd)"
CLUSTER="${MPC_HOME:-$DIR/cluster}"
NATS_PORT="${MPC_NATS_PORT:-4222}"
export PATH="$DIR/bin:$PATH" MPC_HOME="$CLUSTER"

[ -f "$CLUSTER/signer.env" ] && [ -f "$CLUSTER/nats.conf" ] || { echo "Run 'npm run mpc:setup' first (re-run it after upgrading)."; exit 1; }

docker info >/dev/null 2>&1 || { echo "Docker is not running. Start Docker Desktop first."; exit 1; }
docker compose -f "$DIR/docker-compose.yml" up -d >/dev/null

pids=()
trap 'kill "${pids[@]}" 2>/dev/null; wait' EXIT INT TERM

# Credentials go through the environment (viper maps NATS_URL to nats.url), not config.yaml.
NATS_URL="nats://mpc_node:$(cat "$CLUSTER/.nats_node_password")@127.0.0.1:$NATS_PORT"

for node in node0 node1 node2; do
    (cd "$CLUSTER/$node" && NATS_URL="$NATS_URL" exec mpcium start -n "$node" --password-file .badger_password 2>&1 | sed -u "s/^/[$node] /") &
    pids+=($!)
done

node --env-file="$CLUSTER/signer.env" "$DIR/signer/server.mjs" &
pids+=($!)

wait
