# JalaSupport AI

AI-assisted customer support and ticket management application built on
WordPress and the [Roots](https://roots.io/) ecosystem (Bedrock, Sage, Acorn).

> Status: early development. See [ROADMAP.md](ROADMAP.md) for planned work.

## Stack

- PHP 8.3+, WordPress (managed by Composer via Bedrock)
- MySQL 8
- Sage theme (Blade, Tailwind CSS, Vite) with Acorn
- Azure OpenAI (server-side) for AI-assisted ticket processing

## Repository layout

```text
config/                 Bedrock configuration (application.php + per-environment overrides)
web/                    Web root
  app/                  wp-content equivalent (plugins, themes, mu-plugins, uploads)
  wp/                   WordPress core (installed by Composer, not tracked)
tests/                  Project-level tests (Pest)
docker/                 Docker image configuration (PHP, Apache)
```

## Local development (Docker)

Requirements: Docker Engine with Docker Compose v2.

```bash
cp .env.example .env          # set DB passwords and generate salts (https://roots.io/salts.html)
docker compose up -d          # builds the PHP/Apache image, starts MySQL, installs Composer deps
```

On first start, install WordPress:

```bash
docker compose exec -u www-data app wp core install \
  --url=http://localhost:8080 --title="JalaSupport AI" \
  --admin_user=admin --admin_email=admin@example.com --prompt=admin_password
```

The application is available at <http://localhost:8080> and the admin at
<http://localhost:8080/wp/wp-admin/>.

Useful commands:

| Task | Command |
|---|---|
| Composer | `docker compose exec -u www-data app composer <cmd>` |
| WP-CLI | `docker compose exec -u www-data app wp <cmd>` |
| Logs | `docker compose logs -f app` |
| Stop | `docker compose down` (add `-v` to delete the database volume) |

Services:

- `app` — PHP 8.3 + Apache, document root `web/`, port `${APP_PORT:-8080}`
- `db` — MySQL 8.4 with a persistent `db-data` volume, bound to `127.0.0.1:${DB_PORT:-3306}`

The Docker image layout lives in `docker/`. `www-data` inside the container is
remapped to `HOST_UID`/`HOST_GID` (default 1000) so bind-mounted files stay writable.

## Local setup (without Docker)

```bash
composer install
cp .env.example .env   # set DB_HOST, credentials, WP_HOME and salts
```

Point your web server's document root at `web/`.

## Quality checks

```bash
composer validate
composer lint     # Laravel Pint (PER coding style)
composer test     # Pest
```
