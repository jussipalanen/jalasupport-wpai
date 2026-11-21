<div align="center">

# JalaSupport AI

**An AI-assisted customer support and ticket management mini-project built on WordPress and the Roots ecosystem.**

[![CI](https://github.com/jussipalanen/jalasupport-wpai/actions/workflows/ci.yml/badge.svg)](https://github.com/jussipalanen/jalasupport-wpai/actions/workflows/ci.yml)

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-7.1-21759B?logo=wordpress&logoColor=white)
![Bedrock](https://img.shields.io/badge/Roots-Bedrock-525DDC?logo=roots&logoColor=white)
![Sage](https://img.shields.io/badge/Roots-Sage%2011-525DDC?logo=roots&logoColor=white)
![Acorn](https://img.shields.io/badge/Roots-Acorn%206-525DDC?logo=roots&logoColor=white)
![ACF](https://img.shields.io/badge/ACF-6.8-00E4BC?logo=wordpress&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Wordfence](https://img.shields.io/badge/Security-Wordfence-00709E?logo=wordpress&logoColor=white)

![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![Blade](https://img.shields.io/badge/Blade-templates-FF2D20?logo=laravel&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3-8BC0D0?logo=alpinedotjs&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![Pest](https://img.shields.io/badge/Tests-Pest%204-F472B6?logo=php&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%206-4F5B93?logo=php&logoColor=white)
![ESLint](https://img.shields.io/badge/ESLint-10-4B32C3?logo=eslint&logoColor=white)
![GitHub Actions](https://img.shields.io/badge/CI-GitHub%20Actions-2088FF?logo=githubactions&logoColor=white)

</div>

---

## 📖 About

JalaSupport AI is a small, working support desk prototype. Support agents log in
with their WordPress accounts, create and manage tickets, filter and search them,
and (as the project progresses) get AI help with categorizing, summarizing and
replying to tickets.

The project shows how WordPress can serve as the backend of a modern application
rather than a traditional website:

- **WordPress as an application platform.** A private `ticket` post type, ACF
  metadata and a custom REST API with explicit authorization.
- **Clean separation.** All business logic lives in the `jalasupport` plugin. The
  Sage theme only handles presentation and can be replaced without losing data
  or features.
- **Modern tooling.** Composer-managed WordPress (Bedrock), Blade + Tailwind +
  Vite (Sage), Docker-based local development, automated PR checks.
- **Responsible AI.** AI suggestions are processed server-side and are never
  applied without the user confirming them.

> **Status:** early development. See [ROADMAP.md](ROADMAP.md) for what is done and what is planned.

---

<details>
<summary><b>✨ Features</b></summary>

<br>

| | Feature | Status |
|---|---|:-:|
| 🔐 | WordPress authentication, Support Agent role, dedicated ticket capabilities | ✅ |
| 🛡️ | Wordfence (free): malware and vulnerability scans, firewall, login security | ✅ |
| 🎫 | Private `ticket` post type (not public, not exposed via `wp/v2`) | ✅ |
| 🗂️ | Ticket metadata with ACF: status, priority, category, customer, AI fields | ✅ |
| 🔌 | REST API `jalasupport/v1`: list, search, filter, sort, paginate, CRUD, stats | ✅ |
| ✅ | Server-side validation and sanitization, consistent JSON responses | ✅ |
| 🤖 | Azure OpenAI ticket analysis API (category, priority, summary, suggested reply) | ✅ |
| 🖥️ | SaaS-style UI (Blade + Tailwind + Alpine.js): dashboard, ticket list, ticket details, create ticket, settings | ✅ |
| ✨ | AI panel with accept / edit / reject for every suggestion | ✅ |
| ☁️ | Azure App Service deployment | 📅 |

✅ done · 🚧 in progress · 📅 planned

**Ticket vocabulary**

- **Statuses:** Open, In Progress, Resolved, Closed
- **Priorities:** Low, Normal, High, Urgent
- **Categories:** Technical Support, Account Issues, Billing, General Inquiry, Other

</details>

<details>
<summary><b>🧱 Tech stack</b></summary>

<br>

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+ (Docker runtime: PHP 8.5), WordPress 7.1, Roots Bedrock, Roots Acorn 6, Composer |
| Data | MySQL 8.4, WordPress posts + post meta, Advanced Custom Fields 6 |
| Security | Wordfence (free), dedicated capabilities, REST permission checks |
| Frontend | Roots Sage 11, Blade templates and components, Tailwind CSS 4, Alpine.js 3, Vite 8 |
| AI | Azure OpenAI (server-side PHP, structured JSON output) |
| Infrastructure | Docker, Docker Compose, Azure App Service, Azure Database for MySQL, Azure Container Registry |
| Quality | Pest 4, Brain Monkey, Laravel Pint, PHPStan, ESLint, editorconfig-checker, GitHub Actions |

</details>

<details>
<summary><b>🏗️ Architecture</b></summary>

<br>

```text
 Browser: Sage theme (Blade views + Alpine.js components)
        │  fetch() with cookie auth + X-WP-Nonce
        ▼
 WordPress REST API ──► /wp-json/jalasupport/v1/*
        │
        ▼
 jalasupport plugin
   ├─ Http/Controllers   permission checks, request → response
   ├─ Services           TicketValidator, TicketRepository, AI/AzureOpenAIService
   ├─ Models             Ticket, status/priority/category enums
   ├─ PostTypes, Fields  ticket CPT, post meta + ACF field group
   └─ Support            capabilities, roles, installer
        │
        ▼
 MySQL (wp_posts, wp_postmeta)          Azure OpenAI (server-side only)
```

Key decisions:

- **Business logic lives in the plugin, never in the theme.** The theme can be replaced.
- **Tickets are hidden from core `wp/v2`.** Access goes through `jalasupport/v1`,
  where every route checks capabilities on its own and responses contain only
  whitelisted fields.
- **WordPress-native storage.** Tickets are posts and their metadata is post meta.
  Field definitions live in PHP, so they are version-controlled.
- **Acorn is installed at the Bedrock root** and powers Sage. The plugin does not
  depend on Acorn, so it keeps working when the theme changes.

</details>

<details>
<summary><b>📁 Project structure</b></summary>

<br>

```text
.
├── .github/                 CI workflow, PR template, CODEOWNERS
├── config/                  Bedrock configuration (application.php + environments/)
├── docker/                  PHP/Apache image, PHP ini, entrypoint
├── docs/                    Additional documentation (API reference)
├── dev                      Developer helper script (Docker Compose wrapper)
├── tests/                   Pest unit tests, Brain Monkey setup, WP stubs
├── web/                     Web root
│   ├── app/
│   │   ├── mu-plugins/
│   │   ├── plugins/
│   │   │   └── jalasupport/ Application plugin (tracked)
│   │   │       ├── app/     Fields, Http, Models, PostTypes, Services, Support
│   │   │       └── jalasupport.php
│   │   ├── themes/
│   │   │   └── jalasupport/ Sage theme (presentation only)
│   │   └── uploads/         Media (not tracked)
│   └── wp/                  WordPress core (Composer-installed, not tracked)
├── composer.json
├── docker-compose.yml
├── .env.example
└── ROADMAP.md
```

</details>

<details>
<summary><b>🚀 Getting started (Docker)</b></summary>

<br>

**Requirements:** Docker Engine with Docker Compose v2, Node.js 20.19+ or 22.12+ (for theme assets).

```bash
cp .env.example .env   # 1. set DB passwords and generate salts at https://roots.io/salts.html
./dev up               # 2. build the image, start MySQL, install Composer dependencies
./dev setup            # 3. install WordPress, activate ACF, Wordfence, the plugin and the theme
./dev assets           # 4. install theme npm dependencies and build assets
```

| URL | |
|---|---|
| <http://localhost:8080> | Application |
| <http://localhost:8080/wp/wp-admin/> | WordPress admin |
| <http://localhost:8080/wp-json/jalasupport/v1/> | REST API |
| <http://127.0.0.1:8081> | phpMyAdmin (MySQL web GUI, signs in automatically) |

Optional: create a support agent user.

```bash
./dev wp user create agent agent@example.com --role=jalasupport_agent --prompt=user_pass
```

<details>
<summary>Without the helper script</summary>

<br>

```bash
docker compose up -d
docker compose exec -u www-data app wp core install \
    --url=http://localhost:8080 --title="JalaSupport AI" \
    --admin_user=admin --admin_email=admin@example.com --prompt=admin_password
docker compose exec -u www-data app wp plugin activate advanced-custom-fields wordfence jalasupport
docker compose exec -u www-data app wp theme activate jalasupport
cd web/app/themes/jalasupport && npm install && npm run build
```

</details>

</details>

<details>
<summary><b>🛠️ Developer commands (<code>./dev</code>)</b></summary>

<br>

`./dev` is a small Bash wrapper around Docker Compose and the project tooling.
Run `./dev help` for the full list.

| Command | Description |
|---|---|
| `./dev up` | Start the stack in the background (builds on first run) |
| `./dev down` | Stop and remove containers (database volume is kept) |
| `./dev restart [svc]` | Restart all services or one (`app`, `db`) |
| `./dev build` | Rebuild the app image |
| `./dev ps` / `./dev status` | Service status |
| `./dev logs [svc]` | Follow logs (default `app`) |
| `./dev shell` | Shell in the app container |
| `./dev db [args]` | MySQL shell as the app user, or run SQL with `-e` |
| `./dev reset` | Stop and **delete** the database volume (asks for confirmation) |
| `./dev setup` | Install WordPress and activate ACF, Wordfence, plugin and theme |
| `./dev composer <args>` | Composer in the container |
| `./dev wp <args>` | Any WP-CLI command in the container |
| `./dev test` | Pest test suite |
| `./dev lint [--fix]` | All linters: Pint, PHPStan, ESLint, editorconfig (`--fix` auto-fixes style) |
| `./dev npm <args>` | npm in the theme directory (host) |
| `./dev assets` | `npm install` (root tooling and theme) + theme `npm run build` |
| `./dev vite` | Vite dev server for the theme |

**Database and WP-CLI examples**

```bash
./dev db                                   # interactive mysql> prompt
./dev db -e "SHOW TABLES"                  # run a single query
./dev db < dump.sql                        # import an SQL file

./dev wp plugin list                       # any WP-CLI command
./dev wp user list --role=jalasupport_agent
./dev wp post list --post_type=ticket --fields=ID,post_title
./dev wp user create agent agent@example.com --role=jalasupport_agent --prompt=user_pass
./dev wp db export - > backup.sql          # database backup
./dev wp shell                             # interactive PHP shell with WordPress loaded
```

</details>

<details>
<summary><b>🐳 Docker reference</b></summary>

<br>

| Service | Description |
|---|---|
| `app` | PHP 8.5 + Apache (configurable with `PHP_VERSION`), document root `web/`, port `${APP_PORT:-8080}`, Composer and WP-CLI included |
| `db` | MySQL 8.4, persistent `db-data` volume, bound to `127.0.0.1:${DB_PORT:-3306}` |
| `phpmyadmin` | phpMyAdmin 5.2 web GUI for MySQL on `127.0.0.1:${PMA_PORT:-8081}`. Signs in automatically as `DB_USER`, local development only |

`www-data` inside the container is remapped to `HOST_UID`/`HOST_GID` (default
`1000`) so bind-mounted files stay writable.

**Versions.** The Dockerfile reads every version from a build argument
(`PHP_VERSION`, `COMPOSER_VERSION`, `WP_CLI_VERSION`), and Compose passes them
from `.env`, along with `MYSQL_VERSION` and `PHPMYADMIN_VERSION`. The WP-CLI
download is verified against its published SHA-512 checksum. PHP 8.5 is the
default: PHP 8.6 was tested but isn't supported yet (WP-CLI deprecation
notices, and the official image ships without `mbstring`).

</details>

<details>
<summary><b>🧰 WP-CLI on the host (optional)</b></summary>

<br>

WP-CLI is already included in the Docker image (`./dev wp …`), so a host
install is optional. It's useful if you prefer to run `wp` directly. The
snippet installs WP-CLI only if it is missing:

```bash
if ! command -v wp >/dev/null 2>&1; then
    curl -fsSLO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    php wp-cli.phar --info                 # verify the download runs
    chmod +x wp-cli.phar
    sudo mv wp-cli.phar /usr/local/bin/wp
else
    echo "WP-CLI is already installed: $(wp --version)"
fi
```

To connect from the host to the Docker database, add `DB_HOST='127.0.0.1'` to
`.env`. Inside Docker Compose the `app` container still uses `db`, because the
Compose `environment` setting takes precedence over `.env`. The project's
`wp-cli.yml` already points WP-CLI at `web/wp`.

```bash
wp option get blogname
wp plugin list
```

Host requirements: PHP 8.3+ with the `mysqli` extension. The `wp db …` commands
also need a MySQL/MariaDB client on the host, so prefer `./dev wp db …`, which
already has everything set up.

</details>

<details>
<summary><b>💻 Local setup without Docker</b></summary>

<br>

```bash
composer install
cp .env.example .env   # set DB_HOST, credentials, WP_HOME and salts
```

Point your web server's document root at `web/` and install WordPress as above.

</details>

<details>
<summary><b>⚙️ Configuration</b></summary>

<br>

All configuration comes from environment variables (`.env` locally, App
Service settings in Azure). **Never commit `.env`.**

| Variable | Description |
|---|---|
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Database credentials (Docker also uses them to create the database) |
| `DB_ROOT_PASSWORD` | MySQL root password (Docker only) |
| `DB_HOST` | Database host (forced to `db` inside Docker Compose) |
| `DB_SSL` | Enable TLS for the database connection (required for Azure Database for MySQL) |
| `DATABASE_URL` | Optional DSN instead of the individual `DB_*` variables |
| `WP_ENV` | `development`, `staging` or `production` |
| `WP_HOME`, `WP_SITEURL` | Site URLs (`WP_SITEURL` = `${WP_HOME}/wp`) |
| `AUTH_KEY` … `NONCE_SALT` | WordPress salts |
| `AZURE_OPENAI_ENDPOINT` | `https://<resource>.openai.azure.com` (AI is disabled until configured) |
| `AZURE_OPENAI_DEPLOYMENT` | Chat model deployment name (a model that supports structured outputs, e.g. GPT-4o / GPT-4o mini) |
| `AZURE_OPENAI_API_KEY` | API key. Leave empty when using a managed identity |
| `AZURE_OPENAI_USE_MANAGED_IDENTITY` | `true` to authenticate with the App Service managed identity (Entra ID) |
| `AZURE_OPENAI_API_VERSION` | Default `2024-10-21` |
| `AZURE_OPENAI_TIMEOUT` | Request timeout in seconds (default `30`) |
| `APP_PORT`, `DB_PORT`, `PMA_PORT`, `HOST_UID`, `HOST_GID` | Optional Docker Compose overrides |
| `PHP_VERSION`, `COMPOSER_VERSION`, `WP_CLI_VERSION`, `MYSQL_VERSION`, `PHPMYADMIN_VERSION` | Image and tool versions (defaults `8.5`, `2.11.2`, `2.12.0`, `8.4`, `5.2`). Rebuild with `./dev build` after changing them |

</details>

<details>
<summary><b>🎨 Theme development (Sage)</b></summary>

<br>

The `jalasupport` theme in `web/app/themes/jalasupport` is built on Sage 11
(Blade, Tailwind CSS 4, Vite). Acorn and the theme's `App\` namespace are
installed through the root `composer.json`, so the theme has no Composer
dependencies of its own.

```bash
cd web/app/themes/jalasupport
npm install
npm run build      # production assets in public/build
npm run dev        # Vite dev server with hot reload
```

**Application routes** (login required; pretty permalinks must be enabled, which `./dev setup` does):

| URL | Blade view | Notes |
|---|---|---|
| `/` | `app/dashboard` | Ticket counts and recent activity |
| `/tickets/` | `app/tickets/index` | Search, filters, sorting, pagination (state kept in the URL) |
| `/tickets/new/` | `app/tickets/create` | Create ticket form |
| `/tickets/{id}/` | `app/tickets/show` | Details, status/priority/category controls, editing, AI panel |
| `/settings/` | `app/settings` | Read-only configuration (administrators) |

**How the frontend is built**

- All markup lives in Blade files: `layouts/`, `sections/` (sidebar, topbar),
  `partials/` (AI panel), `app/` (pages) and reusable anonymous components in
  `components/` (`<x-card>`, `<x-button>`, `<x-badge>`, `<x-field>`,
  `<x-icon>`, `<x-notice>`, `<x-page-header>`, `<x-ai-badge>`, `<x-toast>`).
- View Composers in `app/View/Composers` provide navigation, the current user,
  ticket options and non-sensitive client configuration.
- Alpine.js components in `resources/js/components` load and change data
  through the `jalasupport/v1` REST API, so the REST layer enforces
  authorization for every action. Dynamic content is rendered with `x-text`
  (never `x-html`).
- AI suggestions use a separate violet style and an "AI suggestion" badge. They
  are saved only when the user clicks **Accept**, and the UI works fully when AI
  is not configured.

</details>

<details>
<summary><b>🔌 REST API</b></summary>

<br>

Base URL: `/wp-json/jalasupport/v1`. Every endpoint requires an authenticated user
with ticket capabilities.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/tickets` | List tickets: `search`, `status`, `priority`, `category`, `orderby`, `order`, `page`, `per_page` |
| `GET` | `/tickets/stats` | Ticket counts by status |
| `POST` | `/tickets` | Create a ticket |
| `GET` | `/tickets/{id}` | Ticket details |
| `PUT` / `PATCH` | `/tickets/{id}` | Update a ticket (partial) |
| `DELETE` | `/tickets/{id}` | Move a ticket to the trash (administrators) |
| `POST` | `/tickets/{id}/ai/analyze` | AI suggestions: category, priority, summary, reply (not saved) |
| `POST` | `/tickets/{id}/ai/suggest-reply` | AI suggested reply (not saved) |

📄 Full reference: [docs/API.md](docs/API.md)

</details>

<details>
<summary><b>🔒 Security</b></summary>

<br>

- Tickets are a non-public post type with dedicated capabilities (`read_tickets`,
  `edit_tickets`, …). Subscribers, authors and editors cannot access them.
- Every REST route has its own permission callback. Single-ticket routes don't
  reveal whether a ticket exists to unauthorized users.
- All input is validated and sanitized on the server. Registered post meta
  sanitizes values a second time.
- Secrets live only in environment variables. Azure credentials stay server-side
  and are never sent to JavaScript.
- Ticket content is treated as untrusted input to the AI. AI output is validated
  and only saved after the user confirms it.
- The plugin and theme file editors in wp-admin are disabled (Bedrock defaults).
- **Wordfence** (free) provides malware and vulnerability scanning, a web
  application firewall, brute-force protection, two-factor authentication and
  login security. See *Wordfence* below.

**Wordfence**

Installed with Composer (`wp-plugin/wordfence`) and activated by `./dev setup`.

1. **Register the free license once:** WP admin → *Wordfence* → follow the
   onboarding (email address and Wordfence terms). Until then, firewall rules
   and malware signatures aren't downloaded, and `web/app/debug.log` shows
   `Wordfence WAF file read failed: … rules.php` warnings.
2. **Run a scan:** *Wordfence → Scan → Start New Scan*. Bedrock keeps WordPress
   core in `web/wp`; Wordfence scans it as a subdirectory install.
3. **Firewall:** works in *Basic* mode out of the box (loaded as a plugin).
   *Extended Protection* loads the firewall before WordPress through
   `auto_prepend_file`. The Apache vhost doesn't read `.htaccess` or
   `.user.ini`, so for production it has to be set in the image's PHP
   configuration (part of the Azure deployment phase).

Notes:

- **Free tier:** new firewall rules and malware signatures arrive 30 days
  after paid users get them.
- **Application passwords stay enabled.** Wordfence disables them by default,
  but the REST API documents them for API clients (HTTPS only). `./dev setup`
  turns them back on (`loginSec_disableApplicationPasswords = 0`). Change this
  in *Wordfence → Login Security → Settings* if no API clients are needed.
- **Runtime data** (`web/app/wflogs/`) holds site-specific firewall
  configuration, keys, attack data and a GeoIP database. It's excluded from
  Git and Docker builds and recreated on each site. On Azure App Service it
  must live on persistent storage.
- **Updates** come through Composer (`composer update wp-plugin/wordfence`),
  since Bedrock disables plugin updates from wp-admin outside development.

</details>

<details>
<summary><b>🧪 Testing and code quality</b></summary>

<br>

All code must pass the linters before it is committed; CI enforces the same
checks on every pull request.

| Tool | Scope | Command | Config |
|---|---|---|---|
| Laravel Pint | PHP code style (PER) | `composer lint` / `composer lint:fix` | `pint.json` |
| PHPStan (level 6) + WordPress stubs | PHP static analysis | `composer analyse` | `phpstan.neon.dist` |
| ESLint + Stylistic | Theme JavaScript | `npm run lint` / `npm run lint:fix` (theme dir) | `web/app/themes/jalasupport/eslint.config.js` |
| editorconfig-checker | Indentation and whitespace in every file | `npm run lint:editorconfig` (root) | `.editorconfig`, `.editorconfig-checker.json` |
| Pest + Brain Monkey | PHP unit tests | `composer test` | `phpunit.xml.dist`, `tests/` |

Run everything at once with `./dev lint` (add `--fix` to auto-fix style first).

**Formatting conventions**

- 4-space indentation for all files: PHP, Blade, JavaScript, CSS, JSON, XML,
  shell, Dockerfile and config files.
- YAML uses 2 spaces, because nested sequence items can't line up on a 4-space grid.
- LF line endings, UTF-8, a final newline, no trailing whitespace.

Unit tests cover enums, the post type and capability configuration, ACF field
definitions and meta sanitization, ticket validation, model serialization, and
REST authentication, authorization and handlers, AI response validation, the
Azure OpenAI client (request shape, prompt-injection boundaries, error mapping,
managed identity) and AI rate limiting.

</details>

<details>
<summary><b>🔁 Pull requests and CI</b></summary>

<br>

Every pull request runs the lightweight checks in
[`.github/workflows/ci.yml`](.github/workflows/ci.yml):

- **PHP 8.3 and 8.5:** `composer validate`, Pint code style, PHPStan static analysis, Pest unit tests (the minimum supported version and the Docker runtime)
- **Theme:** ESLint and `npm run build`
- **Editorconfig:** indentation and whitespace rules for every file

Passing checks are a prerequisite for human review, not a replacement for it.
The [PR template](.github/pull_request_template.md) contains a review checklist,
and [`CODEOWNERS`](.github/CODEOWNERS) requests a review from the code owner. To
enforce both, enable branch protection on `main`: require the *PHP*, *Theme* and
*Editorconfig* status checks and a pull request review.

Branch naming: `feature/…`, `fix/…`, `chore/…`.

</details>

<details>
<summary><b>🗺️ Roadmap</b></summary>

<br>

1. **Foundation:** Bedrock, Docker, Sage, plugin skeleton ✅
2. **Ticket management:** CPT, ACF fields, REST API, tests ✅
3. **AI assistance:** Azure OpenAI service, analyze and suggest-reply endpoints ✅
4. **Application UI:** dashboard, ticket list, details, create, settings ✅
5. **Deployment:** production image, Azure App Service, documentation 📅
6. **MCP integration (post-MVP):** WordPress MCP Adapter with read-only ticket abilities, resources and prompts 📅

See [ROADMAP.md](ROADMAP.md) for the detailed checklist.

</details>
