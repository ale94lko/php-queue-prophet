# Security Policy

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 0.x     | :white_check_mark: |

Security fixes are applied to the latest released `0.x` line.

## Reporting a vulnerability

If you discover a security issue in **php-queue-prophet**, please report it privately.

**Do not** open a public GitHub issue or pull request that discloses exploit details.

Preferred channel:

- Email the maintainer via the address listed on the [GitHub profile](https://github.com/ale94lko), or
- Use [GitHub Security Advisories](https://github.com/ale94lko/php-queue-prophet/security/advisories/new) (private vulnerability reporting) if enabled for this repository

Please include:

1. A description of the issue and its impact
2. Steps to reproduce (minimal script if possible)
3. Affected versions / commit SHA
4. Any suggested fix (optional)

You should receive an acknowledgement within **7 days**. We will coordinate a fix and, when appropriate, a coordinated disclosure and advisory.

## Scope notes

This package is a pure-PHP prediction library with **no network I/O** and **no runtime dependencies**. Typical concerns are limited to:

- Incorrect predictions that could cause workers to run until OOM despite using the API as documented
- Denial-of-service via pathological input sizes (e.g. unbounded caller-supplied windows — the library already bounds its own sliding window)

Misconfiguration of host memory limits, queue brokers, or application code outside this library is out of scope.
