<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const iam = useIamApi()
const router = useRouter()
const toast = useToast()
const { mode, toggle } = useTheme()
const { t } = useI18n()

// Each entry can declare `requires` — a single permission name or an
// array (OR semantics, "any one of these is enough"). Entries with no
// `requires` are visible to every authenticated user. The dashboard
// stays universally visible so users always have a landing page.
interface NavItem  { label: string; icon: string; to: string; requires?: string | string[] }
interface NavGroup { label: string; key: string; items: NavItem[]; requires?: string | string[] }
type NavEntry = NavItem | NavGroup
const isGroup = (e: NavEntry): e is NavGroup => 'items' in e

const { hasAny } = usePermissions()

// Strict OR semantics: an entry is shown when no `requires` is set, OR
// when at least one of the listed perms is held.
const canSee = (req?: string | string[]) => {
  if (!req) return true
  const list = Array.isArray(req) ? req : [req]
  return hasAny(...list)
}

const nav = computed<NavEntry[]>(() => {
  const raw: NavEntry[] = [
    { label: t('nav.dashboard'), icon: 'pi pi-th-large', to: '/' },
    {
      key: 'iam',
      label: t('nav.groups.iam'),
      items: [
        { label: t('nav.roles'),       icon: 'pi pi-id-card', to: '/iam/roles',       requires: 'iam.roles.view' },
        { label: t('nav.permissions'), icon: 'pi pi-key',     to: '/iam/permissions', requires: 'iam.permissions.view' },
        // SSO / audit / branding don't have dedicated perms yet — they
        // ride on iam.roles.* as the "IAM admin" proxy, matching the
        // backend route gates.
        { label: t('nav.sso'),         icon: 'pi pi-shield',  to: '/iam/sso',         requires: 'iam.roles.edit' },
        { label: t('nav.auditLogs'),   icon: 'pi pi-history', to: '/iam/audit-logs',  requires: 'iam.roles.view' },
        { label: t('nav.branding'),    icon: 'pi pi-palette', to: '/iam/branding',    requires: 'iam.roles.edit' },
      ],
    },
    {
      key: 'hrm',
      label: t('nav.groups.hrm'),
      items: [
        { label: t('nav.employees'),   icon: 'pi pi-users',      to: '/hrm/employees',   requires: 'hrm.employee.read' },
        { label: t('nav.departments'), icon: 'pi pi-sitemap',    to: '/hrm/departments', requires: 'hrm.employee.read' },
        { label: t('nav.positions'),   icon: 'pi pi-bookmark',   to: '/hrm/positions',   requires: 'hrm.employee.read' },
        { label: t('nav.leave'),       icon: 'pi pi-calendar',   to: '/hrm/leave',       requires: 'hrm.leave.read' },
        { label: t('nav.attendance'),  icon: 'pi pi-clock',      to: '/hrm/attendance',  requires: 'hrm.attendance.read' },
        { label: t('nav.payroll'),     icon: 'pi pi-dollar',     to: '/hrm/payroll',     requires: 'hrm.payroll.read' },
        { label: t('nav.recruitment'), icon: 'pi pi-briefcase',  to: '/hrm/recruitment', requires: 'hrm.recruitment.read' },
      ],
    },
  ]

  // Drop hidden items first, then drop entire groups that emptied out.
  return raw
    .map((entry) => {
      if (isGroup(entry)) {
        return { ...entry, items: entry.items.filter((i) => canSee(i.requires)) }
      }
      return entry
    })
    .filter((entry) => {
      if (isGroup(entry)) return entry.items.length > 0
      return canSee(entry.requires)
    })
})

type PopupMenu = { toggle: (event: Event) => void }
const userMenu = ref<PopupMenu | null>(null)
const userActions = computed(() => [
  {
    label: t('user.profile'),
    icon: 'pi pi-user',
    command: () => router.push('/profile'),
  },
  {
    label: t('user.mfa'),
    icon: 'pi pi-shield',
    command: () => router.push('/auth/mfa-setup'),
  },
  {
    label: t('user.signOut'),
    icon: 'pi pi-sign-out',
    command: async () => {
      try { await iam.logout() } catch { /* token may already be invalid */ }
      auth.clear()
      toast.add({ severity: 'success', summary: t('user.signedOut'), life: 2000 })
      await router.push('/auth/login')
    },
  },
])

