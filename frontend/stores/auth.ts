import { defineStore } from 'pinia'
import type { User } from '~/types/iam'

interface AuthState {
  user: User | null
  token: string | null
  tenant: string | null
}

const TOKEN_KEY = 'erp.token'
const TENANT_KEY = 'erp.tenant'
const USER_KEY = 'erp.user'

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: null,
    tenant: null,
  }),

  getters: {
    isAuthenticated: (s) => !!s.token && !!s.tenant,
    // Convenience accessors for permission checks. These are sourced
    // entirely from the user payload returned by /api/auth/login — the
    // backend re-checks every request, so the frontend treats them as
    // a presentation hint, not a security boundary.
    permissions: (s): string[] => s.user?.effective_permissions ?? [],
    roleName: (s): string | null => s.user?.role?.name ?? null,
    isSuperAdmin(): boolean {
      return this.roleName === 'super-admin'
    },
    initials: (s) =>
      s.user
        ? s.user.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((p) => p[0]?.toUpperCase() ?? '')
            .join('')
        : '?',
  },

  actions: {
    hydrate() {
      if (!import.meta.client) return
      this.token = localStorage.getItem(TOKEN_KEY)
      this.tenant = localStorage.getItem(TENANT_KEY)
      const rawUser = localStorage.getItem(USER_KEY)
      if (rawUser) {
        try { this.user = JSON.parse(rawUser) as User }
        catch { this.user = null }
      }
    },

    setSession(payload: { user: User; token: string; tenant: string }) {
      this.user = payload.user
      this.token = payload.token
      this.tenant = payload.tenant
      if (import.meta.client) {
        localStorage.setItem(TOKEN_KEY, payload.token)
        localStorage.setItem(TENANT_KEY, payload.tenant)
        // Persist the full user payload including role + permissions so
        // a page refresh doesn't strip the sidebar of valid entries.
        localStorage.setItem(USER_KEY, JSON.stringify(payload.user))
      }
    },

    setTenant(handle: string) {
      this.tenant = handle
      if (import.meta.client) localStorage.setItem(TENANT_KEY, handle)
    },

    clear() {
      this.user = null
      this.token = null
      this.tenant = null
      if (import.meta.client) {
        localStorage.removeItem(TOKEN_KEY)
        localStorage.removeItem(TENANT_KEY)
        localStorage.removeItem(USER_KEY)
      }
    },
  },
})
