/**
 * Global route guard that enforces permissions declared via
 * `definePageMeta({ requires: 'hrm.employee.read' })` or
 * `definePageMeta({ requires: ['iam.roles.view', 'iam.users.view'] })`.
 *
 * If the page declares a `requires` meta and the current user is missing
 * the permission(s), redirect to /403. Otherwise allow the navigation.
 *
 * This is a UX hint — the backend's `permission:` middleware is the real
 * boundary and returns 403 from the API even if a determined user
 * bypasses this guard.
 */
export default defineNuxtRouteMiddleware((to) => {
  // Server-side renders don't have access to the auth store hydrated
  // from localStorage; let them through and re-check on the client.
  if (import.meta.server) return

  const requires = to.meta.requires as string | string[] | undefined
  if (!requires) return

  const auth = useAuthStore()
  // Not logged in yet — the `auth` middleware handles that redirect.
  if (!auth.user) return

  const { hasAny } = usePermissions()
  const list = Array.isArray(requires) ? requires : [requires]
  if (hasAny(...list)) return

  return navigateTo({ path: '/403', query: { from: to.fullPath } })
})
