# Feature: Cambodia Administrative Geography

Cross-cutting reference data for province → district → commune → village.
Lives in the **central (landlord) database** because it's shared across
all tenants and rarely changes.

## Source

[MEF open data portal](https://data.mef.gov.kh/datasets?categories=geospatial-data)
— four per-level datasets published by the Ministry of Land Management,
Urban Planning and Construction (MLMUPC):

| Level     | Dataset ID                            | Rows   | URL                                                                                  |
|-----------|---------------------------------------|--------|--------------------------------------------------------------------------------------|
| Provinces | `pd_66a8603700604c000123e144`         | 25     | https://data.mef.gov.kh/km/datasets/pd_66a8603700604c000123e144                      |
| Districts | `pd_66a8603800604c000123e145`         | 210    | https://data.mef.gov.kh/km/datasets/pd_66a8603800604c000123e145                      |
| Communes  | `pd_66a8603900604c000123e146`         | 1,661  | https://data.mef.gov.kh/km/datasets/pd_66a8603900604c000123e146                      |
| Villages  | `pd_66a8603a00604c000123e147`         | 14,576 | https://data.mef.gov.kh/km/datasets/pd_66a8603a00604c000123e147                      |

Codes embed hierarchy: province `01` → district `0102` → commune `010201`
→ village `01020101`.

## Consumers

- HRM employee creation wizard ([create.vue](../../frontend/pages/hrm/employees/create.vue))
  uses the cascade three times (current address, permanent address, emergency
  contact address).
- Any future module needing Cambodian administrative geography should hit
  `GET /api/geo/{level}` and use the `useCambodiaGeo()` composable.
