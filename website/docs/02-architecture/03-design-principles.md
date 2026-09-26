# Design Principles

Vayu follows a set of intentional design choices that keep the framework lightweight and predictable.

## No Magic Autoloading

All core files are loaded explicitly via `glob()` in `config/bootstrap.php`. There is no PSR-4 autoloader or namespace resolution — every class in `core/` is available globally after bootstrap.

## Controllers Are Thin

Controllers should delegate heavy logic to services or helpers. Their job is to receive a request, call the right logic, and return a response.

## Views Are Dumb

View files receive extracted variables and render HTML. They contain no business logic — only presentation with `<?= $variable ?>` and component calls.

## Components Are Isolated

Each component renders inside its own closure scope via output buffering. Variables from one component cannot leak into another.

## Environment-Driven Config

No hardcoded values in logic. All configuration comes from `.env` via the `env()` helper with sensible defaults.

## SQL Uses Prepared Statements

All database helpers use PDO prepared statements by default — injection-safe without extra effort.

## Two Separate Controller Hierarchies

- **Frontend controllers** extend `BaseController` — render views, merge data, make external API calls
- **API controllers** extend `ApiController` — parse request bodies, validate input, return JSON

This separation keeps each controller type focused and avoids mixing concerns.
