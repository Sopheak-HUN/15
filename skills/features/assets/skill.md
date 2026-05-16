# Skill: Fixed Asset Management Implementation

## Context
Use this skill when managing physical assets, calculating depreciation, and handling asset lifecycles.

## Guidelines

### 1. Asset Lifecycle
- **Acquisition**: Record assets with purchase price, vendor, and location.
- **Disposal**: Implement workflows for retiring or selling assets, including gain/loss calculation.

### 2. Depreciation
- **Methods**: Support Straight-line, Declining Balance, and Sum-of-the-Years'-Digits methods.
- **Scheduling**: Automate monthly depreciation entries in the FMS.

### 3. Physical Tracking
- **Barcodes/QR**: Generate and scan QR codes for physical inventory audits.
- **Custodian**: Track which employee or department is currently responsible for the asset.

## Best Practices
- **Integration**: Link assets to `hrm` (Custodians) and `fms` (Accounting entries).
- **Insurance**: Track insurance policies and renewal dates per asset.
- **Photos**: Allow uploading photos of assets for condition verification.

## Troubleshooting
- **Depreciation Mismatch**: Verify the `Useful Life` and `Salvage Value` settings in the `AssetModel`.
- **Duplicate Assets**: Implement checks for VIN or Serial Number uniqueness within a tenant.
- **Missing Journal Entries**: Check the `FmsIntegrationService` for failures during automated depreciation runs.
