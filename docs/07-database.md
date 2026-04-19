# Database Schema Highlights

The schema lives in [database/migrations/](../database/migrations/) — 176 files, dated `2026_03_04` onward. Below is a thematic walkthrough. For a model-level listing with columns, see [02-domain-model.md](02-domain-model.md).

> **Running migrations** — `php artisan migrate` (production: `php artisan migrate --force`). Seeders: `php artisan db:seed`.

---

## Core tables by theme

### Tenancy

| Migration | Tables |
|-----------|--------|
| [2026_03_03_184132_create_permission_tables](../database/migrations/2026_03_03_184132_create_permission_tables.php) | Spatie permissions (roles, permissions, pivots) |
| [2026_03_04_000001_create_companies_table](../database/migrations/2026_03_04_000001_create_companies_table.php) | `companies` |
| [2026_03_04_000002_create_outlets_table](../database/migrations/2026_03_04_000002_create_outlets_table.php) | `outlets` |
| [2026_03_04_000003_add_company_id_to_users_table](../database/migrations/2026_03_04_000003_add_company_id_to_users_table.php) | `users.company_id`, `outlet_id` |
| [2026_03_07_000041_create_outlet_user_table](../database/migrations/2026_03_07_000041_create_outlet_user_table.php) | `outlet_user` pivot |
| [2026_03_07_000042_add_company_details_fields](../database/migrations/2026_03_07_000042_add_company_details_fields.php) | Logo, address, registration |
| [2026_04_14_000164_create_outlet_groups_tables](../database/migrations/2026_04_14_000164_create_outlet_groups_tables.php) | `outlet_groups`, pivot |
| [2026_03_27_000110_add_ordering_mode_to_companies_table](../database/migrations/2026_03_27_000110_add_ordering_mode_to_companies_table.php) | `companies.ordering_mode` (outlet / cpu) |

### Catalog (ingredients, UOM, recipes)

| Migration | Tables / columns |
|-----------|------------------|
| [2026_03_04_000004_create_units_of_measure_table](../database/migrations/2026_03_04_000004_create_units_of_measure_table.php) | `units_of_measure` |
| [2026_03_04_000006_create_ingredients_table](../database/migrations/2026_03_04_000006_create_ingredients_table.php) | `ingredients` |
| [2026_03_04_000007_create_ingredient_uom_conversions_table](../database/migrations/2026_03_04_000007_create_ingredient_uom_conversions_table.php) | `ingredient_uom_conversions` |
| [2026_03_04_000008_create_ingredient_price_history_table](../database/migrations/2026_03_04_000008_create_ingredient_price_history_table.php) | `ingredient_price_history` |
| [2026_03_04_000020_add_purchase_price_yield_to_ingredients](../database/migrations/2026_03_04_000020_add_purchase_price_yield_to_ingredients_table.php) | `purchase_price`, `yield_percent` |
| [2026_03_04_000021_create_ingredient_categories_table](../database/migrations/2026_03_04_000021_create_ingredient_categories_table.php) | `ingredient_categories` |
| [2026_03_04_000031_add_parent_to_ingredient_categories](../database/migrations/2026_03_04_000031_add_parent_to_ingredient_categories.php) | `parent_id` (hierarchy) |
| [2026_03_04_000010_create_recipes_table](../database/migrations/2026_03_04_000010_create_recipes_table.php) | `recipes` |
| [2026_03_04_000011_create_recipe_lines_table](../database/migrations/2026_03_04_000011_create_recipe_lines_table.php) | `recipe_lines` |
| [2026_03_04_000022_create_recipe_categories_table](../database/migrations/2026_03_04_000022_create_recipe_categories_table.php) | `recipe_categories` |
| [2026_03_04_000026 / 27_add_is_prep_to_recipes_and_ingredients](../database/migrations/) | `is_prep` flags (prep recipes) |
| [2026_03_04_000030_add_cost_per_yield_unit_to_recipes](../database/migrations/2026_03_04_000030_add_cost_per_yield_unit_to_recipes.php) | `cost_per_yield_unit` |
| [2026_03_11_000058_create_recipe_images_table](../database/migrations/2026_03_11_000058_create_recipe_images_table.php) | `recipe_images` |
| [2026_03_11_000059_add_type_to_recipe_images_table](../database/migrations/2026_03_11_000059_add_type_to_recipe_images_table.php) | `type` (dine-in / takeaway) |
| [2026_03_20_000086_create_recipe_steps_table](../database/migrations/2026_03_20_000086_create_recipe_steps_table.php) | `recipe_steps` |
| [2026_04_08_000158/159_create_recipe_price_classes_tables](../database/migrations/) | `recipe_price_classes`, `recipe_prices` (tiered pricing) |
| [2026_03_27_000119_create_tax_rates_table](../database/migrations/2026_03_27_000119_create_tax_rates_table.php) | `tax_rates` |
| [2026_04_03_000150_add_tax_rate_to_ingredients](../database/migrations/2026_04_03_000150_add_tax_rate_to_ingredients.php) | `tax_rate_id` on ingredients |
| [2026_03_12_000062_uppercase_ingredient_and_recipe_names](../database/migrations/2026_03_12_000062_uppercase_ingredient_and_recipe_names.php) | Data migration (uppercase) |
| [2026_03_12_000063/64_add_pack_size](../database/migrations/) | `pack_size` on supplier_ingredients and ingredients |

