# Reporting & Analytics Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `reporting`
- **Actions**: `read`, `write`, `export`, `schedule`

### Feature Matrix:
| Feature | Read | Write | Export | Schedule |
|---------|------|-------|--------|----------|
| `dashboards` | `reporting.dashboards.read`| `reporting.dashboards.write`| `reporting.dashboards.export`| - |
| `analytics` | `reporting.analytics.read` | - | `reporting.analytics.export` | - |
| `reports` | `reporting.reports.read` | `reporting.reports.write` | `reporting.reports.export` | `reporting.reports.schedule` |

## 2. Implementation Standards

### Analytical Pipeline Flow
1. **Aggregation**: Ingest operational data from modules.
2. **Processing**: Run complex analytical queries (optionally on read-replica).
3. **Caching**: Store snapshot results for high-performance retrieval.
4. **Visualization**: Render widgets and dashboard components.
5. **Distribution**: Support export to PDF/Excel or scheduled email delivery.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Reporting`
- **Performance**: Use Read-only database replicas for analytical queries.
- **Caching**: Cache report results where appropriate to reduce DB load.
- **Service Layer**: `Services/ReportGenerator.php`.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/reporting/`
- **Charts**: Deep integration with PrimeVue chart components.
- **Exports**: Support PDF, Excel, and CSV formats via backend services.
