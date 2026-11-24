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
| `./dev azure shell` / `./dev azure wp <args>` | `scripts/azure/exec.sh`: shell or WP-CLI inside the running container (see *WP-CLI and shell on Azure*) |
| `./dev azure export-tickets` / `./dev azure import-tickets <file>` | `scripts/azure/tickets.sh`: copy tickets between this machine and Azure (see *Copying tickets*) |
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
| Azure Container Apps | Consumption, min replicas **0** (`APP_MIN_REPLICAS`), max **1**, 0.5 vCPU / 1 GiB | Free monthly grant (180,000 vCPU-s, 360,000 GiB-s, 2M requests) |
| Azure Database for MySQL Flexible Server | Burstable **B1ms**, 32 GB, auto-grow off, no HA, no geo-backup, 7-day backups | Free for 12 months (750 h/month) |
| Azure Container Registry | Standard, admin user disabled | Free for 12 months |
| Azure Files | Standard LRS, shares `uploads` (5 GB) and `wflogs` (1 GB) | A few cents per month |
| Logs | Container Apps environment with logs **off** | $0 |
| Budget | Monthly, `BUDGET_AMOUNT` (default 5) | $0 |

Check the current free amounts on the
[Azure free services page](https://azure.microsoft.com/en-ca/pricing/free-services)
before you start. They are per subscription and can change.

## Configuration files

The deployment is defined in three files:

| File | Content | In Git |
|---|---|---|
| [`infra/containerapp.yaml`](../infra/containerapp.yaml) | Container app definition: image, settings, secret references, volumes, health probes, scaling. A template with `${PLACEHOLDERS}`, no secrets | ✅ committed, reviewable in pull requests |
| `scripts/azure/config.env` | Your settings: region, names, budget, AI (template: `config.example.env`) | ❌ git-ignored |
| `scripts/azure/.state.env` | Generated values: name suffix, database password, WordPress salts | ❌ git-ignored, private |

`setup.sh` renders the template with the settings and secrets into a private
temporary file, applies it, and deletes it. Rendering fails on any unresolved
placeholder. The AI block (between `# BEGIN optional: ai` and
`# END optional: ai`) is included only when `GEMINI_API_KEY` is set.
`scripts/azure/check-template.sh` checks the template offline and runs in the
Lint workflow.

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
8. Renders `infra/containerapp.yaml` and creates or updates the container app
   (database settings and salts as secrets, `WP_HOME` set to its URL, scale 0-1)

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

## WP-CLI and shell on Azure

```bash
./dev azure wp plugin list                 # WP-CLI as www-data
./dev azure wp user list --role=jalasupport_agent
./dev azure wp option get blogname
./dev azure shell                          # interactive shell as www-data
./dev azure shell --root                   # root shell (only when really needed)
```

The command wakes the app (it scales to zero), waits for a running replica,
then opens `az containerapp exec`. The session is interactive; an idle
replica may be stopped after a few minutes, which ends it.

The helpers (`jalasupport-wp`, `jalasupport-shell`) are part of the production
image. After updating to a version that includes them, run
`./dev azure deploy` once.

**Security**

- **Your Azure login is the credential.** `az containerapp exec` uses your
  `az login` session to get a **short-lived exec token for this container app
  only**. It's sent over an encrypted `wss://` connection; the scripts never
  store or print it.
- **Only people with Azure rights on the app** (e.g. Contributor on the
  resource group) can open a session. MFA and conditional access from
  Entra ID apply.
- **No extra exposure:** sessions go through Azure's management proxy, and
  the app opens no SSH or other port.
- **Least privilege:** WP-CLI and the default shell run as `www-data`; a root
  shell needs `--root` explicitly.
- **No shell injection:** WP-CLI arguments are sent as one base64 token and
  become exact arguments inside the container, without being parsed by a
  remote shell. Arguments with spaces and quotes work as typed.
- **Don't pass secrets as arguments.** Commands are visible to Azure's exec
  endpoint and in your local shell history. Use WP-CLI's prompts instead, e.g.
  `./dev azure wp user create agent agent@example.com --role=jalasupport_agent --prompt=user_pass`.

## Copying tickets (local and Azure)

Tickets move between sites as JSON export files. Imports never copy database
rows: every ticket is validated like a ticket created in the app, gets a new
ID on the target site and the author you choose, and keeps its original
creation date. Each imported ticket remembers where it came from
(`<source site>#<source ticket ID>`), so importing the same file again skips
the tickets that are already there (including trashed ones).

```bash
# Local -> Azure
./dev tickets export                                    # backups/tickets-<time>.json
./dev azure import-tickets backups/tickets-<time>.json --author=admin --dry-run
./dev azure import-tickets backups/tickets-<time>.json --author=admin

# Azure -> local
./dev azure export-tickets                              # backups/azure-tickets-<time>.json
./dev tickets import backups/azure-tickets-<time>.json --author=admin --dry-run
./dev tickets import backups/azure-tickets-<time>.json --author=admin
```

Options: `--ids=4,5` exports selected tickets only; `--file=<path>` chooses
the export file. `--author` must be a user on the *target* site with access
to the support app (an administrator or `jalasupport_agent`). Run the
`--dry-run` first: it validates every ticket and lists what would be
imported, without saving anything. Invalid tickets are reported and skipped.

How the Azure commands transfer the file:

1. They check that the app refuses web access to
   `/app/uploads/jalasupport-import/` (blocked in the Apache configuration of
   the image). If it doesn't (an image from before this feature), they stop
   before writing anything: run `./dev azure deploy` first.
2. The file is uploaded to, or written into, that folder on the `uploads`
   Azure Files share under a random name. The storage account key comes from
   your `az login` session and is passed to `az storage` through the
   environment, not on the command line.
3. WP-CLI runs in the container (as with `./dev azure wp`).
4. The file is deleted from the share afterwards, also when a step fails.

Export files contain customer names and email addresses. `backups/` is
git-ignored and the files are created readable by you only; delete them when
you no longer need them.

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

## Custom domain

Serve the app from your own domain, such as `support.example.com`, with a free
certificate that Azure issues and renews. Use a subdomain: it needs only a
CNAME record, which every DNS provider (GoDaddy included) supports.

```bash
./dev azure domain add support.example.com
```

The command:

1. Prints the two DNS records to add at your DNS provider and waits for your
   confirmation:

   | Type | Name (GoDaddy: without your domain) | Value |
   |---|---|---|
   | CNAME | `support` | the app's Azure address (`…azurecontainerapps.io`) |
   | TXT | `asuid.support` | the app's domain verification ID |

2. Adds the domain to the container app with a managed certificate and waits
   until it answers over HTTPS (usually 5-20 minutes).
3. Sets `WP_HOME` and `WP_SITEURL` to the new address.
4. Replaces the old address in the database with WP-CLI `search-replace`
   (GUIDs are kept). It shows a dry run first and asks before applying.

Then set the domain in `scripts/azure/config.env`, so `./dev azure setup`
keeps it when it applies the app definition again:

```bash
APP_DOMAIN="support.example.com"
```

Sign in again afterwards: login cookies belong to the old address. The Azure
address keeps working and WordPress redirects it to the domain.

| Command | What it does |
|---|---|
| `./dev azure domain` | Show the custom domain, its certificate binding and the WordPress address (read-only) |
| `./dev azure domain add <host>` | Add a domain and move WordPress to it |
| `./dev azure domain update <host>` | Move to another domain, then remove the old domain and its certificate |
| `./dev azure domain clean` | Move WordPress back to the Azure address, then remove the domain and its certificate |

After `update` or `clean`, delete the old DNS records at your DNS provider and
update or remove `APP_DOMAIN`. Every command supports `DRY_RUN=1`.

Custom domains and managed certificates are free on Container Apps.

## Going always-on (no cold starts)

| Option | Change | Approx. cost |
|---|---|---|
| Keep Container Apps | `APP_MIN_REPLICAS="1"` in `scripts/azure/config.env`, then `./dev azure setup` (or right away: `az containerapp update -g rg-jalasupport -n jalasupport-app --min-replicas 1`) | ~$8-12 a month |
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
