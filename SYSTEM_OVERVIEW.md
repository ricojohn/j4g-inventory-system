# J4G Inventory System — Complete System Overview

A complete reference document for the J4G Printing Inventory System. Use this as context when working with the codebase (e.g. pasting into ChatGPT or another AI assistant).

---

## 1. Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Language | PHP | 8.2+ |
| Framework | Laravel | 12.x (streamlined skeleton: no `app/Http/Kernel.php`, middleware in `bootstrap/app.php`) |
| Auth & Permissions | Spatie laravel-permission | latest |
| Realtime | Pusher (`pusher/pusher-php-server`) + Laravel broadcasting | — |
| Testing | Pest 3 + PHPUnit 11 | — |
| Code style | Laravel Pint 1.x | — |
| Frontend bundler | Vite 7 | — |
| CSS | Tailwind CSS v4 (Vite plugin) | — |
| JS | Plain ES modules (no React/Vue), Axios for AJAX | — |
| Local dev | Laravel Herd (`https://j4g-inventory-system.test`) | — |
| DB (dev) | MySQL/MariaDB (via Herd) | — |

No Inertia, no Livewire. Server-rendered Blade + small async-table JS for table interactions.

---

## 2. Domain Model & Data Architecture

### 2.1 Concept

A **Product** is something the print shop sells (e.g. T-Shirt, Polo Shirt, Reversible Adult).
Each product has its own set of **sizes** and **colors**, drawn from global **master tables** that are shared across products. Inventory is tracked at the **cell** level: one row per `(product, color, size)` combination.

### 2.2 Tables

```mermaid
erDiagram
    products ||--o{ product_size : has
    products ||--o{ product_color : has
    sizes ||--o{ product_size : referenced_by
    colors ||--o{ product_color : referenced_by
    product_color ||--o{ product_color_sizes : owns_cells
    product_size ||--o{ product_color_sizes : owns_cells
    product_color_sizes ||--o{ stock_movements : audited_in
    users ||--o{ stock_movements : created_by

    products {
      id bigint PK
      string name
      string code "unique, prefix for item_code"
      text description nullable
      string status "active|inactive"
    }
    sizes {
      id bigint PK
      string name "unique"
    }
    colors {
      id bigint PK
      string name "unique"
    }
    product_size {
      id bigint PK
      bigint product_id FK
      bigint size_id FK
      uint sort_order
    }
    product_color {
      id bigint PK
      bigint product_id FK
      bigint color_id FK
      string color_code "nullable, per-product, plain text"
      string item_code "unique, auto-generated CODE-001"
      uint sort_order
    }
    product_color_sizes {
      id bigint PK
      bigint product_color_id FK
      bigint product_size_id FK
      uint current_stock
      uint reserved_quantity
      uint reorder_level
    }
    stock_movements {
      id bigint PK
      bigint product_color_size_id FK
      string type "IN|OUT|RESERVE|RELEASE|DAMAGED|ADJUSTMENT"
      int quantity
      int before_stock
      int after_stock
      int before_reserved
      int after_reserved
      string remarks nullable
      bigint created_by FK users
      timestamp created_at
    }
```

### 2.3 Key design decisions

- **Global masters, per-product pivots.** `sizes` and `colors` are tiny shared lookup tables. The pivots (`product_size`, `product_color`) own the per-product attributes (sort_order, color_code, item_code).
- `color_code` is **plain text** (not a hex picker). Per-product — does NOT carry across when a color is reused on another product (rule: `.cursor/rules/color-fields.mdc`).
- `item_code` is auto-generated as `{PRODUCT_CODE}-{NNN}` (e.g. `RJA-001`) by `ProductCodeService`. Renaming a product code cascades to rewrite all its colors' item codes (model hook on `Product`).
- **Cells are auto-seeded by model hooks.** When you attach a new size to a product, a cell row is created for every existing color (and vice-versa) by the `booted()` hook on `ProductSize` / `ProductColor`. Default values: `current_stock = 0`, `reserved_quantity = 0`, `reorder_level = 0`.
- All inventory mutations go through `InventoryService` inside a DB transaction with `lockForUpdate()` for safety.
- Every mutation writes a `stock_movements` row (audit log) and broadcasts a `StockUpdated` event via Pusher.

