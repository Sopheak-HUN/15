/**
 * Presigned-URL upload helpers. The browser uploads file bytes DIRECTLY to
 * MinIO / S3 / R2 — Laravel never streams the file. The flow is:
 *
 *   1. POST /api/uploads/<resource> → { upload_url, key, ... }
 *   2. PUT upload_url with the File and Content-Type header
 *   3. Send `key` back to the create endpoint (e.g. as `photo_temp_key`)
 *
 * The backend's EmployeeService then moves `uploads/{nanoid}.{ext}` to
 * `tenants/{handle}/employees/{uuid}/photo.{ext}` and the bucket's 1-day
 * lifecycle rule reclaims any presigned URLs the browser abandoned.
 */

export interface PresignResp {
  upload_url: string
  key: string
  mime: string
  max_bytes: number
  expires_in: number
}

export function useUpload() {
  const api = useApi()

  /**
   * Ask Laravel for a 10-minute presigned PUT URL targeted at the photo
   * lane, then PUT the file directly to the returned URL. Returns the
   * temp key the create endpoint expects in `photo_temp_key`.
   *
   * Throws when the presign fails (auth/tenancy error) or the PUT returns
   * non-2xx — callers should catch and surface a toast.
   */
  const uploadEmployeePhoto = async (file: File): Promise<string> => {
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
      throw new Error('Photo must be a JPEG or PNG image')
    }
    if (file.size > 2 * 1024 * 1024) {
      throw new Error('Photo must be 2 MB or smaller')
    }

    const presign = await api.post<PresignResp>('/api/uploads/employee-photo', {
      mime: file.type,
      size: file.size,
    })

    // $fetch from ofetch defaults to JSON encoding which would break the PUT;
    // we go straight to the global fetch so the body is the raw File.
    const put = await fetch(presign.upload_url, {
      method: 'PUT',
      headers: { 'Content-Type': file.type },
      body: file,
    })
    if (!put.ok) {
      throw new Error(`Upload failed (${put.status} ${put.statusText})`)
    }
    return presign.key
  }

  /**
   * Upload an employee document (PDF, image, Office doc, CSV, zip) up
   * to 10 MB. Unlike photos, this lane writes directly into the
   * tenant's permanent prefix because the employee_id is known at
   * upload time (the dialog opens from the detail page).
   *
   * Returns the object key + the autoresolved mime/size so the caller
   * can pre-fill the metadata form.
   */
  const uploadEmployeeDocument = async (
    employeeId: string,
    file: File,
  ): Promise<{ key: string; mime: string; size: number }> => {
    const allowed = new Set([
      'application/pdf',
      'image/jpeg', 'image/png',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'text/csv',
      'application/zip',
    ])
    if (!allowed.has(file.type)) {
      throw new Error(`Unsupported file type: ${file.type || 'unknown'}`)
    }
    if (file.size > 10 * 1024 * 1024) {
      throw new Error('File must be 10 MB or smaller')
    }

    const presign = await api.post<PresignResp>('/api/uploads/employee-document', {
      employee_id: employeeId,
      mime: file.type,
      size: file.size,
    })

    const put = await fetch(presign.upload_url, {
      method: 'PUT',
      headers: { 'Content-Type': file.type },
      body: file,
    })
    if (!put.ok) {
      throw new Error(`Upload failed (${put.status} ${put.statusText})`)
    }
    return { key: presign.key, mime: file.type, size: file.size }
  }

  /**
   * Upload a leave-request reference file (medical certificate,
   * travel confirmation, etc.). Accepts PDF, image, or Word doc up to
   * 10 MB. Lands under the requester's per-employee prefix.
   */
  const uploadLeaveReference = async (
    employeeId: string,
    file: File,
  ): Promise<{ key: string; mime: string; size: number }> => {
    const allowed = new Set([
      'application/pdf',
      'image/jpeg', 'image/png',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])
    if (!allowed.has(file.type)) {
      throw new Error(`Unsupported file type: ${file.type || 'unknown'}`)
    }
    if (file.size > 10 * 1024 * 1024) {
      throw new Error('File must be 10 MB or smaller')
    }

    const presign = await api.post<PresignResp>('/api/uploads/leave-reference', {
      employee_id: employeeId,
      mime: file.type,
      size: file.size,
    })

    const put = await fetch(presign.upload_url, {
      method: 'PUT',
      headers: { 'Content-Type': file.type },
      body: file,
    })
    if (!put.ok) {
      throw new Error(`Upload failed (${put.status} ${put.statusText})`)
    }
    return { key: presign.key, mime: file.type, size: file.size }
  }

  return { uploadEmployeePhoto, uploadEmployeeDocument, uploadLeaveReference }
}
