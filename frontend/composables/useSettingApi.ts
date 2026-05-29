/**
 * Tenant settings — per-group key/value store.
 *
 * The backend treats `value` as arbitrary JSON, so the typed shapes
 * for each group live here as small interfaces. Adding a new settings
 * group: add an interface + a method pair, keep group strings narrow.
 */

export interface AttendanceSettings {
  morning_late_after: string         // "HH:MM:SS"
  afternoon_late_after: string       // "HH:MM:SS"
  working_days: Array<'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun'>
  work_start_time: string
  work_end_time: string
}

export interface CodeNumberingEntry {
  prefix: string                     // e.g. "TT-EMP-"
  start_from: 0 | 1                  // counter origin
  digits: number                     // zero-pad width (1–10)
  next_number: number                // current counter — admins may reset
}

export type CodeNumberingType = 'employee' | 'quotation' | 'invoice' | 'asset'

export type NumberingSettings = Record<CodeNumberingType, CodeNumberingEntry>

export function useSettingApi() {
  const api = useApi()

  return {
    getAttendanceSettings: () =>
      api.get<{ data: AttendanceSettings }>('/api/iam/settings/attendance'),
    updateAttendanceSettings: (body: AttendanceSettings) =>
      api.put<{ success: boolean; data: AttendanceSettings }>('/api/iam/settings/attendance', body),

    getNumberingSettings: () =>
      api.get<{ data: NumberingSettings }>('/api/iam/settings/numbering'),
    updateNumberingSettings: (body: NumberingSettings) =>
      api.put<{ success: boolean; data: NumberingSettings }>('/api/iam/settings/numbering', body),
  }
}