UOM additions over time:
- 065 `tsp`/`tbsp`, 070 `gm`/`slice`/`bar`/`can`, 071 `block`, 072 `pack`, 079 `roll`/`ream`, 080 `pair`/`sheet`/`set`, 081 `tray`/`tub`/`tie`/`bundle`.

### Suppliers

| Migration | Tables |
|-----------|--------|
| [2026_03_04_000005_create_suppliers_table](../database/migrations/2026_03_04_000005_create_suppliers_table.php) | `suppliers` |
| [2026_03_04_000009_create_supplier_ingredients_table](../database/migrations/2026_03_04_000009_create_supplier_ingredients_table.php) | `supplier_ingredients` pivot |
| [2026_03_27_000123_create_supplier_users_table](../database/migrations/2026_03_27_000123_create_supplier_users_table.php) | `supplier_users` (portal) |
| [2026_03_27_000124/125_create_supplier_products*](../database/migrations/) | `supplier_products`, `supplier_product_mappings` |
| [2026_03_27_000126_add_portal_fields_to_suppliers_table](../database/migrations/2026_03_27_000126_add_portal_fields_to_suppliers_table.php) | `portal_enabled`, contact details |
| [2026_03_27_000128_create_supplier_price_alerts_table](../database/migrations/2026_03_27_000128_create_supplier_price_alerts_table.php) | `supplier_price_alerts` |
| [2026_03_27_000129_create_price_change_notifications_table](../database/migrations/2026_03_27_000129_create_price_change_notifications_table.php) | `price_change_notifications` |
| [2026_03_27_000130_add_price_alert_threshold_to_companies](../database/migrations/2026_03_27_000130_add_price_alert_threshold_to_companies.php) | `companies.price_alert_threshold` |
| [2026_03_27_000131_create_quotation_requests_table](../database/migrations/2026_03_27_000131_create_quotation_requests_table.php) | `quotation_requests`, lines, `quotation_request_suppliers` |
| [2026_03_27_000132_create_supplier_quotations_table](../database/migrations/2026_03_27_000132_create_supplier_quotations_table.php) | `supplier_quotations` |
| [2026_03_27_000133_make_supplier_company_id_nullable](../database/migrations/2026_03_27_000133_make_supplier_company_id_nullable.php) | Global suppliers |
| [2026_03_27_000134_add_supplier_sku_and_custom_items_support](../database/migrations/2026_03_27_000134_add_supplier_sku_and_custom_items_support.php) | `supplier_sku` |
| [2026_03_27_000135_add_location_fields_to_suppliers](../database/migrations/2026_03_27_000135_add_location_fields_to_suppliers.php) | Address/state columns |
| [2026_04_04_000156_create_supplier_item_aliases_table](../database/migrations/2026_04_04_000156_create_supplier_item_aliases_table.php) | `supplier_item_aliases` (fuzzy match learning) |

