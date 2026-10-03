# MPC wallet: security model and production deployment

`npm run mpc:setup` gives you a hardened **single-machine** cluster for development. This document
explains what protects the funds and what you must change before real money is involved.

## Trust model

```
              (1) bearer token          (2) NATS user "mpc_signer"
 Laravel ───────────────────▶ signer ─────────────────────────▶ NATS ◀──── node0 · node1 · node2
 holds: authorizer key        holds: initiator key               │         each holds: 1 key share,
 checks: policy, limits                                           │         identity key, Badger password
                                                                  └──────▶ Consul (peers, key metadata)
```

Every keygen and signing request carries **two** Ed25519 signatures, and each node checks both before
taking part:

| Signature | Key lives on | What it proves |
|---|---|---|
| Initiator | signer host (`event_initiator.key`) | the request came through the signer |
| Authorizer | Laravel host only (`MPC_AUTHORIZER_KEY`) | Laravel rebuilt the transaction itself and it passed policy |

Laravel never co-signs whatever the signer hands it. It recomputes the EIP-1559 transaction hash
from the recipient, amount, chain ID and gas limit it expects, and refuses fees above
`MPC_MAX_FEE_GWEI`, transfers above `MPC_LIMIT_PER_TRANSFER_ETH`, and anything that isn't a plain
ETH transfer. Daily limits, password re-entry and rate limits apply before a transfer is created.

What each compromise gets an attacker:

| Compromised | Can move funds? | Why |
|---|---|---|
| Signer host (token, initiator key, NATS password) | **No** | Nodes reject requests without Laravel's authorizer signature. NATS permissions stop it forging results or touching node traffic. |
| One MPC node | **No** | 2-of-3 threshold. |
| NATS or Consul without node keys | **No** (can disrupt) | Peer messages are signed and encrypted with node identity keys. Denial of service is still possible. |
| Laravel host (authorizer key + signer token) | **Yes**, within whatever the attacker's code allows | Laravel is the policy engine. See *Residual risks*. |
| Two MPC nodes (shares + Badger passwords) | **Yes** | That's the threshold. Keep nodes in separate failure domains. |

## Production checklist

### 1. Separate the nodes
- Run each node on its own host, ideally with different cloud accounts or operators. Two hosts
  under one admin credential make 2-of-3 meaningless.
- Each node gets its **own** Badger password (setup.sh does this for new clusters), delivered as a
  file from a secrets manager on tmpfs: `mpcium start -n nodeX --password-file /run/secrets/badger`.
- Encrypt node identities at rest: `mpcium-cli generate-identity --node nodeX --encrypt`, then start
  with `--decrypt-private-key --identity-password-file /run/secrets/identity`.
- Use Mpcium's systemd units (`mpc/.src/mpcium/deployments/systemd`) under a dedicated user.

### 2. Switch Mpcium to `environment: production`
Production mode turns on mutual TLS to NATS and token auth to Consul:

```yaml
nats:
  url: tls://nats.internal:4222
  username: mpc_node
  password: ""          # injected via NATS_PASSWORD env, not committed
  tls:
    client_cert: /etc/mpcium/certs/client-cert.pem
    client_key: /etc/mpcium/certs/client-key.pem
    ca_cert: /etc/mpcium/certs/rootCA.pem
consul:
  address: https://consul.internal:8501
  token: ""             # CONSUL_TOKEN env; ACL policy: read mpc_peers/, read/write threshold_keyinfo/ and ready/
  ca_cert: /etc/mpcium/certs/consul-ca.pem
environment: production
```

Keep the `authorization:` block that setup.sh writes. Without it the nodes accept anything the
initiator key signs.

### 3. NATS
- Start from the generated `mpc/cluster/nats.conf`: the `mpc_signer` user may only publish requests
  and consume its own client-scoped results. Add a `tls { cert_file, key_file, ca_file, verify: true }` block.
- Only the nodes and the signer should reach port 4222. Keep the monitoring port (8222) private.
- In development mode the node password is passed in `NATS_URL`, and Mpcium logs the URL on reconnect.
  Production mode passes `username`/`password` separately and doesn't have this problem.

### 4. Consul
- Enable ACLs (`acl { enabled = true, default_policy = "deny" }`) and HTTPS. Give nodes a token
  scoped to the keys above.
- **Consul holds per-wallet key metadata (`threshold_keyinfo/`). Without it the nodes cannot sign.**
  Take scheduled `consul snapshot save` backups and test restoring them.

### 5. Signer
- Run it on a host separate from Laravel. Give it only `mpc/cluster/signer.env` (never Laravel's
  `.env`) and the initiator key. Required variables: `MPC_SIGNER_TOKEN` (≥ 32 chars),
  `MPC_NATS_URL`/`USER`/`PASSWORD`, `MPC_NATS_CA_FILE`/`CERT_FILE`/`KEY_FILE` for TLS,
  `BASE_RPC_URL`, `BASE_CHAIN_ID`, `MPC_INITIATOR_KEY_PATH`.
- It refuses to start if the initiator key file is readable by other users.
- It listens on `127.0.0.1` by default (`MPC_SIGNER_HOST`). If Laravel is on another host, put it
  behind a TLS reverse proxy or a private network. Never expose it publicly.
- Use a dedicated RPC provider endpoint. The signer checks the RPC's chain ID on every request, and
  Laravel checks the transaction's chain ID before co-signing.

### 6. Laravel
- `MPC_AUTHORIZER_KEY` comes from your secrets manager. Its public key goes in every node's `config.yaml`.
- Run a queue worker (transfers are signed in `ExecuteWalletTransfer`) and the scheduler
  (`wallet:reconcile` every minute).
- Alert on these log lines:
  - `Refusing to authorize MPC transfer`: the signer proposed a transaction that doesn't match the
    request. Treat it as a possible signer compromise.
  - `Wallet transfer outcome unknown; needs manual review` (critical): check the sender's nonce
    on-chain and settle the transfer by hand.
- Set limits (`MPC_LIMIT_*`, `MPC_MAX_FEE_GWEI`) to what your business actually needs.

### 7. Backups and recovery
Losing two nodes' shares means **permanent loss of every wallet**. For each node, back up separately
and encrypted: the Badger directory or its `backups/*.enc` files, the identity directory, and the
Badger password. Store each node's backup in a different place, and test restoring a node.

### 8. Rotating keys
- **Authorizer**: add the new public key under a new id in `authorizer_public_keys` on all nodes.
  Then switch `required_authorizers` to the new id and Laravel's `MPC_AUTHORIZER_ID`/`MPC_AUTHORIZER_KEY`
  in the same maintenance window, and finally remove the old key.
- **Initiator**: generate a new key with `mpcium-cli generate-initiator`, update
  `event_initiator_pubkey` on all nodes, and restart them and the signer together.
- **Signer token / NATS passwords**: change them in both places and restart.

## Residual risks
- **Laravel compromise**: an attacker with Laravel's authorizer key and signer token can sign
  arbitrary transfers. To limit this, move the policy check into a small separate service holding
  the authorizer key, or add a second required authorizer (for example, human approval above a
  threshold). Mpcium supports several `required_authorizers`.
- **Keygen address**: Laravel trusts the address the signer returns at keygen. A compromised signer
  could hand out an address it controls, so later deposits would go to the attacker. NATS
  permissions stop anyone *other* than the signer from forging keygen results.
- **Late signatures**: if Laravel gives up waiting (timeout) but the nodes finish signing later,
  only the signer can see the result, and it discards it. `wallet:reconcile` flags these transfers
  for manual review rather than guessing.
