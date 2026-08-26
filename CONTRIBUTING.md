# Contributing to php-queue-prophet

Thanks for your interest in contributing. This library aims to stay **small, zero-dependency, and framework-agnostic**.

## Ways to contribute

- Report bugs and propose features via [GitHub Issues](https://github.com/ale94lko/php-queue-prophet/issues)
- Improve documentation and usage examples
- Add tests for edge cases (flat memory, negative slope, insufficient samples, overflow boundaries)
- Submit pull requests that keep the public API stable unless a major version bump is intended

## Development setup

Requirements:

- PHP **8.1+** (CI covers 8.1–8.4)
- [Composer](https://getcomposer.org/)

```bash
git clone https://github.com/ale94lko/php-queue-prophet.git
cd php-queue-prophet
composer install
```

### Useful scripts

```bash
composer test          # PHPUnit
composer test-coverage # PHPUnit with coverage (requires pcov or xdebug)
composer phpstan       # Static analysis (level 8)
composer cs-check      # Coding style dry-run (PER-CS 2.0)
composer cs-fix        # Apply coding style
composer check         # cs-check + phpstan + test
```

### Coverage driver (optional)

Install [pcov](https://pecl.php.net/package/pcov) (preferred) or Xdebug, then:

```bash
composer test-coverage
```

Target: **≥ 95%** line coverage on `src/`.

## Pull request guidelines

1. Keep changes focused — one concern per PR.
2. Add or update PHPUnit tests for behavioral changes.
3. Ensure `composer check` passes locally.
4. Do **not** introduce runtime dependencies.
5. Keep `declare(strict_types=1);` in every PHP file.
6. Prefer immutable DTOs (`readonly`) and interface-driven design under `PhpQueueProphet\Contracts`.
7. Update `CHANGELOG.md` under `[Unreleased]` when relevant.

### Coding standards

- PSR-12 / **PER Coding Style 2.0**
- PHPStan **level 8**, zero errors
- Meaningful names; avoid clever one-liners that hurt readability

## Commit messages

Use short, imperative subjects focused on *why*:

- `fix remaining-jobs prediction when already over limit`
- `Add Symfony Messenger worker example to README`

## Security

Please do **not** open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
