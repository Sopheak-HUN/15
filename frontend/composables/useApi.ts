import type { FetchOptions } from 'ofetch'
import { useAuthStore } from '~/stores/auth'

type ApiOptions = Omit<FetchOptions, 'baseURL' | 'headers'> & {
  headers?: Record<string, string>
  /** Send tenant header even if no tenant is set yet (will throw if missing). */
  requireTenant?: boolean
  /** Skip auto-attaching the tenant header (e.g. central routes like POST /api/tenants). */
  central?: boolean
}

/**
 * Wrapped $fetch that injects:
 *   - tenant: <handle>   (unless `central: true`)
 *   - Authorization: Bearer <token>  (if authenticated)
 *
 * Per AGENTS.md §7, the tenant header is mandatory for every tenant-scoped request.
 * See docs/api-authentication.md for the full contract.
 */
export function useApi() {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  const request = async <T>(url: string, options: ApiOptions = {}): Promise<T> => {
    const { central = false, requireTenant = false, headers = {}, ...rest } = options

    const merged: Record<string, string> = { Accept: 'application/json', ...headers }

    if (!central) {
      if (auth.tenant) {
        merged.tenant = auth.tenant
      } else if (requireTenant) {
        throw new Error('Tenant handle missing — log in or set tenant first.')
      }
    }

    if (auth.token) merged.Authorization = `Bearer ${auth.token}`

    try {
      return await $fetch<T>(url, {
        baseURL: config.public.apiBase,
        headers: merged,
        ...(rest as any),
      })
    } catch (err: unknown) {
      if (
        err && typeof err === 'object' && 'response' in err
        && (err as { response: { status: number } }).response?.status === 401
      ) {
        auth.clear()
        await navigateTo('/auth/login')
      }
      throw err
    }
  }

  return {
    get:  <T>(url: string, options?: ApiOptions) => request<T>(url, { ...options, method: 'GET' }),
    post: <T>(url: string, body?: any, options?: ApiOptions) =>
      request<T>(url, { ...options, method: 'POST', body }),
    put:  <T>(url: string, body?: any, options?: ApiOptions) =>
      request<T>(url, { ...options, method: 'PUT', body }),
    del:  <T>(url: string, options?: ApiOptions) => request<T>(url, { ...options, method: 'DELETE' }),
    raw:  request,
  }
}
