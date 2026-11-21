# CLAUDE.md

@AGENTS.md

# JalaSupport AI — Project Instructions

## 1. Project Overview

JalaSupport AI is a lightweight AI-powered customer support and ticket management application built on WordPress and the Roots ecosystem.

The project aims to demonstrate professional WordPress development, modern frontend engineering, API integrations, AI capabilities, containerization, and Azure cloud deployment.

This is a functional software prototype, not a traditional WordPress website.

### Primary Goals

- Build a modern, responsive support ticket management application.
- Use WordPress as the application backend and content management platform.
- Implement a clean, maintainable architecture using Roots.
- Provide an intuitive SaaS-style frontend.
- Integrate Azure OpenAI for AI-assisted ticket processing.
- Support local Docker development and Azure deployment.
- Maintain high standards for security, code quality, and documentation.

## 2. Technology Stack

Use the following technologies as the preferred project stack.

### Backend

- PHP 8.3+
- WordPress
- Roots Bedrock
- Roots Acorn
- Composer
- MySQL 8+
- Advanced Custom Fields (ACF)

### Frontend

- Roots Sage
- Blade templates
- Tailwind CSS
- Alpine.js
- Vite
- Vanilla JavaScript where appropriate

React may be introduced for specific complex interactive components when there is a clear architectural benefit. Do not introduce React as a mandatory dependency for simple UI interactions.

### AI

- Azure OpenAI
- Server-side PHP integration
- Structured JSON responses
- Secure API authentication

### Infrastructure

- Docker
- Docker Compose
- Azure App Service
- Azure Database for MySQL
- Azure Container Registry
- GitHub Actions
- Application Insights where appropriate

## 3. Architecture Principles

Follow these architectural principles:

1. Keep business logic outside the WordPress theme.
2. Implement application functionality in a dedicated WordPress plugin.
3. Use Sage primarily for presentation and frontend rendering.
4. Use Acorn for service container integration and application services where appropriate.
5. Use ACF for structured ticket metadata.
6. Use WordPress REST API for application endpoints.
7. Keep external integrations isolated behind dedicated service classes.
8. Avoid unnecessary dependencies and architectural complexity.
9. Prefer WordPress-native functionality whenever it is appropriate.
10. Keep the application compatible with the selected Roots versions.

### Important

Never put ticket management business logic, AI integrations, or REST API business rules inside the Sage theme.

The theme must remain replaceable without losing application functionality.

## 4. Project Structure

Follow a structure similar to the following:

```text
jalasupport-ai/
├── config/
├── web/
│   └── app/
│       ├── mu-plugins/
│       ├── plugins/
│       │   └── jalasupport/
│       │       ├── app/
│       │       │   ├── Models/
│       │       │   ├── Services/
│       │       │   ├── Http/
│       │       │   │   └── Controllers/
│       │       │   ├── Providers/
│       │       │   └── Support/
│       │       ├── database/
│       │       ├── resources/
│       │       └── jalasupport.php
│       └── themes/
│           └── jalasupport/
│               ├── app/
│               ├── resources/
│               │   ├── views/
│               │   ├── css/
│               │   └── js/
│               ├── public/
│               └── functions.php
├── docker/
├── .github/
│   └── workflows/
├── docs/
├── docker-compose.yml
├── composer.json
├── package.json
├── CLAUDE.md
├── AGENTS.md
├── README.md
├── ROADMAP.md
└── .env.example
```

This is a reference structure, not a requirement to create every directory immediately.

Inspect the actual Roots project structure before making changes. Follow the conventions of the installed Bedrock, Sage, and Acorn versions.

## 5. WordPress Development

### Custom Post Types

Create a dedicated `ticket` Custom Post Type.

Requirements:

- Use a stable, explicit post type registration.
- Enable REST API support.
- Configure appropriate capabilities.
- Avoid exposing sensitive ticket information publicly.
- Restrict access to authorized users.
- Keep ticket-related functionality inside the JalaSupport plugin.

