# Inventory Management

Use this skill when building warehouse management, procurement, and supply chain features.

## Guidelines

### 1. Stock Management
- **Multi-Warehouse**: Support inventory tracking across multiple physical locations.
- **Units of Measure (UOM)**: Handle conversions between different units (e.g., Box to Pieces).

### 2. Procurement (P2P)
- **Purchase Orders**: Link POs to supplier profiles and track receipt of goods.
- **Vendor Management**: Maintain performance ratings and lead times for each supplier.

### 3. Logistics
- **Transfers**: Implement secure workflows for moving stock between warehouses.
- **Adjustments**: Require a reason and approval for manual stock level changes.

## Best Practices
- **FIFO/LIFO**: Implement standard costing and inventory valuation methods.
- **Low-Stock Alerts**: Trigger notifications when stock levels fall below safety thresholds.
- **Barcode Scanning**: Prioritize mobile-first UI for warehouse staff.

## Troubleshooting
- **Negative Stock**: If stock goes negative, check the `InventoryPolicy` and `StockService` for missing guard clauses.
- **Costing Errors**: Verify the `ValuationService` logic for average cost calculations after a new purchase.
- **Sync Issues**: If physical stock doesn't match the system, audit the `StockAdjustment` logs for unapproved changes.
