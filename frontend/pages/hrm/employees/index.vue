<script setup lang="ts">
import type { Employee } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const search = ref('')
const statusFilter = ref<string | null>(null)
const deptFilter = ref<string | null>(null)
const page = ref(1)

const { data: deptData } = await useAsyncData('hrm-emp-depts', () => hrm.listDepartments({ per_page: 200 }))
const { data: posData }  = await useAsyncData('hrm-emp-positions', () => hrm.listPositions({ per_page: 200 }))
const departments = computed(() => deptData.value?.data?.data ?? [])
const positions   = computed(() => posData.value?.data?.data ?? [])

const { data, refresh, pending } = await useAsyncData(
  'hrm-employees',
  () => hrm.listEmployees({
    q: search.value || undefined,
    status: statusFilter.value || undefined,
    department_id: deptFilter.value || undefined,
    page: page.value,
    per_page: 25,
  }),
  { watch: [search, statusFilter, deptFilter, page] },
)
const rows = computed<Employee[]>(() => data.value?.data?.data ?? [])
const meta = computed(() => data.value?.data)

// Termination dialog (DELETE w/ body)
const termDialog = ref(false)
const termTarget = ref<Employee | null>(null)
const termReason = ref('')
const termEffective = ref('')
const termLoading = ref(false)

const openTerminate = (row: Employee) => {
  termTarget.value = row
  termReason.value = ''
  termEffective.value = ''
  termDialog.value = true
}
const onTerminate = async () => {
  if (!termTarget.value) return
  termLoading.value = true
  try {
    await hrm.terminateEmployee(termTarget.value.id, {
      reason: termReason.value || undefined,
      effective_at: termEffective.value || undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.employees.terminate.success'), life: 2500 })
    termDialog.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.employees.terminate.failed'), detail: data?.message, life: 4000 })
  } finally {
    termLoading.value = false
  }
}

const onRestore = (row: Employee) => {
  confirm.require({
    message: t('hrm.employees.actions.restore') + ` — ${row.first_name} ${row.last_name}?`,
    header: t('hrm.employees.actions.restore'),
    accept: async () => {
      await hrm.restoreEmployee(row.id)
      toast.add({ severity: 'success', summary: t('hrm.employees.toast.restored'), life: 2000 })
      await refresh()
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.employees.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.employees.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <NuxtLink to="/hrm/departments">
          <Button :label="t('hrm.employees.manageDepts')" icon="pi pi-sitemap" severity="secondary" text size="small" />
        </NuxtLink>
        <NuxtLink to="/hrm/positions">
          <Button :label="t('hrm.employees.managePositions')" icon="pi pi-tags" severity="secondary" text size="small" />
        </NuxtLink>
        <NuxtLink to="/hrm/employees/create">
          <Button :label="t('hrm.employees.new')" icon="pi pi-plus" />
        </NuxtLink>
      </div>
    </div>

    <Card>
      <template #content>
        <div class="flex flex-wrap gap-3 mb-4">
          <IconField icon-position="left" class="flex-1 min-w-64">
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" :placeholder="t('hrm.common.search')" class="w-full" />
          </IconField>
          <Select
            v-model="deptFilter"
            :options="departments"
            option-label="name"
            option-value="id"
            :placeholder="t('hrm.common.department')"
            show-clear
            class="w-56"
          />
          <Select
            v-model="statusFilter"
            :options="['active', 'terminated']"
            :placeholder="t('hrm.common.status')"
            show-clear
            class="w-44"
          />
        </div>

        <DataTable :value="rows" :loading="pending" data-key="id" striped-rows class="text-sm">
          <template #empty>
            <div class="py-10 text-center text-surface-500">{{ t('hrm.common.noResults') }}</div>
          </template>

          <Column field="employee_id" :header="t('hrm.employees.columns.employeeId')" sortable :style="{ width: '120px' }">
            <template #body="{ data }">
              <NuxtLink :to="`/hrm/employees/${data.id}`" class="text-primary-600 hover:underline">
                <code class="font-mono text-xs">{{ data.employee_id }}</code>
              </NuxtLink>
            </template>
          </Column>
          <Column :header="t('hrm.employees.columns.fullName')">
            <template #body="{ data }">
              <NuxtLink :to="`/hrm/employees/${data.id}`" class="flex items-center gap-3 group">
                <!-- Avatar: presigned GET URL from the model accessor; falls
                     back to initials when photo_path is null. -->
                <img
                  v-if="data.photo_url"
                  :src="data.photo_url"
                  :alt="`${data.first_name} ${data.last_name}`"
                  class="w-9 h-9 rounded-full object-cover ring-1 ring-surface-200 dark:ring-surface-700 flex-shrink-0"
                  loading="lazy"
                >
                <div
                  v-else
                  class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xs font-semibold uppercase ring-1 ring-primary-200 dark:ring-primary-900 flex-shrink-0"
                >
                  {{ (data.first_name?.[0] ?? '') + (data.last_name?.[0] ?? '') }}
                </div>
                <div class="min-w-0">
                  <div class="font-medium group-hover:text-primary-600 truncate">
                    {{ data.first_name }} {{ data.last_name }}
                  </div>
                  <div class="text-xs text-surface-500 truncate">{{ data.email }}</div>
                </div>
              </NuxtLink>
            </template>
          </Column>
          <Column :header="t('hrm.employees.columns.department')">
            <template #body="{ data }">
              <Tag v-if="data.department" :value="data.department.name" severity="info" />
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.employees.columns.position')">
            <template #body="{ data }">
              <span v-if="data.position">{{ data.position.title }}</span>
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.employees.columns.status')">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="data.status === 'active' ? 'success' : data.status === 'terminated' ? 'danger' : 'secondary'"
              />
            </template>
          </Column>
          <Column header="" body-class="text-right !py-2" :style="{ width: '180px' }">
            <template #body="{ data }">
              <NuxtLink :to="`/hrm/employees/${data.id}/edit`">
                <Button icon="pi pi-pencil" text rounded severity="secondary" />
              </NuxtLink>
              <Button
                v-if="!data.deleted_at"
                icon="pi pi-user-minus"
                text rounded severity="danger"
                :aria-label="t('hrm.employees.actions.terminate')"
                @click="openTerminate(data)"
              />
              <Button
                v-else
                icon="pi pi-undo"
                text rounded severity="success"
                :aria-label="t('hrm.employees.actions.restore')"
                @click="onRestore(data)"
              />
            </template>
          </Column>
        </DataTable>

        <Paginator
          v-if="meta && meta.last_page > 1"
          :rows="meta.per_page"
          :total-records="meta.total"
          :first="(meta.current_page - 1) * meta.per_page"
          @page="(e) => page = e.page + 1"
        />
      </template>
    </Card>

    <!-- Termination dialog -->
    <Dialog
      v-model:visible="termDialog"
      modal
      :header="termTarget ? t('hrm.employees.terminate.title', { name: `${termTarget.first_name} ${termTarget.last_name}` }) : ''"
      :style="{ width: '28rem' }"
    >
      <div class="space-y-4">
        <div>
          <FormLabel :label="t('hrm.employees.terminate.reason')" />
          <Textarea v-model="termReason" rows="3" class="w-full" :placeholder="t('hrm.employees.placeholders.terminationReason')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.employees.terminate.effectiveAt')" />
          <DatePicker v-model="termEffective as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="termDialog = false" />
        <Button :label="t('hrm.employees.terminate.confirm')" severity="danger" icon="pi pi-user-minus" :loading="termLoading" @click="onTerminate" />
      </template>
    </Dialog>
  </div>
</template>
