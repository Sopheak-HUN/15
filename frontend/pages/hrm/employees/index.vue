<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
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

const dialogOpen = ref(false)
const editing    = ref<Employee | null>(null)
const saving     = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  employee_id: z.string().min(1).max(32).optional().or(z.literal('')),
  first_name: z.string().min(1).max(80),
  last_name: z.string().min(1).max(80),
  email: z.string().email(),
  phone: z.string().max(32).optional().or(z.literal('')),
  date_of_birth: z.string().optional().or(z.literal('')),
  gender: z.string().optional().or(z.literal('')),
  department_id: z.string().uuid().nullable().optional(),
  position_id: z.string().uuid().nullable().optional(),
  hire_date: z.string().optional().or(z.literal('')),
  employment_type: z.enum(['full_time', 'part_time', 'contract', 'intern']),
  base_salary: z.coerce.number().min(0).nullable().optional(),
  currency: z.string().length(3).optional().or(z.literal('')),
  pay_frequency: z.enum(['weekly', 'biweekly', 'monthly']).optional(),
  national_id: z.string().optional().or(z.literal('')),
  bank_account: z.string().optional().or(z.literal('')),
  tax_id: z.string().optional().or(z.literal('')),
  address: z.string().optional().or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  country: z.string().optional().or(z.literal('')),
})))

const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    employee_id: '', first_name: '', last_name: '', email: '',
    phone: '', date_of_birth: '', gender: '',
    department_id: null, position_id: null,
    hire_date: '', employment_type: 'full_time',
    base_salary: null, currency: 'USD', pay_frequency: 'monthly',
    national_id: '', bank_account: '', tax_id: '',
    address: '', city: '', country: '',
  },
})
const [employeeIdField] = defineField('employee_id')
const [firstName] = defineField('first_name')
const [lastName] = defineField('last_name')
const [email] = defineField('email')
const [phone] = defineField('phone')
const [department] = defineField('department_id')
const [position] = defineField('position_id')
const [hireDate] = defineField('hire_date')
const [empType] = defineField('employment_type')
const [baseSalary] = defineField('base_salary')
const [currency] = defineField('currency')
const [payFreq] = defineField('pay_frequency')
const [nationalId] = defineField('national_id')
const [bankAccount] = defineField('bank_account')
const [taxId] = defineField('tax_id')
const [address] = defineField('address')
const [city] = defineField('city')
const [country] = defineField('country')
const [gender] = defineField('gender')
const [dob] = defineField('date_of_birth')

const employmentTypes = computed(() => (['full_time', 'part_time', 'contract', 'intern'] as const).map((v) => ({
  label: t(`hrm.employees.employmentTypes.${v}`), value: v,
})))
const payFrequencies = computed(() => (['weekly', 'biweekly', 'monthly'] as const).map((v) => ({
  label: t(`hrm.employees.payFrequencies.${v}`), value: v,
})))
const genders = computed(() => (['male', 'female', 'other', 'prefer_not_to_say'] as const).map((v) => ({
  label: t(`hrm.employees.genders.${v}`), value: v,
})))

const openCreate = () => {
  editing.value = null
  resetForm()
  dialogOpen.value = true
}

