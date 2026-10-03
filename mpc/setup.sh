#!/usr/bin/env bash
# One-time setup of a local 3-node Mpcium cluster (2-of-3 threshold) under mpc/cluster/.
# Re-running is safe: existing identities and keys are kept.
set -euo pipefail

MPCIUM_VERSION="${MPCIUM_VERSION:-eb109ae229f64d8d29a5ac35e19e95d7619129e1}"
NUM_NODES=3
DIR="$(cd "$(dirname "$0")" && pwd)"
CLUSTER="$DIR/cluster"
BIN="$DIR/bin"
export PATH="$BIN:$PATH"
export GOTOOLCHAIN=auto

command -v go >/dev/null || { echo "Go is required: https://go.dev/dl/"; exit 1; }
command -v docker >/dev/null || { echo "Docker is required: https://docs.docker.com/get-docker/"; exit 1; }
docker info >/dev/null 2>&1 || { echo "Docker is installed but not running. Start Docker Desktop and re-run."; exit 1; }

# Mpcium's go.mod uses replace directives, so `go install pkg@version` refuses it; build from a checkout.
if [ ! -x "$BIN/mpcium" ] || [ ! -x "$BIN/mpcium-cli" ]; then
    echo "Building mpcium + mpcium-cli ($MPCIUM_VERSION)..."
    SRC="$DIR/.src/mpcium"
    [ -d "$SRC/.git" ] || git clone -q https://github.com/fystack/mpcium "$SRC"
    git -C "$SRC" fetch -q origin "$MPCIUM_VERSION" && git -C "$SRC" checkout -q "$MPCIUM_VERSION"
    mkdir -p "$BIN"
    (cd "$SRC" && go build -o "$BIN/mpcium" ./cmd/mpcium && go build -o "$BIN/mpcium-cli" ./cmd/mpcium-cli)
fi

echo "Starting NATS + Consul..."
docker compose -f "$DIR/docker-compose.yml" up -d
for _ in $(seq 1 30); do
    curl -sf http://127.0.0.1:8500/v1/status/leader | grep -q ':' && break
    sleep 1
done

mkdir -p "$CLUSTER"
cd "$CLUSTER"

[ -f peers.json ] || mpcium-cli generate-peers -n "$NUM_NODES"

if [ ! -f event_initiator.key ]; then
    mpcium-cli generate-initiator --algorithm ed25519
fi
INITIATOR_PUBKEY=$(grep -o '"public_key": *"[^"]*"' event_initiator.identity.json | cut -d '"' -f4)

[ -f .chain_code ] || openssl rand -hex 32 > .chain_code
[ -f .badger_password ] || openssl rand -hex 16 > .badger_password
CHAIN_CODE=$(cat .chain_code)
BADGER_PASSWORD=$(cat .badger_password)

# Shared node config; mpcium-cli also reads it (for the Consul address) from the cwd.
cat > config.yaml <<YAML
nats:
  url: nats://127.0.0.1:4222
consul:
  address: 127.0.0.1:8500
mpc_threshold: 2
environment: development
badger_password: "$BADGER_PASSWORD"
event_initiator_algorithm: ed25519
event_initiator_pubkey: "$INITIATOR_PUBKEY"
chain_code: "$CHAIN_CODE"
db_path: "."
backup_enabled: true
backup_period_seconds: 300
backup_dir: backups
max_concurrent_keygen: 2
max_concurrent_signing: 10
healthcheck:
  enabled: false
YAML

mpcium-cli register-peers --environment development

for i in $(seq 0 $((NUM_NODES - 1))); do
    node="node$i"
    mkdir -p "$node/identity"
    cp -f peers.json config.yaml "$node/"
    if [ ! -f "$node/identity/${node}_identity.json" ]; then
        (cd "$node" && mpcium-cli generate-identity --node "$node")
    fi
done

# Every node needs every other node's public identity.
for i in $(seq 0 $((NUM_NODES - 1))); do
    for j in $(seq 0 $((NUM_NODES - 1))); do
        [ "$i" = "$j" ] || cp -f "node$i/identity/node${i}_identity.json" "node$j/identity/"
    done
done

echo "Installing signer dependencies..."
(cd "$DIR/signer" && npm install --silent)

echo
echo "MPC cluster ready in mpc/cluster/. Start it with: npm run dev:mpc"