### Purchasing

| Migration | Tables / columns |
|-----------|------------------|
| [2026_03_04_000012_create_purchase_orders_table](../database/migrations/2026_03_04_000012_create_purchase_orders_table.php) | `purchase_orders`, lines |
| [2026_03_04_000013_create_delivery_orders_table](../database/migrations/2026_03_04_000013_create_delivery_orders_table.php) | `delivery_orders`, lines |
| [2026_03_04_000014_create_purchase_records_table](../database/migrations/2026_03_04_000014_create_purchase_records_table.php) | `purchase_records`, lines |
| [2026_03_05_000035/36_create_form_templates](../database/migrations/) | `form_templates`, lines (reusable templates) |
| [2026_03_05_000037_add_cost_center_to_purchase_orders](../database/migrations/2026_03_05_000037_add_cost_center_to_purchase_orders.php) | (later replaced by department) |
| [2026_03_07_000043_create_goods_received_notes_table](../database/migrations/2026_03_07_000043_create_goods_received_notes_table.php) | `goods_received_notes`, lines |
| [2026_03_07_000044_update_purchase_order_statuses](../database/migrations/2026_03_07_000044_update_purchase_order_statuses.php) | Status enum alignment |
| [2026_03_08_000049/50_create_po_approvers_and_require_po_approval](../database/migrations/) | `po_approvers` + `companies.require_po_approval` |
| [2026_03_12_000066/67/69_tax_fields](../database/migrations/) | Company tax fields, PO tax columns, `show_price_on_do_grn` |
| [2026_03_12_000073_add_auto_generate_do_to_companies](../database/migrations/2026_03_12_000073_add_auto_generate_do_to_companies_table.php) | `auto_generate_do` |
| [2026_03_13_000075_add_direct_supplier_order_to_companies](../database/migrations/2026_03_13_000075_add_direct_supplier_order_to_companies_table.php) | `direct_supplier_order` |
| [2026_03_13_000077_add_header_fields_to_form_templates](../database/migrations/2026_03_13_000077_add_header_fields_to_form_templates_table.php) | Template header config |
| [2026_03_27_000111..115_create_purchase_requests_*](../database/migrations/) | `purchase_requests`, lines, `pr_approvers`, adjustment tracking |
| [2026_03_27_000116_create_order_adjustment_logs_table](../database/migrations/2026_03_27_000116_create_order_adjustment_logs_table.php) | `order_adjustment_logs` |
| [2026_03_27_000117_add_partial_delivery_tracking_to_delivery_orders](../database/migrations/2026_03_27_000117_add_partial_delivery_tracking_to_delivery_orders.php) | Partial-delivery columns |
| [2026_03_27_000118_add_multi_supplier_to_purchase_orders](../database/migrations/2026_03_27_000118_add_multi_supplier_to_purchase_orders.php) | `parent_po_id`, `is_multi_supplier` |
| [2026_03_27_000120_create_stock_transfer_orders_table](../database/migrations/2026_03_27_000120_create_stock_transfer_orders_table.php) | `stock_transfer_orders`, lines |
| [2026_03_27_000121_create_procurement_invoices_table](../database/migrations/2026_03_27_000121_create_procurement_invoices_table.php) | `procurement_invoices`, lines |
| [2026_03_27_000122_add_tax_columns_to_existing_tables](../database/migrations/2026_03_27_000122_add_tax_columns_to_existing_tables.php) | Tax on PO/DO/GRN/invoice |
| [2026_03_28_000142/143_credit_notes](../database/migrations/) | `credit_notes`, lines, `procurement_invoices.credit_applied` |
| [2026_04_04_000153/154_ai_invoice_extraction](../database/migrations/) | `procurement_invoices.ai_fields`, `ai_invoice_scans` |

### Inventory