### 2.4 Stock semantics

- `available_stock = current_stock - reserved_quantity`
- `status`:
  - `OUT_OF_STOCK` if `available_stock <= 0`
  - `LOW_STOCK` if `reorder_level > 0` and `available_stock <= reorder_level`
  - `OK` otherwise

---

## 3. Models (`app/Models/`)

| Model | Table | Notable relations / hooks |
|---|---|---|
| `Product` | `products` | `sizes()` HasMany `ProductSize`, `colors()` HasMany `ProductColor`, `cells()` HasManyThrough, `sizeMasters()`/`colorMasters()` BelongsToMany. `booted()` rewrites item_codes when `code` changes. `scopeActive`. |
| `Size` | `sizes` | `productSizes()` HasMany, `products()` BelongsToMany via `product_size`. |
| `Color` | `colors` | `productColors()` HasMany, `products()` BelongsToMany via `product_color`. |
| `ProductSize` | `product_size` | `product()`, `size()`, `cells()`. `booted()::created` auto-creates a cell for every existing color. |
| `ProductColor` | `product_color` | `product()`, `color()`, `cells()`. `booted()::creating` assigns `item_code` via `ProductCodeService`. `booted()::created` auto-creates a cell for every existing size. |
| `ProductColorSize` | `product_color_sizes` | `color()`, `size()`, `movements()`. Accessors: `available_stock`, `product`. Methods: `isLowStock()`, `isOutOfStock()`. |
| `StockMovement` | `stock_movements` | `cell()`, `user()`. `type` cast to `MovementType` enum. No `updated_at`. |
| `User` | `users` | Spatie `HasRoles` trait. `status` column gates login via `EnsureUserIsActive` middleware. |

---

## 4. Enums (`app/Enums/`)

- **`MovementType`** (string): `In`, `Out`, `Reserve`, `Release`, `Damaged`, `Adjustment` → values `IN, OUT, RESERVE, RELEASE, DAMAGED, ADJUSTMENT`.
- **`StockStatus`** (string): `Ok='OK'`, `LowStock='LOW_STOCK'`, `OutOfStock='OUT_OF_STOCK'`; `label()` returns human label.
- **`RecordStatus`**: `active|inactive` (used by Product / User).

---

## 5. Services (`app/Services/`)

### `InventoryService`

The single entry point for all stock mutations. Every method takes a `ProductColorSize $cell`. Internally wraps each call in `DB::transaction()` + `lockForUpdate()`, writes a `StockMovement`, and broadcasts `StockUpdated`.

| Method | Signature | Notes |
|---|---|---|
| `stockIn` | `($cell, $quantity, ?$remarks)` | Adds to `current_stock`. |
| `stockOut` | `($cell, $quantity, ?$remarks)` | Subtracts. Throws if available < quantity. |
| `reserve` | `($cell, $quantity, ?$remarks)` | Adds to `reserved_quantity`. Throws if available < quantity. |
| `release` | `($cell, $quantity, ?$remarks)` | Subtracts from `reserved_quantity`. Throws if reserved < quantity. |
| `damage` | `($cell, $quantity, ?$remarks)` | Subtracts from `current_stock`. Throws if available < quantity. |
| `adjust` | `($cell, $newQty, $remarks, ?$reorderLevel)` | Sets `current_stock = $newQty`. Remarks REQUIRED. Throws if new < reserved. |
| `getAvailableStock` | `($cell): int` | `current - reserved`. |
| `getStockStatus` | `($cell): StockStatus` | OK / LOW / OUT logic. |
| `formatCellForDisplay` | `($cell): array` | Full DTO for grid rendering. |
| `formatCellResponse` | `($cell): array` | Compact DTO for API success responses. |

