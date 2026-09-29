# OrderRegistry on Base

AMPUH records a keccak256 fingerprint of every paid order in the `OrderRegistry`
contract on [Base](https://base.org). Anyone can recompute the fingerprint from the
order data and read the contract to confirm the order was not changed after payment.

Only the fingerprint goes on chain. Buyer names, emails, phone numbers and addresses never leave the database.

## What is hashed

`App\Services\BaseChainService::fingerprint()` builds this JSON (compact, keys in this order)
and hashes it with keccak256:

```json
{"order_number":"ORD-…","user_id":1,"seller_id":1,"total_price":"221000","created_at":"2026-09-20T01:02:03Z",
 "items":[{"product_id":1,"quantity":2,"price":"98000"}]}
```

`anchor(bytes32 dataHash, uint64 orderId)` stores it; `recordOf(bytes32)` returns
`(orderId, anchoredAt)` or `(0, 0)` if the hash is unknown.

## Local Base (development)

Requirements: [Foundry](https://book.getfoundry.sh/getting-started/installation) (`anvil`, `forge`, `cast`).

```bash
# 1. Start a local node forked from Base Sepolia (chain id 84532).
#    Falls back to a plain anvil chain with the same id when offline.
blockchain/start-local.sh

# 2. In another terminal: deploy the contract and print the .env lines.
blockchain/deploy-local.sh

# 3. Paste the printed BASE_* lines into .env, then:
php artisan config:clear
php artisan base:status          # RPC, chain id, contract, recorder address

# 4. Optional: demo data with orders anchored on the local chain.
php artisan db:seed --class=DemoSeeder
```

Chain state is saved to `blockchain/.anvil-state.json`, so the contract and anchored
orders survive a restart of `start-local.sh`. Delete that file to start clean (and redeploy).

The deploy script uses anvil's first dev account. That key is public: never use it on a real network.

### Useful commands

```bash
php artisan base:anchor 12             # anchor or retry order 12 (must be paid)
cast call $BASE_REGISTRY_ADDRESS "recordOf(bytes32)(uint64,uint64)" 0x<data-hash> --rpc-url http://127.0.0.1:8545
cd blockchain && forge test            # contract tests
```

## Base Sepolia / Base mainnet

```bash
cd blockchain
forge create src/OrderRegistry.sol:OrderRegistry \
  --rpc-url https://sepolia.base.org --private-key $DEPLOYER_KEY --broadcast
```

`.env`:

```
BASE_ENABLED=true
BASE_NETWORK_NAME="Base Sepolia"
BASE_RPC_URL=https://sepolia.base.org        # mainnet: https://mainnet.base.org (chain 8453)
BASE_CHAIN_ID=84532
BASE_REGISTRY_ADDRESS=0x…
BASE_RECORDER_PRIVATE_KEY=0x…                # funded with a little ETH on Base for gas
BASE_EXPLORER_URL=https://sepolia.basescan.org   # mainnet: https://basescan.org
```

Use a dedicated recorder wallet that holds only gas money. If it is different from the
deployer, allow it with `cast send $BASE_REGISTRY_ADDRESS "setRecorder(address,bool)" <recorder> true`.

## When anchoring fails

Payments never depend on the chain. The Midtrans/CoinPayments webhook marks the order paid,
commits, and only then anchors it. If the RPC is down, the order keeps
`blockchain_status = failed` with the error in `blockchain_metadata`; retry with
`php artisan base:anchor <id>`.
