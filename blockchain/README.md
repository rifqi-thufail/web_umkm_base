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

## Base mainnet / Base Sepolia

```bash
blockchain/deploy-base.sh mainnet     # or: sepolia (free faucet ETH) to rehearse first
```

The script, run from your machine (needs Foundry):

1. Uses `BASE_RECORDER_PRIVATE_KEY` from `.env`, or generates a new wallet and saves it there.
   Back that key up. It only needs to hold gas money.
2. Stops with the address to fund if it holds less than 0.0002 ETH. Send ETH **on the Base
   network** (an exchange withdrawal to "Base", or https://bridge.base.org), then run it again.
   Deploying costs a few cents; each anchor about a tenth of a cent.
3. Deploys `OrderRegistry` (skipped if `.env` already points at a deployed one on that chain)
   and writes `BASE_ENABLED=true` plus the network's RPC, chain id, contract and explorer into `.env`.
   Set `BASESCAN_API_KEY` to also verify the source on Basescan.

Then:

```bash
php artisan config:clear && php artisan base:status   # balance, recorder allowed, warnings
php artisan base:anchor --pending                    # record orders paid while Base was off
```

On the server, copy the same `BASE_*` lines into its `.env` and run `php artisan optimize`.
`https://mainnet.base.org` is rate limited; for real traffic use a provider URL
(Alchemy, QuickNode, Coinbase CDP) in `BASE_RPC_URL`.

To use a recorder different from the deployer, allow it from the deployer wallet:
`cast send $BASE_REGISTRY_ADDRESS "setRecorder(address,bool)" <recorder> true`.

## When anchoring fails

Payments never depend on the chain. The Midtrans/CoinPayments webhook marks the order paid,
commits, and only then anchors it. If the RPC is down, the order keeps
`blockchain_status = failed` with the error in `blockchain_metadata`; retry with
`php artisan base:anchor <id>`, or all of them with `php artisan base:anchor --pending`.
