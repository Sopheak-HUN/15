# Testing Strategy: Fixed Asset Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Assets and Custodians must be scoped to the `tenant_id`. |
| **P0** | **Accuracy** | Depreciation schedules must match selected accounting methods. |
| **P1** | **Integration** | Disposal events must trigger GL entries in FMS module. |
| **P1** | **API Contract** | Follows `erp_collection.json`; captures `lastAssetId`. |
| **P2** | **Tracking** | QR code generation must point to the correct tenant URL. |

## 2. Backend Testing (Pest PHP)

### Financial Accuracy (P0)
- **Rule**: Depreciation calculations must match industry standards (e.g., Straight-line).
- **Test Case**: Unit test for `DepreciationService` with a 5-year asset and 20% salvage value.

### Multi-Tenancy
- **Rule**: Asset lists must be filtered by `tenant_id`.
- **Test Case**: Verify `tenant_id` is automatically injected into all asset queries.

### Lifecycle Events
- **Rule**: Disposing of an asset must set its status to `retired` and log a disposal entry.
- **Test Case**: `PATCH /api/assets/disposal/{id}` and verify status.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Contract**: Verify JSON response includes `currentValue` and `accumulatedDepreciation`.

## 3. Integration
- **Rule**: Disposal entries must trigger a loss/gain journal entry in FMS.
- **Test Case**: Assert journal entry creation upon asset disposal.
