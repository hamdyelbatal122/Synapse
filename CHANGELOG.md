# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

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
