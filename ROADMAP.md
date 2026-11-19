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
- [ ] REST API under `/wp-json/jalasupport/v1/` with explicit permission checks
- [ ] Tests for validation and REST authorization

## Phase 3 — AI assistance

- [ ] `AzureOpenAIService` with structured JSON responses and validation
- [ ] Analyze and suggest-reply endpoints (suggestions require user confirmation)

## Phase 4 — Application UI

- [ ] Dashboard (ticket counts, recent activity)
- [ ] Ticket list (search, filters, sorting, pagination)
- [ ] Ticket details (status/priority controls, AI suggestions with accept/edit/reject)
- [ ] Create ticket
- [ ] Settings

## Phase 5 — Deployment

- [ ] Production Docker image
- [x] GitHub Actions CI (lint, tests, build)
- [ ] Azure App Service + Azure Database for MySQL + Azure Container Registry
- [ ] Deployment documentation

## Out of scope for the MVP

Multi-tenancy, customer organizations, real-time chat, advanced notifications,
billing, complex workflow automation, RAG knowledge base, autonomous agents.
