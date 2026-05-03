# Contributing

## Workflow

1. Create a feature branch from `master`.
2. Follow Conventional Commits.
3. Run quality checks before pushing:

```bash
composer install
composer format -- --test
composer analyse
composer test
```

## Commit Style

Use [Conventional Commits](https://www.conventionalcommits.org/) to enable automated tags and releases.

Examples:

- `feat: add esp32 stream parser`
- `fix: handle empty serial packets`
- `chore: improve ci matrix`

## Supported Versions

| Laravel | PHP    | Testbench |
|---------|--------|-----------|
| 11.x    | 8.2+   | ^9.0      |
| 12.x    | 8.2+   | ^9.0      |
| 13.x    | 8.3+   | ^10.0     |
