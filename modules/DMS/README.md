# DMS – Distribution Management System

Module documentation for the KlinFlow DMS module.

## Manifest

| Field       | Value                                                              |
|-------------|--------------------------------------------------------------------|
| Slug        | `dms`                                                              |
| Name        | Distribution Management System                                     |
| Namespace   | `Modules\DMS`                                                      |
| Version     | 1.0.0                                                              |

## Permissions

```
dms.view
dms.manage.suppliers
dms.manage.purchases
dms.manage.sales
dms.manage.inventory
dms.manage.accounts
dms.manage.reporting
```

## File Locations

| Component   | Path                             |
|-------------|----------------------------------|
| Routes      | `modules/DMS/routes.php`         |
| Controllers | `modules/DMS/src/Controllers`    |
| Services    | `modules/DMS/src/Services`       |
| Migrations  | _none found_                     |

## Module Report

Run the helper script from the repository root to get a JSON summary:

```bash
scripts/module-report.sh
```
