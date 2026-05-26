# Cambodia Administrative Geography — Detailed Tasks

Session built: 2026-05-26.

### 1. Database & Models (central scope)

- [x] **4 central migrations** under `backend/database/migrations/` (NOT in
  `migrations/tenant/` — this is reference data shared across tenants):
  - `2026_05_26_120001_create_provinces_table.php`
  - `2026_05_26_120002_create_districts_table.php`
  - `2026_05_26_120003_create_communes_table.php`
  - `2026_05_26_120004_create_villages_table.php`
- [x] PK is the **raw MEF code** (`code`, varchar 16, no autoincrement)
  for interoperability with any other system using official MEF codes.
- [x] FK chain enforced with `cascadeOnUpdate()->restrictOnDelete()`:
  provinces ← districts.province_code ← communes.district_code ←
  villages.commune_code.
- [x] **4 Eloquent models** under
  [backend/app/Models/Geo/](../../backend/app/Models/Geo/):
  - `Province` (hasMany districts)
  - `District` (belongsTo province, hasMany communes)
  - `Commune`  (belongsTo district, hasMany villages)
  - `Village`  (belongsTo commune)

### 2. Backend API

- [x] [`App\Http\Controllers\GeoController`](../../backend/app/Http/Controllers/GeoController.php)
  serves `GET /api/geo/{level}` from the local DB.
- [x] Optional parent-code filter per sub-level so each cascade only pulls
  rows it needs (5–30 villages per commune instead of 14,576):
  - `?province_code=01` for districts
  - `?district_code=0102` for communes
  - `?commune_code=010201` for villages
- [x] Response shape: `[{code, parent_code, name_kh, name_en}]`. Ordered by
  `name_en`, cached for 1 hour via `Cache-Control` header.
- [x] Route registered in [routes/api.php](../../backend/routes/api.php) with
  `where('level', 'provinces|districts|communes|villages')` constraint.

### 3. Import command (artisan)

- [x] [`App\Console\Commands\ImportGeo`](../../backend/app/Console/Commands/ImportGeo.php)
  — `php artisan geo:import`. Idempotent via upsert on `code` PK.

**Flow per level:**
1. If `backend/database/seed-data/{level}.csv` exists → use it.
2. Otherwise paginate the MEF JSON API and write the CSV.
3. Parse → orphan-filter → bulk upsert in 1000-row chunks.

**Flags:**
- `--level=villages` — limit to one level (parents must already exist).
- `--refresh` — force re-download even when the cached CSV exists.
- `--from-mef` — bypass CSV cache entirely; live MEF on every run.

**Robustness features built in (lessons from this session):**
- **Per-page retries** with exponential backoff: 1s → 2s → 4s → 8s → 16s,
  capped at 6 attempts. Mitigates Cloudflare's burst-rate protection on
  the 73-page villages download.
- **150 ms inter-page pacing** to stay under the same threshold.
- **Resume support** via `{level}.csv.partial` + `{level}.csv.cursor.json`.
  If a page fails after retries, the partial CSV and cursor stay on disk;
  re-running `geo:import` picks up from the last successful page instead
  of starting over. Cursor includes `dataset_id` + `total_pages` +
  `total_items`, so a dataset update is detected and starts fresh.
- **Row-count assertion** — refuses to promote `{level}.csv.partial` to
  `{level}.csv` unless the row count matches MEF's `total_items`. A bad
  download can never silently overwrite a good cache.
- **Per-level orphan filter** before upsert. MEF datasets occasionally
  reference codes across levels that don't line up (e.g. village under a
  commune that's missing from the communes dataset). Filter drops those
  rows and logs the count + up to 5 sample parent codes, so the FK
  constraint never fires.
- **Per-PK dedupe** before upsert. Postgres rejects
  `ON CONFLICT DO UPDATE` when the same PK appears twice in one
  statement (`Cardinality violation: 7`). Dedupe keys mapped rows
  in-memory; last write wins.
