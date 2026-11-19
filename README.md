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
```

## Local setup (without Docker)

```bash
composer install
cp .env.example .env   # then edit database credentials, WP_HOME and salts
```

Point your web server's document root at `web/`.

## Quality checks

```bash
composer validate
composer lint     # Laravel Pint (PER coding style)
composer test     # Pest
```
