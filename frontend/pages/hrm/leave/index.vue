<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { LeaveRequest, LeaveType } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const uploads = useUpload()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
const { has } = usePermissions()

const tab = ref<'requests' | 'types'>('requests')

// Only employees-write users (HR admins) get a free employee picker.
// Staff submitting their own leave see a locked selector pre-filled with
// themselves — they can't request leave for someone else.
const canPickEmployee = computed(() => has('hrm.employee.write'))

// ---------- shared lookups ----------
// Current user's employee profile — used as the default + locked value
// for the employee selector when the user lacks employee-write. Fails
// gracefully (data stays null) for admin accounts that aren't linked
// to an employee row.
const { data: meData } = await useAsyncData('hrm-leave-me', () => hrm.me())
const myEmployee = computed(() => meData.value?.data ?? null)

const { data: empData } = await useAsyncData('hrm-leave-employees', () => hrm.listEmployees({ status: 'active', per_page: 200 }))
const employees = computed(() => empData.value?.data?.data ?? [])

const { data: typesData, refresh: refreshTypes } = await useAsyncData('hrm-leave-types', () => hrm.listLeaveTypes())
const leaveTypes = computed<LeaveType[]>(() => typesData.value?.data ?? [])

// ---------- Requests tab ----------
const reqStatus = ref<string | null>(null)
const reqFrom   = ref<string | null>(null)
const reqTo     = ref<string | null>(null)
const reqPage   = ref(1)

const { data: reqData, refresh: refreshRequests, pending: reqPending } = await useAsyncData(
  'hrm-leave-requests',
  () => hrm.listLeaveRequests({
    status: reqStatus.value || undefined,
    from: reqFrom.value || undefined,
    to: reqTo.value || undefined,
    page: reqPage.value, per_page: 25,
  }),
  { watch: [reqStatus, reqFrom, reqTo, reqPage] },
)
const requests = computed<LeaveRequest[]>(() => reqData.value?.data?.data ?? [])
const reqMeta  = computed(() => reqData.value?.data)

const statusOptions = computed(() => ['pending', 'approved', 'rejected'])

// ---------- New request dialog ----------
const reqDialog = ref(false)
const reqSaving = ref(false)
const reqSchema = computed(() => {
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

  return toTypedSchema(z.object({
    employee_id: z.string().uuid(),
    leave_type_id: z.string().uuid(),
    duration_type: z.enum(['full_day', 'half_day']),
    start_date: z.preprocess(datePreprocess, z.string().min(1, 'Start date is required')),
    end_date: z.preprocess(datePreprocess, z.string().min(1, 'End date is required')),
    days: z.coerce.number().min(0.5).nullable().optional(),
    reason: z.string().min(1, 'Reason is required').max(500),
    assign_to: z.string().uuid().nullable().optional().or(z.literal('')),
    reference_path: z.string().nullable().optional().or(z.literal('')),
  }))
})
const { defineField, handleSubmit, errors, resetForm, setFieldValue } = useForm({
  validationSchema: reqSchema,
  initialValues: {
    employee_id: '',
    leave_type_id: '',
    duration_type: 'full_day' as 'full_day' | 'half_day',
    start_date: '',
    end_date: '',
    days: null,
    reason: '',
    assign_to: '',
    reference_path: '',
  },
})
const [employeeId] = defineField('employee_id')
const [leaveTypeId] = defineField('leave_type_id')
const [durationType] = defineField('duration_type')
const [startDate] = defineField('start_date')
const [endDate] = defineField('end_date')
const [days] = defineField('days')
const [reason] = defineField('reason')
const [assignTo] = defineField('assign_to')
const [referencePath] = defineField('reference_path')

// ── Half-day forces a single-day window ────────────────────────
// When the user picks Half Day the end_date is locked to start_date
// and `days` is fixed at 0.5. The backend enforces this too.
watch(durationType, (val) => {
  if (val === 'half_day') {
    setFieldValue('end_date', startDate.value)
    setFieldValue('days', 0.5)
    daysTouched.value = true   // suppress auto-recompute
  } else {
    daysTouched.value = false
  }
})
watch(startDate, (val) => {
  if (durationType.value === 'half_day') {
    setFieldValue('end_date', val)
    setFieldValue('days', 0.5)
  }
})

