<script setup lang="ts">
import type { AuditLog } from '~/types/iam'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const { t } = useI18n()
const { data, pending, refresh } = await useAsyncData('iam-audit-logs', () => iam.listAuditLogs())

const logs = computed<AuditLog[]>(() => data.value?.data.data ?? [])
const total = computed(() => data.value?.data.total ?? 0)

const selectedLog = ref<AuditLog | null>(null)
const detailOpen = ref(false)

const openDetail = (log: AuditLog) => {
  selectedLog.value = log
  detailOpen.value = true
}

const actionSeverity = (action: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' => {
  if (action.startsWith('created')) return 'success'
  if (action.startsWith('updated')) return 'info'
  if (action.startsWith('deleted')) return 'danger'
  return 'secondary'
}

const shortType = (full: string) => full.split('\\').pop() ?? full
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('audit.title') }}</h1>
        <p class="text-surface-500 mt-1">
          <i18n-t keypath="audit.subtitle" tag="span">
            <template #total><Badge :value="total" /></template>
          </i18n-t>
        </p>
      </div>
      <Button :label="t('common.refresh')" icon="pi pi-refresh" severity="secondary" outlined :loading="pending" @click="refresh()" />
    </div>

    <Card>
      <template #content>
        <DataTable
          :value="logs"
          :loading="pending"
          striped-rows
          data-key="id"
          paginator
          :rows="15"
          :rows-per-page-options="[15, 30, 50]"
          row-hover
          class="text-sm"
          @row-click="(e) => openDetail(e.data as AuditLog)"
        >
          <template #empty>
            <div class="py-10 text-center text-surface-500">
              {{ t('audit.empty') }}
            </div>
          </template>

          <Column field="created_at" :header="t('audit.columns.when')" sortable :style="{ width: '200px' }">
            <template #body="{ data }">
              <span class="font-mono text-xs">{{ formatDateTime(data.created_at, 'DD-MM-yyyy HH:mm:ss') }}</span>
            </template>
          </Column>
          <Column field="action" :header="t('audit.columns.action')" :style="{ width: '120px' }">
            <template #body="{ data }">
              <Tag :value="data.action" :severity="actionSeverity(data.action)" />
            </template>
          </Column>
          <Column field="auditable_type" :header="t('audit.columns.entity')">
            <template #body="{ data }">
              <span class="font-medium">{{ shortType(data.auditable_type) }}</span>
              <span class="text-surface-400 ml-2 font-mono text-xs">{{ data.auditable_id }}</span>
            </template>
          </Column>
          <Column field="user_id" :header="t('audit.columns.actor')" :style="{ width: '180px' }">
            <template #body="{ data }">
              <span class="font-mono text-xs text-surface-500">{{ data.user_id || t('common.system') }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="detailOpen" modal :header="t('audit.detailTitle')" :style="{ width: '48rem' }">
      <div v-if="selectedLog" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-xs uppercase text-surface-500">{{ t('audit.columns.when') }}</div>
            <div class="font-mono">{{ formatDateTime(selectedLog.created_at, 'DD-MM-yyyy HH:mm:ss') }}</div>
          </div>
          <div>
            <div class="text-xs uppercase text-surface-500">{{ t('audit.columns.action') }}</div>
            <Tag :value="selectedLog.action" :severity="actionSeverity(selectedLog.action)" />
          </div>
          <div>
            <div class="text-xs uppercase text-surface-500">{{ t('audit.columns.entity') }}</div>
            <div>{{ shortType(selectedLog.auditable_type) }} <span class="font-mono text-xs text-surface-400">{{ selectedLog.auditable_id }}</span></div>
          </div>
          <div>
            <div class="text-xs uppercase text-surface-500">{{ t('audit.columns.actor') }}</div>
            <div class="font-mono text-xs">{{ selectedLog.user_id || t('common.system') }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-xs uppercase text-surface-500 mb-1">{{ t('audit.oldValues') }}</div>
            <pre class="text-xs bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded p-3 overflow-auto max-h-64">{{ selectedLog.old_values ? JSON.stringify(selectedLog.old_values, null, 2) : t('common.dash') }}</pre>
          </div>
          <div>
            <div class="text-xs uppercase text-surface-500 mb-1">{{ t('audit.newValues') }}</div>
            <pre class="text-xs bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded p-3 overflow-auto max-h-64">{{ selectedLog.new_values ? JSON.stringify(selectedLog.new_values, null, 2) : t('common.dash') }}</pre>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>
