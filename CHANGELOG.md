# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
