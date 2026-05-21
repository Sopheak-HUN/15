<script setup lang="ts">
import type { Permission } from '~/types/iam'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const { data, pending } = await useAsyncData('iam-permissions-list', () => iam.listPermissions())
const perms = computed<Permission[]>(() => data.value?.data ?? [])

const search = ref('')
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return perms.value
  return perms.value.filter((p) =>
    p.name.toLowerCase().includes(q) ||
    (p.description?.toLowerCase() ?? '').includes(q),
  )
})

interface PermGroup {
  module: string
  perms: Permission[]
}

const grouped = computed<PermGroup[]>(() => {
  const map = new Map<string, Permission[]>()
  for (const p of filtered.value) {
    const mod = p.name.split('.')[0] ?? 'other'
    const list = map.get(mod) ?? []
    list.push(p)
    map.set(mod, list)
  }
  return Array.from(map.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([module, perms]) => ({ module, perms: perms.sort((a, b) => a.name.localeCompare(b.name)) }))
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Permission catalog</h1>
        <p class="text-surface-500 mt-1">
          Permissions follow the <code>module.feature.action</code> pattern. Assign them to roles under
          <NuxtLink to="/iam/roles" class="text-primary-600 hover:underline">Roles</NuxtLink>.
        </p>
      </div>
      <IconField icon-position="left" class="w-full sm:w-72">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Search permissions..." class="w-full" />
      </IconField>
    </div>

    <div v-if="pending" class="py-16 text-center">
      <ProgressSpinner />
    </div>

    <div v-else-if="!perms.length">
      <Card>
        <template #content>
          <div class="py-12 text-center">
            <i class="pi pi-key text-4xl text-surface-300 mb-3" />
            <p class="text-surface-500">No permissions seeded yet. Run <code class="font-mono">php artisan tenants:seed</code> on the backend.</p>
          </div>
        </template>
      </Card>
    </div>

    <div v-else class="space-y-4">
      <Card v-for="group in grouped" :key="group.module">
        <template #title>
          <div class="flex items-center gap-3">
            <Tag :value="group.module" severity="info" class="font-mono" />
            <span class="text-sm text-surface-500">{{ group.perms.length }} permission{{ group.perms.length === 1 ? '' : 's' }}</span>
          </div>
        </template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            <div
              v-for="p in group.perms"
              :key="p.id"
              class="p-3 rounded-lg border border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900/50"
            >
              <div class="font-mono text-sm text-primary-700 dark:text-primary-300">{{ p.name }}</div>
              <div v-if="p.description" class="text-xs text-surface-500 mt-1">{{ p.description }}</div>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>
