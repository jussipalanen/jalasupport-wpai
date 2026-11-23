# Changelog

All notable changes to JalaSupport AI are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Releases are tagged on GitHub as plain `x.y.z` (for example `0.1.0`); see
[Releasing](README.md#releasing) for the workflow.

## [Unreleased]

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
