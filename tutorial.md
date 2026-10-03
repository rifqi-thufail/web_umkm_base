# Tutorial: From `npm` to Your First MPC Wallet Transfer

This guide takes you from a fresh clone to sending ETH between two buyer wallets. Everything runs on your machine, is free, and is open source:

- **[Mpcium](https://github.com/fystack/mpcium)** (Apache-2.0): 3 local MPC nodes. Each wallet key is split into 2-of-3 threshold shares, so the full private key never exists anywhere.
- **anvil** (Foundry): a local chain forked from Base Sepolia, with free test ETH.
- **`mpc/signer`**: a small Node service that connects Laravel to the MPC cluster.

```
Laravel ──HTTP──▶ mpc/signer ──NATS──▶ node0 · node1 · node2   (any 2 can sign)
   │                  │
   └── balance ───────┴── broadcast ──▶ anvil (local Base Sepolia, :8545)
```

## 1. Prerequisites

| Tool | Check | Install |
|---|---|---|
| PHP 8.2+ with `gmp` and `bcmath` | `php -m \| grep -E "gmp\|bcmath"` | — |
| Composer | `composer -V` | https://getcomposer.org |
| Node.js 20.6+ | `node -v` | https://nodejs.org |
| Go 1.25+ | `go version` | https://go.dev/dl (newer toolchains download automatically) |
| Docker Desktop, **running** | `docker info` | https://docs.docker.com/get-docker |
| Foundry (`anvil`) | `anvil --version` | https://book.getfoundry.sh/getting-started/installation |

## 2. Install the app

```bash
composer install
```

```bash
npm install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

```bash
touch database/database.sqlite
```

```bash
php artisan migrate --seed
```

## 3. Set up the MPC cluster (one time)

```bash
npm run mpc:setup
```

This command:

1. Builds the `mpcium` and `mpcium-cli` binaries into `mpc/bin/` (the first run takes a few minutes)
2. Starts NATS and Consul in Docker
3. Generates 3 node identities, the shared chain code, and the event-initiator key (the key Laravel's signer uses to authorize requests)
4. Registers the peers in Consul and installs the signer's npm packages

Everything it generates goes in `mpc/cluster/`, which is git-ignored. **Never commit that folder.** It holds the node key shares.

## 4. Configure `.env`

Turn on MPC wallets and set a random signer token:

```bash
openssl rand -hex 24
```

```dotenv
MPC_WALLET_ENABLED=true
MPC_SIGNER_URL=http://127.0.0.1:7077
MPC_SIGNER_PORT=7077
MPC_SIGNER_TOKEN=paste-the-random-value-here
MPC_WALLET_PREFIX=ampuh-user

# The local chain (the defaults are already correct)
BASE_RPC_URL=http://127.0.0.1:8545
BASE_CHAIN_ID=84532
```

Then clear cached config:

```bash
php artisan config:clear
```

## 5. Run everything

```bash
npm run dev:all
```

This starts six processes with color-coded output:

| Name | What it runs |
|---|---|
| `server` | Laravel at http://127.0.0.1:8000 |
| `queue` | queue worker, which creates wallets after signup |
| `logs` | `php artisan pail` |
| `vite` | frontend dev server |
| `chain` | anvil on `:8545` |
| `mpc` | 3 Mpcium nodes + the signer on `:7077` |

Wait until the `mpc` output shows `[READY] Node is ready` from all three nodes and `[signer] listening on http://127.0.0.1:7077`. Each node spends about 20–30 seconds on first boot generating pre-parameters.

To check that the signer is up (`$MPC_SIGNER_TOKEN` is the value from `.env`):

```bash
curl -s localhost:7077/health -H "Authorization: Bearer $MPC_SIGNER_TOKEN"
```

## 6. Create two buyers

1. Open http://127.0.0.1:8000 → **Login → Pembeli → Daftar** and register user **A**.
2. In the `queue` output you'll see `ProvisionMpcWallet` run. Distributed key generation takes about 3 seconds.
3. Log out and register user **B**.

Users created before MPC was enabled (for example the seeded `test@example.com`) can get a wallet with:

```bash
php artisan wallet:provision
```

## 7. Fund the sender with free test ETH

```bash
php artisan wallet:fund userA@example.com 1
```

This adds 1 ETH to A's wallet on the local anvil chain. Use A's email or any `0x` address. Open **Account menu → Wallet** (`/wallet`) as A, and **Saldo** shows `1 ETH`.

## 8. Make your first transfer

On A's `/wallet` page, fill in **Kirim ETH**:

| Field | Value |
|---|---|
| Penerima | user B's **email** (or any `0x…` address) |
| Jumlah (ETH) | `0.25` |

Click **Kirim**. Behind the scenes:

1. Laravel checks the balance and records a pending `wallet_transfers` row.
2. The signer builds the transaction (nonce, gas, chain ID from anvil) and sends its hash to the cluster.
3. Two of the three Mpcium nodes run threshold ECDSA and return `(r, s, v)`. The signer checks that the signature recovers to A's address.
4. The signer broadcasts the signed transaction to anvil, and Laravel stores the transaction hash.

You'll see **"Transfer berhasil! TX: 0x…"**, and the transfer appears in **Riwayat transfer**. A couple of seconds later (one anvil block), A has about `0.749978 ETH` (0.25 sent plus gas) and B has `0.25 ETH`. Log in as B to see the transfer as received.

🎉 That's your first self-hosted MPC transaction.

## Try the threshold yourself

Stop one node (Ctrl+C won't work inside `dev:all`, so find its PID with `pgrep -fl "mpcium start"` and `kill` it). Sends still work with the two remaining nodes. Signing needs 2 of 3, so **creating new wallets** needs all 3 nodes online.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `Docker is not running` | Start Docker Desktop, then re-run. |
| "Wallet sedang disiapkan" never goes away | The queue worker or `mpc` isn't running. Run `php artisan wallet:provision` to see the actual error. |
| `MPC request … timed out — are all nodes running?` | A node is still booting or has crashed. Check the `mpc` output for `[READY]` from all three nodes. |
| `401 unauthorized` from the signer | The signer reads `.env` on startup. Restart `npm run dev:mpc` after changing `MPC_SIGNER_TOKEN`. |
| "Wallet belum tersedia" | `MPC_WALLET_ENABLED` isn't `true`. Run `php artisan config:clear`. |
| `wallet:fund` fails with `anvil_setBalance` | `BASE_RPC_URL` isn't the local anvil chain, or `npm run dev:chain` isn't running. |
| `insufficient funds … incl. gas` | Fund the sender again; it needs the amount plus gas. |
| Starting over from scratch | Stop everything, then `rm -rf mpc/cluster && docker compose -f mpc/docker-compose.yml down -v && npm run mpc:setup`. Existing wallets become unusable, so clear `wallet_address` / `mpc_wallet_id` for those users. |

> **Notes**
> - Transfers are marked *Berhasil* as soon as they're broadcast, not when they're mined.
> - This setup is for local development: unencrypted node keys, one machine, no TLS. For production, follow Mpcium's [production guide](https://github.com/fystack/mpcium/tree/master/deployments/systemd): encrypted identities (`--encrypt`), nodes on separate hosts, and NATS with TLS.
