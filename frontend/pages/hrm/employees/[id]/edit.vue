<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const hrm = useHrmApi()
const toast = useToast()
const { t } = useI18n()

const employeeIdParam = route.params.id as string

// Fetch Lookups
const { data: deptData } = await useAsyncData('hrm-emp-depts', () => hrm.listDepartments({ per_page: 200 }))
const { data: posData }  = await useAsyncData('hrm-emp-positions', () => hrm.listPositions({ per_page: 200 }))
const departments = computed(() => deptData.value?.data?.data ?? [])
const positions   = computed(() => posData.value?.data?.data ?? [])

// Fetch target employee details
const { data: detailData } = await useAsyncData(
  'hrm-employee-edit-detail',
  () => hrm.showEmployee(employeeIdParam)
)
const employee = computed(() => detailData.value?.data)

// Options for dropdowns
const genders = computed(() =>
  ['male', 'female', 'other', 'prefer_not_to_say'].map((v) => ({
    label: t(`hrm.employees.genders.${v}`),
    value: v,
  }))
)

const employmentTypes = computed(() =>
  ['full_time', 'part_time', 'contract', 'intern'].map((v) => ({
    label: t(`hrm.employees.employmentTypes.${v}`),
    value: v,
  }))
)

const payFrequencies = computed(() =>
  ['weekly', 'biweekly', 'monthly'].map((v) => ({
    label: t(`hrm.employees.payFrequencies.${v}`),
    value: v,
  }))
)

// Date conversion helpers
const datePreprocess = (val: unknown) => {
  if (val instanceof Date) {
    const year = val.getFullYear()
    const month = String(val.getMonth() + 1).padStart(2, '0')
    const day = String(val.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  if (typeof val === 'string' && val.trim() !== '') {
    return val.split('T')[0]
  }
  return null
}

const parseDate = (dStr: string | null | undefined) => {
  if (!dStr) return null
  const clean = dStr.split('T')[0] || ''
  const parts = clean.split('-')
  if (parts.length === 3) {
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
  }
  return new Date(clean)
}

// Validation Schema
const schema = toTypedSchema(
  z.object({
    employee_id: z.string().max(30).optional().or(z.literal('')),
    first_name: z.string().min(1, 'First name is required'),
    last_name: z.string().min(1, 'Last name is required'),
    email: z.string().email('Invalid email address').min(1, 'Email is required'),
    phone: z.string().max(30).nullable().optional(),
    date_of_birth: z.preprocess(datePreprocess, z.string().nullable().optional()),
    gender: z.string().nullable().optional(),
    hire_date: z.preprocess(datePreprocess, z.string().nullable().optional()),
    department_id: z.string().uuid().nullable().optional(),
    position_id: z.string().uuid().nullable().optional(),
    employment_type: z.string().nullable().optional(),
    base_salary: z.coerce.number().min(0).nullable().optional(),
    currency: z.string().max(3).optional().or(z.literal('')),
    pay_frequency: z.string().nullable().optional(),
    national_id: z.string().nullable().optional(),
    bank_account: z.string().nullable().optional(),
    tax_id: z.string().nullable().optional(),
    address: z.string().nullable().optional(),
    city: z.string().nullable().optional(),
    country: z.string().max(64).nullable().optional(),
  })
)

const { defineField, handleSubmit, errors, isSubmitting, setValues } = useForm({
  validationSchema: schema,
})

// Seeding values on mount or when employee details load
onMounted(() => {
  if (employee.value) {
    setValues({
      employee_id: employee.value.employee_id || '',
      first_name: employee.value.first_name || '',
      last_name: employee.value.last_name || '',
      email: employee.value.email || '',
      phone: employee.value.phone || '',
      date_of_birth: parseDate(employee.value.date_of_birth),
      gender: employee.value.gender || null,
      hire_date: parseDate(employee.value.hire_date),
      department_id: employee.value.department_id || null,
      position_id: employee.value.position_id || null,
      employment_type: employee.value.employment_type || 'full_time',
      base_salary: employee.value.base_salary ? Number(employee.value.base_salary) : null,
      currency: employee.value.currency || 'USD',
      pay_frequency: employee.value.pay_frequency || 'monthly',
      national_id: employee.value.national_id || '',
      bank_account: employee.value.bank_account || '',
      tax_id: employee.value.tax_id || '',
      address: employee.value.address || '',
      city: employee.value.city || '',
      country: employee.value.country || '',
    })
  }
})

// Define form fields
const [employeeId] = defineField('employee_id')
const [firstName] = defineField('first_name')
const [lastName] = defineField('last_name')
const [email] = defineField('email')
const [phone] = defineField('phone')
const [dob] = defineField('date_of_birth')
const [gender] = defineField('gender')
const [hireDate] = defineField('hire_date')
const [departmentId] = defineField('department_id')
const [positionId] = defineField('position_id')
const [employmentType] = defineField('employment_type')
const [baseSalary] = defineField('base_salary')
const [currency] = defineField('currency')
const [payFrequency] = defineField('pay_frequency')
const [nationalId] = defineField('national_id')
const [bankAccount] = defineField('bank_account')
const [taxId] = defineField('tax_id')
const [address] = defineField('address')
const [city] = defineField('city')
const [country] = defineField('country')

// Form Submit Handler
const onSubmit = handleSubmit(
  async (values) => {
    try {
      await hrm.updateEmployee(employeeIdParam, {
        employee_id: values.employee_id || undefined,
        first_name: values.first_name,
        last_name: values.last_name,
        email: values.email,
        phone: values.phone || undefined,
        date_of_birth: values.date_of_birth || undefined,
        gender: values.gender || undefined,
        hire_date: values.hire_date || undefined,
        department_id: values.department_id || undefined,
        position_id: values.position_id || undefined,
        employment_type: (values.employment_type as any) || undefined,
        base_salary: values.base_salary ?? undefined,
        currency: values.currency || undefined,
        pay_frequency: values.pay_frequency || undefined,
        national_id: values.national_id || undefined,
        bank_account: values.bank_account || undefined,
        tax_id: values.tax_id || undefined,
        address: values.address || undefined,
        city: values.city || undefined,
        country: values.country || undefined,
      })
      toast.add({ severity: 'success', summary: t('hrm.employees.toast.updated'), life: 2500 })
      router.push('/hrm/employees')
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } }).data
      toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
    }
  },
  ({ errors }) => {
    const firstError = Object.entries(errors)[0]
    if (firstError) {
      toast.add({
        severity: 'warn',
        summary: 'Form Validation Error',
        detail: `${firstError[0]}: ${firstError[1]}`,
        life: 5000,
      })
    }
  }
)
</script>

