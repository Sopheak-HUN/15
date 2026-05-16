# Skill: Reporting & Analytics Implementation

## Context
Use this skill when building dashboards, custom report builders, or data visualization tools.

## Guidelines

### 1. Dashboard Construction
- **Widgets**: Create a library of reusable widgets (KPI cards, Charts, Lists).
- **Personalization**: Allow users to customize their own dashboard layout.

### 2. Report Generation
- **Dynamic Filters**: Support complex filtering (Date ranges, Tenant-specific dimensions).
- **Export Formats**: Standardize PDF, Excel, and CSV exports across all modules.

### 3. Data Integrity
- **Real-time vs Cached**: Clearly indicate if data is real-time or from a cached snapshot.
- **Consistency**: Ensure reports match the data seen in the operational modules.

## Best Practices
- **Performance**: Use specialized reporting queries (or a data warehouse) to avoid slowing down the production database.
- **Visualization**: Stick to a consistent color palette (defined in `iam.md`) for all charts.
- **Security**: Reports must respect the user's module permissions (e.g., no payroll reports for a Sales Rep).

## Troubleshooting
- **Timeout**: If reports time out, move generation to a background job and notify the user when ready.
- **Data Mismatch**: Check if the report is using a different timezone or stale cache.
- **Broken Charts**: Verify the frontend charting library (e.g., Chart.js) is receiving the correct JSON structure.
