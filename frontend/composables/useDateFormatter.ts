/**
 * Project-wide date / datetime formatter.
 *
 * Default output format is `DD-MM-yyyy` (Cambodian convention) — every
 * date the API returns as ISO `yyyy-MM-dd` or `yyyy-MM-ddTHH:mm:ss` gets
 * rendered through this helper. The optional `format` argument supports
 * a small token vocabulary so callers can opt into datetime or
 * shorter shapes without pulling in a date library.
 *
 *   formatDate('2026-05-26')                  → '26-05-2026'
 *   formatDate('2026-05-26T10:15:00Z')        → '26-05-2026'
 *   formatDate(new Date())                    → '26-05-2026'
 *   formatDate('2026-05-26', 'yyyy/MM/DD')    → '2026/05/26'
 *   formatDate(null)                          → '—'
 *
 * Files in `composables/` are auto-imported by Nuxt, so consumers can
 * just call `formatDate(value)` from any `<script setup>` or template
 * without any import statement.
 */

export type DateInput = string | number | Date | null | undefined

/** Recognised tokens. Longest-first so `yyyy` wins over `yy`, etc. */
const TOKENS = ['yyyy', 'yy', 'MM', 'DD', 'dd', 'HH', 'mm', 'ss'] as const
type Token = typeof TOKENS[number]

/**
 * Parse the input into a Date or return null when the value can't be
 * interpreted. Accepts Date, epoch number, or strings (ISO, `dd-mm-yyyy`,
 * `dd/mm/yyyy`, `yyyy-mm-dd`).
 */
function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === '') return null
  if (value instanceof Date) return isNaN(value.getTime()) ? null : value
  if (typeof value === 'number') {
    const d = new Date(value)
    return isNaN(d.getTime()) ? null : d
  }
  const s = String(value).trim()
  if (!s) return null

  // ISO / RFC-ish — let the Date constructor handle it.
  if (/T|Z|\+\d{2}:\d{2}$/.test(s)) {
    const d = new Date(s)
    if (!isNaN(d.getTime())) return d
  }

  // Plain `yyyy-mm-dd` or `yyyy/mm/dd`.
  const yMd = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/)
  if (yMd) {
    return new Date(Number(yMd[1]), Number(yMd[2]) - 1, Number(yMd[3]))
  }

  // Already formatted as `dd-mm-yyyy` or `dd/mm/yyyy` — parse so callers
  // can safely re-format without thinking about input shape.
  const dMy = s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/)
  if (dMy) {
    return new Date(Number(dMy[3]), Number(dMy[2]) - 1, Number(dMy[1]))
  }

  // Last-ditch: let the Date constructor try.
  const fallback = new Date(s)
  return isNaN(fallback.getTime()) ? null : fallback
}

function pad2(n: number): string {
  return n < 10 ? '0' + n : String(n)
}

function tokenValue(d: Date, token: Token): string {
  switch (token) {
    case 'yyyy': return String(d.getFullYear())
    case 'yy':   return String(d.getFullYear()).slice(-2)
    case 'MM':   return pad2(d.getMonth() + 1)
    case 'DD':
    case 'dd':   return pad2(d.getDate())
    case 'HH':   return pad2(d.getHours())
    case 'mm':   return pad2(d.getMinutes())
    case 'ss':   return pad2(d.getSeconds())
  }
}

/**
 * Format a date value using a token-based format string.
 * Defaults to `DD-MM-yyyy`. Returns `'—'` for null/empty/unparseable input.
 */
export function formatDate(value: DateInput, format = 'DD-MM-yyyy'): string {
  const d = toDate(value)
  if (!d) return '—'

  // Walk the format string and substitute the first matching token at
  // each position. Sorting by length ensures `yyyy` is matched before
  // `yy` etc.
  const sorted = [...TOKENS].sort((a, b) => b.length - a.length)
  let out = ''
  let i = 0
  while (i < format.length) {
    let matched = false
    for (const t of sorted) {
      if (format.startsWith(t, i)) {
        out += tokenValue(d, t)
        i += t.length
        matched = true
        break
      }
    }
    if (!matched) {
      out += format[i]
      i += 1
    }
  }
  return out
}

/**
 * Convenience wrapper that includes the time. Equivalent to
 * `formatDate(value, 'DD-MM-yyyy HH:mm')`.
 */
export function formatDateTime(value: DateInput, format = 'DD-MM-yyyy HH:mm'): string {
  return formatDate(value, format)
}
