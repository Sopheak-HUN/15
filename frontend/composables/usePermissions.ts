import { useAuthStore } from '~/stores/auth'

/**
 * Composable for permission checks. Reads from the auth store, which is
 * hydrated from /api/auth/login's `user.effective_permissions` array.
 *
 * The backend re-checks every request via the `permission:` middleware,
 * so these helpers are a presentation hint (hide a button, redirect a
 * link), NOT a security boundary. A user editing localStorage to grant
 * themselves perms still gets 403 from the API.
 *
 * Super-admin role bypasses everything — see the matching shortcut in
 * EnsurePermission middleware so the two layers stay in sync.
 */
export function usePermissions() {
  const auth = useAuthStore()

  const has = (perm: string): boolean => {
    if (!auth.user) return false
    if (auth.isSuperAdmin) return true
    return auth.permissions.includes(perm)
  }

  const hasAny = (...perms: string[]): boolean => {
    if (perms.length === 0) return true
    if (!auth.user) return false
    if (auth.isSuperAdmin) return true
    return perms.some((p) => auth.permissions.includes(p))
  }

  const hasAll = (...perms: string[]): boolean => {
    if (perms.length === 0) return true
    if (!auth.user) return false
    if (auth.isSuperAdmin) return true
    return perms.every((p) => auth.permissions.includes(p))
  }

  return { has, hasAny, hasAll }
}
