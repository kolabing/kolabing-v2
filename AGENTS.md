# Repository Guidelines

## Project Structure & Module Organization
This repository is a Laravel 12 application with a small Vite frontend. Core backend code lives in `app/`, with HTTP entry points in `app/Http`, domain logic in `app/Services`, and Eloquent models in `app/Models`. API and web routes are defined in [routes/api.php](/Users/volkanoluc/Projects/kolabing-v2/routes/api.php) and [routes/web.php](/Users/volkanoluc/Projects/kolabing-v2/routes/web.php). Frontend assets live in `resources/js`, `resources/css`, and Blade templates in `resources/views`. Database migrations, factories, and seeders are under `database/`. Tests are split into `tests/Feature` and `tests/Unit`, with most API coverage in `tests/Feature/Api/V1`.

## Build, Test, and Development Commands
Use Composer and npm from the repo root.

- `composer setup` installs PHP and Node dependencies, creates `.env`, generates the app key, runs migrations, and builds assets.
- `composer dev` starts the local stack: Laravel server, queue worker, log tailing, and Vite.
- `composer test` clears config and runs the full Laravel test suite.
- `npm run dev` starts Vite only.
- `npm run build` creates the production frontend bundle.
- `./vendor/bin/pint` formats PHP code to the project standard.

## Coding Style & Naming Conventions
Follow `.editorconfig`: UTF-8, LF line endings, spaces for indentation, and 4-space indents except YAML, which uses 2. Use PSR-4 namespaces that mirror directory structure, for example `App\Services\NotificationService`. Prefer singular PascalCase for models and services, and keep controllers, requests, and tests descriptive, such as `PushNotificationTest.php`. Keep business rules in services rather than controllers or database triggers.

## Testing Guidelines
Write feature tests for endpoint behavior and unit tests for isolated domain logic. Place API tests in `tests/Feature/Api/V1/*Test.php` and unit tests in `tests/Unit/**/*Test.php`. Name tests after the behavior or resource they cover, for example `GamificationWalletTest.php`. Run `composer test` before opening a PR, and add or update tests for every bug fix or endpoint change.

NEVER use Laravel's `RefreshDatabase` trait in this repository. It is explicitly forbidden because it wipes the database. Do not add it to new tests, and replace it with safer patterns if you encounter it in proposed changes.

## Commit & Pull Request Guidelines
Recent history uses Conventional Commit prefixes like `feat:`, `test:`, and `chore:`. Keep commits focused and written in the imperative, for example `feat: add generic upload endpoint`. PRs should include a short summary, linked issue or task, testing notes, and sample request/response payloads or screenshots when UI or API output changes.

## Security & Configuration Tips
Do not commit real secrets from `.env`, Firebase, Stripe, or Apple IAP credentials. Start from `.env.example` or `.env.testing`, and prefer configuration changes through `config/` files and environment variables.