### `ProductCodeService`

- `preview(Product)` / `generate(Product)` → next item code `CODE-NNN` (zero-padded to 3).
- `rebuildForProduct(string $existingCode, Product)` → swaps prefix when product code changes.
- `suggestPrefixFromName(string)` → builds suggested prefix from initials (e.g. "Dry Fit Long Sleeves" → "DFLS").

---

## 6. Controllers (`app/Http/Controllers/`)

### Public-side
- `AuthController` — login/logout (no registration).
- `DashboardController` — stats card + recent movements table.
- `ProductController` — index/data/create/store/edit/update/destroy, plus `manageInventory` + `inventoryData` (the grid).
- `ProductSizeController` — per-product sizes (data, suggestions, store, storeBulk, update, destroy). `suggestions()` supports `?exclude_product_id=X`.
- `ProductColorController` — per-product colors (same shape). Bulk endpoint dedupes by `color_id`.
- `InventoryController` — `stockIn`, `stockOut`, `reserve`, `release`, `damage`, `adjust`, `bulk`. Each guarded by a permission and validates the product isn't inactive.
- `ReportController` — `stockHistory(Data|FilterOptions)`, `lowStock(Data)`, `outOfStock(Data)`.

### Admin (`app/Http/Controllers/Admin/`)
- `UserController` — list/create/edit/update users.
- `RoleController` — list/edit roles.
- `SizeController` — CRUD for the master `sizes` table. Delete blocked if attached to any product.
- `ColorController` — CRUD for the master `colors` table. Delete blocked if attached.

---

## 7. Routes (`routes/web.php`)

All auth-protected. Permission middleware enforces per-route access.

### Auth
- `GET  /login` `POST /login` `POST /logout`

### Dashboard (`view dashboard`)
- `GET /dashboard` `GET /dashboard/stats` `GET /dashboard/recent-movements/data`

### Products (`view products` for read; `create|edit|delete products` for write)
- `GET /products` `GET /products/data`
- `GET /products/create` `POST /products` `GET /products/preview-code`
- `GET /products/{product}/edit` `PUT /products/{product}` `DELETE /products/{product}`

### Product sizes (per product, `edit products`)
- `GET  /products/sizes/suggestions[?exclude_product_id=X]`
- `GET  /products/{product}/sizes/data`
- `POST /products/{product}/sizes`
- `POST /products/{product}/sizes/bulk`
- `PUT  /products/{product}/sizes/{size}`  (size = pivot row id)
- `DELETE /products/{product}/sizes/{size}`

### Product colors (per product, `edit products`)
- `GET  /products/colors/suggestions[?exclude_product_id=X]`
- `GET  /products/{product}/colors/data`
- `POST /products/{product}/colors`
- `POST /products/{product}/colors/bulk`
- `PUT  /products/{product}/colors/{color}`  (color = pivot row id)
- `DELETE /products/{product}/colors/{color}`

### Inventory (`view inventory` for read; specific permission for each action)
- `GET  /products/{product}/inventory` `GET /products/{product}/inventory/data`
- `POST /inventory/stock-in`     (`stock in`)
- `POST /inventory/stock-out`    (`stock out`)
- `POST /inventory/reserve`      (`reserve stock`)
- `POST /inventory/release`      (`release stock`)
- `POST /inventory/damage`       (`damage stock`)
- `POST /inventory/adjust`       (`adjust stock`)
- `POST /inventory/bulk`         (per-action permission resolved inside)

### Reports
- `GET /reports/stock-history` + `/data` + `/filter-options` (`view stock history`)
- `GET /reports/low-stock` + `/data` (`view low stock report`)
- `GET /reports/out-of-stock` + `/data` (`view out of stock report`)