| Migration | Tables |
|-----------|--------|
| [2026_03_04_000016_create_wastage_records_table](../database/migrations/2026_03_04_000016_create_wastage_records_table.php) | `wastage_records`, lines |
| [2026_03_04_000017_create_outlet_transfers_table](../database/migrations/2026_03_04_000017_create_outlet_transfers_table.php) | `outlet_transfers`, lines |
| [2026_03_04_000018_create_stock_takes_table](../database/migrations/2026_03_04_000018_create_stock_takes_table.php) | `stock_takes`, lines |
| [2026_03_04_000028_add_total_stock_cost_to_stock_takes](../database/migrations/2026_03_04_000028_add_total_stock_cost_to_stock_takes.php) | `total_stock_cost` |
| [2026_03_07_000045_create_staff_meal_records_table](../database/migrations/2026_03_07_000045_create_staff_meal_records_table.php) | `staff_meal_records`, lines |
| [2026_03_11_000057_create_ingredient_par_levels_table](../database/migrations/2026_03_11_000057_create_ingredient_par_levels_table.php) | `ingredient_par_levels` |
| [2026_03_15_000084_add_method_to_stock_takes](../database/migrations/2026_03_15_000084_add_method_to_stock_takes.php) | `method` (detailed/summary) |

### Sales

| Migration | Tables / columns |
|-----------|------------------|
| [2026_03_04_000015_create_sales_records_table](../database/migrations/2026_03_04_000015_create_sales_records_table.php) | `sales_records`, lines |
| [2026_03_04_000024_create_sales_categories_table](../database/migrations/2026_03_04_000024_create_sales_categories_table.php) | `sales_categories` |
| [2026_03_04_000025_add_sales_category_to_sales_record_lines](../database/migrations/2026_03_04_000025_add_sales_category_to_sales_record_lines.php) | `sales_category_id` |
| [2026_03_05_000038_redesign_sales_for_restaurant](../database/migrations/2026_03_05_000038_redesign_sales_for_restaurant.php) | Revenue per category + meal period |
| [2026_03_05_000039_add_is_revenue_to_categories_and_create_app_settings](../database/migrations/2026_03_05_000039_add_is_revenue_to_categories_and_create_app_settings.php) | `is_revenue`, `app_settings` |
| [2026_03_07_000046_add_ingredient_category_to_sales_categories](../database/migrations/2026_03_07_000046_add_ingredient_category_to_sales_categories.php) | `ingredient_category_id` |
| [2026_03_08_000051_create_sales_record_attachments_table](../database/migrations/2026_03_08_000051_create_sales_record_attachments_table.php) | `sales_record_attachments` |
| [2026_03_10_000055_create_sales_closures_table](../database/migrations/2026_03_10_000055_create_sales_closures_table.php) | `sales_closures` |
| [2026_03_10_000056_create_sales_targets_table](../database/migrations/2026_03_10_000056_create_sales_targets_table.php) | `sales_targets` |

### Kitchen / CPU / Production

| Migration | Tables |
|-----------|--------|
| [2026_03_27_000108/109_central_purchasing_units](../database/migrations/) | `central_purchasing_units`, `cpu_users` |
| [2026_03_28_000136_create_central_kitchens_table](../database/migrations/2026_03_28_000136_create_central_kitchens_table.php) | `central_kitchens`, `kitchen_users` |
| [2026_03_28_000137_create_production_orders_table](../database/migrations/2026_03_28_000137_create_production_orders_table.php) | `production_orders`, lines |
| [2026_03_28_000138_create_production_logs_table](../database/migrations/2026_03_28_000138_create_production_logs_table.php) | `production_logs` |
| [2026_03_28_000139_create_outlet_prep_requests_table](../database/migrations/2026_03_28_000139_create_outlet_prep_requests_table.php) | `outlet_prep_requests`, lines |
| [2026_03_28_000140_create_kitchen_inventory_table](../database/migrations/2026_03_28_000140_create_kitchen_inventory_table.php) | `kitchen_inventory` |
| [2026_03_30_000147_create_production_recipes_table](../database/migrations/2026_03_30_000147_create_production_recipes_table.php) | `production_recipes`, lines |

### HR / Labour