const sidebarOpen = ref(true)
</script>

<template>
  <div class="h-screen overflow-hidden flex bg-surface-50 dark:bg-surface-950">
    <!-- Sidebar -->
    <aside
      class="hidden md:flex flex-col border-r border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 transition-all duration-200"
      :class="sidebarOpen ? 'w-64' : 'w-16'"
    >
      <div class="h-16 flex items-center gap-2 px-4 border-b border-surface-200 dark:border-surface-800">
        <span class="size-8 rounded-lg bg-primary-600 grid place-items-center text-white shrink-0">
          <i class="pi pi-bolt text-sm" />
        </span>
        <span v-if="sidebarOpen" class="font-semibold truncate">{{ t('app.short') }}</span>
      </div>

      <nav class="flex-1 p-2 overflow-y-auto">
        <template v-for="(entry, idx) in nav" :key="isGroup(entry) ? entry.key : entry.to">
          <!-- Standalone link -->
          <NuxtLink
            v-if="!isGroup(entry)"
            :to="entry.to"
            class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
            active-class="bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 font-medium"
          >
            <i :class="entry.icon" class="text-base" />
            <span v-if="sidebarOpen">{{ entry.label }}</span>
          </NuxtLink>

          <!-- Grouped section -->
          <div v-else class="mt-3 first:mt-0">
            <!-- Header when sidebar is expanded -->
            <div
              v-if="sidebarOpen"
              class="px-3 pt-3 pb-1 text-[10px] uppercase tracking-wider font-semibold text-surface-400 dark:text-surface-500"
            >
              {{ entry.label }}
            </div>
            <!-- Visual divider when collapsed -->
            <div
              v-else-if="idx > 0"
              class="my-2 mx-3 border-t border-surface-200 dark:border-surface-800"
              :title="entry.label"
            />
            <div class="space-y-1">
              <NuxtLink
                v-for="item in entry.items"
                :key="item.to"
                :to="item.to"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
                active-class="bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 font-medium"
              >
                <i :class="item.icon" class="text-base" />
                <span v-if="sidebarOpen">{{ item.label }}</span>
              </NuxtLink>
            </div>
          </div>
        </template>
      </nav>

      <div class="p-2 border-t border-surface-200 dark:border-surface-800">
        <Button
          :icon="sidebarOpen ? 'pi pi-angle-left' : 'pi pi-angle-right'"
          text severity="secondary"
          class="w-full"
          :aria-label="sidebarOpen ? t('nav.collapse') : t('nav.expand')"
          @click="sidebarOpen = !sidebarOpen"
        />
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 flex items-center justify-between gap-4 px-6 border-b border-surface-200 dark:border-surface-800 bg-surface-0/80 dark:bg-surface-900/80 backdrop-blur">
        <div class="flex items-center gap-3 min-w-0">
          <Chip
            v-if="auth.tenant"
            :label="auth.tenant"
            icon="pi pi-building"
            class="!bg-primary-50 dark:!bg-primary-950/40 !text-primary-700 dark:!text-primary-300"
          />
        </div>
        <div class="flex items-center gap-2">
          <LocaleSwitcher />
          <Button
            :icon="mode === 'dark' ? 'pi pi-sun' : 'pi pi-moon'"
            text rounded severity="secondary"
            :aria-label="t('theme.toggle')"
            @click="() => toggle()"
          />
          <button
            class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
            :aria-label="t('user.menu')"
            @click="(e) => userMenu?.toggle(e)"
          >
            <Avatar :label="auth.initials" shape="circle" class="!bg-primary-600 !text-white" />
            <span class="hidden sm:block text-sm font-medium">{{ auth.user?.name }}</span>
            <i class="pi pi-angle-down text-xs text-surface-500" />
          </button>
          <Menu ref="userMenu" :model="userActions" :popup="true" />
        </div>
      </header>

      <main class="flex-1 overflow-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
