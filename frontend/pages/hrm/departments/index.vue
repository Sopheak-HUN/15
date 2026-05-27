<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Department } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const search = ref('')
const includeInactive = ref(false)
const page = ref(1)

const { data, refresh, pending } = await useAsyncData(
  'hrm-departments',
  () => hrm.listDepartments({ q: search.value, include_inactive: includeInactive.value, page: page.value, per_page: 25 }),
  { watch: [search, includeInactive, page] },
)
const rows = computed<Department[]>(() => data.value?.data?.data ?? [])
const meta = computed(() => data.value?.data)

// Employees list for the Manager picker. Loaded once on mount — small
// enough that paginating in a dropdown would be overkill. The dropdown
// is single-select and uses the employee UUID as its value.
const { data: empData } = await useAsyncData(
  'hrm-departments-employees',
  () => hrm.listEmployees({ status: 'active', per_page: 200 }),
)
const employees = computed(() => empData.value?.data?.data ?? [])

const dialogOpen = ref(false)
const editing    = ref<Department | null>(null)
const saving     = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  name: z.string().min(2).max(120),
  code: z.string().min(1).max(32),
  parent_id: z.string().uuid().nullable().optional(),
  manager_id: z.string().uuid().nullable().optional(),
  description: z.string().max(500).optional().or(z.literal('')),
  is_active: z.boolean(),
})))
const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { name: '', code: '', parent_id: null, manager_id: null, description: '', is_active: true },
})
const [name, nameAttrs]         = defineField('name')
const [code, codeAttrs]         = defineField('code')
const [parentId, parentAttrs]   = defineField('parent_id')
const [managerId, managerAttrs] = defineField('manager_id')
const [description, descAttrs]  = defineField('description')
const [isActive, isActiveAttrs] = defineField('is_active')

const parentOptions = computed(() =>
  rows.value
    .filter((r) => !editing.value || r.id !== editing.value.id)
    .map((r) => ({ label: r.name, value: r.id })),
)

const openCreate = () => {
  editing.value = null
  resetForm({ values: { name: '', code: '', parent_id: null, manager_id: null, description: '', is_active: true } })
  dialogOpen.value = true
}
const openEdit = (row: Department) => {
  editing.value = row
  setValues({
    name: row.name,
    code: row.code,
    parent_id: row.parent_id ?? null,
    manager_id: row.manager_id ?? null,
    description: row.description ?? '',
    is_active: row.is_active,
  })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = { ...values, description: values.description || null }
    if (editing.value) {
      await hrm.updateDepartment(editing.value.id, payload)
      toast.add({ severity: 'success', summary: t('hrm.departments.toast.updated'), life: 2000 })
    } else {
      await hrm.createDepartment(payload)
      toast.add({ severity: 'success', summary: t('hrm.departments.toast.created'), life: 2000 })
    }
    dialogOpen.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
})

const onDelete = (row: Department) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.name }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteDepartment(row.id)
      toast.add({ severity: 'success', summary: t('hrm.departments.toast.deleted'), life: 2000 })
      await refresh()
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.departments.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.departments.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <IconField icon-position="left">
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" :placeholder="t('common.search')" />
        </IconField>
        <Button :label="t('hrm.departments.new')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </div>

    <Card>
      <template #content>
        <DataTable
          :value="rows"
          :loading="pending"
          data-key="id"
          striped-rows
          class="text-sm"
        >
          <template #empty>
            <div class="py-10 text-center text-surface-500">
              {{ t('hrm.departments.empty') }}
            </div>
          </template>

          <Column field="code" :header="t('hrm.departments.fields.code')" sortable>
            <template #body="{ data }">
              <code class="font-mono text-xs">{{ data.code }}</code>
            </template>
          </Column>
          <Column field="name" :header="t('hrm.departments.fields.name')" sortable />
          <Column :header="t('hrm.departments.fields.parent')">
            <template #body="{ data }">
              <Tag v-if="data.parent" :value="data.parent.name" severity="info" />
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.departments.fields.manager')">
            <template #body="{ data }">
              <span v-if="data.manager">{{ data.manager.first_name }} {{ data.manager.last_name }}</span>
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.departments.fields.isActive')">
            <template #body="{ data }">
              <Tag :value="data.is_active ? t('hrm.common.active') : t('hrm.common.inactive')"
                   :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column header="" body-class="text-right" :style="{ width: '140px' }">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
              <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDelete(data)" />
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

    <Dialog
      v-model:visible="dialogOpen"
      modal
      :header="editing ? t('hrm.employees.dialog.editTitle', { name: editing.name }) : t('hrm.departments.new')"
      :style="{ width: '32rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSave">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.departments.fields.name')" required />
            <InputText v-model="name" v-bind="nameAttrs" class="w-full" :invalid="!!errors.name" :placeholder="t('hrm.departments.placeholders.name')" />
            <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
          </div>
          <div>
            <FormLabel :label="t('hrm.departments.fields.code')" required />
            <InputText v-model="code" v-bind="codeAttrs" class="w-full font-mono" :invalid="!!errors.code" :placeholder="t('hrm.departments.placeholders.code')" />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.departments.fields.parent')" />
            <Select v-model="parentId" v-bind="parentAttrs" :options="parentOptions" option-label="label" option-value="value" show-clear class="w-full" :placeholder="t('hrm.departments.placeholders.parent')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.departments.fields.manager')" />
            <Select
              v-model="managerId"
              v-bind="managerAttrs"
              :options="employees"
              option-value="id"
              filter
              show-clear
              class="w-full"
              :placeholder="t('hrm.departments.placeholders.manager')"
            >
              <template #option="{ option }">
                {{ option.first_name }} {{ option.last_name }}
                <code v-if="option.employee_id" class="font-mono text-xs text-surface-500 ml-1">({{ option.employee_id }})</code>
              </template>
              <template #value="{ value }">
                <span v-if="value">
                  <span v-for="emp in employees.filter((e) => e.id === value)" :key="emp.id">
                    {{ emp.first_name }} {{ emp.last_name }}
                  </span>
                </span>
              </template>
            </Select>
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.departments.fields.description')" />
          <Textarea v-model="description" v-bind="descAttrs" rows="3" class="w-full" :placeholder="t('hrm.departments.placeholders.description')" />
        </div>
        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="isActive" v-bind="isActiveAttrs" input-id="dept-active" />
          <label for="dept-active" class="text-sm">{{ t('hrm.departments.fields.isActive') }}</label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
