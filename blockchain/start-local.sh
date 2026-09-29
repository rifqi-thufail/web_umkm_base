#!/usr/bin/env bash
# Local Base: an anvil node forked from Base Sepolia (chain id 84532).
# Falls back to a plain anvil chain with the same id when the fork RPC is unreachable (offline work).
set -euo pipefail

PORT="${PORT:-8545}"
FORK_URL="${BASE_FORK_URL:-https://sepolia.base.org}"
STATE_FILE="${STATE_FILE:-$(dirname "$0")/.anvil-state.json}"

args=(--port "$PORT" --chain-id 84532 --block-time 2 --state "$STATE_FILE")

if curl -s -m 5 -X POST "$FORK_URL" -H 'content-type: application/json' \
    -d '{"jsonrpc":"2.0","id":1,"method":"eth_chainId"}' | grep -q '0x14a34'; then
    echo "Forking Base Sepolia from $FORK_URL"
    args+=(--fork-url "$FORK_URL")
else
    echo "Base Sepolia RPC unreachable; starting a plain local chain with chain id 84532"
fi

exec anvil "${args[@]}"
