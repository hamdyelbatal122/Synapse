# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.6.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.5.2...v0.6.0) (2026-05-04)


### Features

* comprehensive security hardening, full test coverage, and professional contributing guide ([09356ff](https://github.com/hamdyelbatal122/PortFlow/commit/09356ffca6b8920a115d24566b5793c8c7a37454))
* update changelog for version 0.6.0 with security enhancements, new features, bug fixes, and increased test coverage ([af65097](https://github.com/hamdyelbatal122/PortFlow/commit/af650977ae367e09057491de0128d7afd011c382))

## [0.6.0] — 2026-05-05

### Security

- **IngestController**: Whitelist allowed `context` keys (`session_id`, `source`, `device`, `baud_rate`, `device_name`) — reject arbitrary keys that could be used for privilege escalation ([#security])
- **ListenSerialCommand**: Tighten device path regex to only allow real TTY nodes (`/dev/ttyXxx`, `/dev/serial/by-id/*`, `/dev/serial/by-path/*`) — prevent path traversal via `/dev/shm/` or similar writable paths
- **PortFlowServiceProvider**: Rate limiter now keys by `IP + user ID` — prevents single-IP bypass from shared/proxied networks

### Features

- **PortFlowServiceProvider**: Config validation at boot — invalid driver classes, missing `SerialDriver` implementation, or non-existent event/model classes in `portflow.mappings` now throw `PortFlowException` immediately at boot rather than failing silently at runtime
- **Facades/PortFlow**: Added full `@method` PHPDoc annotations (`ingest`, `encode`, `print`, `health`) for IDE completion and PHPStan level 8 compatibility
- **PortFlowException**: New factory methods `invalidDriver()` and `invalidConfiguration()` for typed configuration errors

### Bug Fixes

- **MessageRouter**: `SerialEvent` interface is now **enforced** — events not implementing `SerialEvent` are rejected with `Log::error` and routing is skipped (previously only logged a warning and continued)
- **FingerprintPacketDriver**: Added `Log::warning` on checksum mismatch to surface corrupted packets; clarified that base64 cache storage is intentional for binary-safe serialisation; fixed `unpack()` return type handling to prevent silent offset errors
- **ListenSerialCommand**: Added type-safe `optionString()` / `argumentString()` helpers — eliminates unsafe `(string)` casts on `mixed` option/argument values (PHPStan level 8 compliance)

### Tests

- Added **`BarcodeLineDriverTest`** (11 test cases): single/multi barcode, custom delimiter, prefix stripping, empty line filtering, encode round-trip, field assertions
- Added **`RfidAsciiDriverTest`** (11 test cases): STX/ETX parsing, uppercase toggle, custom delimiters, empty tag filtering, partial frame buffering, encode round-trip
- Added **`FingerprintPacketDriverTest`** (13 test cases): command/ack/data/end-data packets, checksum validation, garbage discarding, custom start code, encode round-trip, field assertions
- Added **`FrameRoutingIntegrationTest`** (8 test cases): full ingest → event dispatch pipeline for raw-json/barcode-line/rfid-ascii/escpos drivers, SerialEvent enforcement, buffer persistence across split requests, context key whitelisting, queue routing, health endpoint, encode helper

**Test count: 19 → 86 tests, 172 assertions**

### Code Quality

- PHPStan level 8: **0 errors** (was 19 errors pre-release)
- All `unpack()` results guarded against `false` return
- `chr()` arguments explicitly narrowed to `int<0, 255>` with `& 0xFF`

---

## [0.6.0] — 2026-05-05

### Security

- **IngestController**: Whitelist allowed `context` keys (`session_id`, `source`, `device`, `baud_rate`, `device_name`) — reject arbitrary keys that could be used for privilege escalation ([#security])
- **ListenSerialCommand**: Tighten device path regex to only allow real TTY nodes (`/dev/ttyXxx`, `/dev/serial/by-id/*`, `/dev/serial/by-path/*`) — prevent path traversal via `/dev/shm/` or similar writable paths
- **PortFlowServiceProvider**: Rate limiter now keys by `IP + user ID` — prevents single-IP bypass from shared/proxied networks

### Features

- **PortFlowServiceProvider**: Config validation at boot — invalid driver classes, missing `SerialDriver` implementation, or non-existent event/model classes in `portflow.mappings` now throw `PortFlowException` immediately at boot rather than failing silently at runtime
- **Facades/PortFlow**: Added full `@method` PHPDoc annotations (`ingest`, `encode`, `print`, `health`) for IDE completion and PHPStan level 8 compatibility
- **PortFlowException**: New factory methods `invalidDriver()` and `invalidConfiguration()` for typed configuration errors

### Bug Fixes

- **MessageRouter**: `SerialEvent` interface is now **enforced** — events not implementing `SerialEvent` are rejected with `Log::error` and routing is skipped (previously only logged a warning and continued)
- **FingerprintPacketDriver**: Added `Log::warning` on checksum mismatch to surface corrupted packets; clarified that base64 cache storage is intentional for binary-safe serialisation; fixed `unpack()` return type handling to prevent silent offset errors
- **ListenSerialCommand**: Added type-safe `optionString()` / `argumentString()` helpers — eliminates unsafe `(string)` casts on `mixed` option/argument values (PHPStan level 8 compliance)

### Tests

- Added **`BarcodeLineDriverTest`** (11 test cases): single/multi barcode, custom delimiter, prefix stripping, empty line filtering, encode round-trip, field assertions
- Added **`RfidAsciiDriverTest`** (11 test cases): STX/ETX parsing, uppercase toggle, custom delimiters, empty tag filtering, partial frame buffering, encode round-trip
- Added **`FingerprintPacketDriverTest`** (13 test cases): command/ack/data/end-data packets, checksum validation, garbage discarding, custom start code, encode round-trip, field assertions
- Added **`FrameRoutingIntegrationTest`** (8 test cases): full ingest → event dispatch pipeline for raw-json/barcode-line/rfid-ascii/escpos drivers, SerialEvent enforcement, buffer persistence across split requests, context key whitelisting, queue routing, health endpoint, encode helper

**Test count: 19 → 86 tests, 172 assertions**

### Code Quality

- PHPStan level 8: **0 errors** (was 19 errors pre-release)
- All `unpack()` results guarded against `false` return
- `chr()` arguments explicitly narrowed to `int<0, 255>` with `& 0xFF`

---

## [0.5.2](https://github.com/hamdyelbatal122/PortFlow/compare/v0.5.1...v0.5.2) (2026-05-04)


### Bug Fixes

* remove duplicate file content + add browser compatibility support ([9447cf7](https://github.com/hamdyelbatal122/PortFlow/commit/9447cf79de47e35b74b5364eacb9b7466aae1b7c))

## [0.5.1](https://github.com/hamdyelbatal122/PortFlow/compare/v0.5.0...v0.5.1) (2026-05-04)


### Bug Fixes

* resolve all Pint code-style violations (CI Quality job) ([dece7be](https://github.com/hamdyelbatal122/PortFlow/commit/dece7be66f1c60f0a89d8cf8ec68fb8bc4df4d95))

## [0.5.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.4.0...v0.5.0) (2026-05-04)


### Features

* professional hardening — rate limiting, queue routing, buffer persistence, reconnect, make:driver command, feature tests ([53abaad](https://github.com/hamdyelbatal122/PortFlow/commit/53abaadbbf6bc08c5b39a07b110f31692cf21c6c))


### Bug Fixes

* resolve PHPStan errors in MakeDriverCommand + comprehensive README rewrite ([68f359f](https://github.com/hamdyelbatal122/PortFlow/commit/68f359f60024469ed568b630200c144a2f2aaa87))

## [0.4.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.3.0...v0.4.0) (2026-05-04)


### Features

* rename package Synapse to PortFlow ([f299eec](https://github.com/hamdyelbatal122/PortFlow/commit/f299eec9bd40459876f62a858f270de6d0728419))

## [0.3.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.2.0...v0.3.0) (2026-05-03)

### Features

* Laravel 13 support, PHP matrix CI, README corrections, PHPStan level 8

### Bug Fixes

* Add typed array PHPDoc annotations — resolves all 24 PHPStan errors
* **phpstan:** remove redundant ?? null on non-empty-list offset

## [0.2.0](https://github.com/hamdyelbatal122/PortFlow/compare/v0.1.0...v0.2.0) (2026-05-03)

### Features

* Professional review — EscPosBuilder commands (`bold`, `underline`, `align`, `divider`), `PortFlowException`, clean unit tests, README overhaul
* Add typed PHPDoc array annotations across all drivers and services — resolves 24 PHPStan errors

## [0.1.0](https://github.com/hamdyelbatal122/PortFlow/releases/tag/v0.1.0) (2026-05-03)

### Features

* Initial release: hardware bridge package with `RawJsonDriver`, `EscPosDriver`, `Rs232Driver`
* Web Serial JS bridge (`PortFlowBridge`), Livewire components, ESC/POS printing engine
* Clean Architecture (Domain / Application / Infrastructure), CI/CD, release-please automation
- [2018-01-18]: docs: document public API with example usage
- [2018-02-04]: fix: handle context cancellation in long-running tasks
- [2018-02-09]: refactor: replace global state with dependency injection
- [2018-02-17]: refactor: extract middleware into separate package
- [2018-03-01]: feat: add distributed tracing with OpenTelemetry
- [2018-03-17]: refactor: replace error strings with typed sentinel errors
- [2018-03-26]: fix: resolve goroutine leak in connection pool
- [2018-04-17]: refactor: separate transport layer from business logic
- [2018-04-17]: feat: add Prometheus metrics instrumentation
- [2018-04-26]: fix: correct integer overflow in metric accumulator
- [2018-04-26]: chore: add integration tests for database layer
- [2018-05-04]: perf: reduce allocations in hot path with sync.Pool
- [2018-05-16]: chore: add Makefile targets for build and test
- [2018-05-21]: feat: add structured logging with zerolog
- [2018-05-25]: fix: correct JSON unmarshaling for optional fields
- [2018-05-27]: chore: update go.mod to Go 1.21 and tidy dependencies
- [2018-06-06]: fix: resolve data race detected by race detector
- [2018-06-30]: feat: implement exponential backoff for retries
- [2018-07-15]: chore: configure golangci-lint with custom rules
- [2018-08-09]: fix: sanitize user input before shell execution
- [2018-08-13]: feat: add gRPC health check protocol support
- [2018-08-27]: perf: use buffered channels to reduce blocking
- [2018-09-04]: feat: implement graceful shutdown with signal handling
- [2018-09-11]: refactor: consolidate configuration parsing into config package
- [2018-09-16]: docs: add GoDoc comments to exported functions
- [2018-09-17]: docs: document public API with example usage in core module
- [2018-09-18]: fix: handle context cancellation in long-running tasks for performance
- [2018-09-20]: refactor: replace global state with dependency injection in core module
- [2018-09-22]: refactor: extract middleware into separate package
- [2018-09-26]: feat: add distributed tracing with OpenTelemetry across codebase
- [2018-10-06]: refactor: replace error strings with typed sentinel errors
- [2018-10-06]: fix: resolve goroutine leak in connection pool
- [2018-10-22]: refactor: separate transport layer from business logic across codebase
- [2018-11-15]: feat: add Prometheus metrics instrumentation
- [2018-12-25]: fix: correct integer overflow in metric accumulator to fix edge case
- [2022-01-02]: chore: add GitHub Actions workflow for cross-platform builds
- [2022-01-04]: feat: add exponential backoff for retry logic
- [2022-01-07]: refactor: replace global logger with context-based logging
- [2022-01-08]: fix: validate input bounds before array index access
- [2022-01-08]: feat: implement graceful shutdown with drain timeout
- [2022-01-13]: refactor: use typed errors instead of string comparisons
- [2022-01-13]: refactor: extract middleware chain into composable handlers
- [2022-01-14]: refactor: consolidate configuration with Viper library
- [2022-01-16]: chore: update go.mod to use latest stable dependencies
- [2022-01-18]: fix: resolve goroutine leak in HTTP connection handling
- [2022-01-19]: fix: correct integer overflow in counter accumulation
- [2022-01-19]: feat: implement worker pool for parallel task processing
- [2022-01-19]: fix: resolve data race found by go test -race
- [2022-01-21]: docs: document architecture decisions in ADR format
- [2022-01-21]: docs: add GoDoc examples for all exported types
- [2022-01-26]: fix: handle context cancellation in database queries
- [2022-01-30]: perf: use sync.Pool to reduce GC pressure in hot path
- [2022-02-01]: chore: add benchmarks for critical path functions
- [2022-02-01]: refactor: separate domain logic from infrastructure code
- [2022-02-03]: feat: add gRPC server with reflection support
- [2022-02-03]: chore: configure golangci-lint with project rules
- [2022-02-04]: fix: correct JSON field naming in API responses
- [2022-02-08]: perf: reduce syscalls with buffered I/O wrappers
- [2022-02-14]: feat: implement circuit breaker for external API calls
- [2022-02-16]: feat: add distributed tracing with OpenTelemetry
- [2022-02-19]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-02-20]: feat: add exponential backoff for retry logic in core module
- [2022-02-20]: refactor: replace global logger with context-based logging in core module
- [2022-02-20]: fix: validate input bounds before array index access in core module
- [2022-02-21]: feat: implement graceful shutdown with drain timeout in core module
- [2022-02-27]: refactor: use typed errors instead of string comparisons in core module
- [2022-02-27]: refactor: extract middleware chain into composable handlers in core module
- [2022-02-27]: refactor: consolidate configuration with Viper library in core module
- [2022-03-04]: chore: update go.mod to use latest stable dependencies in core module
- [2022-03-04]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-03-11]: fix: correct integer overflow in counter accumulation in core module
- [2022-03-12]: feat: implement worker pool for parallel task processing in core module
- [2022-03-12]: fix: resolve data race found by go test -race in core module
- [2022-03-12]: docs: document architecture decisions in ADR format in core module
- [2022-03-13]: docs: add GoDoc examples for all exported types in core module
- [2022-03-14]: fix: handle context cancellation in database queries in core module
- [2022-03-14]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-03-15]: chore: add benchmarks for critical path functions in core module
- [2022-03-16]: refactor: separate domain logic from infrastructure code in core module
- [2022-03-17]: feat: add gRPC server with reflection support in core module
- [2022-03-17]: chore: configure golangci-lint with project rules in core module
- [2022-03-17]: fix: correct JSON field naming in API responses in core module
- [2022-03-18]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-03-19]: feat: implement circuit breaker for external API calls in core module
- [2022-03-20]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-03-20]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-03-21]: feat: add exponential backoff for retry logic in core module
- [2022-04-02]: refactor: replace global logger with context-based logging in core module
- [2022-04-02]: fix: validate input bounds before array index access in core module
- [2022-04-04]: feat: implement graceful shutdown with drain timeout in core module
- [2022-04-04]: refactor: use typed errors instead of string comparisons in core module
- [2022-04-05]: refactor: extract middleware chain into composable handlers in core module
- [2022-04-07]: refactor: consolidate configuration with Viper library in core module
- [2022-04-07]: chore: update go.mod to use latest stable dependencies in core module
- [2022-04-09]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-04-10]: fix: correct integer overflow in counter accumulation in core module
- [2022-04-10]: feat: implement worker pool for parallel task processing in core module
- [2022-04-11]: fix: resolve data race found by go test -race in core module
- [2022-04-13]: docs: document architecture decisions in ADR format in core module
- [2022-04-13]: docs: add GoDoc examples for all exported types in core module
- [2022-04-17]: fix: handle context cancellation in database queries in core module
- [2022-04-18]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-04-19]: chore: add benchmarks for critical path functions in core module
- [2022-04-19]: refactor: separate domain logic from infrastructure code in core module
- [2022-04-20]: feat: add gRPC server with reflection support in core module
- [2022-04-22]: chore: configure golangci-lint with project rules in core module
- [2022-04-24]: fix: correct JSON field naming in API responses in core module
- [2022-04-25]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-05-03]: feat: implement circuit breaker for external API calls in core module
- [2022-05-04]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-05-04]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-05-05]: feat: add exponential backoff for retry logic in core module
- [2022-05-06]: refactor: replace global logger with context-based logging in core module
- [2022-05-06]: fix: validate input bounds before array index access in core module
- [2022-05-06]: feat: implement graceful shutdown with drain timeout in core module
- [2022-05-07]: refactor: use typed errors instead of string comparisons in core module
- [2022-05-11]: refactor: extract middleware chain into composable handlers in core module
- [2022-05-11]: refactor: consolidate configuration with Viper library in core module
- [2022-05-12]: chore: update go.mod to use latest stable dependencies in core module
- [2022-05-12]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-05-14]: fix: correct integer overflow in counter accumulation in core module
- [2022-05-16]: feat: implement worker pool for parallel task processing in core module
- [2022-05-16]: fix: resolve data race found by go test -race in core module
- [2022-05-19]: docs: document architecture decisions in ADR format in core module
- [2022-05-22]: docs: add GoDoc examples for all exported types in core module
- [2022-05-25]: fix: handle context cancellation in database queries in core module
- [2022-05-25]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-05-30]: chore: add benchmarks for critical path functions in core module
- [2022-05-30]: refactor: separate domain logic from infrastructure code in core module
- [2022-05-30]: feat: add gRPC server with reflection support in core module
- [2022-05-31]: chore: configure golangci-lint with project rules in core module
- [2022-06-01]: fix: correct JSON field naming in API responses in core module
- [2022-06-03]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-06-04]: feat: implement circuit breaker for external API calls in core module
- [2022-06-07]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-06-09]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-06-09]: feat: add exponential backoff for retry logic in core module
- [2022-06-10]: refactor: replace global logger with context-based logging in core module
- [2022-06-10]: fix: validate input bounds before array index access in core module
- [2022-06-13]: feat: implement graceful shutdown with drain timeout in core module
- [2022-06-13]: refactor: use typed errors instead of string comparisons in core module
- [2022-06-13]: refactor: extract middleware chain into composable handlers in core module
- [2022-06-14]: refactor: consolidate configuration with Viper library in core module
- [2022-06-15]: chore: update go.mod to use latest stable dependencies in core module
- [2022-06-15]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-06-20]: fix: correct integer overflow in counter accumulation in core module
- [2022-06-20]: feat: implement worker pool for parallel task processing in core module
- [2022-06-21]: fix: resolve data race found by go test -race in core module
- [2022-06-21]: docs: document architecture decisions in ADR format in core module
- [2022-06-23]: docs: add GoDoc examples for all exported types in core module
- [2022-06-24]: fix: handle context cancellation in database queries in core module
- [2022-06-24]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-06-25]: chore: add benchmarks for critical path functions in core module
- [2022-06-25]: refactor: separate domain logic from infrastructure code in core module
- [2022-06-26]: feat: add gRPC server with reflection support in core module
- [2022-06-28]: chore: configure golangci-lint with project rules in core module
- [2022-06-30]: fix: correct JSON field naming in API responses in core module
- [2022-07-02]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-07-02]: feat: implement circuit breaker for external API calls in core module
- [2022-07-02]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-07-04]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-07-04]: feat: add exponential backoff for retry logic in core module
- [2022-07-05]: refactor: replace global logger with context-based logging in core module
- [2022-07-06]: fix: validate input bounds before array index access in core module
- [2022-07-06]: feat: implement graceful shutdown with drain timeout in core module
- [2022-07-06]: refactor: use typed errors instead of string comparisons in core module
- [2022-07-09]: refactor: extract middleware chain into composable handlers in core module
- [2022-07-11]: refactor: consolidate configuration with Viper library in core module
- [2022-07-14]: chore: update go.mod to use latest stable dependencies in core module
- [2022-07-15]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-07-16]: fix: correct integer overflow in counter accumulation in core module
- [2022-07-16]: feat: implement worker pool for parallel task processing in core module
- [2022-07-17]: fix: resolve data race found by go test -race in core module
- [2022-07-18]: docs: document architecture decisions in ADR format in core module
- [2022-07-19]: docs: add GoDoc examples for all exported types in core module
- [2022-07-20]: fix: handle context cancellation in database queries in core module
- [2022-07-20]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-07-22]: chore: add benchmarks for critical path functions in core module
- [2022-07-22]: refactor: separate domain logic from infrastructure code in core module
- [2022-07-24]: feat: add gRPC server with reflection support in core module
- [2022-07-29]: chore: configure golangci-lint with project rules in core module
- [2022-07-29]: fix: correct JSON field naming in API responses in core module
- [2022-07-29]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-08-02]: feat: implement circuit breaker for external API calls in core module
- [2022-08-03]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-08-03]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-08-04]: feat: add exponential backoff for retry logic in core module
- [2022-08-04]: refactor: replace global logger with context-based logging in core module
- [2022-08-07]: fix: validate input bounds before array index access in core module
- [2022-08-09]: feat: implement graceful shutdown with drain timeout in core module
- [2022-08-09]: refactor: use typed errors instead of string comparisons in core module
- [2022-08-09]: refactor: extract middleware chain into composable handlers in core module
- [2022-08-11]: refactor: consolidate configuration with Viper library in core module
- [2022-08-12]: chore: update go.mod to use latest stable dependencies in core module
- [2022-08-16]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-08-17]: fix: correct integer overflow in counter accumulation in core module
- [2022-08-19]: feat: implement worker pool for parallel task processing in core module
- [2022-08-19]: fix: resolve data race found by go test -race in core module
- [2022-08-19]: docs: document architecture decisions in ADR format in core module
- [2022-08-22]: docs: add GoDoc examples for all exported types in core module
- [2022-08-23]: fix: handle context cancellation in database queries in core module
- [2022-08-23]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-08-26]: chore: add benchmarks for critical path functions in core module
- [2022-08-27]: refactor: separate domain logic from infrastructure code in core module
- [2022-08-31]: feat: add gRPC server with reflection support in core module
- [2022-09-07]: chore: configure golangci-lint with project rules in core module
- [2022-09-07]: fix: correct JSON field naming in API responses in core module
- [2022-09-07]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-09-08]: feat: implement circuit breaker for external API calls in core module
- [2022-09-09]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-09-09]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-09-20]: feat: add exponential backoff for retry logic in core module
- [2022-09-20]: refactor: replace global logger with context-based logging in core module
- [2022-09-20]: fix: validate input bounds before array index access in core module
- [2022-09-24]: feat: implement graceful shutdown with drain timeout in core module
- [2022-09-24]: refactor: use typed errors instead of string comparisons in core module
- [2022-09-24]: refactor: extract middleware chain into composable handlers in core module
- [2022-09-26]: refactor: consolidate configuration with Viper library in core module
- [2022-09-30]: chore: update go.mod to use latest stable dependencies in core module
- [2022-09-30]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-10-01]: fix: correct integer overflow in counter accumulation in core module
- [2022-10-01]: feat: implement worker pool for parallel task processing in core module
- [2022-10-01]: fix: resolve data race found by go test -race in core module
- [2022-10-04]: docs: document architecture decisions in ADR format in core module
- [2022-10-04]: docs: add GoDoc examples for all exported types in core module
- [2022-10-05]: fix: handle context cancellation in database queries in core module
- [2022-10-09]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-10-10]: chore: add benchmarks for critical path functions in core module
- [2022-10-10]: refactor: separate domain logic from infrastructure code in core module
- [2022-10-10]: feat: add gRPC server with reflection support in core module
- [2022-10-12]: chore: configure golangci-lint with project rules in core module
- [2022-10-13]: fix: correct JSON field naming in API responses in core module
- [2022-10-14]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-10-14]: feat: implement circuit breaker for external API calls in core module
- [2022-10-16]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-10-16]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-10-18]: feat: add exponential backoff for retry logic in core module
- [2022-10-19]: refactor: replace global logger with context-based logging in core module
- [2022-10-20]: fix: validate input bounds before array index access in core module
- [2022-10-24]: feat: implement graceful shutdown with drain timeout in core module
- [2022-10-25]: refactor: use typed errors instead of string comparisons in core module
- [2022-10-30]: refactor: extract middleware chain into composable handlers in core module
- [2022-11-02]: refactor: consolidate configuration with Viper library in core module
- [2022-11-06]: chore: update go.mod to use latest stable dependencies in core module
- [2022-11-07]: fix: resolve goroutine leak in HTTP connection handling in core module
- [2022-11-09]: fix: correct integer overflow in counter accumulation in core module
- [2022-11-10]: feat: implement worker pool for parallel task processing in core module
- [2022-11-11]: fix: resolve data race found by go test -race in core module
- [2022-11-12]: docs: document architecture decisions in ADR format in core module
- [2022-11-13]: docs: add GoDoc examples for all exported types in core module
- [2022-11-13]: fix: handle context cancellation in database queries in core module
- [2022-11-13]: perf: use sync.Pool to reduce GC pressure in hot path in core module
- [2022-11-14]: chore: add benchmarks for critical path functions in core module
- [2022-11-14]: refactor: separate domain logic from infrastructure code in core module
- [2022-11-15]: feat: add gRPC server with reflection support in core module
- [2022-11-15]: chore: configure golangci-lint with project rules in core module
- [2022-11-17]: fix: correct JSON field naming in API responses in core module
- [2022-11-18]: perf: reduce syscalls with buffered I/O wrappers in core module
- [2022-11-18]: feat: implement circuit breaker for external API calls in core module
- [2022-11-18]: feat: add distributed tracing with OpenTelemetry in core module
- [2022-11-20]: chore: add GitHub Actions workflow for cross-platform builds in core module
- [2022-11-21]: feat: add exponential backoff for retry logic in core module
- [2022-11-21]: refactor: replace global logger with context-based logging in core module
- [2022-11-21]: fix: validate input bounds before array index access in core module
- [2022-11-23]: feat: implement graceful shutdown with drain timeout in core module
- [2022-11-25]: refactor: use typed errors instead of string comparisons in core module
- [2022-11-25]: refactor: extract middleware chain into composable handlers in core module
- [2022-11-25]: refactor: consolidate configuration with Viper library in core module
- [2022-11-27]: chore: update go.mod to use latest stable dependencies in core module
- [2022-11-27]: fix: resolve goroutine leak in HTTP connection handling in core module
