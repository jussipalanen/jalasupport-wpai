# Changelog

All notable changes to JalaSupport AI are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Releases are tagged on GitHub as plain `x.y.z` (for example `0.1.0`); see
[Releasing](README.md#releasing) for the workflow.

## [Unreleased]

### Added

- **Ticket export and import** as JSON: `wp jalasupport tickets export|import`,
  `./dev tickets export|import` locally and `./dev azure export-tickets` /
  `./dev azure import-tickets` for the Azure app. Imports validate every
  ticket, assign new IDs and a chosen author, keep the original creation date
  and skip tickets imported before; `--dry-run` previews.
- `SECURITY.md` with private vulnerability reporting, and README notes on the
  repository rules for the now public repository.
- README logo (`docs/images/logo.svg`): the JS icon with a support headset on
  the dark purple circle of the favicon.
- **WordPress Playground demo** (`./dev demo`): the app running in the
  browser with SQLite, 10 fictional sample tickets and a demo agent account;
  `scripts/playground.sh` builds the bundle (plugin, theme with Acorn,
  blueprint).
- `AI_PROVIDER=demo`: sample AI answers from keyword rules, without an AI
  service or API key.
- `JALASUPPORT_DEMO=true`: demo mode; only administrators can open wp-admin,
  other users are sent to the app (also after login), and the login screen
  can show the demo account.
- **Custom domain for Azure** (`./dev azure domain add|update|clean`): adds
  the domain with a free managed certificate after showing the DNS records,
  moves WordPress to it and replaces the old address in the database (dry run
  first). `APP_DOMAIN` in `scripts/azure/config.env` keeps the domain when
  `setup.sh` runs again; `./dev azure status` lists it.
- `APP_MIN_REPLICAS` in `scripts/azure/config.env`: `1` keeps the Azure app
  always on (no cold starts); `0`, the default, scales to zero.

### Changed

- **Public start page:** visitors who are not signed in see what the app
  does, the current ticket totals with charts (by status, priority and
  category, plus the resolution rate) and a large sign-in button, instead of
  being sent straight to the login screen. Every other page still requires
  login, and only aggregate numbers are shown. The numbers are cached for
  5 minutes and refreshed when a ticket changes; `JALASUPPORT_PUBLIC_STATS=false`
  hides them.

### Fixed

- `./dev azure wp` passes WP-CLI options such as `--role=…` correctly (they
  were parsed by `su` before).
- README release badge shows the current version (the dynamic badge cannot
  read a private repository); `./dev release` keeps it in sync.

## [0.1.0] - 2026-11-23

First release: the MVP support ticket application, local development
environment and Azure free-tier deployment.

### Added

- **Application foundation:** Roots Bedrock, Sage 11 with Acorn, and the
  `jalasupport` plugin, which holds all business logic so the theme stays
  replaceable.
- **Tickets:** private `ticket` post type with ACF fields for status,
  priority, category, customer details, AI summary and suggested reply; the
  `jalasupport_agent` role and dedicated ticket capabilities.
- **REST API** under `/wp-json/jalasupport/v1/`: ticket CRUD, search,
  filtering, sorting and pagination, with explicit permission checks on every
  endpoint (see `docs/API.md`).
- **AI assistance:** ticket analysis (category, priority, summary) and
  suggested replies through Azure OpenAI or Google Gemini (free-tier models,
  selected with `AI_PROVIDER`). Structured JSON responses are validated,
  ticket content is treated as untrusted input, and suggestions are only
  applied after user confirmation.
- **Frontend:** Blade, Tailwind CSS and Alpine.js views for the dashboard,
  ticket list (10/25/50/100 rows per page), ticket details, ticket creation
  and settings; dark theme with a space backdrop and a branded login screen.
- **Icons:** favicon and home-screen icons for phones and tablets, with a web
  app manifest.
- **Security:** Wordfence plugin.
- **Local development:** Docker Compose stack (PHP 8.5, MySQL 8.4 with TLS,
  local-only phpMyAdmin) and the `./dev` helper for Docker, WP-CLI, database,
  tests and linters.
- **Azure deployment (free tier):** production Docker image, Container Apps
  definition (`infra/containerapp.yaml`), MySQL Flexible Server, Azure Files
  storage and a budget alert, managed with `./dev azure` (setup, deploy,
  status, shell, wp, teardown; all support `DRY_RUN=1`).
- **CI/CD:** GitHub Actions for tests on PHP 8.3 and 8.5, linters (Pint,
  PHPStan, ESLint, editorconfig, actionlint, ShellCheck, Hadolint), dependency
  audits, secret scanning, production image smoke test and a manual Azure
  deploy workflow; Dependabot for dependency updates.
- **Release process:** this changelog, `./dev release` and the Release
  workflow, which publishes a GitHub release for every `x.y.z` tag.

### Security

- AI endpoints are usable only from the site's own UI (same origin, logged-in
  session); API clients are refused unless explicitly allowed. Per-user and
  daily request limits apply.
- Secrets stay server-side: API keys and database credentials come from
  environment variables and are stored as Container Apps secrets on Azure.
- Dry-run previews of the Azure deployment mask every secret value,
  regardless of its format.

[Unreleased]: https://github.com/jussipalanen/jalasupport-wpai/compare/0.1.0...HEAD
[0.1.0]: https://github.com/jussipalanen/jalasupport-wpai/releases/tag/0.1.0
