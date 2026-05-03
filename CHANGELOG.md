# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.3.0](https://github.com/hamdyelbatal122/Synapse/compare/v0.2.0...v0.3.0) (2026-05-03)


### Features

* Laravel 13 support, PHP matrix CI, README corrections, PHPStan level 8 ([bdc8732](https://github.com/hamdyelbatal122/Synapse/commit/bdc8732b96561afe0da7832dec97d856706379bb))


### Bug Fixes

* add typed array PHPDoc annotations — resolves all 24 PHPStan errors ([1887064](https://github.com/hamdyelbatal122/Synapse/commit/1887064977f81eaaa01d039ad35d469d09712a0d))
* **phpstan:** remove redundant ?? null on non-empty-list offset ([bb52890](https://github.com/hamdyelbatal122/Synapse/commit/bb5289077996f3c096747be08379fc4e8f80daa5))

## [0.2.0](https://github.com/hamdyelbatal122/Synapse/compare/v0.1.0...v0.2.0) (2026-05-03)

### Features

* Professional review — EscPosBuilder commands (`bold`, `underline`, `align`, `divider`), `SynapseException`, clean unit tests, README overhaul
* Add typed PHPDoc array annotations across all drivers and services — resolves 24 PHPStan errors

## [0.1.0](https://github.com/hamdyelbatal122/Synapse/releases/tag/v0.1.0) (2026-05-03)

### Features

* Initial release: hardware bridge package with `RawJsonDriver`, `EscPosDriver`, `Rs232Driver`
* Web Serial JS bridge (`SynapseBridge`), Livewire components, ESC/POS printing engine
* Clean Architecture (Domain / Application / Infrastructure), CI/CD, release-please automation
