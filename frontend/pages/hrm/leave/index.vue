<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { LeaveRequest, LeaveType } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const tab = ref<'requests' | 'types'>('requests')

// ---------- shared lookups ----------
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
const reqSchema = computed(() => toTypedSchema(z.object({
  employee_id: z.string().uuid(),
  leave_type_id: z.string().uuid(),
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  days: z.coerce.number().min(0.5).nullable().optional(),
  reason: z.string().max(500).optional().or(z.literal('')),
})))
const { defineField, handleSubmit, errors, resetForm } = useForm({
  validationSchema: reqSchema,
  initialValues: { employee_id: '', leave_type_id: '', start_date: '', end_date: '', days: null, reason: '' },
})
const [employeeId] = defineField('employee_id')
const [leaveTypeId] = defineField('leave_type_id')
const [startDate] = defineField('start_date')
const [endDate] = defineField('end_date')
const [days] = defineField('days')
const [reason] = defineField('reason')

const openNewRequest = () => {
  resetForm()
  reqDialog.value = true
}

const onSubmitRequest = handleSubmit(async (values) => {
  reqSaving.value = true
  try {
    await hrm.submitLeaveRequest({
      employee_id: values.employee_id,
      leave_type_id: values.leave_type_id,
      start_date: values.start_date,
      end_date: values.end_date,
      days: values.days ?? undefined,
      reason: values.reason || undefined,
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
})

const approve = async (row: LeaveRequest) => {
  try {
    await hrm.approveLeaveRequest(row.id)
    toast.add({ severity: 'success', summary: t('hrm.leave.toast.approved'), life: 2000 })
    await refreshRequests()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', detail: data?.message, summary: t('hrm.common.saveFailed'), life: 5000 })
  }
}

const rejectDialog = ref(false)
const rejectTarget = ref<LeaveRequest | null>(null)
const rejectReason = ref('')
const openReject = (row: LeaveRequest) => {
  rejectTarget.value = row
  rejectReason.value = ''
  rejectDialog.value = true
}
const onReject = async () => {
  if (!rejectTarget.value) return
  try {
    await hrm.rejectLeaveRequest(rejectTarget.value.id, rejectReason.value || undefined)
    toast.add({ severity: 'success', summary: t('hrm.leave.toast.rejected'), life: 2000 })
    rejectDialog.value = false
    await refreshRequests()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', detail: data?.message, summary: t('hrm.common.saveFailed'), life: 5000 })
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
                <DatePicker v-model="reqFrom" date-format="yy-mm-dd" :placeholder="t('hrm.leave.filters.from')" class="w-44" />
                <DatePicker v-model="reqTo"   date-format="yy-mm-dd" :placeholder="t('hrm.leave.filters.to')"   class="w-44" />
              </div>

              <DataTable :value="requests" :loading="reqPending" data-key="id" striped-rows class="text-sm">
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
                    <Tag v-if="data.leaveType" :value="data.leaveType.name" />
                  </template>
                </Column>
                <Column :header="t('hrm.leave.requests.columns.dates')">
                  <template #body="{ data }">
                    <span class="font-mono text-xs">{{ data.start_date }} → {{ data.end_date }}</span>
                  </template>
                </Column>
                <Column field="days" :header="t('hrm.leave.requests.columns.days')" />
                <Column :header="t('hrm.leave.requests.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                  </template>
                </Column>
                <Column header="" body-class="text-right !py-2" :style="{ width: '180px' }">
                  <template #body="{ data }">
                    <template v-if="data.status === 'pending'">
                      <Button
                        icon="pi pi-check"
                        text rounded severity="success"
                        :aria-label="t('hrm.leave.actions.approve')"
                        @click="approve(data)"
                      />
                      <Button
                        icon="pi pi-times"
                        text rounded severity="danger"
                        :aria-label="t('hrm.leave.actions.reject')"
                        @click="openReject(data)"
                      />
                    </template>
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

    <!-- New request dialog -->
    <Dialog v-model:visible="reqDialog" modal :header="t('hrm.leave.newRequest')" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onSubmitRequest">
        <div>
          <FormLabel :label="t('hrm.leave.form.employee')" required />
          <Select
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
          <FormLabel :label="t('hrm.leave.form.type')" required />
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
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.leave.form.startDate')" required />
            <DatePicker v-model="startDate" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.leave.form.endDate')" required />
            <DatePicker v-model="endDate" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.leave.form.days')" />
          <InputNumber v-model="days" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" :placeholder="t('hrm.leave.placeholders.days')" />
          <small class="text-surface-500 text-xs">{{ t('hrm.leave.form.daysHint') }}</small>
        </div>
        <div>
          <FormLabel :label="t('hrm.leave.form.reason')" />
          <Textarea v-model="reason" rows="3" class="w-full" :placeholder="t('hrm.leave.placeholders.reason')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="reqDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="reqSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Reject dialog -->
    <Dialog v-model:visible="rejectDialog" modal :header="t('hrm.leave.actions.reject')" :style="{ width: '28rem' }">
      <div>
        <FormLabel :label="t('hrm.leave.actions.rejectReason')" />
        <Textarea v-model="rejectReason" rows="3" class="w-full" :placeholder="t('hrm.leave.placeholders.rejectReason')" />
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="rejectDialog = false" />
        <Button :label="t('hrm.leave.actions.reject')" severity="danger" icon="pi pi-times" @click="onReject" />
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
