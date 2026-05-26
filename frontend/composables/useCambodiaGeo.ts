/**
 * Cambodia administrative geography (province → district → commune → village).
 *
 * Backed by the Laravel `/api/geo/{level}` endpoint which reads from the
 * local `provinces` / `districts` / `communes` / `villages` tables. Each
 * sub-level accepts an optional parent-code filter so each cascade only
 * pulls the ~5–30 rows it needs, instead of the full 14.5K-village dataset.
 *
 * Backend command to (re-)import from MEF:
 *   php artisan geo:import
 *
 * Override the URL via `NUXT_PUBLIC_API_BASE` (defaults to http://localhost:8000).
 */

export interface GeoUnit {
  id: string         // raw MEF code, e.g. '01' (province), '010201' (commune)
  name: string
  name_kh?: string
  parent_id?: string
}

interface DbRow {
  code: string
  name_kh: string
  name_en: string
  province_code?: string
  district_code?: string
  commune_code?: string
}

// Tab-wide caches so re-selecting the same parent doesn't re-hit the API.
let provincesCache: DbRow[] | null = null
const districtsCache = new Map<string, DbRow[]>()  // keyed by province_code
const communesCache = new Map<string, DbRow[]>()  // keyed by district_code
const villagesCache = new Map<string, DbRow[]>()  // keyed by commune_code

// In-flight de-dup so concurrent calls share one request.
let provincesInflight: Promise<DbRow[]> | null = null
const districtsInflight = new Map<string, Promise<DbRow[]>>()
const communesInflight = new Map<string, Promise<DbRow[]>>()
const villagesInflight = new Map<string, Promise<DbRow[]>>()

export const useCambodiaGeo = () => {
  const config = useRuntimeConfig()
  const apiBase = (config.public.apiBase as string) || 'http://localhost:8000'

  const fetchLevel = async (
    level: 'provinces' | 'districts' | 'communes' | 'villages',
    filter?: Record<string, string>,
  ): Promise<DbRow[]> => {
    try {
      return await $fetch<DbRow[]>(`${apiBase}/api/geo/${level}`, { query: filter })
    } catch (err) {
      console.warn(`[useCambodiaGeo] ${level} failed:`, err)
      return []
    }
  }

  const toUnit = (r: DbRow, parentKey?: keyof DbRow): GeoUnit => ({
    id: r.code,
    name: r.name_en ?? r.code,
    name_kh: r.name_kh,
    parent_id: parentKey ? (r[parentKey] as string | undefined) : undefined,
  })

  // Cache only non-empty results. An empty array is treated as a transient
  // failure (e.g. backend not yet seeded with that level's data) so the next
  // call retries instead of returning `[]` forever.
  const isHit = <T>(v: T[] | null | undefined): v is T[] => Array.isArray(v) && v.length > 0

  return {
    async listProvinces(): Promise<GeoUnit[]> {
      if (isHit(provincesCache)) return provincesCache.map((r) => toUnit(r))
      if (!provincesInflight) {
        provincesInflight = fetchLevel('provinces').then((rows) => {
          if (rows.length > 0) provincesCache = rows
          provincesInflight = null
          return rows
        })
      }
      const rows = await provincesInflight
      return rows.map((r) => toUnit(r))
    },

    async listDistricts(provinceCode: string): Promise<GeoUnit[]> {
      const cached = districtsCache.get(provinceCode)
      if (isHit(cached)) return cached.map((r) => toUnit(r, 'province_code'))
      let promise = districtsInflight.get(provinceCode)
      if (!promise) {
        promise = fetchLevel('districts', { province_code: provinceCode }).then((rows) => {
          if (rows.length > 0) districtsCache.set(provinceCode, rows)
          districtsInflight.delete(provinceCode)
          return rows
        })
        districtsInflight.set(provinceCode, promise)
      }
      const rows = await promise
      return rows.map((r) => toUnit(r, 'province_code'))
    },

    async listCommunes(districtCode: string): Promise<GeoUnit[]> {
      const cached = communesCache.get(districtCode)
      if (isHit(cached)) return cached.map((r) => toUnit(r, 'district_code'))
      let promise = communesInflight.get(districtCode)
      if (!promise) {
        promise = fetchLevel('communes', { district_code: districtCode }).then((rows) => {
          if (rows.length > 0) communesCache.set(districtCode, rows)
          communesInflight.delete(districtCode)
          return rows
        })
        communesInflight.set(districtCode, promise)
      }
      const rows = await promise
      return rows.map((r) => toUnit(r, 'district_code'))
    },

    async listVillages(communeCode: string): Promise<GeoUnit[]> {

      const cached = villagesCache.get(communeCode)
      if (isHit(cached)) return cached.map((r) => toUnit(r, 'commune_code'))
      let promise = villagesInflight.get(communeCode)
      if (!promise) {
        promise = fetchLevel('villages', { commune_code: communeCode }).then((rows) => {
          if (rows.length > 0) villagesCache.set(communeCode, rows)
          villagesInflight.delete(communeCode)
          return rows
        })
        villagesInflight.set(communeCode, promise)
      }
      const rows = await promise
      return rows.map((r) => toUnit(r, 'commune_code'))
    },
  }
}
