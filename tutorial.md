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

Every request to the nodes needs two signatures: the signer's, and Laravel's **authorizer** signature. Laravel only adds its signature after rebuilding the transaction itself and checking it against limits, so the signer on its own can't move funds. See [mpc/PRODUCTION.md](mpc/PRODUCTION.md) for the full security model.

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

1. Builds the `mpcium` and `mpcium-cli` binaries into `mpc/bin/` from a pinned commit (the first run takes a few minutes)
2. Starts NATS (password-protected, with per-role permissions) and Consul in Docker, listening on `127.0.0.1` only
3. Generates 3 node identities, a separate share-store password for each node, the chain code, and the event-initiator key (used by the signer)
4. Generates **Laravel's authorizer key** and the **signer token**, and writes both to your `.env` (`MPC_AUTHORIZER_KEY`, `MPC_SIGNER_TOKEN`) if they're empty
5. Registers the peers in Consul and installs the signer's npm packages (`npm ci`)

Everything it generates goes in `mpc/cluster/` (mode `700`), which is git-ignored. **Never commit that folder.** It holds the node key shares. The signer reads only `mpc/cluster/signer.env`, never Laravel's `.env`, so it never sees the authorizer key.

> **Upgrading a cluster made by an older version of this script?** Just run `npm run mpc:setup` again. It exports the Consul registry (wallet key metadata) to `mpc/cluster/consul-kv-backup-*.json`, replaces the old containers (keeping their volumes), restores the registry, and keeps your existing share-store password. Then restart `npm run dev:mpc`.

## 4. Configure `.env`

Turn on MPC wallets. `MPC_SIGNER_TOKEN` and `MPC_AUTHORIZER_KEY` were already filled in by the setup step:

```dotenv
MPC_WALLET_ENABLED=true
MPC_SIGNER_URL=http://127.0.0.1:7077

# Transfer policy that Laravel enforces before co-signing
MPC_LIMIT_PER_TRANSFER_ETH=1
MPC_LIMIT_DAILY_ETH=2
MPC_MAX_FEE_GWEI=50

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
| `queue` | queue worker, which creates wallets after signup and signs transfers |
| `logs` | `php artisan pail` |
| `vite` | frontend dev server |
| `chain` | anvil on `:8545` |
| `mpc` | 3 Mpcium nodes + the signer on `:7077` |

Wait until the `mpc` output shows `[READY] Node is ready` from all three nodes and `[signer] listening on http://127.0.0.1:7077`. Each node spends about 20–30 seconds on first boot generating pre-parameters.

To check that the signer is up:

```bash
curl -s localhost:7077/health
```

`dev:all` doesn't run the scheduler. In production, run `php artisan schedule:work` (or cron) so `wallet:reconcile` can settle transfers whose outcome was lost.

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
| Kata sandi akun | A's password |

Click **Kirim**. Behind the scenes:

1. Laravel checks the password, balance and limits, records a pending `wallet_transfers` row, and queues `ExecuteWalletTransfer`.
2. The signer proposes the transaction (nonce and fees from anvil).
3. Laravel rebuilds it from the recipient and amount it expects, checks the chain ID and fee cap, and co-signs it with its authorizer key.
4. Two of the three Mpcium nodes check both signatures, run threshold ECDSA, and return `(r, s, v)`. The signer checks that the signature recovers to A's address and broadcasts the transaction.
5. `ConfirmWalletTransfer` waits for the receipt and marks the transfer **Berhasil**.

You'll see **"Transfer sedang diproses…"**, and the transfer appears in **Riwayat transfer** as *Menunggu*, then *Berhasil* after a reload once it's mined. A has about `0.749978 ETH` (0.25 sent plus gas) and B has `0.25 ETH`. Log in as B to see the transfer as received.

🎉 That's your first self-hosted MPC transaction.

## Try the threshold yourself

Stop one node (Ctrl+C won't work inside `dev:all`, so find its PID with `pgrep -fl "mpcium start"` and `kill` it). Sends still work with the two remaining nodes. Signing needs 2 of 3, so **creating new wallets** needs all 3 nodes online.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `Docker is not running` | Start Docker Desktop, then re-run. |
| "Wallet sedang disiapkan" never goes away | The queue worker or `mpc` isn't running. Run `php artisan wallet:provision` to see the actual error. |
| `MPC … timed out — are enough nodes running?` | A node is still booting or has crashed. Check the `mpc` output for `[READY]` from all three nodes. |
| `missing required authorizer signature` / `authorizer … verification failed` in node logs | `MPC_AUTHORIZER_KEY` in `.env` doesn't match the public key in `mpc/cluster/config.yaml`. Re-run `npm run mpc:setup`, then restart `dev:mpc` and `php artisan config:clear`. |
| `401 unauthorized` from the signer | `MPC_SIGNER_TOKEN` in `.env` differs from `mpc/cluster/signer.env`. Re-run `npm run mpc:setup` and restart. |
| `Run 'npm run mpc:setup' first (re-run it after upgrading)` | Your cluster predates the hardened setup. Re-run setup; existing wallets are kept. |
| Transfer stuck on *Menunggu* | The queue worker isn't running, or a node is down. Run `php artisan wallet:reconcile`. |
| "Wallet belum tersedia" | `MPC_WALLET_ENABLED` isn't `true`. Run `php artisan config:clear`. |
| `wallet:fund` fails with `anvil_setBalance` | `BASE_RPC_URL` isn't the local anvil chain, or `npm run dev:chain` isn't running. |
| "Saldo tidak mencukupi untuk jumlah ini ditambah biaya jaringan" | Fund the sender again; it needs the amount plus gas. |
| "Biaya jaringan sedang terlalu tinggi" | Gas price is above `MPC_MAX_FEE_GWEI`. |
| Starting over from scratch | Stop everything, then `rm -rf mpc/cluster && docker compose -f mpc/docker-compose.yml down -v && npm run mpc:setup`. **This destroys every wallet's key shares and metadata**, so clear `wallet_address` / `mpc_wallet_id` for those users. |

> **Notes**
> - Transfers are marked *Berhasil* only after the transaction is mined (`MPC_CONFIRMATIONS`).
> - This setup is hardened but still a single machine: all three shares on one disk, unencrypted node identities, no TLS. Before real money is involved, follow [mpc/PRODUCTION.md](mpc/PRODUCTION.md).