### Admin (prefix `/admin`, name `admin.*`)
- `users.*`   — `manage users`
- `roles.*`   — `manage roles`
- `sizes.*`   — `manage sizes`
- `colors.*`  — `manage colors`

---

## 8. Permissions & Roles

Seeded by `database/seeders/PermissionSeeder.php`.

### Permissions
```
view dashboard, view products, create products, edit products, delete products,
view inventory, stock in, stock out, reserve stock, release stock, damage stock, adjust stock,
view stock history, view low stock report, view out of stock report,
manage users, manage roles, manage permissions, manage sizes, manage colors
```

### Roles
| Role | Permissions |
|---|---|
| **Admin** | ALL |
| **Manager** | All EXCEPT `manage roles`, `manage permissions` |
| **Staff** | view dashboard, view products, create/edit/delete products, view inventory + all 6 stock actions, all 3 report views |
| **Viewer** | view dashboard, view products, view inventory, all 3 report views (no mutations) |

### Seeded users (`UserSeeder`, password: `password`)
- `admin@j4g.test` → Admin
- `manager@j4g.test` → Manager
- `staff@j4g.test` → Staff
- `viewer@j4g.test` → Viewer

The `EnsureUserIsActive` middleware (in the `web` group) forces logout if `users.status = 'inactive'`.

---

## 9. Policies (`app/Policies/`)

- `ProductPolicy` — `viewAny`, `view`, `create`, `update`, `delete` mapped to `view/create/edit/delete products` permissions.
- `SizePolicy` / `ColorPolicy` — `viewAny|create|update|delete` mapped to `manage sizes` / `manage colors`.

---

## 10. Form Requests (`app/Http/Requests/`)

| Request | Purpose |
|---|---|
| `StoreProductRequest`, `UpdateProductRequest` | Product CRUD validation. |
| `StoreProductSizeRequest`, `UpdateProductSizeRequest`, `BulkStoreProductSizesRequest` | Per-product size attachment. |
| `StoreProductColorRequest`, `UpdateProductColorRequest`, `BulkStoreProductColorsRequest` | Per-product color attachment. |
| `Inventory/StockMovementRequest` | `cell_id`, `quantity`, `remarks?` for stock-in/out/reserve/release/damage. |
| `Inventory/AdjustStockRequest` | `cell_id`, `new_quantity`, `remarks` (required), `reorder_level?`. |
| `Inventory/BulkStockMovementRequest` | `product_id`, `action`, `remarks?`, `items[]`. |
| `TableDataRequest` | Common base for `page`, `per_page`, `search`. Helpers: `pageNumber()`, `perPageCount()`. |
| `Admin/StoreSizeRequest`, `Admin/UpdateSizeRequest` | Master size CRUD. |
| `Admin/StoreColorRequest`, `Admin/UpdateColorRequest` | Master color CRUD. |

---

## 11. Events & Broadcasting

- Event: `App\Events\StockUpdated` (`ShouldBroadcast`).
- Broadcast on private channel `product.{product_id}` (see `routes/channels.php`).
- Fired by `InventoryService::applyMovement` via `DB::afterCommit`.
- Payload includes cell info, before/after totals, movement type, user. Frontend listens via Pusher to refresh grids in real time.

---

## 12. Backup Command

`php artisan inventory:backup [--path=backups/inventory-YYYY-MM-DD-HHMMSS.json]`

Writes a single JSON file under `storage/app/` containing all rows from products, sizes, colors, pivots, cells, and stock movements. Implemented in `App\Console\Commands\BackupInventoryCommand` (auto-registered by Laravel 12).

---

## 13. Frontend

### Layout
- `resources/views/layouts/app.blade.php` — base layout, includes sidebar + navbar.
- `resources/views/partials/sidebar.blade.php` — collapsible sidebar with sections: Dashboard, Products, Reports, Administration. Links gated by `@can` per permission.
- `resources/views/partials/navbar.blade.php` — top bar with hamburger + user menu.
- `resources/views/partials/toast.blade.php` — global toast container (`showToast(msg, type)` JS helper).