### ACF Fields

Use ACF for structured ticket metadata.

Initial fields:

| Field | Type |
|---|---|
| ticket_status | Select |
| ticket_priority | Select |
| ticket_category | Select |
| customer_name | Text |
| customer_email | Email |
| ai_summary | Textarea |
| ai_suggested_reply | Textarea |

Use appropriate field validation and sanitization.

Do not store passwords, API keys, or other secrets in ACF.

Prefer registering field groups in PHP or using ACF Local JSON when appropriate. Keep field definitions version-controlled.

### Ticket Statuses

Use the following initial statuses:

- Open
- In Progress
- Resolved
- Closed

### Ticket Priorities

- Low
- Normal
- High
- Urgent

### Ticket Categories

- Technical Support
- Account Issues
- Billing
- General Inquiry
- Other

## 6. REST API

Implement custom REST API endpoints under:

`/wp-json/jalasupport/v1/`

Suggested endpoints:

- GET /tickets
- POST /tickets
- GET /tickets/{id}
- PUT /tickets/{id}
- DELETE /tickets/{id}
- POST /tickets/{id}/ai/analyze
- POST /tickets/{id}/ai/suggest-reply

Requirements:

- Validate all incoming data.
- Sanitize user input.
- Verify permissions on every protected endpoint.
- Use appropriate HTTP status codes.
- Return consistent JSON responses.
- Never expose internal exceptions or secrets.
- Support pagination and filtering.
- Avoid unnecessary database queries.

Do not assume that registering a CPT with `show_in_rest` automatically makes every custom field or private ticket accessible safely.

Implement explicit authorization and permission checks.

## 7. AI Integration — Azure OpenAI

Implement AI functionality through a dedicated service class.

Suggested class:

`AzureOpenAIService`

Responsibilities:

- Authenticate with Azure OpenAI.
- Submit ticket content for analysis.
- Request structured JSON output.
- Validate the returned response.
- Handle API errors and timeouts.
- Log safe diagnostic information.

### Initial AI Features

1. Ticket category suggestion.
2. Ticket priority suggestion.
3. Ticket summary generation.
4. Suggested customer response.

AI suggestions must never be applied automatically without user confirmation.

### AI Response Format

Use structured JSON similar to:

```json
{
  "category": "Technical Support",
  "priority": "High",
  "summary": "The customer reports an issue with account access.",
  "suggested_reply": "Thank you for contacting support..."
}
```

Validate every field before using the response.

Treat ticket descriptions as untrusted user input. Do not allow instructions embedded in ticket content to override system instructions or application behavior.

### Security

- Keep Azure credentials server-side.
- Use environment variables or Azure Key Vault.
- Never expose credentials to JavaScript.
- Never commit secrets to Git.
- Use Microsoft Entra ID / Managed Identity where practical.
- Implement reasonable request limits.
- Handle Azure OpenAI failures gracefully.

## 8. Frontend Guidelines

The frontend should look like a modern professional SaaS application.

### Design Direction

- Clean and minimal interface.
- Light theme by default.
- Professional typography.
- Neutral colors.
- Consistent spacing.
- Subtle borders and shadows.
- Responsive layouts.
- Accessible controls.
- Clear visual hierarchy.

Use Tailwind CSS consistently.

### Main Application Views

1. Dashboard
2. Ticket List
3. Ticket Details
4. Create Ticket
5. Settings

### Dashboard

Display:

- Total tickets.
- Open tickets.
- In-progress tickets.
- Resolved tickets.
- Recent ticket activity.

### Ticket List

Include:

- Search.
- Status filters.
- Priority filters.
- Category filters.
- Sorting.
- Pagination.

### Ticket Details

Include:

- Ticket information.
- Customer information.
- Status and priority controls.
- AI analysis.
- AI-generated summary.
- Suggested response.

