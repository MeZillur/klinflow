# .github

This directory contains GitHub-specific configuration for the KlinFlow repository.

## Contents

### workflows/ci.yml

A minimal CI workflow with two jobs:

1. **php-check** – Sets up PHP 8.1, validates `composer.json`, and installs dependencies (no-dev). Non-destructive smoke check.

2. **node-build** – Sets up Node 20, installs dependencies with `npm ci`, and runs `npm run build`. Ensures the frontend builds correctly.

## Purpose

This PR adds documentation, editor configuration, CI workflow, and a helper script. It does **not** modify any runtime application code.

## Safe Intent

All changes in this PR are non-invasive housekeeping:
- Documentation (`README.md`, module docs)
- Editor/git config (`.editorconfig`, `.gitattributes`)
- CI workflow for automated checks
- Helper script (`scripts/module-report.sh`)
- Removal of accidental placeholder file (`50MB`)