### UI components (`resources/views/components/ui/`)
- `button`, `input`, `select`, `textarea`, `label`, `badge`, `status-pill`, `card`, `page-card` (with optional `toolbar` slot), `page-header`, `async-table`, `table-wrap`, `stat-card`, `empty-state`.
- Tailwind v4 layers in `resources/css/app.css` define utility classes like `.ui-table`, `.ui-modal-overlay`, `.ui-modal-panel`, `.ui-modal-header/body/footer`, `.ui-row-action`, `.ui-toolbar-form`.

### Pages
- `dashboard/index.blade.php` — stat cards + recent movements table.
- `products/index.blade.php` — async products table (search, status filter, per-page, pagination).
- `products/create.blade.php` — product form with auto-suggest code.
- `products/edit.blade.php` — product details form + Sizes async-table + Colors async-table, each with add/delete; modals included.
- `products/inventory.blade.php` — color × size GRID (not paginated server-side), bulk-update modal, per-cell modal for 6 actions.
- `products/partials/size-modal.blade.php` — TABS: "Pick existing" (master sizes minus already-attached, search) + "Add new" (textarea, one per line). Posts to bulk endpoint.
- `products/partials/color-modal.blade.php` — same shape, but each picked/new color is sent as `{color_name}`; `color_code` is intentionally blank (editable later from the row).
- `admin/users/*`, `admin/roles/*`, `admin/sizes/index.blade.php`, `admin/colors/index.blade.php` — async tables + add/edit modal.
- `reports/stock-history|low-stock|out-of-stock.blade.php` — async tables with filters.

### JS (`resources/js/`)
- `app.js` — boot file: registers global helpers, sidebar toggle, Pusher client, `postData(url, payload, method='POST')`, `showToast()`, `escapeHtml()`, `getStatusBadgeClasses()`.
- `data-table.js` — `initAsyncTable({...})` plus `fetchTableData`, `showTableLoading/Empty/Error`, `renderPagination`, `renderStatusPill`, `renderStockBadge`, `debounce`. Exposes them on `window` for inline scripts in Blade pages.

### Conventions
- All table pages use `initAsyncTable({ tbodyId, paginationId, dataUrl, columnCount, emptyMessage, getParams, getPerPage, renderRows, onLoaded })`.
- Modals use the `.ui-modal-overlay` + `.ui-modal-panel` class pair (centered, dim backdrop). Backdrop click and `[data-close="modal-id"]` buttons both dismiss.
- Buttons send AJAX via `postData()`; success/error feedback via `showToast()`.

---

## 14. Inventory Grid Data Shape

`GET /products/{product}/inventory/data` returns:
```json
{
  "success": true,
  "product": { "id": 1, "name": "T-Shirt", "code": "TSC", "status": "active" },
  "sizes": [
    { "id": 12, "size_name": "S", "sort_order": 1 },
    { "id": 13, "size_name": "M", "sort_order": 2 }
  ],
  "colors": [
    {
      "id": 7,
      "color_name": "BLACK",
      "color_code": null,
      "item_code": "TSC-001",
      "sort_order": 1,
      "cells": {
        "12": {
          "id": 101,
          "color_id": 7, "size_id": 12,
          "color_name": "BLACK", "color_item_code": "TSC-001", "size_name": "S",
          "current_stock": 10, "reserved_quantity": 2, "available_stock": 8,
          "reorder_level": 5, "status": "OK", "status_label": "OK"
        }
      }
    }
  ]
}
```

The grid renders one row per color, one column per size. Cells are keyed by `product_size_id` so the JS just indexes `color.cells[size.id]`.