// ── Leave Balance preview ──────────────────────────────────────
// Fetches the requester's balances when they're selected, then we
// filter to the chosen leave_type_id. The number shown is `available()`
// from the backend ( balance + accrued - used - pending ).
const balances = ref<{ leave_type_id: string; balance: number; used: number; pending: number }[]>([])
const balancesLoading = ref(false)
watch(employeeId, async (val) => {
  balances.value = []
  if (!val) return
  balancesLoading.value = true
  try {
    const resp = await hrm.employeeLeaveBalances(val)
    balances.value = (resp?.data ?? []).map((b) => ({
      leave_type_id: b.leave_type_id,
      balance: Number(b.balance ?? 0),
      used: Number(b.used ?? 0),
      pending: Number(b.pending ?? 0),
    }))
  } catch {
    /* silently ignore — UI just shows '—' */
  } finally {
    balancesLoading.value = false
  }
})

const currentBalanceAvailable = computed<number | null>(() => {
  if (!leaveTypeId.value) return null
  const b = balances.value.find((x) => x.leave_type_id === leaveTypeId.value)
  if (!b) return null
  return Math.max(0, b.balance - b.used - b.pending)
})

// ── Reference file upload ──────────────────────────────────────
const referenceFileInput = ref<HTMLInputElement | null>(null)
const referenceFileName = ref<string | null>(null)
const referenceUploading = ref(false)

const onReferenceChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !employeeId.value) {
    if (!employeeId.value) {
      toast.add({ severity: 'warn', summary: t('hrm.leave.form.pickEmployeeFirst'), life: 3000 })
    }
    return
  }
  referenceUploading.value = true
  try {
    const result = await uploads.uploadLeaveReference(employeeId.value, file)
    referenceFileName.value = file.name
    setFieldValue('reference_path', result.key)
    toast.add({ severity: 'success', summary: t('hrm.notes.toast.fileUploaded'), life: 1800 })
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : String(err)
    toast.add({ severity: 'error', summary: t('hrm.notes.toast.uploadFailed'), detail: msg, life: 4500 })
  } finally {
    referenceUploading.value = false
    if (referenceFileInput.value) referenceFileInput.value.value = ''
  }
}
const clearReference = () => {
  referenceFileName.value = null
  setFieldValue('reference_path', '')
  if (referenceFileInput.value) referenceFileInput.value.value = ''
}

const openNewRequest = () => {
  resetForm()
  daysTouched.value = false
  referenceFileName.value = null
  balances.value = []
  // Staff (no hrm.employee.write) must always submit leave for themselves.
  // Pre-set their own employee id so the balance preview and submit work
  // without them touching the (disabled) employee selector.
  if (!canPickEmployee.value && myEmployee.value?.id) {
    setFieldValue('employee_id', myEmployee.value.id)
  }
  reqDialog.value = true
}

// ── Detail dialog ──────────────────────────────────────────────
// Row click opens a read-only view backed by GET /leave-requests/{id}
// which eager-loads employee, leave_type and approver. The list rows
// already carry employee + leave_type so we can render immediately
// and refresh with the fuller payload once the fetch resolves.
const detailDialog = ref(false)
const detail = ref<LeaveRequest | null>(null)
const detailLoading = ref(false)

const openDetail = async (row: LeaveRequest) => {
  detail.value = row
  detailDialog.value = true
  detailLoading.value = true
  try {
    const resp = await hrm.showLeaveRequest(row.id)
    if (resp?.data) detail.value = resp.data
  } catch (err: unknown) {
    const msg = (err as { data?: { message?: string } }).data?.message
    toast.add({ severity: 'warn', summary: t('hrm.common.loadFailed'), detail: msg, life: 4000 })
  } finally {
    detailLoading.value = false
  }
}

