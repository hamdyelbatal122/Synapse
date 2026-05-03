# Contributing

## Workflow

1. Create a feature branch from `main`.
2. Follow Conventional Commits.
3. Run quality checks before pushing:

```bash
composer install
composer format -- --test
composer test
```

## Commit Style

Use Conventional Commits to enable automated tags and releases.

Examples:

- `feat: add esp32 stream parser`
- `fix: handle empty serial packets`
- `chore: improve ci matrix`
