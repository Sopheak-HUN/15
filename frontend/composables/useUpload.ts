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

  return { uploadEmployeePhoto }
}
