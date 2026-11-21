# Roadmap

Planned development for the JalaSupport AI MVP. Items are delivered in small,
reviewable increments.

## Phase 1 — Foundation

- [x] Bedrock project scaffold
- [x] Docker Compose local development environment
- [x] Sage theme with Acorn, Tailwind CSS and Vite
- [x] JalaSupport plugin skeleton

## Phase 2 — Ticket management

- [x] `ticket` custom post type with restricted capabilities
- [x] ACF field group for ticket metadata (status, priority, category, customer, AI fields)
- [x] REST API under `/wp-json/jalasupport/v1/` with explicit permission checks
- [x] Tests for validation and REST authorization

## Phase 3 — AI assistance

- [x] `AzureOpenAIService` with structured JSON responses and validation
- [x] Analyze and suggest-reply endpoints (suggestions require user confirmation)

## Phase 4 — Application UI

- [x] Dashboard (ticket counts, recent activity)
- [x] Ticket list (search, filters, sorting, pagination)
- [x] Ticket details (status/priority controls, AI suggestions with accept/edit/reject)
- [x] Create ticket
- [x] Settings

## Phase 5 — Deployment

- [ ] Production Docker image
- [x] GitHub Actions CI (lint, tests, build)
- [ ] Azure App Service + Azure Database for MySQL + Azure Container Registry
- [ ] Deployment documentation

## Phase 6 — MCP integration (post-MVP)

Expose JalaSupport to MCP clients (e.g. Claude Code, Claude Desktop) through the
official WordPress MCP Adapter and the core Abilities API.

- [ ] Install `wordpress/mcp-adapter` (^0.7, pinned while pre-1.0) via Composer
- [ ] Dedicated "JalaSupport" MCP server exposing only `jalasupport/*` abilities
- [ ] Read-only abilities: list tickets, get ticket, ticket stats
- [ ] Resources: ticket vocabulary and triage guidelines
- [ ] Prompts: triage ticket, draft reply
- [ ] Unit tests for ability permissions, input validation and output
- [ ] Documentation: local (WP-CLI) and remote (HTTP + application password) setup
- [ ] Optional write abilities (status/priority/category, save AI notes, create
      ticket) behind `JALASUPPORT_MCP_WRITE=false`; ticket deletion is never exposed

## Out of scope for the MVP

Multi-tenancy, customer organizations, real-time chat, advanced notifications,
billing, complex workflow automation, RAG knowledge base, autonomous agents.
