/**
 * Date formatting helpers for frontend pages.
 * Enforces strict 'dd-mm-yyyy' and 'dd-mm-yyyy hh:mm' formats.
 */

export function formatDate(val: string | Date | null | undefined): string {
  if (!val) return '—'
  
  if (val instanceof Date) {
    const year = val.getFullYear()
    const month = String(val.getMonth() + 1).padStart(2, '0')
    const day = String(val.getDate()).padStart(2, '0')
    return `${day}-${month}-${year}`
  }
  
  const str = String(val).trim()
  if (!str) return '—'
  
  // Extract date portion if it has time part or ISO 'T'
  const datePart = str.split('T')[0] || ''
  const parts = datePart.split(/[-/]/)
  if (parts.length === 3) {
    const p1 = parts[0] || ''
    const p2 = parts[1] || ''
    const p3 = parts[2] || ''
    // Check if format is yyyy-mm-dd
    if (p1.length === 4) {
      return `${p3}-${p2}-${p1}`
    }
    // Check if format is dd-mm-yyyy or mm-dd-yyyy
    if (p3.length === 4) {
      return `${p1}-${p2}-${p3}`
    }
  }
  
  return str
}

export function formatDateTime(val: string | Date | null | undefined): string {
  if (!val) return '—'
  
  const d = new Date(val)
  if (isNaN(d.getTime())) return '—'
  
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  
  return `${day}-${month}-${year} ${hours}:${minutes}`
}
