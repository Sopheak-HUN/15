# Geo seed data

Per-level CSV cache for `php artisan geo:import`. Each file mirrors one MEF
open-data dataset and maps 1:1 to a DB table.

| File             | Rows   | Columns                                                                       | MEF dataset                                                                                  |
|------------------|--------|-------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| `provinces.csv`  | 25     | `province_code, province_kh, province_en`                                     | [pd_66a8603700604c000123e144](https://data.mef.gov.kh/km/datasets/pd_66a8603700604c000123e144) |
| `districts.csv`  | 210    | `province_code, district_code, district_kh, district_en`                      | [pd_66a8603800604c000123e145](https://data.mef.gov.kh/km/datasets/pd_66a8603800604c000123e145) |
| `communes.csv`   | 1,661  | `province_code, district_code, commune_code, commune_kh, commune_en`          | [pd_66a8603900604c000123e146](https://data.mef.gov.kh/km/datasets/pd_66a8603900604c000123e146) |
| `villages.csv`   | 14,576 | `province_code, district_code, commune_code, village_code, village_kh, village_en` | [pd_66a8603a00604c000123e147](https://data.mef.gov.kh/km/datasets/pd_66a8603a00604c000123e147) |

## How files get populated

- **First run**: `geo:import` paginates each MEF dataset's JSON API and
  writes the rows to the matching CSV here.
- **Subsequent runs**: cached CSVs are reused (fast, offline). Pass
  `--refresh` to re-download all four (or `--level=X --refresh` for one).

## Manual placement

You can drop any of these files yourself. The reader is tolerant of:
- UTF-8 BOM on the header row
- Quoted Khmer names with embedded commas

Just keep the column order matching the table above so the importer can map
rows to DB columns correctly.
