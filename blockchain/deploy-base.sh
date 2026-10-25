#!/usr/bin/env bash
# Deploy OrderRegistry to a real Base network and write the BASE_* settings into .env.
#
#   blockchain/deploy-base.sh mainnet      # Base mainnet (chain 8453, real ETH)
#   blockchain/deploy-base.sh sepolia      # Base Sepolia testnet (chain 84532, free faucet ETH)
#
# The wallet that deploys also becomes the recorder (the contract allows its deployer).
# Key source, in order: $PRIVATE_KEY, BASE_RECORDER_PRIVATE_KEY in .env, or a new wallet
# generated here and saved to .env. Fund its address with a little ETH on Base, then rerun.
set -euo pipefail
cd "$(dirname "$0")"
ENV_FILE="${ENV_FILE:-../.env}"

case "${1:-}" in
    mainnet)
        NAME="Base"; RPC_URL="${RPC_URL:-https://mainnet.base.org}"; CHAIN_ID=8453
        EXPLORER="https://basescan.org" ;;
    sepolia)
        NAME="Base Sepolia"; RPC_URL="${RPC_URL:-https://sepolia.base.org}"; CHAIN_ID=84532
        EXPLORER="https://sepolia.basescan.org" ;;
    *)
        echo "Usage: $0 mainnet|sepolia" >&2
        exit 1 ;;
esac

# Deploy (~400k gas) plus room for a few thousand anchors (~70k gas each) at Base's usual fees.
MIN_BALANCE_WEI="${MIN_BALANCE_WEI:-200000000000000}" # 0.0002 ETH
ANVIL_KEY=0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80

env_get() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }
env_set() {
    local key=$1 value=$2
    if grep -qE "^$key=" "$ENV_FILE"; then
        # Rewrite via awk so values containing "/" or "&" survive.
        awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k {print k "=" v; next} {print}' "$ENV_FILE" > "$ENV_FILE.tmp"
        mv "$ENV_FILE.tmp" "$ENV_FILE"
    else
        echo "$key=$value" >> "$ENV_FILE"
    fi
}

[ "$(cast chain-id --rpc-url "$RPC_URL")" = "$CHAIN_ID" ] || { echo "$RPC_URL is not chain $CHAIN_ID" >&2; exit 1; }

KEY="${PRIVATE_KEY:-$(env_get BASE_RECORDER_PRIVATE_KEY)}"
if [ "$KEY" = "$ANVIL_KEY" ]; then
    echo "BASE_RECORDER_PRIVATE_KEY is anvil's public dev key. Clear it in .env to generate a real one." >&2
    exit 1
fi
if [ -z "$KEY" ]; then
    KEY=$(cast wallet new --json | sed -nE 's/.*"private_key": *"(0x[0-9a-fA-F]{64})".*/\1/p')
    [ -n "$KEY" ] || { echo "Could not generate a wallet" >&2; exit 1; }
    env_set BASE_RECORDER_PRIVATE_KEY "$KEY"
    echo "Generated a new recorder wallet and saved its key to .env (BASE_RECORDER_PRIVATE_KEY)."
    echo "Back up that key: it pays the gas for every anchor."
fi
ADDRESS=$(cast wallet address --private-key "$KEY")

BALANCE=$(cast balance "$ADDRESS" --rpc-url "$RPC_URL")
echo "Recorder $ADDRESS on $NAME: $(cast from-wei "$BALANCE") ETH"
if [ "$(echo "$BALANCE < $MIN_BALANCE_WEI" | bc)" = 1 ]; then
    cat >&2 <<MSG

Not enough ETH for gas. Send at least $(cast from-wei "$MIN_BALANCE_WEI") ETH on $NAME (chain $CHAIN_ID) to:

    $ADDRESS

$( [ "$CHAIN_ID" = 84532 ] && echo "Free testnet ETH: https://docs.base.org/base-chain/tools/network-faucets" || echo "Withdraw from an exchange on the Base network, or bridge at https://bridge.base.org" )
Then run this script again.
MSG
    exit 2
fi

EXISTING=$(env_get BASE_REGISTRY_ADDRESS)
if [ -n "$EXISTING" ] && [ "$(env_get BASE_CHAIN_ID)" = "$CHAIN_ID" ] \
    && [ "$(cast code "$EXISTING" --rpc-url "$RPC_URL")" != "0x" ]; then
    echo "OrderRegistry already deployed at $EXISTING on $NAME, keeping it."
    ADDRESS_REGISTRY=$EXISTING
else
    [ -d lib/forge-std ] || forge install foundry-rs/forge-std --no-git
    VERIFY=()
    [ -n "${BASESCAN_API_KEY:-}" ] && VERIFY=(--verify --etherscan-api-key "$BASESCAN_API_KEY")
    ADDRESS_REGISTRY=$(forge create src/OrderRegistry.sol:OrderRegistry \
        --rpc-url "$RPC_URL" --private-key "$KEY" --broadcast "${VERIFY[@]+"${VERIFY[@]}"}" 2>&1 \
        | tee /dev/stderr | awk '/Deployed to:/ {print $3}')
    [ -n "$ADDRESS_REGISTRY" ] || { echo "Deploy failed" >&2; exit 1; }
fi

env_set BASE_ENABLED true
env_set BASE_NETWORK_NAME "\"$NAME\""
env_set BASE_RPC_URL "$RPC_URL"
env_set BASE_CHAIN_ID "$CHAIN_ID"
env_set BASE_REGISTRY_ADDRESS "$ADDRESS_REGISTRY"
env_set BASE_EXPLORER_URL "$EXPLORER"

cat <<DONE

OrderRegistry on $NAME: $EXPLORER/address/$ADDRESS_REGISTRY
.env updated (BASE_ENABLED, BASE_NETWORK_NAME, BASE_RPC_URL, BASE_CHAIN_ID, BASE_REGISTRY_ADDRESS,
BASE_EXPLORER_URL, BASE_RECORDER_PRIVATE_KEY). Next:

    php artisan config:clear && php artisan base:status

Production: copy the same BASE_* lines into the server's .env, then php artisan optimize.
Orders paid before this can be anchored with php artisan base:anchor <id>.
DONE
