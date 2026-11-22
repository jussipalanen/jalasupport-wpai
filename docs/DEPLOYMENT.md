# Azure deployment (free tier)

JalaSupport AI runs on Azure using only always-free and 12-months-free
services. Everything is created in one resource group by
`scripts/azure/setup.sh` and removed by `scripts/azure/teardown.sh`.

The scripts never run on their own. You run them, and you can preview every
command first with `DRY_RUN=1`.

Every script is also available through the developer helper:

| `./dev` command | Script |
|---|---|
| `./dev azure setup` | `scripts/azure/setup.sh` |
| `./dev azure deploy` | `scripts/azure/deploy.sh` |
| `./dev azure status` | `scripts/azure/status.sh`: URL, revision, replicas, database (read-only; `--health` also requests the health endpoint and wakes the app) |
| `./dev azure github-oidc` | `scripts/azure/github-oidc.sh` |
| `./dev azure teardown` | `scripts/azure/teardown.sh` |

For example: `DRY_RUN=1 ./dev azure setup`.

## Architecture

```text
            HTTPS (free managed certificate, *.azurecontainerapps.io)
                                   │
                                   ▼
┌───────────────────────── Resource group: rg-jalasupport ─────────────────────────┐
│                                                                                  │
│  Container Apps environment (consumption, logs off)                              │
│  └─ jalasupport-app  0.5 vCPU / 1 GiB, scale 0-1 ── pulls image ──► Container    │
│       │   │                                          (managed        Registry    │
│       │   └─ Azure Files: uploads, wflogs             identity)      (Standard)  │
│       ▼                                                                          │
│  MySQL Flexible Server B1ms (TLS required, Azure services only)                  │
│                                                                                  │
│  Budget alert (email at 50 / 80 / 100 %)                                         │
└──────────────────────────────────────────────────────────────────────────────────┘
```

| Resource | Tier / settings | Cost |
|---|---|---|
| Azure Container Apps | Consumption, min replicas **0**, max **1**, 0.5 vCPU / 1 GiB | Free monthly grant (180,000 vCPU-s, 360,000 GiB-s, 2M requests) |
| Azure Database for MySQL Flexible Server | Burstable **B1ms**, 32 GB, auto-grow off, no HA, no geo-backup, 7-day backups | Free for 12 months (750 h/month) |
| Azure Container Registry | Standard, admin user disabled | Free for 12 months |
| Azure Files | Standard LRS, shares `uploads` (5 GB) and `wflogs` (1 GB) | A few cents per month |
| Logs | Container Apps environment with logs **off** | $0 |
| Budget | Monthly, `BUDGET_AMOUNT` (default 5) | $0 |

