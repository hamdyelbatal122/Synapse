# Contributing to PortFlow

Thank you for taking the time to contribute. PortFlow is an open-source Laravel package for hardware serial communication, and every contribution — bug report, feature request, or pull request — helps make it better.

Please read this guide fully before opening issues or submitting pull requests.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Reporting Bugs](#reporting-bugs)
- [Requesting Features](#requesting-features)
- [Development Setup](#development-setup)
- [Branching Strategy](#branching-strategy)
- [Commit Convention](#commit-convention)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Quality Checklist](#quality-checklist)
- [Supported Versions](#supported-versions)
- [License](#license)

---

## Code of Conduct

This project follows a simple rule: **be respectful**. Harassment, abuse, or discriminatory language will not be tolerated in issues, pull requests, or any other communication channel.

---

## Reporting Bugs

Before opening a bug report, please:

1. **Search existing issues** at [`hamdyelbatal122/PortFlow/issues`](https://github.com/hamdyelbatal122/PortFlow/issues) to avoid duplicates.
2. Confirm the bug is reproducible on a **supported version** (see table below).
3. Gather as much detail as possible.

**When opening a bug report, include:**

- PortFlow version (e.g. `v0.6.0`)
- PHP and Laravel versions
- Operating system and serial device type (if relevant)
- Minimal reproduction steps
- Expected vs actual behaviour
- Relevant log output or stack trace

Use the **Bug Report** issue template when available.

---

## Requesting Features

1. **Open an issue first** before writing any code. Describe the use case and why it cannot be achieved today.
2. Label the issue `enhancement`.
3. Wait for a maintainer to acknowledge the issue before investing significant time in implementation. This prevents wasted effort on changes that won't be merged.

---

## Development Setup

### Requirements

- PHP 8.2+
- Composer
- A working Laravel application (or use Orchestra Testbench via the dev dependencies)

### Install

```bash
git clone https://github.com/hamdyelbatal122/PortFlow.git
cd PortFlow
composer install
```

### Available Commands

| Command | Description |
| --- | --- |
| `composer test` | Run the full PHPUnit test suite |
| `composer analyse` | Run PHPStan level 8 static analysis |
| `composer format` | Auto-fix code style via Laravel Pint |
| `composer format -- --test` | Check code style without modifying files |

All four commands must pass before a pull request can be merged.

---

## Branching Strategy

> **Never push directly to `master`.** All changes must go through a pull request.

### Rules

| Branch | Purpose |
| --- | --- |
| `master` | Stable, release-ready code — protected |
| `fix/issue-{id}-short-description` | Bug fixes |
| `feat/issue-{id}-short-description` | New features |
| `chore/short-description` | Non-functional changes (docs, deps, CI) |
| `refactor/short-description` | Internal code improvements without behaviour change |

### Workflow

```
1. Find or open an issue describing the problem or feature
2. Create a branch from master:
   git checkout master && git pull origin master
   git checkout -b fix/issue-42-barcode-delimiter-crash

3. Make your changes (keep commits focused and atomic)

4. Push your branch:
   git push origin fix/issue-42-barcode-delimiter-crash

5. Open a Pull Request against master
```

---

## Commit Convention

PortFlow uses [Conventional Commits](https://www.conventionalcommits.org/) to power automated changelogs and semantic versioning.

### Format

```
<type>(<scope>): <short summary>

[optional body]

[optional footer: Closes #<issue-id>]
```

### Types

| Type | When to use |
| --- | --- |
| `feat` | A new feature |
| `fix` | A bug fix |
| `test` | Adding or correcting tests |
| `refactor` | Code restructuring without behaviour change |
| `docs` | Documentation only changes |
| `chore` | Build process, dependency updates, CI changes |
| `perf` | Performance improvements |

### Referencing Issues

Every commit that addresses an issue **must** reference the issue ID in the footer:

```
fix(barcode-line): handle empty delimiter edge case

Previously, an empty delimiter caused an infinite loop during parseInbound().
The delimiter now falls back to "\n" when configured as an empty string.

Closes #42
```

If a PR addresses multiple issues:

```
Closes #42, Closes #51
```

### Examples

```bash
# Good
feat(rfid-ascii): add configurable STX/ETX delimiter support   # Closes #17
fix(ingest): reject unknown context keys in POST payload        # Closes #31
test(fingerprint): add full packet parsing test suite           # Closes #44
chore(deps): bump phpstan to ^2.1                               # no issue needed

# Bad — missing issue reference, vague message
git commit -m "fixed stuff"
git commit -m "update"
```

---

## Pull Request Guidelines

### Before Submitting

Run the full quality suite and confirm everything passes:

```bash
composer install
composer format -- --test   # code style
composer analyse            # PHPStan level 8
composer test               # PHPUnit
```

All four must exit with code 0.

### PR Description

Your pull request description must include:

1. **What** — A clear summary of what changed and why
2. **How** — A brief explanation of the implementation approach
3. **Issue reference** — `Closes #<id>` or `Related to #<id>`
4. **Testing** — How you tested the change (new tests added? manual testing?)

### Checklist

Before marking a PR ready for review, verify:

- [ ] Branch is based on the latest `master`
- [ ] Branch name follows the naming convention
- [ ] All commits reference an issue ID
- [ ] `composer format -- --test` passes
- [ ] `composer analyse` passes (PHPStan level 8, zero errors)
- [ ] `composer test` passes (all tests green)
- [ ] New behaviour is covered by tests
- [ ] Public API changes are reflected in the README

### Review Process

1. A maintainer will review your PR within a reasonable time.
2. Address all review comments before re-requesting review.
3. PRs that fail CI checks will not be merged until fixed.
4. Once approved, the maintainer will merge and release.

---

## Quality Checklist

All merged code must satisfy:

| Requirement | Tool |
| --- | --- |
| PSR-12 / Laravel code style | `pint` |
| PHPStan level 8, zero errors | `phpstan` |
| All existing tests pass | `phpunit` |
| New code is covered by tests | `phpunit` |
| No security regressions | Manual review + OWASP checklist |

---

## Supported Versions

| Laravel | PHP | Testbench | Status |
| --- | --- | --- | --- |
| 11.x | 8.2+ | ^9.0 | Supported |
| 12.x | 8.2+ | ^9.0 | Supported |
| 13.x | 8.3+ | ^10.0 | Supported |

Only these version combinations receive bug fixes. Issues reported on unsupported versions will be closed.

---

## License

By contributing to PortFlow, you agree that your contributions will be licensed under the [MIT License](LICENSE).

