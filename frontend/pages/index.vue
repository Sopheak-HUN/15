<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const iam = useIamApi()

const { data: rolesRes } = await useAsyncData('home-roles', () => iam.listRoles())
const { data: permsRes } = await useAsyncData('home-perms', () => iam.listPermissions())
const { data: auditRes } = await useAsyncData('home-audit', () => iam.listAuditLogs())

const rolesCount = computed(() => rolesRes.value?.data.length ?? 0)
const permsCount = computed(() => permsRes.value?.data.length ?? 0)
const auditCount = computed(() => auditRes.value?.data.total ?? 0)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Welcome, {{ auth.user?.name }}</h1>
      <p class="text-surface-500 mt-1">
        You are signed in to tenant <span class="font-mono text-primary-700 dark:text-primary-300">{{ auth.tenant }}</span>.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <NuxtLink to="/iam/roles" class="block">
        <Card class="h-full hover:shadow-lg transition-shadow">
          <template #content>
            <div class="flex items-center gap-4">
              <div class="size-12 rounded-xl bg-primary-100 dark:bg-primary-950/50 grid place-items-center text-primary-700 dark:text-primary-300">
                <i class="pi pi-id-card text-xl" />
              </div>
              <div>
                <div class="text-sm text-surface-500">Roles</div>
                <div class="text-2xl font-semibold">{{ rolesCount }}</div>
              </div>
            </div>
          </template>
        </Card>
      </NuxtLink>

      <NuxtLink to="/iam/permissions" class="block">
        <Card class="h-full hover:shadow-lg transition-shadow">
          <template #content>
            <div class="flex items-center gap-4">
              <div class="size-12 rounded-xl bg-amber-100 dark:bg-amber-950/50 grid place-items-center text-amber-700 dark:text-amber-300">
                <i class="pi pi-key text-xl" />
              </div>
              <div>
                <div class="text-sm text-surface-500">Permissions</div>
                <div class="text-2xl font-semibold">{{ permsCount }}</div>
              </div>
            </div>
          </template>
        </Card>
      </NuxtLink>

      <NuxtLink to="/iam/audit-logs" class="block">
        <Card class="h-full hover:shadow-lg transition-shadow">
          <template #content>
            <div class="flex items-center gap-4">
              <div class="size-12 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 grid place-items-center text-emerald-700 dark:text-emerald-300">
                <i class="pi pi-history text-xl" />
              </div>
              <div>
                <div class="text-sm text-surface-500">Audit entries</div>
                <div class="text-2xl font-semibold">{{ auditCount }}</div>
              </div>
            </div>
          </template>
        </Card>
      </NuxtLink>
    </div>

    <Card>
      <template #title>Quick start</template>
      <template #content>
        <ol class="list-decimal pl-6 space-y-1 text-sm text-surface-700 dark:text-surface-300">
          <li>Define roles under <NuxtLink to="/iam/roles" class="text-primary-600 hover:underline">Roles</NuxtLink>.</li>
          <li>Review the permission catalog under <NuxtLink to="/iam/permissions" class="text-primary-600 hover:underline">Permissions</NuxtLink>.</li>
          <li>Track activity in <NuxtLink to="/iam/audit-logs" class="text-primary-600 hover:underline">Audit Logs</NuxtLink>.</li>
          <li>Customize tenant theme in <NuxtLink to="/iam/branding" class="text-primary-600 hover:underline">Branding</NuxtLink>.</li>
        </ol>
      </template>
    </Card>
  </div>
</template>