| Migration | Tables |
|-----------|--------|
| [2026_03_13_000074_create_departments_table](../database/migrations/2026_03_13_000074_create_departments_table.php) | `departments` |
| [2026_03_15_000082/83_department_cost_chain](../database/migrations/) | Replace old `cost_center` with `department_id` |
| [2026_03_18_000085_create_labour_cost_tables](../database/migrations/2026_03_18_000085_create_labour_cost_tables.php) | `labour_costs`, `labour_cost_allowances` |
| [2026_04_06_000157 / 2026_04_18_000171_employees](../database/migrations/) | `ot_employees` → renamed `employees` |
| [2026_04_12_000161_create_video_share_tokens_table](../database/migrations/2026_04_12_000161_create_video_share_tokens_table.php) | Public video share |
| [2026_04_18_000173_create_sections_and_link_employees](../database/migrations/2026_04_18_000173_create_sections_and_link_employees.php) | `sections` |
| [2026_04_18_000174_add_section_to_ot_approvers](../database/migrations/2026_04_18_000174_add_section_to_ot_approvers.php) | OT approval routing |
| [2026_04_18_000175_add_overtime_to_labour_costs](../database/migrations/2026_04_18_000175_add_overtime_to_labour_costs.php) | Overtime columns |
| [2026_04_06_000157_create_ot_employees_table](../database/migrations/2026_04_06_000157_create_ot_employees_table.php) + [2026_04_12_000152_create_overtime_claims_tables](../database/migrations/2026_04_03_000152_create_overtime_claims_tables.php) | `overtime_claims`, `overtime_claim_approvers` |

### AI / audit / scanning

| Migration | Tables |
|-----------|--------|
| [2026_03_04_000019_create_audit_logs_table](../database/migrations/2026_03_04_000019_create_audit_logs_table.php) | `audit_logs` |
| [2026_03_09_000053_create_ai_analysis_logs_table](../database/migrations/2026_03_09_000053_create_ai_analysis_logs_table.php) | `ai_analysis_logs` |
| [2026_04_04_000154_create_ai_invoice_scans_table](../database/migrations/2026_04_04_000154_create_ai_invoice_scans_table.php) | `ai_invoice_scans` |
| [2026_04_18_000176_create_scanned_documents_table](../database/migrations/2026_04_18_000176_create_scanned_documents_table.php) | `scanned_documents` |

### SaaS billing / referrals / marketing

| Migration | Tables |
|-----------|--------|
| [2026_03_25_000090_create_plans_table](../database/migrations/2026_03_25_000090_create_plans_table.php) | `plans` |
| [2026_03_25_000091_create_subscriptions_table](../database/migrations/2026_03_25_000091_create_subscriptions_table.php) | `subscriptions` |
| [2026_03_25_000092_create_usage_records_table](../database/migrations/2026_03_25_000092_create_usage_records_table.php) | `usage_records` |
| [2026_03_25_000093_add_subscription_fields_to_companies](../database/migrations/2026_03_25_000093_add_subscription_fields_to_companies_table.php) | Trial / period / status |
| [2026_03_25_000094_create_onboarding_steps_table](../database/migrations/2026_03_25_000094_create_onboarding_steps_table.php) | `onboarding_steps` |
| [2026_03_25_000095/96_create_payments_and_invoices](../database/migrations/) | `payments`, `invoices` |
| [2026_03_25_000097..100_referrals](../database/migrations/) | `referral_programs`, `referral_codes`, `referrals`, `commissions` |
| [2026_03_25_000101_create_announcements_table](../database/migrations/2026_03_25_000101_create_announcements_table.php) | `announcements` |
| [2026_03_25_000102_add_api_rate_limit_to_plans_table](../database/migrations/2026_03_25_000102_add_api_rate_limit_to_plans_table.php) | `plans.api_rate_limit` |
| [2026_03_26_000104/105_pages](../database/migrations/) | `pages` + `external_url` |
| [2026_03_26_000106/107_affiliates](../database/migrations/) | `affiliates`, `affiliate_password_resets` |
| [2026_04_13_000163_create_coupons_tables](../database/migrations/2026_04_13_000163_create_coupons_tables.php) | `coupons`, `coupon_redemptions` |

---

## Conventions