// ── Auto-compute days when start/end change ────────────────────
// Inclusive calendar-day count (June 1 → June 5 = 5 days), matching
// LeaveService::submitRequest's `diffInDays + 1` on the backend.
// We only overwrite the field when the user hasn't manually typed a
// value — touching `days` once locks it.
const daysTouched = ref(false)
watch(days, (val, oldVal) => {
  // Treat any user edit as "touched". Programmatic writes from this
  // watcher land via setFieldValue, which doesn't toggle the flag.
  if (val !== oldVal && val !== computedDays.value) daysTouched.value = true
})
const computedDays = computed<number | null>(() => {
  if (!startDate.value || !endDate.value) return null
  const sStr = String(startDate.value).split('T')[0] ?? ''
  const eStr = String(endDate.value).split('T')[0] ?? ''
  if (!sStr || !eStr) return null
  const s = new Date(sStr)
  const e = new Date(eStr)
  if (isNaN(s.getTime()) || isNaN(e.getTime()) || e < s) return null
  return Math.round((e.getTime() - s.getTime()) / 86400000) + 1
})
watch(computedDays, (val) => {
  if (val == null) return
  if (daysTouched.value) return
  days.value = val
})

const onSubmitRequest = handleSubmit(
  async (values) => {
    reqSaving.value = true
    try {
      await hrm.submitLeaveRequest({
        employee_id: values.employee_id,
        leave_type_id: values.leave_type_id,
        duration_type: values.duration_type,
        start_date: values.start_date,
        // Half-day collapses end → start on the server too; sending start
        // here for safety even though the backend ignores it for half_day.
        end_date: values.duration_type === 'half_day' ? values.start_date : values.end_date,
        days: values.days ?? undefined,
        reason: values.reason,
        assign_to: values.assign_to || null,
        reference_path: values.reference_path || null,
      })
      toast.add({ severity: 'success', summary: t('hrm.leave.toast.submitted'), life: 2500 })
      reqDialog.value = false
      await refreshRequests()
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } }).data
      toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
    } finally {
      reqSaving.value = false
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

// Approve/reject are only triggered from the detail modal now (no list
// row actions). `detailActionLoading` drives the in-modal button spinner
// so the user sees feedback without the dialog flicker-closing.
const detailActionLoading = ref(false)
const approveFromDetail = async () => {
  if (!detail.value) return
  detailActionLoading.value = true
  try {
    await hrm.approveLeaveRequest(detail.value.id)
    toast.add({ severity: 'success', summary: t('hrm.leave.toast.approved'), life: 2000 })
    detailDialog.value = false
    await refreshRequests()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', detail: data?.message, summary: t('hrm.common.saveFailed'), life: 5000 })
  } finally {
    detailActionLoading.value = false
  }
}

const rejectDialog = ref(false)
const rejectTarget = ref<LeaveRequest | null>(null)
const rejectReason = ref('')
const rejectSaving = ref(false)
const openRejectFromDetail = () => {
  if (!detail.value) return
  rejectTarget.value = detail.value
  rejectReason.value = ''
  detailDialog.value = false
  rejectDialog.value = true
}
const onReject = async () => {
  if (!rejectTarget.value) return
  rejectSaving.value = true
  try {
    await hrm.rejectLeaveRequest(rejectTarget.value.id, rejectReason.value || undefined)
    toast.add({ severity: 'success', summary: t('hrm.leave.toast.rejected'), life: 2000 })
    rejectDialog.value = false
    await refreshRequests()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', detail: data?.message, summary: t('hrm.common.saveFailed'), life: 5000 })
  } finally {
    rejectSaving.value = false
  }
}

// ---------- Types tab ----------
const typeDialog = ref(false)
const editingType = ref<LeaveType | null>(null)
const typeSaving = ref(false)
const typeSchema = toTypedSchema(z.object({
  name: z.string().min(2).max(80),
  code: z.string().min(1).max(32),
  default_balance: z.coerce.number().min(0),
  is_paid: z.boolean(),
  accrues: z.boolean(),
  requires_approval: z.boolean(),
  color: z.string().max(32).optional().or(z.literal('')),
}))
const { defineField: tField, handleSubmit: handleType, errors: tErrors, resetForm: resetType, setValues: setType } = useForm({
  validationSchema: typeSchema,
  initialValues: { name: '', code: '', default_balance: 0, is_paid: true, accrues: true, requires_approval: true, color: '' },
})
const [tName] = tField('name')
const [tCode] = tField('code')
const [tBalance] = tField('default_balance')
const [tPaid] = tField('is_paid')
const [tAccrues] = tField('accrues')
const [tApproval] = tField('requires_approval')
const [tColor] = tField('color')

const openTypeCreate = () => {
  editingType.value = null
  resetType()
  typeDialog.value = true
}
const openTypeEdit = (row: LeaveType) => {
  editingType.value = row
  setType({
    name: row.name,
    code: row.code,
    default_balance: Number(row.default_balance),
    is_paid: row.is_paid,
    accrues: row.accrues,
    requires_approval: row.requires_approval,
    color: row.color ?? '',
  })
  typeDialog.value = true
}

const onSaveType = handleType(async (values) => {
  typeSaving.value = true
  try {
    if (editingType.value) {
      await hrm.updateLeaveType(editingType.value.id, values)
      toast.add({ severity: 'success', summary: t('hrm.leave.toast.typeUpdated'), life: 2000 })
    } else {
      await hrm.createLeaveType(values)
      toast.add({ severity: 'success', summary: t('hrm.leave.toast.typeCreated'), life: 2000 })
    }
    typeDialog.value = false
    await refreshTypes()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    typeSaving.value = false
  }
})

const onDeleteType = (row: LeaveType) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.name }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteLeaveType(row.id)
      toast.add({ severity: 'success', summary: t('hrm.leave.toast.typeDeleted'), life: 2000 })
      await refreshTypes()
    },
  })
}

