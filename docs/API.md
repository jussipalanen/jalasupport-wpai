# REST API

Base URL: `/wp-json/jalasupport/v1`

## Authentication

All endpoints require an authenticated WordPress user.

- **Browser (theme UI):** WordPress login cookie plus the `X-WP-Nonce` header
  (nonce action `wp_rest`).
- **API clients:** [Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
  over HTTPS (HTTP Basic authentication).

## Authorization

| Capability | Support Agent | Administrator |
|---|:-:|:-:|
| `read_tickets` (list, view, stats) | ✅ | ✅ |
| `create_tickets` | ✅ | ✅ |
| `edit_post` on a ticket (update) | ✅ | ✅ |
| `delete_post` on a ticket (delete) | ❌ | ✅ |

Every route checks permissions on its own. For single-ticket routes, users
without ticket access get `403` whether or not the ticket exists, so ticket IDs
cannot be probed. The core `wp/v2` routes do not expose tickets.

## Response format

Success:

```json
{ "data": { "...": "..." } }
```

Collections add pagination metadata (also sent as `X-WP-Total` and
`X-WP-TotalPages` headers):

```json
{ "data": [ ... ], "meta": { "total": 42, "pages": 2, "page": 1, "per_page": 25 } }
```

Errors use the standard WordPress format:

```json
{
    "code": "jalasupport_validation_failed",
    "message": "The submitted ticket data is invalid.",
    "data": { "status": 422, "errors": { "customer_email": "Enter a valid email address." } }
}
```

| Status | Meaning |
|---|---|
| 400 | Malformed request / no valid fields to update |
| 401 | Not authenticated |
| 403 | Authenticated but not allowed |
| 404 | Ticket not found |
| 422 | Validation failed (`data.errors` holds messages per field) |
| 500 | Ticket could not be saved (details are logged, not returned) |

## Ticket object

```json
{
    "id": 12,
    "title": "Cannot log in",
    "description": "I reset my password but still cannot log in.",
    "status": "open",
    "status_label": "Open",
    "priority": "high",
    "priority_label": "High",
    "category": "account_issues",
    "category_label": "Account Issues",
    "customer": { "name": "Jane Doe", "email": "jane@example.com" },
    "ai": { "summary": "", "suggested_reply": "" },
    "author_id": 1,
    "created_at": "2026-11-20T10:00:00+00:00",
    "updated_at": "2026-11-20T10:00:00+00:00"
}
```

Allowed values:

- `status`: `open`, `in_progress`, `resolved`, `closed`
- `priority`: `low`, `normal`, `high`, `urgent`
- `category`: `technical_support`, `account_issues`, `billing`, `general_inquiry`, `other`

## Endpoints

### `GET /tickets`

| Parameter | Type | Default | Notes |
|---|---|---|---|
| `search` | string | | Searches title and description |
| `status` | enum | | Filter |
| `priority` | enum | | Filter |
| `category` | enum | | Filter |
| `orderby` | `created_at` \| `updated_at` \| `title` | `created_at` | |
| `order` | `asc` \| `desc` | `desc` | |
| `page` | integer | 1 | |
| `per_page` | integer | 25 | 1–100. The UI offers 10, 25, 50 and 100 |

### `GET /tickets/stats`

Ticket counts: `{ "data": { "total": 10, "open": 4, "in_progress": 3, "resolved": 2, "closed": 1 } }`

### `POST /tickets`

JSON body. Required: `title` (≤200), `description` (≤10000), `customer_name`
(≤200), `customer_email`. Optional: `status`, `priority`, `category`
(defaults `open`, `normal`, `general_inquiry`), `ai_summary`,
`ai_suggested_reply` (≤5000). Returns `201`.

### `GET /tickets/{id}`

Returns a single ticket.

### `PUT|PATCH /tickets/{id}`

Partial update: send only the fields to change. Same rules as create.

### `DELETE /tickets/{id}`

Moves the ticket to the trash (recoverable in wp-admin). Administrators only.

### `POST /tickets/{id}/ai/analyze`

Asks the configured AI provider (Google Gemini or Azure OpenAI, see
`AI_PROVIDER`) for suggestions. **Nothing is saved.** To accept a
suggestion, the client sends a regular `PATCH /tickets/{id}` (for example
`{"category": "billing", "ai_summary": "..."}`) after the user confirms it.

```json
{
    "data": {
    "ticket_id": 12,
    "suggestions": {
            "category": "account_issues",
            "category_label": "Account Issues",
            "priority": "high",
            "priority_label": "High",
            "summary": "The customer cannot log in after resetting their password.",
            "suggested_reply": "Hello Jane, thank you for contacting us..."
    },
    "applied": false
    }
}
```

### `POST /tickets/{id}/ai/suggest-reply`

Same as above but only returns `suggestions.suggested_reply`.

**AI access, limits and errors.**

- **Only this site's own UI can use the AI.** The AI endpoints accept browser
  sessions (cookie plus `X-WP-Nonce`) from the same origin. Requests from other
  origins, requests marked cross-site by the browser, and application-password
  API clients are refused with `403`. Set `AI_ALLOW_API_CLIENTS=true` to allow
  API clients.
- Each request requires `edit_post` on the ticket.
- Per-user limit: 20 requests per 10 minutes (filters
  `jalasupport_ai_rate_limit`, `jalasupport_ai_rate_window`).
- Site-wide limit: `AI_DAILY_LIMIT` requests per day, default 200 (filter
  `jalasupport_ai_daily_limit`).

| Status | Code | Meaning |
|---|---|---|
| 403 | `jalasupport_ai_forbidden` | Not from this site's UI (cross-origin, cross-site, or an API client) |
| 422 | `jalasupport_ai_content_filtered` | Blocked by the provider's safety filters |
| 429 | `jalasupport_ai_rate_limited` | Per-user limit reached |
| 429 | `jalasupport_ai_daily_limit` | Site-wide daily limit reached |
| 429 / 503 | `jalasupport_ai_busy` | Provider quota exhausted or model overloaded (Gemini first retries once with the fallback model) |
| 502 | `jalasupport_ai_failed` | Network or authentication error, timeout, or another provider error |
| 502 | `jalasupport_ai_invalid_response` | The model output failed validation |
| 503 | `jalasupport_ai_unavailable` | AI is not configured |

**How the AI integration is protected**

- Credentials stay on the server (API keys or a managed identity token) and are
  never sent to the browser. The Gemini key is sent in the `x-goog-api-key`
  header, never in URLs.
- The system prompts are version-controlled in `web/app/plugins/jalasupport/resources/prompts/`.
- Ticket content is sent as a JSON document in the user message, and the system
  prompt tells the model to treat it as data. Instructions inside a ticket cannot
  change the application's behavior: output is limited to a strict JSON schema
  and validated server-side (enums, types, lengths, sanitization).
- The customer email address is never sent to the model.
- Logs contain only ticket IDs, HTTP statuses and error codes. Ticket content,
  prompts and responses are never logged.

## Example

```bash
curl -u admin:"xxxx xxxx xxxx xxxx xxxx xxxx" \
    -H "Content-Type: application/json" \
    -d '{"title":"Cannot log in","description":"Password reset fails","customer_name":"Jane","customer_email":"jane@example.com"}' \
    http://localhost:8080/wp-json/jalasupport/v1/tickets
```
