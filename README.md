# klinflow

KlinFlow — modular multi-tenant PHP application.

## Short description

This repository contains the KlinFlow project (PHP backend + SolidJS UI). Modules live under `modules/` and the code uses PSR-4 autoloading via Composer.

## Quick start

- Install PHP dependencies:

  ```bash
  composer install
  ```

- Copy environment file:

  ```bash
  cp .env.example .env
  ```

- Install frontend dependencies and build (optional for development):

  ```bash
  npm ci
  npm run build
  ```

- Serve (for quick local testing):

  ```bash
  php -S localhost:8000 -t public
  ```

## Modules

- Modules are located in `modules/`.
- Each module contains a `manifest.php` and `module.php` and typically a `routes.php` and `src/` directory.

## Notes

- This PR is a non-invasive cleanup: documentation, CI workflow, editor config, and a helper script. It does not change runtime code.
