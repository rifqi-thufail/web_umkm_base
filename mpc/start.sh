#!/usr/bin/env bash
# Runs NATS + Consul (Docker), the three Mpcium nodes, and the HTTP signer that Laravel talks to.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$DIR")"
export PATH="$DIR/bin:$PATH"

[ -f "$DIR/cluster/event_initiator.key" ] || { echo "Run 'npm run mpc:setup' first."; exit 1; }

docker info >/dev/null 2>&1 || { echo "Docker is not running. Start Docker Desktop first."; exit 1; }
docker compose -f "$DIR/docker-compose.yml" up -d >/dev/null

pids=()
trap 'kill "${pids[@]}" 2>/dev/null; wait' EXIT INT TERM

for node in node0 node1 node2; do
    (cd "$DIR/cluster/$node" && exec mpcium start -n "$node" 2>&1 | sed -u "s/^/[$node] /") &
    pids+=($!)
done

cd "$ROOT"
node --env-file=.env "$DIR/signer/server.mjs" &
pids+=($!)

wait
