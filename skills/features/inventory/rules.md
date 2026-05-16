# Inventory Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `inventory`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `warehouse` | `inventory.warehouse.read`| `inventory.warehouse.write`| `inventory.warehouse.delete`| `inventory.warehouse.export`|
| `procurement`| `inventory.procurement.read`| `inventory.procurement.write`| `inventory.procurement.delete`| `inventory.procurement.export`|
| `logistics` | `inventory.logistics.read` | `inventory.logistics.write` | - | `inventory.logistics.export` |
| `suppliers` | `inventory.suppliers.read` | `inventory.suppliers.write` | `inventory.suppliers.delete` | `inventory.suppliers.export` |

## 2. Implementation Standards

### Inventory Operational Flow
1. **Procurement**: Issue PO and process goods receipt (GRN).
2. **Stock-In**: Validate and move to warehouse location.
3. **Monitoring**: Track stock levels and safety thresholds.
4. **Movement**: Handle inter-warehouse transfers.
5. **Consumption**: Fulfillment for sales or internal use.
6. **Reconciliation**: Period stock-taking and adjustments.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Inventory`
- **Atomic Operations**: Stock adjustments and transfers MUST be transaction-safe.
- **Service Layer**: `Services/StockService.php`, `Services/ProcurementService.php`.
- **Validation**: Prevent negative stock unless explicitly allowed by tenant config.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/inventory/`
- **UX**: Barcode scanner integration for stock-taking.
- **Status**: Low-stock alerts on the main dashboard.