const statusSeverity = (s: string) =>
  s === 'approved' ? 'success' : s === 'rejected' ? 'danger' : s === 'pending' ? 'warn' : 'secondary'
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.leave.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.leave.subtitle') }}</p>
      </div>
      <Button
        v-if="tab === 'requests'"
        :label="t('hrm.leave.newRequest')"
        icon="pi pi-plus"
        @click="openNewRequest"
      />
      <Button
        v-else
        :label="t('hrm.leave.newType')"
        icon="pi pi-plus"
        @click="openTypeCreate"
      />
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="requests">{{ t('hrm.leave.tabs.requests') }}</Tab>
        <Tab value="types">{{ t('hrm.leave.tabs.types') }}</Tab>
      </TabList>
      <TabPanels>
        <!-- Requests -->
        <TabPanel value="requests">
          <Card>
            <template #content>
              <div class="flex flex-wrap items-center gap-3 mb-4">
                <Select
                  v-model="reqStatus"
                  :options="statusOptions"
                  :placeholder="t('hrm.leave.filters.anyStatus')"
                  show-clear
                  class="w-44"
                />
                <DatePicker v-model="reqFrom as any" date-format="yy-mm-dd" :placeholder="t('hrm.leave.filters.from')" class="w-44" />
                <DatePicker v-model="reqTo as any"   date-format="yy-mm-dd" :placeholder="t('hrm.leave.filters.to')"   class="w-44" />
              </div>

              <DataTable
                :value="requests"
                :loading="reqPending"
                data-key="id"
                striped-rows
                row-hover
                class="text-sm cursor-pointer"
                @row-click="(e) => openDetail(e.data as LeaveRequest)"
              >
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.leave.requests.empty') }}</div>
                </template>

                <Column :header="t('hrm.leave.requests.columns.employee')">
                  <template #body="{ data }">
                    <div class="font-medium">{{ data.employee?.first_name }} {{ data.employee?.last_name }}</div>
                    <code v-if="data.employee?.employee_id" class="font-mono text-xs text-surface-500">{{ data.employee.employee_id }}</code>
                  </template>
                </Column>
                <Column :header="t('hrm.leave.requests.columns.type')">
                  <template #body="{ data }">
                    <Tag v-if="data.leave_type" :value="data.leave_type.name" />
                    <span v-else class="text-surface-400">—</span>
                  </template>
                </Column>
                <Column :header="t('hrm.leave.requests.columns.dates')">
                  <template #body="{ data }">
                    <span class="font-mono text-xs">{{ formatDate(data.start_date) }} → {{ formatDate(data.end_date) }}</span>
                  </template>
                </Column>
                <Column field="days" :header="t('hrm.leave.requests.columns.days')" />
                <Column :header="t('hrm.leave.requests.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                  </template>
                </Column>
              </DataTable>

              <Paginator
                v-if="reqMeta && reqMeta.last_page > 1"
                :rows="reqMeta.per_page"
                :total-records="reqMeta.total"
                :first="(reqMeta.current_page - 1) * reqMeta.per_page"
                @page="(e) => reqPage = e.page + 1"
              />
            </template>
          </Card>
        </TabPanel>

        <!-- Types -->
        <TabPanel value="types">
          <Card>
            <template #content>
              <DataTable :value="leaveTypes" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.leave.types.empty') }}</div>
                </template>

                <Column field="name" :header="t('hrm.leave.types.fields.name')" />
                <Column field="code" :header="t('hrm.leave.types.fields.code')">
                  <template #body="{ data }">
                    <code class="font-mono text-xs">{{ data.code }}</code>
                  </template>
                </Column>
                <Column field="default_balance" :header="t('hrm.leave.types.fields.defaultBalance')" />
                <Column :header="t('hrm.leave.types.fields.isPaid')">
                  <template #body="{ data }">
                    <i :class="data.is_paid ? 'pi pi-check text-emerald-500' : 'pi pi-times text-surface-400'" />
                  </template>
                </Column>
                <Column :header="t('hrm.leave.types.fields.accrues')">
                  <template #body="{ data }">
                    <i :class="data.accrues ? 'pi pi-check text-emerald-500' : 'pi pi-times text-surface-400'" />
                  </template>
                </Column>
                <Column :header="t('hrm.leave.types.fields.requiresApproval')">
                  <template #body="{ data }">
                    <i :class="data.requires_approval ? 'pi pi-check text-emerald-500' : 'pi pi-times text-surface-400'" />
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '140px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openTypeEdit(data)" />
                    <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDeleteType(data)" />
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Detail dialog -->
    <!-- Single source of truth for approve/reject — the list no longer
         shows row actions, so everything goes through this modal. -->
    <Dialog
      v-model:visible="detailDialog"
      modal
      :header="t('hrm.leave.detail.title')"
      :style="{ width: '42rem' }"
      :pt="{ root: { class: '!max-w-[95vw]' } }"
    >
      <div v-if="detail" class="space-y-5 text-sm">
        <div v-if="detailLoading" class="flex items-center gap-2 text-xs text-surface-500">
          <i class="pi pi-spin pi-spinner" />
          <span>{{ t('hrm.common.loading') }}</span>
        </div>

        <!-- Header: employee + status pill -->
        <div class="flex items-center gap-3 pb-3 border-b border-surface-200 dark:border-surface-800">
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-base">
              {{ detail.employee?.first_name }} {{ detail.employee?.last_name }}
            </div>
            <code v-if="detail.employee?.employee_id" class="font-mono text-xs text-surface-500">{{ detail.employee.employee_id }}</code>
          </div>
          <Tag :value="detail.status" :severity="statusSeverity(detail.status)" />
        </div>

        <!-- Core leave info -->
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
          <div>
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.requests.columns.type') }}</dt>
            <dd>
              <Tag v-if="detail.leave_type" :value="detail.leave_type.name" />
              <span v-else class="text-surface-400">—</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-surface-500">រយៈពេល / Duration</dt>
            <dd>
              <span v-if="detail.duration_type === 'half_day'">កន្លះថ្ងៃ / Half Day</span>
              <span v-else>ថ្ងៃ / Full Day</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.form.startDate') }}</dt>
            <dd class="font-mono">{{ formatDate(detail.start_date) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.form.endDate') }}</dt>
            <dd class="font-mono">{{ formatDate(detail.end_date) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.requests.columns.days') }}</dt>
            <dd class="font-mono font-semibold">{{ detail.days }}</dd>
          </div>
          <div v-if="detail.created_at">
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.detail.submittedAt') }}</dt>
            <dd class="font-mono">{{ formatDateTime(detail.created_at) }}</dd>
          </div>

          <div class="col-span-2">
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.form.reason') }}</dt>
            <dd class="whitespace-pre-wrap mt-1 p-3 rounded-md bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800">
              {{ detail.reason || '—' }}
            </dd>
          </div>

          <div v-if="detail.assigned_to" class="col-span-2">
            <dt class="text-xs text-surface-500">សុំផ្ទេរការងារទៅ / Assigned To</dt>
            <dd>
              {{ detail.assigned_to.first_name }} {{ detail.assigned_to.last_name }}
              <code v-if="detail.assigned_to.employee_id" class="font-mono text-xs text-surface-500 ml-1">({{ detail.assigned_to.employee_id }})</code>
            </dd>
          </div>

          <div v-if="detail.reference_url" class="col-span-2">
            <dt class="text-xs text-surface-500">ឯកសារយោង / Reference</dt>
            <dd>
              <a :href="detail.reference_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-primary-600 hover:underline">
                <i class="pi pi-file" />
                <span>{{ t('hrm.leave.detail.openReference') }}</span>
                <i class="pi pi-external-link text-xs" />
              </a>
            </dd>
          </div>

          <div v-if="detail.approver" class="col-span-2 pt-3 border-t border-surface-200 dark:border-surface-800">
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.detail.approver') }}</dt>
            <dd>
              {{ detail.approver.first_name }} {{ detail.approver.last_name }}
              <span v-if="detail.approved_at" class="text-xs text-surface-500 ml-2">
                · {{ formatDateTime(detail.approved_at) }}
              </span>
            </dd>
          </div>
          <div v-if="detail.rejection_reason" class="col-span-2">
            <dt class="text-xs text-surface-500">{{ t('hrm.leave.detail.rejectionReason') }}</dt>
            <dd class="whitespace-pre-wrap mt-1 p-3 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300">
              {{ detail.rejection_reason }}
            </dd>
          </div>
        </dl>
      </div>
      <template #footer>
        <Button :label="t('common.close')" severity="secondary" text @click="detailDialog = false" />
        <template v-if="detail?.status === 'pending'">
          <Button
            :label="t('hrm.leave.actions.reject')"
            severity="danger"
            icon="pi pi-times"
            outlined
            :disabled="detailActionLoading"
            @click="openRejectFromDetail"
          />
          <Button
            :label="t('hrm.leave.actions.approve')"
            severity="success"
            icon="pi pi-check"
            :loading="detailActionLoading"
            @click="approveFromDetail"
          />
        </template>
      </template>
    </Dialog>

    <!-- ─────────── New request dialog ─────────── -->
    <!-- Cambodian-HR layout: bilingual labels (Khmer / English), two-
         column responsive grid, balance preview, half-day support,
         optional assign-to + reference-file upload. -->
    <Dialog
      v-model:visible="reqDialog"
      modal
      :style="{ width: '56rem' }"
      :pt="{ root: { class: '!max-w-[95vw]' } }"
    >
      <template #header>
        <div class="text-primary-600 font-semibold tracking-tight">
          ពាក្យសុំអនុញ្ញាតច្បាប់សម្រាក&nbsp;/&nbsp;LEAVE APPLICATION FORM
        </div>
      </template>

      <form class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4 pt-2" @submit.prevent="onSubmitRequest">
        <!-- Row 1: Employee  +  Leave Balance -->
        <div>
          <FormLabel label="បុគ្គលិក / Employee" required />
          <!-- Staff path: locked to themselves. We render a styled read-
               only field with name + ID so it's obvious *who* the leave
               is for. The disabled Select alternative looked like a bug
               (empty-looking dropdown) so a plain field is clearer. -->
          <div
            v-if="!canPickEmployee"
            class="w-full px-3 py-2 rounded-md bg-surface-100 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-sm"
          >
            <template v-if="myEmployee">
              <span class="font-medium">{{ myEmployee.first_name }} {{ myEmployee.last_name }}</span>
              <code v-if="myEmployee.employee_id" class="font-mono text-xs text-surface-500 ml-2">({{ myEmployee.employee_id }})</code>
            </template>
            <span v-else class="text-surface-400 italic">{{ t('hrm.leave.form.noLinkedEmployee') }}</span>
          </div>
          <Select
            v-else
            v-model="employeeId"
            :options="employees"
            option-value="id"
            class="w-full"
            :invalid="!!errors.employee_id"
            :placeholder="t('hrm.leave.placeholders.employee')"
          >
            <template #option="{ option }">{{ option.first_name }} {{ option.last_name }} ({{ option.employee_id }})</template>
            <template #value="{ value }">
              <span v-if="value">
                <span v-for="emp in employees.filter((e) => e.id === value)" :key="emp.id">
                  {{ emp.first_name }} {{ emp.last_name }}
                </span>
              </span>
            </template>
          </Select>
        </div>
        <div>
          <FormLabel label="ចំនួនបានស្នើ / Leave Balance" />
          <InputText
            :model-value="currentBalanceAvailable === null
              ? (balancesLoading ? t('hrm.common.loading') : '—')
              : `${currentBalanceAvailable}`"
            disabled
            readonly
            class="w-full font-mono bg-surface-100 dark:bg-surface-900"
          />
        </div>

        <!-- Row 2: Leave Type (full width on small, half on large) -->
        <div>
          <FormLabel label="ប្រភេទច្បាប់ / Leave Type" required />
          <Select
            v-model="leaveTypeId"
            :options="leaveTypes"
            option-label="name"
            option-value="id"
            class="w-full"
            :invalid="!!errors.leave_type_id"
            :placeholder="t('hrm.leave.placeholders.type')"
          />
        </div>
        <div />

        <!-- Duration radios (full width) -->
        <div class="lg:col-span-2">
          <FormLabel label="រយៈពេល / Duration" required />
          <div class="flex flex-wrap items-center gap-6 pt-1">
            <div class="flex items-center gap-2">
              <RadioButton v-model="durationType" input-id="dur-full" name="duration_type" value="full_day" />
              <label for="dur-full" class="text-sm cursor-pointer">ថ្ងៃ / Full Day</label>
            </div>
            <div class="flex items-center gap-2">
              <RadioButton v-model="durationType" input-id="dur-half" name="duration_type" value="half_day" />
              <label for="dur-half" class="text-sm cursor-pointer">កន្លះថ្ងៃ / Half Day</label>
            </div>
          </div>
        </div>

        <!-- Row: From Date  +  To Date -->
        <div>
          <FormLabel label="ចាប់ពីថ្ងៃ / From Date" required />
          <DatePicker v-model="startDate as any" date-format="yy-mm-dd" show-icon icon-display="input" class="w-full" :placeholder="t('common.placeholders.date')" />
          <small v-if="errors.start_date" class="text-red-500 text-xs mt-1 block">{{ errors.start_date }}</small>
        </div>
        <div>
          <FormLabel label="ដល់ថ្ងៃ / To Date" required />
          <DatePicker
            v-model="endDate as any"
            date-format="yy-mm-dd"
            show-icon icon-display="input"
            :disabled="durationType === 'half_day'"
            class="w-full"
            :placeholder="t('common.placeholders.date')"
          />
          <small v-if="errors.end_date" class="text-red-500 text-xs mt-1 block">{{ errors.end_date }}</small>
          <small v-else-if="computedDays !== null" class="text-surface-500 text-xs mt-1 block">
            {{ durationType === 'half_day'
              ? '0.5 days'
              : t('hrm.leave.form.daysComputed', { n: computedDays })
            }}
          </small>
        </div>

        <!-- Reason (full width) -->
        <div class="lg:col-span-2">
          <FormLabel label="មូលហេតុ / Reason" required />
          <Textarea v-model="reason" rows="4" class="w-full" :invalid="!!errors.reason" :placeholder="t('hrm.leave.placeholders.reason')" />
          <small v-if="errors.reason" class="text-red-500 text-xs mt-1 block">{{ errors.reason }}</small>
        </div>

        <!-- Assign To (full width) -->
        <div class="lg:col-span-2">
          <FormLabel label="សុំផ្ទេរការងារទៅ / Assign To" />
          <Select
            v-model="assignTo"
            :options="employees.filter((e) => e.id !== employeeId)"
            option-value="id"
            show-clear
            class="w-full"
            :placeholder="t('hrm.leave.placeholders.assignTo')"
          >
            <template #option="{ option }">{{ option.first_name }} {{ option.last_name }} ({{ option.employee_id }})</template>
            <template #value="{ value }">
              <span v-if="value">
                <span v-for="emp in employees.filter((e) => e.id === value)" :key="emp.id">
                  {{ emp.first_name }} {{ emp.last_name }}
                </span>
              </span>
            </template>
          </Select>
        </div>

        <!-- Reference upload (full width) -->
        <div class="lg:col-span-2">
          <FormLabel label="ឯកសារយោង / Reference" />
          <div
            class="border-2 border-dashed rounded-xl p-5 transition-colors cursor-pointer text-center"
            :class="referenceUploading
              ? 'border-primary-300 bg-primary-50/30 dark:bg-primary-950/20'
              : 'border-surface-300 dark:border-surface-700 hover:border-primary-400'"
            @click="referenceFileInput?.click()"
          >
            <input
              ref="referenceFileInput"
              type="file"
              accept="application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              class="hidden"
              @change="onReferenceChange"
            >
            <div v-if="referenceUploading" class="text-primary-600 text-sm">
              <i class="pi pi-spin pi-spinner mr-2" />{{ t('hrm.notes.documents.uploading') }}
            </div>
            <div v-else-if="!referenceFileName" class="text-surface-500">
              <i class="pi pi-cloud-upload text-3xl text-primary-400 block mb-1" />
              <div class="text-sm">Browse to Upload</div>
              <div class="text-xs mt-0.5">PDF, image, or Word doc · up to 10 MB</div>
            </div>
            <div v-else class="flex items-center justify-center gap-3">
              <i class="pi pi-file text-2xl text-primary-600" />
              <div class="font-medium text-sm">{{ referenceFileName }}</div>
              <Button icon="pi pi-times" text rounded severity="danger" size="small" @click.stop="clearReference" />
            </div>
          </div>
          <div class="text-xs text-surface-500 mt-1">Detail File</div>
        </div>
      </form>

      <template #footer>
        <Button :label="t('common.cancel')" severity="danger" @click="reqDialog = false" />
        <Button :label="t('common.save')" severity="info" icon="pi pi-check" :loading="reqSaving" @click="onSubmitRequest" />
      </template>
    </Dialog>

    <!-- Reject dialog -->
    <Dialog v-model:visible="rejectDialog" modal :header="t('hrm.leave.actions.reject')" :style="{ width: '28rem' }">
      <div>
        <FormLabel :label="t('hrm.leave.actions.rejectReason')" />
        <Textarea v-model="rejectReason" rows="3" class="w-full" :placeholder="t('hrm.leave.placeholders.rejectReason')" />
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text :disabled="rejectSaving" @click="rejectDialog = false" />
        <Button :label="t('hrm.leave.actions.reject')" severity="danger" icon="pi pi-times" :loading="rejectSaving" @click="onReject" />
      </template>
    </Dialog>

    <!-- Type dialog -->
    <Dialog
      v-model:visible="typeDialog"
      modal
      :header="editingType ? t('hrm.employees.dialog.editTitle', { name: editingType.name }) : t('hrm.leave.newType')"
      :style="{ width: '32rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSaveType">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.leave.types.fields.name')" required />
            <InputText v-model="tName" class="w-full" :invalid="!!tErrors.name" :placeholder="t('hrm.leave.placeholders.typeName')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.leave.types.fields.code')" required />
            <InputText v-model="tCode" class="w-full font-mono" :invalid="!!tErrors.code" :placeholder="t('hrm.leave.placeholders.typeCode')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.leave.types.fields.defaultBalance')" />
          <InputNumber v-model="tBalance" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.leave.placeholders.defaultBalance')" />
        </div>
        <div class="flex flex-wrap gap-6">
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="tPaid" input-id="lt-paid" />
            <label for="lt-paid" class="text-sm">{{ t('hrm.leave.types.fields.isPaid') }}</label>
          </div>
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="tAccrues" input-id="lt-accrues" />
            <label for="lt-accrues" class="text-sm">{{ t('hrm.leave.types.fields.accrues') }}</label>
          </div>
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="tApproval" input-id="lt-approval" />
            <label for="lt-approval" class="text-sm">{{ t('hrm.leave.types.fields.requiresApproval') }}</label>
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.leave.types.fields.color')" />
          <InputText v-model="tColor" class="w-full" :placeholder="t('hrm.leave.placeholders.color')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="typeDialog = false" />
          <Button type="submit" :label="editingType ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="typeSaving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
