#!/usr/bin/env bash
# Deploy OrderRegistry to the local Base node and print the .env lines to use.
set -euo pipefail
cd "$(dirname "$0")"

RPC_URL="${RPC_URL:-http://127.0.0.1:8545}"
# anvil's first dev account. Public test key: never use it on a real network.
PRIVATE_KEY="${PRIVATE_KEY:-0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80}"

[ -d lib/forge-std ] || forge install foundry-rs/forge-std --no-git

address=$(forge create src/OrderRegistry.sol:OrderRegistry \
    --rpc-url "$RPC_URL" --private-key "$PRIVATE_KEY" --broadcast 2>&1 \
    | awk '/Deployed to:/ {print $3}')

if [ -z "$address" ]; then
    echo "Deploy failed" >&2
    exit 1
fi

cat <<ENV
OrderRegistry deployed at $address

Add to .env:
BASE_ENABLED=true
BASE_RPC_URL=$RPC_URL
BASE_CHAIN_ID=84532
BASE_REGISTRY_ADDRESS=$address
BASE_RECORDER_PRIVATE_KEY=$PRIVATE_KEY
ENV
