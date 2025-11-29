# Scripts

Utility scripts for KlinFlow repository management.

## module-report.sh

Scans the `modules/` directory and prints a JSON report for each module.

### Usage

```bash
# Run from repo root
scripts/module-report.sh

# Or specify a custom modules directory
scripts/module-report.sh path/to/modules
```

### Output

Returns a JSON array with one object per module containing:
- `module` – directory name
- `composer_name` – name from module's composer.json (if present)
- `has_migrations`, `has_routes`, `has_src` – boolean flags
- `providers`, `controllers`, `models` – file counts

### Quick Lookups

```bash
# Pretty-print with jq
scripts/module-report.sh | jq .

# Count controllers per module
scripts/module-report.sh | jq '.[] | {module, controllers}'
```
