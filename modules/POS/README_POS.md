---

## ⚙️ Phase Plan

### **Phase 0 — Module Bootstrap**
- [x] Create `module.php`, `front.php`, `nav.php`
- [x] Register manifest in Control Panel via `cp_modules`
- [x] Verify tenant routing `/t/{slug}/apps/pos/dashboard`

### **Phase 1 — Database Schema**
- [x] `pos_products` (stock, pricing, category)
- [x] `pos_customers`
- [x] `pos_sales` (header)
- [x] `pos_sale_items` (lines)
- [x] `pos_payments` (multi-method)
- [x] `pos_stock_moves`
- [x] `pos_settings`
- [ ] Foreign keys (with CASCADE)
- [ ] Default seeding (currency, receipt footer)
- [x] All inside `Modules\POS\Support\Installer::run()`

### **Phase 2 — UI Shells & Branding**
- [x] Tenant shell compatible layout
- [x] POS-specific sidebar (from `nav.php`)
- [x] Brand color: `--brand: #228B22`
- [x] Light/Dark theme support
- [ ] Alpine `cartStore` + `searchStore`
- [ ] Print shell (`receipt` layout)

### **Phase 3 — Dashboard & Overview**
- [x] `/dashboard`: Sales summary, KPIs (today, week, month)
- [x] Links: Register, Products, Sales, Reports
- [ ] Product stats (low stock count)
- [ ] Sales chart (recharts / chart.js)

### **Phase 4 — CRUD Operations**
#### **Products**
- [x] `/products` list with search/filter
- [x] `/products/create` + `/edit`
- [ ] Low-stock flag & reorder level
- [ ] Upload image (optional)
#### **Customers**
- [x] `/customers` index + create
- [ ] Email/phone uniqueness
- [ ] Order history per customer

### **Phase 5 — Sales Register (Core POS)**
- [x] `/register` (scan, search, add to cart)
- [x] Cart panel (qty adjust, discount %, tax)
- [x] Payment modal (cash/card/split)
- [ ] Offline caching for temporary cart
- [ ] Drawer session (open/close log)
- [ ] Auto-receipt print

### **Phase 6 — Sales & Reporting**
- [x] `/sales` list with filters (date range, cashier)
- [x] `/sales/{id}/view` (detail + print)
- [x] `/reports` (summary by date/product)
- [ ] CSV export
- [ ] Filter memory in session

### **Phase 7 — Settings**
- [x] `/settings` tabbed UI
- [x] Tax / Discount config
- [x] Receipt footer editor
- [ ] User role-specific settings visibility

### **Phase 8 — Accounting Addendum (v1.1)**
- [ ] Cash Drawer (open/close session log)
- [ ] Journals & Ledger reports
- [ ] Trial Balance
- [ ] Profit & Loss
- [ ] Balance Sheet
- [ ] CSV Exports (finance-ready)

---

## 🧠 Engineering Guardrails

| Area | Standard |
|------|-----------|
| **Money Type** | Store in cents (INT), display with 2 decimals |
| **Qty Type** | DECIMAL(10,3) |
| **Stock Integrity** | Use `pos_stock_moves` for all mutations |
| **Immutability** | Completed sales are immutable; use refunds/voids |
| **Soft Deletes** | Use `deleted_at` pattern |
| **Currency** | Single per org, in `pos_settings` |
| **Permissions** | via `cap` flags (`pos.sell`, `pos.report.view`) |
| **Receipts** | Safe HTML template with `{org_name}`, `{sale_no}`, `{total}` placeholders |
| **Dark Mode** | CSS uses `:root[data-theme='dark']` |
| **Printing** | No outer shell, A4 and 80mm optimized |

---

## 🧪 Definition of Done (v1)
- [ ] Installer runs cleanly
- [ ] All routes return 200 OK
- [ ] Dashboard, Products, Register, Reports usable end-to-end
- [ ] Receipts print cleanly (A4 + 80mm)
- [ ] A11y ≥ 90
- [ ] Per-org isolation verified
- [ ] CSV exports valid
- [ ] Errors logged to `storage/logs`

---

## 🪄 Quick Commands

| Purpose | Example |
|----------|----------|
| Run Installer | `\Modules\POS\Support\Installer::run();` |
| Force Module Sync | `\Shared\Helpers\ModuleSync::syncFromFilesystem();` |
| Enable Module for Org | Insert into `cp_org_modules (org_id, module_id)` |
| Access POS Dashboard | `/t/{slug}/apps/pos/dashboard` |

---

## 📚 References

- **Schema Spec:** `POS_Database_Schema.md`
- **Frontend Milestones:** `POS_Frontend_Milestones.md`
- **Accounting Addendum:** `POS_Accounting_Addendum.md`

---

**Maintainer:** KlinFlow Core Team  
**Last Updated:** October 2025  
**Version:** POS v1.0 – Tenant Edition