Bulk POST:
```
POST /inventory/bulk
{
  "product_id": 1,
  "action": "stock-in",
  "remarks": "Restock 2026-05-31",
  "items": [
    { "cell_id": 101, "quantity": 5 },
    { "cell_id": 102, "quantity": 3 }
  ]
}
```
For `action: "adjust"` each item uses `new_quantity` instead of `quantity`. Returns per-item success/failure so partial failures don't roll back the rest.

---

## 15. Seeders (`database/seeders/`)

Run order (set in `DatabaseSeeder::run`):

1. `PermissionSeeder` — creates all permissions + 4 roles.
2. `UserSeeder` — creates the 4 test users.
3. `ProductSeeder` — creates the 7 production products, attaches sizes & colors via `Size::firstOrCreate` + `Color::firstOrCreate` + pivot rows. Cells auto-created by model hooks. Sets `reorder_level = 5` on all.

Seeded products:
| Product | Code | # sizes | # colors |
|---|---|---|---|
| Reversible Adult | RJA | 7 | 38 |
| Reversible Kids | RJK | 2 | 10 |
| T-Shirt | TSC | 9 | 32 |
| Polo Shirt | PSC | 9 | 5 |
| Dry Fit Long Sleeves | DFLS | 9 | 7 |
| Dry Fit Hoodie | DFH | 9 | 1 |
| Dry Fit Short Sleeves | DFSL | 9 | 14 |

---

## 16. Tests (`tests/Feature/`, Pest)

Run: `php artisan test --compact` (currently 53 tests, ~167 assertions).

| File | Coverage |
|---|---|
| `AuthTest` | Login/logout, inactive user blocked. |
| `PermissionAccessTest` | Role-based route access. |
| `DashboardStatsTest` | Stats payload shape. |
| `AsyncTableDataTest` | Async-table envelope (data + pagination). |
| `ProductInventoryTest` | Inventory page + data endpoint, stock-in via API, inactive product guard. |
| `ProductRefactorTest` | Product seeder count, item-code generation, code rename cascade, suggestions (master + exclude), bulk-attach create-and-skip, backup command. |
| `InventoryServiceTest` | Each service method incl. broadcast + audit row. |
| `InventoryBulkTest` | Bulk endpoint success / partial failure / permission / validation / adjust-requires-remarks. |
| `InventoryConcurrencyTest` | `lockForUpdate` prevents overselling. |
| `SizeAdminTest` | Admin Size/Color CRUD + delete-blocked-when-attached + Staff forbidden. |
| `ProductCodeTest` | (covered inside ProductRefactorTest) |
| `CategorySizeTest`, `ExampleTest` | Smoke/legacy. |

`tests/Helpers.php` provides `seedBaseData()`, `userWithRole($role)`, `createTestProduct()`, `attachTestSize()`, `attachTestColor()`, `createTestCell()`, `createTestProductWithSizeAndColor()`.

---

## 17. Application Bootstrap (Laravel 12)

`bootstrap/app.php`:
- Web routes: `routes/web.php`. Console: `routes/console.php`. Channels: `routes/channels.php`.
- Middleware aliases registered for `permission`, `role`, `role_or_permission` (Spatie).
- `EnsureUserIsActive` appended to the `web` group.
- No `app/Console/Kernel.php`; commands auto-register from `app/Console/Commands/`.

---

## 18. File Map (selected)

