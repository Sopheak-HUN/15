# Fleet Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `fleet`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `vehicles` | `fleet.vehicles.read` | `fleet.vehicles.write` | `fleet.vehicles.delete` | `fleet.vehicles.export` |
| `tracking` | `fleet.tracking.read` | - | - | - |
| `maintenance`| `fleet.maintenance.read`| `fleet.maintenance.write`| `fleet.maintenance.delete`| `fleet.maintenance.export`|
| `fuel` | `fleet.fuel.read` | `fleet.fuel.write` | `fleet.fuel.delete` | `fleet.fuel.export` |

## 2. Implementation Standards

### Fleet Operational Flow
1. **Asset Entry**: Register vehicle and telematics link.
2. **Tracking**: Ingest real-time GPS/Engine data.
3. **Monitoring**: Check mileage against maintenance thresholds.
4. **Alerting**: Generate service requests when thresholds hit.
5. **Logging**: Record maintenance costs and fuel consumption.
6. **Analysis**: Update TCO and efficiency dashboards.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Fleet`
- **Service Layer**: `Services/VehicleService.php`, `Services/TrackingService.php`.
- **Integrations**: Standardize API connectors for external Telematics providers.
- **Maintenance**: Automated reminders based on mileage/date intervals.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/fleet/`
- **GIS**: Integrate maps (Google Maps/Leaflet) for real-time tracking.
- **Forms**: Rich inputs for vehicle specs and maintenance logs.