### Status columns
Most lifecycle tables use `status` as a string (no DB enum). Allowed values live in Livewire forms and services — see [05-workflows.md](05-workflows.md) for the canonical values per entity.

### Auto-generated reference numbers
- `PO-YYYYMMDD-NNN`, `PR-YYYYMMDD-NNN`, `DO-YYYYMMDD-NNN`, `GRN-YYYYMMDD-NNN`, `STO-YYYYMMDD-NNN`, `PROD-YYYYMMDD-NNN`, `QTN-YYYYMMDD-NNN`.
- Generated in services or Livewire `save()` methods — scan for `generateNumber()` / `sprintf('PO-%s-%03d', …)`.

### Cost precision
Numeric columns use `decimal(12,4)` for unit costs and conversion factors, `decimal(12,2)` for totals and money.

### Soft deletes
Almost every "header" table has `deleted_at`. Line tables generally don't soft-delete — removing a line physically removes the row.

### Company toggle columns
Most feature flags are columns on `companies` added via migrations under `2026_03_0*`:
- `require_po_approval`, `require_pr_approval`, `auto_generate_do`, `direct_supplier_order`, `show_price_on_do_grn`, `ordering_mode`, `price_alert_threshold`, `is_grandfathered` (implicit via trial/subscription state).

### Timezone
- `companies.timezone` and `users.timezone` set in migration [2026_04_15_000168](../database/migrations/2026_04_15_000168_add_timezone_to_users_and_companies.php).
- [SetDisplayTimezone](../app/Http/Middleware/SetDisplayTimezone.php) applies per-request.

---

## Roles & permissions migrations

Note the sequence — roles evolved during development:

- [2026_03_05_000040_define_roles_and_permissions](../database/migrations/2026_03_05_000040_define_roles_and_permissions.php) — initial roles.
- [2026_03_08_000048_add_operations_manager_role](../database/migrations/2026_03_08_000048_add_operations_manager_role.php) — Operations Manager added.
- [2026_03_09_000054_rename_manager_to_branch_manager](../database/migrations/2026_03_09_000054_rename_manager_to_branch_manager.php) — rename.
- [2026_03_26_000103_give_company_admin_full_permissions](../database/migrations/2026_03_26_000103_give_company_admin_full_permissions.php) — Company Admin super-rights.
- [2026_03_28_000144_add_designation_and_capabilities_to_users](../database/migrations/2026_03_28_000144_add_designation_and_capabilities_to_users.php) — capability JSON on `users`.
- [2026_03_28_000145_migrate_roles_to_direct_permissions](../database/migrations/2026_03_28_000145_migrate_roles_to_direct_permissions.php) — direct permission attach.
- [2026_04_03_000149_add_hr_view_permission](../database/migrations/2026_04_03_000149_add_hr_view_permission.php) — HR permission.
- [2026_04_09_000160_add_receiving_and_invoice_capabilities_to_users](../database/migrations/2026_04_09_000160_add_receiving_and_invoice_capabilities_to_users.php) — capability flags for receive/invoice actions.
- [2026_04_15_000170_grant_hr_view_to_company_admins](../database/migrations/2026_04_15_000170_grant_hr_view_to_company_admins.php) — grant hr.view to company admins.

---

## Pivot tables summary

| Pivot | Columns beyond FKs |
|-------|--------------------|
| `outlet_user` | — |
| `outlet_recipe` | — |
| `outlet_ingredient` | — |
| `outlet_group_outlet` | — |
| `supplier_ingredients` | `supplier_sku`, `last_cost`, `uom_id`, `pack_size`, `is_preferred` |
| `quotation_request_suppliers` | `status`, `responded_at` |

---

## Migration tips

- **Never drop columns without a rollback path** — several historical migrations rename or restructure columns (e.g. `cost_center` → `department_id` in 082/083). Review surrounding migrations to see conventions.
- **Data migrations are fine** — there are examples under 062 (uppercase names), 083 (remove old columns after copy).
- **Add to `companies` for new feature toggles** — existing precedent above.
- **`tenant` tables need `company_id + index`** — then add `CompanyScope` on the model.
