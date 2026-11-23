## Summary

<!-- What does this change do and why? -->

## Changes

-

## How to test

<!-- Steps a reviewer can follow locally, e.g. docker compose up -d, URLs, WP-CLI commands. -->

## Human review checklist

Automated checks (lint, tests, build) must pass, but they do not replace review.
The reviewer confirms:

- [ ] The change matches the requested scope; no out-of-scope features
- [ ] Business logic stays in the `jalasupport` plugin, not in the theme
- [ ] Input is validated/sanitized and output is escaped
- [ ] REST endpoints enforce capability checks independently
- [ ] AI suggestions are never applied without user confirmation
- [ ] No secrets, credentials or `.env` files are committed
- [ ] Tests were added or updated for business logic and permissions
- [ ] Documentation (README / ROADMAP / docs) is updated where needed
- [ ] `CHANGELOG.md` has an entry under `[Unreleased]` for user-facing changes
- [ ] The change was run locally and behaves as described