Check the current free amounts on the
[Azure free services page](https://azure.microsoft.com/en-ca/pricing/free-services)
before you start. They are per subscription and can change.

## Costs and the free account

- **Days 1-30:** the free account has a $200 credit and a spending limit, so
  nothing is charged to your card. If you don't upgrade, Azure disables the
  subscription after 30 days or when the credit runs out.
- **After upgrading to pay-as-you-go** (needed to keep the 12-month offers):
  you pay only for usage beyond the free amounts. With these settings that's
  about **$0-1 a month** in year one.
- **What can cost money:**
  - Traffic that keeps the app awake beyond about 100 active hours a month.
    Bots count too. Max replicas 1 caps the worst case at roughly $30-40 a
    month.
  - Changed settings: a bigger database, storage auto-grow, high availability,
    logs without a cap, or min replicas above 0.
  - **Month 13:** the database (~$15-30 a month) and the registry (downgrade to
    Basic, ~$5 a month, or move it).
- A budget alert **notifies but does not stop** spending. Keep `BUDGET_EMAIL`
  set and watch your inbox.

**Trade-off of scaling to zero:** the first request after a few idle minutes
takes several seconds (cold start). For always-on production, see
*Going always-on* below.

## Prerequisites

- Azure free account (or any subscription where you are Owner)
- [Azure CLI](https://learn.microsoft.com/cli/azure/install-azure-cli) 2.60+
  (`az version`)
- Docker running locally (the image is built on your machine)
- This repository, with the theme building (`./dev assets`)

Optional: test the production image locally first.

```bash
./dev prod        # http://localhost:8090, production mode against the local database
./dev prod down
```

## 1. Configure

```bash
cp scripts/azure/config.example.env scripts/azure/config.env
```

Edit `scripts/azure/config.env`:

| Setting | Meaning |
|---|---|
| `AZ_LOCATION` | Region with Container Apps and MySQL B1ms, e.g. `northeurope`, `swedencentral`, `westeurope` |
| `AZ_RESOURCE_GROUP` | Everything goes here (default `rg-jalasupport`) |
| `AZ_PREFIX` | Name prefix, 3-14 lowercase letters or digits |
| `BUDGET_EMAIL` | **Set this.** Receives budget alerts |
| `BUDGET_AMOUNT` | Monthly budget in your billing currency (default 5) |
| `GEMINI_API_KEY` | Optional. Enables AI; stored as a Container Apps secret. Leave empty to deploy without AI |
| `AI_PROVIDER`, `GEMINI_MODEL`, `AI_DAILY_LIMIT` | AI provider settings (defaults `gemini`, `gemini-flash-lite-latest`, `200`) |

`config.env` and the generated `scripts/azure/.state.env` (database password,
WordPress salts, name suffix) are git-ignored. Keep `.state.env` private, and
keep it if you want to re-run or deploy later.

## 2. Sign in

```bash
az login
az account show --output table     # check the subscription
```

## 3. Preview (changes nothing)

```bash
DRY_RUN=1 scripts/azure/setup.sh
```

This prints every command that would create or change something, plus the
container app definition with secrets masked.

## 4. Create the deployment

```bash
scripts/azure/setup.sh
```

The script asks for confirmation, then:

1. Registers the resource providers and creates the resource group
2. Creates the budget alert (if `BUDGET_EMAIL` is set)
3. Creates MySQL Flexible Server B1ms and the `jalasupport` database (5-15 minutes)
4. Creates the storage account and the `uploads` and `wflogs` file shares
5. Creates the container registry and a managed identity with `AcrPull`
6. Builds the production image locally and pushes it to the registry
7. Creates the Container Apps environment (logs off) and mounts the file shares
8. Creates the container app with the database settings and salts as secrets,
   `WP_HOME` set to its URL, and scale 0-1

The script is safe to re-run: existing resources are kept and the app is updated.

## 5. First-time WordPress setup

1. Open the URL printed at the end and add `/wp/wp-admin/install.php`. The
   first request can take a few seconds (cold start).
2. Install WordPress: site title, admin user and a strong password.
3. **WP admin → Plugins:** activate **Advanced Custom Fields**, **Wordfence**
   and **JalaSupport**. The JalaSupport theme is active by default, and pretty
   permalinks are switched on automatically.
4. **Wordfence:** register the free license (WP admin → Wordfence). For API
   clients, keep application passwords enabled: *Wordfence → Login Security →
   Settings → Disable WordPress application passwords* = off.
5. Create support agents: *Users → Add New*, role **Support Agent**.

**AI (optional):** with `GEMINI_API_KEY` set in `config.env`, AI is enabled
when the app starts. Protect the key in Google Cloud: in *APIs & Services →
Credentials*, restrict it to the **Generative Language API**, and keep separate
keys for local development and Azure. Container Apps has no fixed outbound IP
on the consumption plan, so an IP restriction isn't practical. The app also
only lets its own UI use the AI, with a site-wide daily limit.

Without a key, AI features stay disabled; the app works
fully without them.

## Deploying code changes

```bash
DRY_RUN=1 scripts/azure/deploy.sh   # preview
scripts/azure/deploy.sh             # build, push, roll out a new revision
```

Images are tagged with the commit hash; uncommitted changes add a
`-dirty-<timestamp>` suffix.

### Deploy from GitHub Actions (optional)

The [`Deploy`](../.github/workflows/deploy.yml) workflow does the same from
GitHub: build, push to the registry, roll out a new revision, and smoke-test
`/healthz.txt` (allowing for a cold start). It runs only when you trigger it.

One-time setup (after `setup.sh`):

```bash
gh auth status                            # GitHub CLI logged in
DRY_RUN=1 scripts/azure/github-oidc.sh    # preview
scripts/azure/github-oidc.sh
```

This creates an Entra ID app registration that trusts **only this
repository's `main` branch** (OIDC federated credential, no passwords or keys).
It grants **AcrPush** on the registry and **Contributor** on the resource
group, and stores `AZURE_CLIENT_ID`, `AZURE_TENANT_ID` and
`AZURE_SUBSCRIPTION_ID` as repository secrets and the resource names as
repository variables.

Deploy: **Actions → Deploy → Run workflow** (branch `main`), or
`gh workflow run deploy.yml --ref main`.

`teardown.sh` also deletes the app registration. The repository secrets and
variables can then be removed in the repository settings.

## Ending the deployment

```bash
BACKUP=1 scripts/azure/teardown.sh   # export the database and uploads to ./backups, then delete
scripts/azure/teardown.sh            # delete without a backup
```

Teardown deletes the whole resource group (app, database, storage, registry,
budget) after you type its name. For the backup, the script briefly opens the
database firewall for your current IP address, which it looks up through
`api.ipify.org`, and removes the rule again afterwards.

Alternatively, on a free account, simply don't upgrade: Azure disables the
subscription after 30 days and nothing is charged.

## Going always-on (no cold starts)

| Option | Change | Approx. cost |
|---|---|---|
| Keep Container Apps | `az containerapp update -g rg-jalasupport -n jalasupport-app --min-replicas 1` | ~$8-12 a month |
| App Service B1 (Linux) | Run the same image on App Service; use `/home` storage for uploads and `wflogs` | ~$13 a month |

## Security notes

- Secrets (database password, WordPress salts, Gemini API key) are stored as Container Apps
  secrets, not as plain environment variables, and never in the image.
- The database requires TLS (`DB_SSL=true`) and only accepts connections from
  Azure services. No public IP ranges are allowed except during a teardown
  backup.
- The app pulls images with a managed identity. The registry has no admin
  user and no passwords.
- HTTPS is enforced (`allowInsecure: false`). Bedrock detects HTTPS from
  `X-Forwarded-Proto`.
- **Known limitation:** the app connects with the MySQL admin user created by
  the script. A dedicated least-privilege database user is a planned
  improvement.

## Troubleshooting

| Symptom | Fix |
|---|---|
| MySQL create fails with a capacity or SKU error | Try another `AZ_LOCATION`; free-account regions vary |
| App revision fails to pull the image | The `AcrPull` role can take a few minutes to apply; re-run `setup.sh` |
| `Error establishing a database connection` | Check the server is running: `az mysql flexible-server show -g rg-jalasupport -n <server> --query state` |
| Uploads or Wordfence can't write files | Check the volume mounts: `az containerapp show -g rg-jalasupport -n jalasupport-app --query properties.template.volumes` |
| Slow first page | Expected cold start after idle periods (scale to zero) |
| Unexpected costs | Cost Management → Cost analysis, filtered by the resource group |