```
app/
  Console/Commands/BackupInventoryCommand.php
  Enums/{MovementType, StockStatus, RecordStatus}.php
  Events/StockUpdated.php
  Http/
    Controllers/
      AuthController, DashboardController, ProductController,
      ProductSizeController, ProductColorController,
      InventoryController, ReportController
      Admin/{UserController, RoleController, SizeController, ColorController}.php
    Middleware/EnsureUserIsActive.php
    Requests/
      StoreProductRequest, UpdateProductRequest,
      StoreProductSizeRequest, UpdateProductSizeRequest, BulkStoreProductSizesRequest,
      StoreProductColorRequest, UpdateProductColorRequest, BulkStoreProductColorsRequest,
      TableDataRequest,
      Inventory/{StockMovementRequest, AdjustStockRequest, BulkStockMovementRequest}.php
      Admin/{StoreSizeRequest, UpdateSizeRequest, StoreColorRequest, UpdateColorRequest}.php
  Models/{Product, Size, Color, ProductSize, ProductColor, ProductColorSize, StockMovement, User}.php
  Policies/{ProductPolicy, SizePolicy, ColorPolicy}.php
  Services/{InventoryService, ProductCodeService}.php
  Support/PaginatedJsonResponse.php

database/
  migrations/
    0001_01_01_000000_create_users_table.php
    2026_05_30_124937_create_permission_tables.php
    2026_05_31_000000_create_sizes_table.php
    2026_05_31_000001_create_colors_table.php
    2026_05_31_000001_create_products_table.php
    2026_05_31_000002_create_product_sizes_table.php       (table: product_size)
    2026_05_31_000003_create_product_colors_table.php      (table: product_color)
    2026_05_31_000004_create_product_color_sizes_table.php
    2026_05_31_000005_create_stock_movements_table.php
  seeders/{DatabaseSeeder, PermissionSeeder, UserSeeder, ProductSeeder}.php

resources/
  css/app.css                    (Tailwind v4 + ui-* utility layer)
  js/{app.js, data-table.js}
  views/
    layouts/app.blade.php
    partials/{sidebar, navbar, alerts, toast, status-badge}.blade.php
    components/ui/*.blade.php
    auth/login.blade.php
    dashboard/index.blade.php
    products/{index, create, edit, inventory}.blade.php
    products/partials/{size-modal, color-modal}.blade.php
    admin/{users, roles, sizes, colors}/index.blade.php
    admin/users/{create, edit}.blade.php
    admin/roles/edit.blade.php
    reports/{stock-history, low-stock, out-of-stock}.blade.php

routes/{web.php, channels.php, console.php}
tests/{Pest.php, TestCase.php, Helpers.php, Feature/*.php}
```

---

## 19. Common Commands

```bash
# DB
php artisan migrate:fresh --seed --no-interaction

# Tests
php artisan test --compact
php artisan test --compact --filter=InventoryBulkTest

# Code style
vendor/bin/pint --dirty

# Frontend
npm run dev          # Vite HMR
npm run build        # Production bundle

# Backup
php artisan inventory:backup
php artisan inventory:backup --path=backups/custom.json

# Routes
php artisan route:list
php artisan route:clear
```

---

## 20. Cursor / AI Rules

Project-specific rules live under `.cursor/rules/*.mdc`:
- `laravel-boost.mdc` — Laravel Boost MCP usage, version pins, style conventions.
- `color-fields.mdc` — `color_code` is plain text, not hex.
- `data-loading.mdc` — async-table pattern conventions.
- `shopify-admin-ui.mdc`, `ui-design.mdc` — visual style guidance.

Workspace also exposes Laravel Boost MCP tools (database-query, tinker, list-artisan-commands, search-docs, browser-logs, etc.) when working inside Cursor.

---

## 21. Quick Glossary

- **Cell** — a single inventory row at `(product, color, size)`. Stored in `product_color_sizes`.
- **Item code** — the unique SKU for a `product_color`, format `{PRODUCT_CODE}-{NNN}`.
- **Color code** — free-text label per `product_color` (e.g. "PMS 286C"). Optional, per-product.
- **Pivot row** — a row in `product_size` or `product_color` that ties a global size/color to a product (with `sort_order` and color-specific extras).
- **Master** — the global `sizes` / `colors` table (just `id`, `name`).
- **Bulk update** — applying the same action across multiple cells of one product in one POST.
- **Reserve** — locks part of `current_stock` so it can't be sold/issued, without removing it (used for pending orders). `available = current - reserved`.