<template>
  <div class="space-y-6">
    <!-- Header with Back Button -->
    <div class="flex items-center gap-3">
      <NuxtLink to="/hrm/employees">
        <Button icon="pi pi-arrow-left" severity="secondary" rounded text class="hover:scale-105 transition-transform" />
      </NuxtLink>
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-50" v-if="employee">
          {{ t('hrm.employees.dialog.editTitle', { name: `${employee.first_name} ${employee.last_name}` }) }}
        </h1>
        <h1 class="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-50" v-else>
          Edit Employee
        </h1>
        <p class="text-surface-500 text-sm mt-0.5" v-if="employee">Updating profile for {{ employee.first_name }} {{ employee.last_name }}</p>
      </div>
    </div>

    <!-- Content Card -->
    <Card class="shadow-sm border border-surface-200/50 dark:border-surface-800">
      <template #content>
        <form class="space-y-6" @submit="onSubmit">
          <!-- Identity section -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.employeeId')" />
              <InputText v-model="employeeId" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.employeeId')" />
              <small class="text-surface-500 text-xs mt-1 block">{{ t('hrm.employees.fields.employeeIdHint') }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.firstName')" required />
              <InputText v-model="firstName" class="w-full" :invalid="!!errors.first_name" :placeholder="t('hrm.employees.placeholders.firstName')" />
              <small v-if="errors.first_name" class="text-red-500 text-xs mt-1 block">{{ errors.first_name }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.lastName')" required />
              <InputText v-model="lastName" class="w-full" :invalid="!!errors.last_name" :placeholder="t('hrm.employees.placeholders.lastName')" />
              <small v-if="errors.last_name" class="text-red-500 text-xs mt-1 block">{{ errors.last_name }}</small>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.email')" required />
              <InputText v-model="email" type="email" class="w-full" :invalid="!!errors.email" :placeholder="t('hrm.employees.placeholders.email')" />
              <small v-if="errors.email" class="text-red-500 text-xs mt-1 block">{{ errors.email }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.phone')" />
              <InputText v-model="phone" class="w-full" :invalid="!!errors.phone" :placeholder="t('hrm.employees.placeholders.phone')" />
              <small v-if="errors.phone" class="text-red-500 text-xs mt-1 block">{{ errors.phone }}</small>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.dateOfBirth')" />
              <DatePicker v-model="dob as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
              <small v-if="errors.date_of_birth" class="text-red-500 text-xs mt-1 block">{{ errors.date_of_birth }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.gender')" />
              <Select v-model="gender" :options="genders" option-label="label" option-value="value" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.gender')" />
              <small v-if="errors.gender" class="text-red-500 text-xs mt-1 block">{{ errors.gender }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.hireDate')" />
              <DatePicker v-model="hireDate as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
              <small v-if="errors.hire_date" class="text-red-500 text-xs mt-1 block">{{ errors.hire_date }}</small>
            </div>
          </div>

          <Divider align="left">
            <span class="text-xs font-semibold tracking-wider uppercase text-surface-400 dark:text-surface-500">{{ t('hrm.common.department') }} / {{ t('hrm.common.position') }}</span>
          </Divider>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.department')" />
              <Select v-model="departmentId" :options="departments" option-label="name" option-value="id" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.department')" />
              <small v-if="errors.department_id" class="text-red-500 text-xs mt-1 block">{{ errors.department_id }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.position')" />
              <Select v-model="positionId" :options="positions" option-label="title" option-value="id" show-clear class="w-full" :placeholder="t('hrm.employees.placeholders.position')" />
              <small v-if="errors.position_id" class="text-red-500 text-xs mt-1 block">{{ errors.position_id }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.employmentType')" />
              <Select v-model="employmentType" :options="employmentTypes" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.employees.placeholders.employmentType')" />
              <small v-if="errors.employment_type" class="text-red-500 text-xs mt-1 block">{{ errors.employment_type }}</small>
            </div>
          </div>

          <Divider align="left">
            <span class="text-xs font-semibold tracking-wider uppercase text-surface-400 dark:text-surface-500">{{ t('hrm.employees.sections.compensation') }}</span>
          </Divider>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.baseSalary')" />
              <InputNumber v-model="baseSalary" mode="decimal" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.employees.placeholders.baseSalary')" />
              <small v-if="errors.base_salary" class="text-red-500 text-xs mt-1 block">{{ errors.base_salary }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.currency')" />
              <InputText v-model="currency" class="w-full font-mono" maxlength="3" :placeholder="t('hrm.employees.placeholders.currency')" />
              <small v-if="errors.currency" class="text-red-500 text-xs mt-1 block">{{ errors.currency }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.payFrequency')" />
              <Select v-model="payFrequency" :options="payFrequencies" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.employees.placeholders.payFrequency')" />
              <small v-if="errors.pay_frequency" class="text-red-500 text-xs mt-1 block">{{ errors.pay_frequency }}</small>
            </div>
          </div>

          <Divider align="left">
            <span class="text-xs font-semibold tracking-wider uppercase text-surface-400 dark:text-surface-500">{{ t('hrm.employees.sections.pii') }}</span>
          </Divider>
          <Message severity="info" :closable="false" class="!my-0">
            <span class="text-xs">{{ t('hrm.employees.fields.piiNote') }}</span>
          </Message>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <FormLabel :label="t('hrm.employees.fields.nationalId')" />
              <InputText v-model="nationalId" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.nationalId')" />
              <small v-if="errors.national_id" class="text-red-500 text-xs mt-1 block">{{ errors.national_id }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.bankAccount')" />
              <InputText v-model="bankAccount" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.bankAccount')" />
              <small v-if="errors.bank_account" class="text-red-500 text-xs mt-1 block">{{ errors.bank_account }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.taxId')" />
              <InputText v-model="taxId" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.taxId')" />
              <small v-if="errors.tax_id" class="text-red-500 text-xs mt-1 block">{{ errors.tax_id }}</small>
            </div>
          </div>

          <Divider align="left">
            <span class="text-xs font-semibold tracking-wider uppercase text-surface-400 dark:text-surface-500">{{ t('hrm.employees.sections.address') }}</span>
          </Divider>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <FormLabel :label="t('hrm.employees.fields.address')" />
              <InputText v-model="address" class="w-full" :placeholder="t('hrm.employees.placeholders.address')" />
              <small v-if="errors.address" class="text-red-500 text-xs mt-1 block">{{ errors.address }}</small>
            </div>
            <div>
              <FormLabel :label="t('hrm.employees.fields.city')" />
              <InputText v-model="city" class="w-full" :placeholder="t('hrm.employees.placeholders.city')" />
              <small v-if="errors.city" class="text-red-500 text-xs mt-1 block">{{ errors.city }}</small>
            </div>
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.country')" />
            <InputText v-model="country" class="w-full" maxlength="64" :placeholder="t('hrm.employees.placeholders.country')" />
            <small v-if="errors.country" class="text-red-500 text-xs mt-1 block">{{ errors.country }}</small>
          </div>

          <div class="flex justify-end gap-3 pt-4">
            <NuxtLink to="/hrm/employees">
              <Button type="button" :label="t('common.cancel')" severity="secondary" text class="hover:bg-surface-100 dark:hover:bg-surface-800" />
            </NuxtLink>
            <Button type="submit" :label="t('common.save')" icon="pi pi-check" :loading="isSubmitting" class="hover:scale-[1.02] active:scale-[0.98] transition-transform duration-100" />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
