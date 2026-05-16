# Sales Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `sales`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `crm` | `sales.crm.read` | `sales.crm.write` | `sales.crm.delete` | `sales.crm.export` |
| `orders` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |
| `invoices` | `sales.invoices.read` | `sales.invoices.write` | `sales.invoices.delete` | `sales.invoices.export` |
| `leads` | `sales.leads.read` | `sales.leads.write` | `sales.leads.delete` | `sales.leads.export` |

## 2. Implementation Standards

### Order-to-Cash (O2C) Workflow
1. **Inquiry/Quotation**: Create lead and generate quote.
2. **Approval**: Wait for customer sign-off.
3. **Order Conversion**: Convert approved quote to Sales Order.
4. **Inventory Check**: Deduct stock via `InventoryService`.
5. **Fulfillment**: Update status to `shipped`.
6. **Invoicing**: Generate PDF invoice and post to FMS (Accounts Receivable).
7. **Settlement**: Record payment and close order.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Sales`
- **Service Layer**: Logic in `Services/OrderService.php`, `Services/CrmService.php`, etc.
- **Transactions**: Order creation and inventory deduction MUST be atomic.
- **Resources**: Use `OrderResource` and `CustomerResource` for API responses.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/sales/`
- **Components**: Use PrimeVue DataTables for lead and order management.
- **UX**: Implement real-time notifications for order status updates.
