# Fixed Asset Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `assets`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `tracking` | `assets.tracking.read` | `assets.tracking.write` | `assets.tracking.delete` | `assets.tracking.export` |
| `depreciation`| `assets.depreciation.read`| `assets.depreciation.write`| - | `assets.depreciation.export`|
| `disposal` | `assets.disposal.read` | `assets.disposal.write` | - | `assets.disposal.export` |

## 2. Implementation Standards

### Asset Lifecycle Flow
1. **Acquisition**: Record purchase and location.
2. **Depreciation**: Execute automated monthly valuation jobs.
3. **Integration**: Post depreciation journal entries to FMS.
4. **Audit**: Periodic verification via physical QR scanning.
5. **Disposal**: Handle retirement and record gain/loss.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Assets`
- **Depreciation**: Implement various schedules (Straight-line, Declining balance).
- **Service Layer**: `Services/AssetService.php`, `Services/DepreciationService.php`.
- **FMS Link**: Automatically generate journal entries for depreciation/disposal in `fms` module.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/assets/`
- **Visuals**: QR code generation for asset physical tracking.
- **Data**: Detailed tables with asset lifecycle history.
