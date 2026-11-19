# AGENTS.md

# General Engineering Instructions

These instructions apply to all development work in this repository.

## 1. Engineering Philosophy

- Write clean, maintainable, production-conscious code.
- Prefer simple solutions over unnecessary abstractions.
- Follow established framework conventions.
- Avoid premature optimization.
- Keep components small and focused.
- Reuse existing functionality when appropriate.
- Do not introduce unnecessary dependencies.
- Prioritize correctness, security, and maintainability.

## 2. Before Making Changes

Before implementing anything:

1. Inspect the existing project structure.
2. Read relevant documentation.
3. Check the installed framework and dependency versions.
4. Understand existing conventions.
5. Identify affected components.
6. Create a concise implementation plan.

Never assume that a package, API, or framework feature is available without checking the installed version.

## 3. PHP Standards

- Follow modern PHP standards.
- Use strict typing where practical.
- Prefer dependency injection for services.
- Keep classes focused on a single responsibility.
- Avoid unnecessary global state.
- Use namespaces consistently.
- Follow PSR-12 coding conventions.
- Handle errors explicitly.
- Avoid suppressing errors without a valid reason.

## 4. WordPress Standards

- Follow WordPress Coding Standards where applicable.
- Use WordPress APIs instead of reinventing existing functionality.
- Escape output using context-appropriate functions.
- Sanitize and validate all incoming data.
- Use nonces where appropriate.
- Enforce capability checks.
- Use prepared database queries.
- Avoid direct SQL unless necessary.
- Prevent unauthorized access to private application data.
- Keep business logic out of theme files.

Never trust frontend validation alone.

## 5. Frontend Standards

- Use semantic HTML.
- Prioritize accessibility.
- Build responsive interfaces.
- Use reusable components and templates.
- Maintain consistent spacing and typography.
- Avoid unnecessary JavaScript.
- Prefer Alpine.js for lightweight interactions.
- Use React only when a component genuinely benefits from it.
- Keep CSS maintainable and use Tailwind conventions.
- Avoid duplicated UI logic.

The application should remain usable on desktop, tablet, and mobile devices.

## 6. API Development

- Validate all request parameters.
- Sanitize user-provided values.
- Check authentication and authorization.
- Return consistent response structures.
- Use appropriate HTTP status codes.
- Handle failures gracefully.
- Avoid exposing internal implementation details.
- Document custom endpoints.

Every endpoint that accesses private ticket information must enforce authorization independently.

## 7. Security

Security must be considered throughout development.

Important requirements:

- Never commit credentials or secrets.
- Never expose server-side API keys.
- Use environment variables for configuration.
- Validate and sanitize all input.
- Escape all output.
- Apply least-privilege access.
- Protect sensitive REST API endpoints.
- Avoid insecure deserialization.
- Prevent SQL injection.
- Consider XSS and CSRF risks.
- Protect AI integrations against prompt injection.
- Do not log sensitive customer information unnecessarily.

Review security implications before completing authentication, API, and AI-related changes.

## 8. AI Integration Guidelines

- Treat external AI responses as untrusted input.
- Validate structured responses.
- Handle malformed JSON.
- Implement timeout and error handling.
- Avoid unnecessary AI requests.
- Keep prompts version-controlled where appropriate.
- Do not expose internal prompts or credentials to the browser.
- Require user confirmation before applying AI-generated changes.
- Never let AI-generated content bypass application authorization.

AI must assist users, not independently perform privileged actions.

## 9. Database and Data Modeling

- Use appropriate data types.
- Avoid unnecessary duplicated data.
- Consider indexing for frequently queried fields.
- Validate relationships between records.
- Preserve data integrity.
- Handle database errors.
- Avoid unnecessary schema changes.
- Document custom database structures.

Prefer WordPress-native storage mechanisms when they fit the requirements.

Use custom tables only when there is a clear technical justification.

## 10. Testing

Run relevant checks after implementation.

Examples:

```bash
composer validate
composer test
npm run build
npm run lint
```

Only run commands that actually exist in the project.

Add tests for:

- Business logic.
- REST API authorization.
- Input validation.
- AI response validation.
- Critical application workflows.

Do not claim successful tests without executing them.

## 11. Git Workflow

- Use descriptive branch names.
- Make focused commits.
- Avoid committing generated secrets or temporary files.
- Do not overwrite unrelated user changes.
- Review changes before committing.
- Keep pull requests focused.
- Provide clear commit messages.

Suggested branch naming:

```text
feature/ticket-management
feature/ai-ticket-analysis
feature/dashboard-ui
fix/rest-api-permissions
chore/azure-deployment
```

## 12. Documentation

Update documentation when introducing:

- New features.
- New dependencies.
- Configuration changes.
- Environment variables.
- API endpoints.
- Database changes.
- Deployment requirements.

Keep README.md focused on installation and project usage.

Keep ARCHITECTURE.md focused on system design.

Keep ROADMAP.md focused on planned development.

## 13. AI Coding Agent Behavior

When implementing features:

- Work in small, reviewable steps.
- Explain significant architectural decisions.
- Do not silently change the project architecture.
- Do not remove working functionality without justification.
- Do not replace existing libraries without a clear reason.
- Avoid unnecessary refactoring.
- Ask before making destructive changes.
- Report assumptions and limitations.
- Report tests that could not be executed.
- Summarize modified files after each implementation phase.

If requirements are ambiguous, identify the ambiguity before making major architectural decisions.

## 14. Definition of Done

A feature is complete only when:

- The implementation meets the requested requirements.
- Relevant tests have been executed.
- No known critical security issues remain.
- The frontend builds successfully where applicable.
- Error handling is implemented.
- Documentation is updated where necessary.
- No secrets have been committed.
- The changes are ready for human review.

## 15. Project Priority

Always prioritize the following:

1. Security.
2. Functional correctness.
3. Maintainability.
4. User experience.
5. Performance.
6. Developer experience.

The objective is to build a reliable and understandable application, not simply generate code quickly.
