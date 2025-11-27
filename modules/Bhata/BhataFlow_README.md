# 🧱 BhataFlow — Smart Brick Field Management Module

## Overview
**BhataFlow** is a production management and accounting module designed for **brick kilns in Bangladesh**.  
It supports both **traditional (manual)** and **standardized business (semi-mechanized)** brickfield systems.

The goal is to digitalize every step — from soil preparation to finished brick sale — while remaining simple for local operators and scalable for modern kiln owners.

---

## 🌍 Supported Operations

| Operation | Typical (Manual) | Standard (Business / Mechanized) |
|------------|------------------|-----------------------------------|
| Soil Preparation | Manual soil collection, clay stacking, mixing | Excavator & mechanical mixing |
| Moulding | Hand moulding (local workers) | Extruder machine with wire cutter |
| Drying | Open-field sun drying | Controlled shed or tunnel drying |
| Firing | Fixed Chimney / Zigzag | HHK / Tunnel continuous kiln |
| Sorting | Manual | Semi-mechanized sorting + barcode tagging |
| Stock & Sale | Counted manually | Digital batch tagging & POS integration |
| Payroll | Daily cash wage | Worker ID, attendance & payroll automation |

---

## ⚙️ Core Features

### 1. Production Chain
Track every batch through key stages:
```
Raw Soil → Moulding → Drying → Firing → Sorting → Sale
```
Each batch has:
- Batch Code
- Season (Year)
- Supervisor / Section In-Charge
- Quantity (Green, Dry, Burnt, Broken)
- Fuel Usage (Coal, Sawdust, etc.)
- Firing start & end dates

### 2. Worker Management
- Daily/weekly wage tracking  
- Worker groups (Moulder, Loader, Fireman, Sorter, etc.)  
- Attendance via register or mobile entry  
- Automatic payroll calculation  
- Loan & advance tracking  

### 3. Inventory & Fuel Management
- Coal, clay, sand, and water input tracking  
- Vendor/supplier register  
- Cost per batch or per 1000 bricks  
- Fuel efficiency reports  

### 4. Sales & Accounts
- Integrated with KlinFlow POS  
- Invoice, delivery challan, and cash ledger  
- Credit sale & collection reports  
- Price by class: 1st, 2nd, 3rd, bats  
- Auto-summary: Production vs Sale vs Balance  

### 5. Reports
- Production by batch / season  
- Fuel cost per 1000 bricks  
- Worker payroll & efficiency  
- Profit & loss summary  
- Stock movement & wastage  
- Brick class distribution  

---

## 🧠 Technical Concept (Developer Notes)
**Module key:** `bhata`  
**Base URL:** `/t/{slug}/apps/bhata`  
**Namespace:** `Modules\Bhata`  
**Database Engine:** InnoDB (multi-tenant, org_id scoped)

### Core Tables (simplified)
| Table | Description |
|--------|--------------|
| `bhata_batches` | Each production batch with lifecycle stages |
| `bhata_workers` | Worker registry and daily logs |
| `bhata_fuel_usage` | Fuel and material consumption |
| `bhata_sales` | Finished brick sales linked to POS |
| `bhata_reports` | Aggregated analytics and season stats |

All tables include:
```
id, org_id, created_by, created_at, updated_at
```

---

## 🧩 Integration with Other Modules
| Module | Integration |
|--------|--------------|
| POS | Direct brick sale, stock deduction |
| DMS | Supplier & transporter management |
| Accounts | Ledger entries, expense grouping |
| Payroll | Worker payments auto-synced |
| Reports | Aggregates from all kiln branches |

---

## 🔄 Typical Workflow Examples

### Typical Bhata (Manual)
1. Supervisor creates batch → assigns moulder group  
2. Green bricks counted → drying log updated  
3. Firing → record coal quantity → record output  
4. Sorting → classify 1st/2nd/3rd/bats  
5. Sale → POS invoice → update batch balance  

### Business-Standard Bhata (Modern)
1. Machine moulding → auto batch generation  
2. Tunnel drying/firing sensors → automated log import  
3. Fuel & temperature data auto-recorded  
4. Sorting with scanner → brick type tagging  
5. POS + accounting auto-sync → profit per batch  

---

## 🧾 Module Manifest Example
`modules/Bhata/module.php`
```php
<?php
return [
  'slug' => 'bhata',
  'name' => 'BhataFlow (Smart Brick Field)',
  'version' => '1.2.0',
  'enabled' => true,
  'auto_include_on_org_create' => 1,
  'icon' => 'fa-solid fa-fire-flame-curved',
  'namespace' => 'Modules\\Bhata',
  'entry_path' => '/apps/bhata',
  'permissions' => ['bhata.view','bhata.manage'],
];
```

---

## 📦 Installation / Auto-Registration
Run once to sync manifests:
```bash
php -d display_errors=1 -r "require 'bootstrap/Kernel.php'; \App\Services\ModulesCatalog::syncSafe(); echo 'Modules sync complete\n';"
```
This auto-registers BhataFlow into `cp_modules` and includes it for new organizations automatically.

---

## ✅ Version Roadmap

| Version | Target | Highlights |
|----------|---------|-------------|
| **v1.0.0** | Base Release | Manual Bhata operations |
| **v1.2.0** | Dual Mode | Typical + Standard kiln support |
| **v1.4.0** | Integration | POS + Payroll sync |
| **v2.0.0** | IoT-Ready | Fuel sensors, temperature logs |

---

## 💡 Design Philosophy
> “BhataFlow bridges the local craft of brick-making with modern business control —  
> respecting the way things work today, while preparing for how they’ll scale tomorrow.”