const openEdit = async (row: Employee) => {
  editing.value = row
  setValues({
    employee_id: row.employee_id,
    first_name: row.first_name,
    last_name: row.last_name,
    email: row.email,
    phone: row.phone ?? '',
    date_of_birth: row.date_of_birth ?? '',
    gender: row.gender ?? '',
    department_id: row.department_id ?? null,
    position_id: row.position_id ?? null,
    hire_date: row.hire_date ?? '',
    employment_type: row.employment_type,
    currency: row.currency || 'USD',
    pay_frequency: (row.pay_frequency as 'monthly' | 'weekly' | 'biweekly') || 'monthly',
    address: row.address ?? '',
    city: row.city ?? '',
    country: row.country ?? '',
  })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...values }
    // Empty-string PII fields shouldn't overwrite existing encrypted values.
    for (const k of ['national_id', 'bank_account', 'tax_id', 'employee_id'] as const) {
      if (payload[k] === '') delete payload[k]
    }
    if (editing.value) {
      await hrm.updateEmployee(editing.value.id, payload)
      toast.add({ severity: 'success', summary: t('hrm.employees.toast.updated'), life: 2000 })
    } else {
      await hrm.createEmployee(payload)
      toast.add({ severity: 'success', summary: t('hrm.employees.toast.created'), life: 2000 })
    }
    dialogOpen.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    saving.value = false
  }
})

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
        <Button :label="t('hrm.employees.new')" icon="pi pi-plus" @click="openCreate" />
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

          <Column field="employee_id" :header="t('hrm.employees.columns.employeeId')" sortable>
            <template #body="{ data }">
              <NuxtLink :to="`/hrm/employees/${data.id}`" class="text-primary-600 hover:underline">
                <code class="font-mono text-xs">{{ data.employee_id }}</code>
              </NuxtLink>
            </template>
          </Column>
          <Column :header="t('hrm.employees.columns.fullName')">
            <template #body="{ data }">
              <NuxtLink :to="`/hrm/employees/${data.id}`" class="font-medium hover:text-primary-600">
                {{ data.first_name }} {{ data.last_name }}
              </NuxtLink>
              <div class="text-xs text-surface-500">{{ data.email }}</div>
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
              <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
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

    <!-- Create / Edit dialog -->
    <Dialog
      v-model:visible="dialogOpen"
      modal
      :header="editing ? t('hrm.employees.dialog.editTitle', { name: `${editing.first_name} ${editing.last_name}` }) : t('hrm.employees.dialog.createTitle')"
      :style="{ width: '52rem' }"
    >
      <form class="space-y-6" @submit.prevent="onSave">
        <!-- Identity -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.employeeId')" />
            <InputText v-model="employeeIdField" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.employeeId')" />
            <small class="text-surface-500 text-xs">{{ t('hrm.employees.fields.employeeIdHint') }}</small>
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.firstName')" required />
            <InputText v-model="firstName" class="w-full" :invalid="!!errors.first_name" :placeholder="t('hrm.employees.placeholders.firstName')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.lastName')" required />
            <InputText v-model="lastName" class="w-full" :invalid="!!errors.last_name" :placeholder="t('hrm.employees.placeholders.lastName')" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.email')" required />
            <InputText v-model="email" type="email" class="w-full" :invalid="!!errors.email" :placeholder="t('hrm.employees.placeholders.email')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.phone')" />
            <InputText v-model="phone" class="w-full" :placeholder="t('hrm.employees.placeholders.phone')" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.dateOfBirth')" />
            <DatePicker v-model="dob" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.gender')" />
            <Select v-model="gender" :options="genders" option-label="label" option-value="value" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.gender')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.hireDate')" />
            <DatePicker v-model="hireDate" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>

        <Divider align="left"><span class="text-xs uppercase text-surface-500">{{ t('hrm.common.department') }} / {{ t('hrm.common.position') }}</span></Divider>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.department')" />
            <Select v-model="department" :options="departments" option-label="name" option-value="id" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.department')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.position')" />
            <Select v-model="position" :options="positions" option-label="title" option-value="id" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.position')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.employmentType')" />
            <Select v-model="empType" :options="employmentTypes" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.employees.placeholders.employmentType')" />
          </div>
        </div>

        <Divider align="left"><span class="text-xs uppercase text-surface-500">{{ t('hrm.employees.sections.compensation') }}</span></Divider>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.baseSalary')" />
            <InputNumber v-model="baseSalary" mode="decimal" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.employees.placeholders.baseSalary')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.currency')" />
            <InputText v-model="currency" class="w-full font-mono" maxlength="3" :placeholder="t('hrm.employees.placeholders.currency')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.payFrequency')" />
            <Select v-model="payFreq" :options="payFrequencies" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.employees.placeholders.payFrequency')" />
          </div>
        </div>

        <Divider align="left">
          <span class="text-xs uppercase text-surface-500">{{ t('hrm.employees.sections.pii') }}</span>
        </Divider>
        <Message severity="info" :closable="false" class="!my-0">
          <span class="text-xs">{{ t('hrm.employees.fields.piiNote') }}</span>
        </Message>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.nationalId')" />
            <InputText v-model="nationalId" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.nationalId')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.bankAccount')" />
            <InputText v-model="bankAccount" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.bankAccount')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.taxId')" />
            <InputText v-model="taxId" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.taxId')" />
          </div>
        </div>

        <Divider align="left">
          <span class="text-xs uppercase text-surface-500">{{ t('hrm.employees.sections.address') }}</span>
        </Divider>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <FormLabel :label="t('hrm.employees.fields.address')" />
            <InputText v-model="address" class="w-full" :placeholder="t('hrm.employees.placeholders.address')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.city')" />
            <InputText v-model="city" class="w-full" :placeholder="t('hrm.employees.placeholders.city')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.employees.fields.country')" />
          <InputText v-model="country" class="w-full" maxlength="64" :placeholder="t('hrm.employees.placeholders.country')" />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

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
          <DatePicker v-model="termEffective" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="termDialog = false" />
        <Button :label="t('hrm.employees.terminate.confirm')" severity="danger" icon="pi pi-user-minus" :loading="termLoading" @click="onTerminate" />
      </template>
    </Dialog>
  </div>
</template>
