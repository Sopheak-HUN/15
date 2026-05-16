# Sales & CRM

Use this skill when developing features for lead management, customer relationships, order processing, and invoicing. This ensures a seamless Order-to-Cash (O2C) workflow within the ERP.

## Guidelines

### 1. Lead & Customer Management
- **Centralized Data**: Store customer profiles with full interaction history.
- **Validation**: Ensure tax IDs and billing addresses are validated before invoicing.

### 2. Order Processing (O2C)
- **Atomicity**: Order creation must be atomic. If inventory deduction fails, the order must be rolled back.
- **Status Workflow**: Use a standard state machine (Draft -> Confirmed -> Shipped -> Invoiced).

### 3. Invoicing & Revenue
- **ERP Integration**: Invoices generated in Sales must automatically reflect in the FMS (Accounts Receivable).
- **Taxation**: Apply tenant-specific tax rules based on the customer's location.

## Best Practices
- **Real-time Updates**: Use WebSockets to notify sales reps when an order status changes.
- **Data Export**: Ensure all sales reports are exportable to Excel/PDF with proper permission checks (`sales.orders.export`).
- **Performance**: Use eager loading for order items to avoid N+1 issues in invoice generation.

## Troubleshooting
- **Stock Discrepancy**: If an order is confirmed but items are missing, check the `InventoryService` logs for transaction failures.
- **Invoice Formatting**: If PDF invoices look broken, verify the `headless-chrome` or `dompdf` configuration on the server.
- **Permission Denied**: Verify the user has `sales.crm.write` to modify customer data.
