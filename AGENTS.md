# Repository Guidelines

## Project Structure & Module Organization
Application code lives under `app/src` (PSR-4 namespace `App\\`). Configuration is in `app/config`, view templates in `app/views`, and web entrypoints/static files in `public`. Front-end assets live in `resources`, while documentation is in `docs`. Tests are grouped in `tests/Unit` and `tests/Feature` (namespace `Tests\\`). Runtime caches/logs are under `runtime`. Composer dependencies are in `vendor`.

## Build, Test, and Development Commands
- `composer install` installs PHP dependencies into `vendor`.
- `./rr serve` starts the RoadRunner HTTP server (reads `.rr.yaml` and `.env`).
- `php app.php` lists available console commands.
- `php app.php create:controller Foo` scaffolds a controller in `app/src/Endpoint/Web`.
- `composer test` runs PHPUnit for all suites.
- `composer test-coverage` runs tests with coverage output.
- `composer psalm` runs static analysis.
- `composer cs:fix` applies the Spiral code style (php-cs-fixer).

## Coding Style & Naming Conventions
Indentation uses 4 spaces by default, with YAML at 2 spaces (see `.editorconfig`). PHP formatting follows Spiral’s code-style rules via php-cs-fixer (`composer cs:fix`). Keep PSR-4 alignment: classes under `app/src` use the `App\\` namespace, and test classes under `tests` use the `Tests\\` namespace.

## Testing Guidelines
Tests are written with PHPUnit and are split into unit (`tests/Unit`) and feature (`tests/Feature`) suites. Test files should end with `Test.php`. Add or update tests alongside the code you change, and run `composer test` before opening a PR.

## Commit & Pull Request Guidelines
No formal commit convention is defined in this repo. Use short, imperative subjects and add a scope when helpful (example: `auth: handle token refresh`). PRs should include a brief summary, a testing note (commands run), and call out any config or migration changes. If a change affects UI or views, include screenshots.
