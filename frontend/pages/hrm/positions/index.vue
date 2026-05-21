<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Position } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const page = ref(1)
const departmentFilter = ref<string | null>(null)

const { data: deptData } = await useAsyncData('hrm-positions-depts', () => hrm.listDepartments({ per_page: 200 }))
const departments = computed(() => deptData.value?.data?.data ?? [])

const { data, refresh, pending } = await useAsyncData(
  'hrm-positions',
  () => hrm.listPositions({ department_id: departmentFilter.value ?? undefined, page: page.value, per_page: 25 }),
  { watch: [page, departmentFilter] },
)
const rows = computed<Position[]>(() => data.value?.data?.data ?? [])
const meta = computed(() => data.value?.data)

const dialogOpen = ref(false)
const editing    = ref<Position | null>(null)
const saving     = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  title: z.string().min(2).max(120),
  code: z.string().min(1).max(32),
  department_id: z.string().uuid().nullable().optional(),
  min_salary: z.coerce.number().min(0).nullable().optional(),
  max_salary: z.coerce.number().min(0).nullable().optional(),
  is_active: z.boolean(),
})))
const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { title: '', code: '', department_id: null, min_salary: null, max_salary: null, is_active: true },
})
const [titleField, titleAttrs] = defineField('title')
const [codeField, codeAttrs]   = defineField('code')
const [deptField, deptAttrs]   = defineField('department_id')
const [minSalary, minAttrs]    = defineField('min_salary')
const [maxSalary, maxAttrs]    = defineField('max_salary')
const [isActive, activeAttrs]  = defineField('is_active')

const openCreate = () => {
  editing.value = null
  resetForm({ values: { title: '', code: '', department_id: null, min_salary: null, max_salary: null, is_active: true } })
  dialogOpen.value = true
}
const openEdit = (row: Position) => {
  editing.value = row
  setValues({
    title: row.title,
    code: row.code,
    department_id: row.department_id ?? null,
    min_salary: row.min_salary != null ? Number(row.min_salary) : null,
    max_salary: row.max_salary != null ? Number(row.max_salary) : null,
    is_active: row.is_active,
  })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    if (editing.value) {
      await hrm.updatePosition(editing.value.id, values)
      toast.add({ severity: 'success', summary: t('hrm.positions.toast.updated'), life: 2000 })
    } else {
      await hrm.createPosition(values)
      toast.add({ severity: 'success', summary: t('hrm.positions.toast.created'), life: 2000 })
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

const onDelete = (row: Position) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.title }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deletePosition(row.id)
      toast.add({ severity: 'success', summary: t('hrm.positions.toast.deleted'), life: 2000 })
      await refresh()
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.positions.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.positions.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Select
          v-model="departmentFilter"
          :options="departments"
          option-label="name"
          option-value="id"
          :placeholder="t('hrm.common.department')"
          show-clear
          class="w-56"
        />
        <Button :label="t('hrm.positions.new')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </div>

    <Card>
      <template #content>
        <DataTable :value="rows" :loading="pending" data-key="id" striped-rows class="text-sm">
          <template #empty>
            <div class="py-10 text-center text-surface-500">{{ t('hrm.positions.empty') }}</div>
          </template>

          <Column field="code" :header="t('hrm.positions.fields.code')" sortable>
            <template #body="{ data }">
              <code class="font-mono text-xs">{{ data.code }}</code>
            </template>
          </Column>
          <Column field="title" :header="t('hrm.positions.fields.title')" sortable />
          <Column :header="t('hrm.positions.fields.department')">
            <template #body="{ data }">
              <Tag v-if="data.department" :value="data.department.name" severity="info" />
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.positions.fields.minSalary')">
            <template #body="{ data }">
              <span v-if="data.min_salary">{{ data.min_salary }}</span>
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('hrm.positions.fields.maxSalary')">
            <template #body="{ data }">
              <span v-if="data.max_salary">{{ data.max_salary }}</span>
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
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
      :header="editing ? t('hrm.employees.dialog.editTitle', { name: editing.title }) : t('hrm.positions.new')"
      :style="{ width: '32rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSave">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.positions.fields.title')" required />
            <InputText v-model="titleField" v-bind="titleAttrs" class="w-full" :invalid="!!errors.title" :placeholder="t('hrm.positions.placeholders.title')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.positions.fields.code')" required />
            <InputText v-model="codeField" v-bind="codeAttrs" class="w-full font-mono" :placeholder="t('hrm.positions.placeholders.code')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.positions.fields.department')" />
          <Select v-model="deptField" v-bind="deptAttrs" :options="departments" option-label="name" option-value="id" show-clear class="w-full" :placeholder="t('hrm.positions.placeholders.department')" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.positions.fields.minSalary')" />
            <InputNumber v-model="minSalary" v-bind="minAttrs" mode="decimal" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.positions.placeholders.minSalary')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.positions.fields.maxSalary')" />
            <InputNumber v-model="maxSalary" v-bind="maxAttrs" mode="decimal" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.positions.placeholders.maxSalary')" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="isActive" v-bind="activeAttrs" input-id="pos-active" />
          <label for="pos-active" class="text-sm">{{ t('hrm.positions.fields.isActive') }}</label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