### AI UI

Clearly distinguish AI-generated suggestions from user-provided information.

Provide explicit actions to accept, edit, or reject AI suggestions.

Do not make the interface dependent on AI being available.

## 9. Docker and Local Development

Local development must work through Docker Compose.

Requirements:

- WordPress/PHP environment.
- MySQL database.
- Persistent database storage.
- Environment-based configuration.
- Composer dependency installation.
- Node.js frontend build.
- Clear setup documentation.

Provide a simple developer workflow:

```bash
docker compose up -d
```

Document all required installation and initialization commands.

Never commit `.env` files containing actual credentials.

## 10. Azure Deployment

The application should be deployable to Azure App Service using a Docker image.

Target architecture:

- Azure App Service for the application.
- Azure Database for MySQL for persistent database storage.
- Azure Container Registry for Docker images.
- Azure OpenAI for AI features.
- GitHub Actions for CI/CD.

Deployment requirements:

- Production-ready Docker image.
- Environment-specific configuration.
- Secure secret management.
- Persistent storage for WordPress uploads.
- HTTPS.
- Database connectivity and firewall configuration.
- Health checks where appropriate.
- Deployment documentation.

Do not assume that container filesystem changes are persistent.

Do not introduce Azure infrastructure resources unless they are necessary for the current development phase.

## 11. Testing and Code Quality

Use appropriate tools for the installed project stack.

Preferred tools:

- PHPStan
- PHPUnit
- PHPCS
- ESLint
- TypeScript checks
- Composer validation
- npm build

Write tests for critical business logic and REST API permission checks.

Before considering a feature complete:

1. Verify the implementation.
2. Run relevant tests.
3. Check for PHP errors.
4. Check frontend build output.
5. Review security implications.
6. Update documentation when necessary.

Do not claim that a test passed unless it was actually executed.

## 12. Development Workflow

Work incrementally.

For each feature:

1. Inspect the existing implementation.
2. Understand the project architecture.
3. Propose a small implementation plan.
4. Implement the feature.
5. Run relevant checks.
6. Fix identified problems.
7. Update documentation.
8. Summarize changed files and remaining work.

Avoid rewriting working components without a clear reason.

Do not introduce unnecessary abstractions.

Do not install additional packages without checking whether existing dependencies already provide the required functionality.

## 13. Current MVP Scope

The first version should remain small.

Required features:

- WordPress authentication.
- Ticket creation.
- Ticket listing.
- Ticket details.
- Ticket editing.
- Ticket status management.
- Ticket priority management.
- Search and filtering.
- AI ticket analysis.
- AI-generated summary.
- Responsive SaaS-style dashboard.
- Docker-based local development.
- Azure deployment.

Out of scope for the initial prototype:

- Multi-tenant architecture.
- Complex customer organizations.
- Real-time chat.
- Advanced notification systems.
- Billing and subscriptions.
- Complex workflow automation.
- Full RAG knowledge base.
- Autonomous AI agents.

Do not implement out-of-scope functionality unless explicitly requested.

### Planned post-MVP extensions

- MCP access through the official WordPress MCP Adapter (`wordpress/mcp-adapter`) and the core Abilities API, as described in ROADMAP.md (Phase 6).
- Abilities must reuse the plugin's services (`TicketRepository`, `TicketValidator`) and capability checks; they must not duplicate business logic.
- Start read-only. Write abilities must be opt-in (disabled by default), and ticket deletion, user management, settings and credentials must never be exposed.
- MCP clients act on behalf of an authenticated WordPress user; this is not an autonomous agent and must never bypass application authorization.

## 14. Final Development Principle

Build a small, functional, secure, and well-structured application.

Prioritize working functionality, maintainability, and clear architecture over unnecessary complexity.

The project should demonstrate that WordPress can be used as a foundation for a modern application with professional frontend development, external AI integrations, and cloud deployment.
