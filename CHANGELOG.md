# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-08-26

### Added

- `WorkerHealthPredictor` with sliding-window OLS memory-leak forecasting
- `QueueOverflowPredictor` for queue Time-to-Overflow estimation
- `LinearRegression` support class (slope, intercept, R²)
- Immutable `HealthReport` and `QueueMetrics` DTOs
- Contracts, typed exceptions, PHPStan level 8, PHPUnit 10 suite
- GitHub Actions CI matrix for PHP 8.1–8.4

[Unreleased]: https://github.com/ale94lko/php-queue-prophet/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ale94lko/php-queue-prophet/releases/tag/v0.1.0
