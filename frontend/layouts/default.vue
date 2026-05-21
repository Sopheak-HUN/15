<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const iam = useIamApi()
const router = useRouter()
const toast = useToast()
const { mode, toggle } = useTheme()

const nav = [
  { label: 'Dashboard',   icon: 'pi pi-th-large',      to: '/' },
  { label: 'Roles',       icon: 'pi pi-id-card',       to: '/iam/roles' },
  { label: 'Permissions', icon: 'pi pi-key',           to: '/iam/permissions' },
  { label: 'Audit Logs',  icon: 'pi pi-history',       to: '/iam/audit-logs' },
  { label: 'Branding',    icon: 'pi pi-palette',       to: '/iam/branding' },
]

type PopupMenu = { toggle: (event: Event) => void }
const userMenu = ref<PopupMenu | null>(null)
const userActions = [
  {
    label: 'Two-Factor Auth',
    icon: 'pi pi-shield',
    command: () => router.push('/auth/mfa-setup'),
  },
  {
    label: 'Sign out',
    icon: 'pi pi-sign-out',
    command: async () => {
      try { await iam.logout() } catch { /* token may already be invalid */ }
      auth.clear()
      toast.add({ severity: 'success', summary: 'Signed out', life: 2000 })
      await router.push('/auth/login')
    },
  },
]

const sidebarOpen = ref(true)
</script>

<template>
  <div class="min-h-screen flex bg-surface-50 dark:bg-surface-950">
    <!-- Sidebar -->
    <aside
      class="hidden md:flex flex-col border-r border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 transition-all duration-200"
      :class="sidebarOpen ? 'w-64' : 'w-16'"
    >
      <div class="h-16 flex items-center gap-2 px-4 border-b border-surface-200 dark:border-surface-800">
        <span class="size-8 rounded-lg bg-primary-600 grid place-items-center text-white shrink-0">
          <i class="pi pi-bolt text-sm" />
        </span>
        <span v-if="sidebarOpen" class="font-semibold truncate">ERP</span>
      </div>

      <nav class="flex-1 p-2 space-y-1">
        <NuxtLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
          active-class="bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 font-medium"
        >
          <i :class="item.icon" class="text-base" />
          <span v-if="sidebarOpen">{{ item.label }}</span>
        </NuxtLink>
      </nav>

      <div class="p-2 border-t border-surface-200 dark:border-surface-800">
        <Button
          :icon="sidebarOpen ? 'pi pi-angle-left' : 'pi pi-angle-right'"
          text severity="secondary"
          class="w-full"
          @click="sidebarOpen = !sidebarOpen"
          :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
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
          <Button
            :icon="mode === 'dark' ? 'pi pi-sun' : 'pi pi-moon'"
            text rounded severity="secondary"
            @click="() => toggle()"
            aria-label="Toggle dark mode"
          />
          <button
            class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
            @click="(e) => userMenu?.toggle(e)"
            aria-label="User menu"
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