- **SSL `withoutVerifying()`** workaround on `Http::` calls. Remove once
  ca-certificates is baked into the production image (it already is in
  the Dockerfile + php.ini, just needs a rebuild — see "container fixes"
  below).

### 4. Seed data (committed CSVs)

- [x] [backend/database/seed-data/README.md](../../backend/database/seed-data/README.md)
  documents the four expected files.
- [ ] **CSV files themselves are not yet committed to git** — first
  developer to run `geo:import --refresh` downloads them. Once they exist
  locally, commit them so subsequent developers / deploys skip the MEF
  download entirely.

### 5. Frontend composable

- [x] [`useCambodiaGeo()`](../../frontend/composables/useCambodiaGeo.ts)
  fronts the four backend endpoints with module-scope caching:
  - `listProvinces()` — single cache (all 25 rows after first call).
  - `listDistricts(provinceCode)`, `listCommunes(districtCode)`,
    `listVillages(communeCode)` — keyed by parent code, so reselecting
    the same parent skips the network.
  - **In-flight de-dup** via promise Maps prevents concurrent calls from
    hitting the API twice.
  - **Empty-cache refetch** — `isHit()` requires the cached array to be
    non-empty. If a fetch returned `[]` once (e.g. the import wasn't
    finished yet), the next call retries instead of replaying the stale
    empty result. This was a real bug: previously `cached` was truthy
    even for `[]` so villages stopped appearing after the first commune
    selection.

### 6. Container fixes for MEF download (Alpine PHP)

- [x] [backend/Dockerfile](../../backend/Dockerfile) — `ca-certificates`
  added to the `apk add` list.
- [x] [backend/docker/php/php.ini](../../backend/docker/php/php.ini) —
  `curl.cainfo` and `openssl.cafile` set to
  `/etc/ssl/certs/ca-certificates.crt`.
- [ ] **Rebuild required to take effect:**
  ```bash
  docker compose build app && docker compose up -d app
  ```
  Until then, `Http::withoutVerifying()` is doing the work in
  `ImportGeo`. After the rebuild it can be dropped.

---

## How to bring it up on a fresh machine

```bash
cd backend
php artisan migrate                                  # creates the 4 tables
docker compose exec app php artisan geo:import       # downloads + imports
```

First run ≈ 30–60 s (4 downloads with pacing). Subsequent runs reuse the
cached CSVs and take ~3 s.

## Validation

```bash
docker compose exec app php artisan tinker --execute="
  echo 'provinces='.App\Models\Geo\Province::count(),
       ' districts='.App\Models\Geo\District::count(),
       ' communes='.App\Models\Geo\Commune::count(),
       ' villages='.App\Models\Geo\Village::count();
"
# Expected: provinces=25 districts=210 communes=1661 villages=14572-14576
# (villages count may be ≤14,576 if MEF data has cross-level orphans;
#  the import command logs the skipped count when that happens.)
```

```bash
curl -s http://localhost:8000/api/geo/provinces | head -c 200
curl -s "http://localhost:8000/api/geo/villages?commune_code=010201" | jq length
# Expected: ~19 villages for Banteay Neang commune.
```

## Known gaps / nice-to-haves

- Commit the 4 CSV files to git after a successful import so other
  developers don't have to re-download from MEF.
- After Dockerfile rebuild, drop `Http::withoutVerifying()` in `ImportGeo`.
- No tests yet. P0 test would be: `geo:import --from-mef --level=provinces`
  populates 25 rows; subsequent `geo:import` no-ops the download and
  upserts cleanly (uses the cached CSV).
- No tracking of cross-level orphans in DB. Currently they're logged
  during import but not stored anywhere queryable.
- Cache headers on `/api/geo/{level}` are `public, max-age=3600` — fine
  for browsers but no server-side cache (e.g. Redis). At current scale
  the DB queries are sub-10ms so it's not worth adding yet.
