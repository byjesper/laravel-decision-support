# Laravel Decision Support

> Read-only, DB-backed decision-support guide engine for Laravel (graph-first, resumable evaluator, Mermaid).

## Installation

```bash
composer require byjesper/laravel-decision-support
```

The service provider is auto-discovered. Publish the config if you need to
customise it:

```bash
php artisan vendor:publish --tag=decision-support-config
```

## Usage

```php
// ...
```

## Testing

```bash
composer test
```

This runs lint, static analysis (Larastan level 8), 100% type coverage, and the
unit + integration test suites. Tag database-bound tests with
`->group('integration')` so they only run under `composer test:integration`.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
