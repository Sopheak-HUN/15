<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Appraisal, AppraisalCycle } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const tab = ref<'cycles' | 'appraisals'>('cycles')

const { data: empData } = await useAsyncData('hrm-perf-employees', () => hrm.listEmployees({ status: 'active', per_page: 200 }))
const employees = computed(() => empData.value?.data?.data ?? [])

// ----- Cycles -----
const { data: cycData, refresh: refreshCycles } = await useAsyncData('hrm-perf-cycles', () => hrm.listAppraisalCycles())
const cycles = computed<AppraisalCycle[]>(() => cycData.value?.data ?? [])

const cycleDialog = ref(false)
const editingCycle = ref<AppraisalCycle | null>(null)
const cycleSaving = ref(false)
const cycleSchema = toTypedSchema(z.object({
  name: z.string().min(2).max(120),
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  is_active: z.boolean(),
  rating_scale_json: z.string().optional().or(z.literal('')),
}))
const { defineField: cyField, handleSubmit: handleCycle, errors: cyErrors, resetForm: resetCycle, setValues: setCycle } = useForm({
  validationSchema: cycleSchema,
  initialValues: { name: '', start_date: '', end_date: '', is_active: true, rating_scale_json: '' },
})
const [cyName] = cyField('name')
const [cyStart] = cyField('start_date')
const [cyEnd] = cyField('end_date')
const [cyActive] = cyField('is_active')
const [cyScale] = cyField('rating_scale_json')

const openCycleCreate = () => {
  editingCycle.value = null
  resetCycle()
  cycleDialog.value = true
}
const openCycleEdit = (row: AppraisalCycle) => {
  editingCycle.value = row
  setCycle({
    name: row.name,
    start_date: row.start_date,
    end_date: row.end_date,
    is_active: row.is_active,
    rating_scale_json: row.rating_scale ? JSON.stringify(row.rating_scale, null, 2) : '',
  })
  cycleDialog.value = true
}
const onSaveCycle = handleCycle(async (values) => {
  cycleSaving.value = true
  try {
    let rating_scale: AppraisalCycle['rating_scale'] = null
    if (values.rating_scale_json) {
      try {
        const parsed = JSON.parse(values.rating_scale_json)
        if (Array.isArray(parsed)) rating_scale = parsed
      } catch {
        toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: 'Invalid JSON for rating scale.', life: 4000 })
        cycleSaving.value = false
        return
      }
    }
    const payload = {
      name: values.name,
      start_date: values.start_date,
      end_date: values.end_date,
      is_active: values.is_active,
      rating_scale,
    }
    if (editingCycle.value) {
      await hrm.updateAppraisalCycle(editingCycle.value.id, payload)
      toast.add({ severity: 'success', summary: t('hrm.performance.toast.cycleUpdated'), life: 2000 })
    } else {
      await hrm.createAppraisalCycle(payload)
      toast.add({ severity: 'success', summary: t('hrm.performance.toast.cycleCreated'), life: 2000 })
    }
    cycleDialog.value = false
    await refreshCycles()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    cycleSaving.value = false
  }
})
const onDeleteCycle = (row: AppraisalCycle) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.name }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteAppraisalCycle(row.id)
      toast.add({ severity: 'success', summary: t('hrm.performance.toast.cycleDeleted'), life: 2000 })
      await refreshCycles()
    },
  })
}

// ----- Appraisals -----
const apprPage = ref(1)
const apprCycle = ref<string | null>(null)
const apprStatus = ref<string | null>(null)
const { data: apprData, refresh: refreshAppraisals, pending: apprPending } = await useAsyncData(
  'hrm-appraisals',
  () => hrm.listAppraisals({
    cycle_id: apprCycle.value || undefined,
    status: apprStatus.value || undefined,
    page: apprPage.value, per_page: 25,
  }),
  { watch: [apprPage, apprCycle, apprStatus] },
)
const appraisals = computed<Appraisal[]>(() => apprData.value?.data?.data ?? [])
const apprMeta = computed(() => apprData.value?.data)

const apprDialog = ref(false)
const apprSaving = ref(false)
const apprSchema = toTypedSchema(z.object({
  cycle_id: z.string().uuid(),
  employee_id: z.string().uuid(),
  reviewer_id: z.string().uuid().nullable().optional(),
  employee_comments: z.string().optional().or(z.literal('')),
}))
const { defineField: apField, handleSubmit: handleAppr, errors: apErrors, resetForm: resetAppr } = useForm({
  validationSchema: apprSchema,
  initialValues: { cycle_id: '', employee_id: '', reviewer_id: null, employee_comments: '' },
})
const [apCycle] = apField('cycle_id')
const [apEmployee] = apField('employee_id')
const [apReviewer] = apField('reviewer_id')
const [apComments] = apField('employee_comments')

const openApprCreate = () => { resetAppr(); apprDialog.value = true }
const onCreateAppr = handleAppr(async (values) => {
  apprSaving.value = true
  try {
    await hrm.createAppraisal({
      cycle_id: values.cycle_id,
      employee_id: values.employee_id,
      reviewer_id: values.reviewer_id ?? null,
      employee_comments: values.employee_comments || undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.performance.toast.appraisalCreated'), life: 2000 })
    apprDialog.value = false
    await refreshAppraisals()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    apprSaving.value = false
  }
})

// Review dialog
const reviewDialog = ref(false)
const reviewTarget = ref<Appraisal | null>(null)
const reviewComments = ref('')
const reviewScore = ref<number | null>(null)
const openReview = (row: Appraisal) => {
  reviewTarget.value = row
  reviewComments.value = row.manager_comments ?? ''
  reviewScore.value = row.overall_score != null ? Number(row.overall_score) : null
  reviewDialog.value = true
}
const onReview = async () => {
  if (!reviewTarget.value) return
  try {
    await hrm.reviewAppraisal(reviewTarget.value.id, {
      manager_comments: reviewComments.value || undefined,
      overall_score: reviewScore.value ?? undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.performance.toast.appraisalReviewed'), life: 2000 })
    reviewDialog.value = false
    await refreshAppraisals()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}

const submitAppraisal = async (row: Appraisal) => {
  try {
    await hrm.submitAppraisal(row.id)
    toast.add({ severity: 'success', summary: t('hrm.performance.toast.appraisalSubmitted'), life: 2000 })
    await refreshAppraisals()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}
const closeAppraisal = async (row: Appraisal) => {
  try {
    await hrm.closeAppraisal(row.id)
    toast.add({ severity: 'success', summary: t('hrm.performance.toast.appraisalClosed'), life: 2000 })
    await refreshAppraisals()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}

const apprStatuses = ['draft', 'submitted', 'reviewed', 'closed']
const apprSeverity = (s: string) => s === 'closed' ? 'success' : s === 'draft' ? 'secondary' : 'info'
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.performance.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.performance.subtitle') }}</p>
      </div>
      <Button v-if="tab === 'cycles'"      :label="t('hrm.performance.cycles.new')"     icon="pi pi-plus" @click="openCycleCreate" />
      <Button v-else                       :label="t('hrm.performance.appraisals.new')" icon="pi pi-plus" @click="openApprCreate" />
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="cycles">{{ t('hrm.performance.tabs.cycles') }}</Tab>
        <Tab value="appraisals">{{ t('hrm.performance.tabs.appraisals') }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="cycles">
          <Card>
            <template #content>
              <DataTable :value="cycles" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.performance.cycles.empty') }}</div>
                </template>
                <Column field="name" :header="t('hrm.performance.cycles.columns.name')" />
                <Column :header="t('hrm.performance.cycles.columns.start')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ data.start_date }}</span></template>
                </Column>
                <Column :header="t('hrm.performance.cycles.columns.end')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ data.end_date }}</span></template>
                </Column>
                <Column :header="t('hrm.performance.cycles.columns.active')">
                  <template #body="{ data }">
                    <i :class="data.is_active ? 'pi pi-check text-emerald-500' : 'pi pi-times text-surface-400'" />
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '140px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openCycleEdit(data)" />
                    <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDeleteCycle(data)" />
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </TabPanel>

        <TabPanel value="appraisals">
          <Card>
            <template #content>
              <div class="flex flex-wrap items-center gap-3 mb-4">
                <Select v-model="apprCycle" :options="cycles" option-label="name" option-value="id" :placeholder="t('hrm.performance.tabs.cycles')" show-clear class="w-64" />
                <Select v-model="apprStatus" :options="apprStatuses" :placeholder="t('hrm.common.status')" show-clear class="w-44" />
              </div>
              <DataTable :value="appraisals" :loading="apprPending" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.performance.appraisals.empty') }}</div>
                </template>
                <Column :header="t('hrm.performance.appraisals.columns.employee')">
                  <template #body="{ data }">
                    <span v-if="data.employee">{{ data.employee.first_name }} {{ data.employee.last_name }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.performance.appraisals.columns.cycle')">
                  <template #body="{ data }">{{ data.cycle?.name }}</template>
                </Column>
                <Column :header="t('hrm.performance.appraisals.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="apprSeverity(data.status)" />
                  </template>
                </Column>
                <Column field="overall_score" :header="t('hrm.performance.appraisals.columns.score')">
                  <template #body="{ data }">
                    <span v-if="data.overall_score != null" class="font-mono">{{ data.overall_score }}</span>
                    <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
                  </template>
                </Column>
                <Column header="" body-class="text-right !py-2" :style="{ width: '260px' }">
                  <template #body="{ data }">
                    <Button v-if="data.status === 'draft'"     :label="t('hrm.performance.appraisals.actions.submit')" size="small" text @click="submitAppraisal(data)" />
                    <Button v-if="data.status === 'submitted'" :label="t('hrm.performance.appraisals.actions.review')" size="small" text severity="info" @click="openReview(data)" />
                    <Button v-if="data.status === 'reviewed'"  :label="t('hrm.performance.appraisals.actions.close')"  size="small" text severity="success" @click="closeAppraisal(data)" />
                  </template>
                </Column>
              </DataTable>
              <Paginator v-if="apprMeta && apprMeta.last_page > 1" :rows="apprMeta.per_page" :total-records="apprMeta.total" :first="(apprMeta.current_page - 1) * apprMeta.per_page" @page="(e) => apprPage = e.page + 1" />
            </template>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Cycle dialog -->
    <Dialog
      v-model:visible="cycleDialog"
      modal
      :header="editingCycle ? t('hrm.employees.dialog.editTitle', { name: editingCycle.name }) : t('hrm.performance.cycles.new')"
      :style="{ width: '36rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSaveCycle">
        <div>
          <FormLabel :label="t('hrm.performance.cycles.fields.name')" required />
          <InputText v-model="cyName" class="w-full" :invalid="!!cyErrors.name" :placeholder="t('hrm.performance.placeholders.cycleName')" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.performance.cycles.fields.startDate')" required />
            <DatePicker v-model="cyStart" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.performance.cycles.fields.endDate')" required />
            <DatePicker v-model="cyEnd" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.performance.cycles.fields.ratingScale')" />
          <Textarea v-model="cyScale" rows="4" class="w-full font-mono text-xs" :placeholder="t('hrm.performance.placeholders.ratingScale')" />
        </div>
        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="cyActive" input-id="cycle-active" />
          <label for="cycle-active" class="text-sm">{{ t('hrm.performance.cycles.fields.isActive') }}</label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="cycleDialog = false" />
          <Button type="submit" :label="editingCycle ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="cycleSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Appraisal dialog -->
    <Dialog v-model:visible="apprDialog" modal :header="t('hrm.performance.appraisals.new')" :style="{ width: '36rem' }">
      <form class="space-y-4" @submit.prevent="onCreateAppr">
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.cycle')" required />
          <Select v-model="apCycle" :options="cycles" option-label="name" option-value="id" class="w-full" :invalid="!!apErrors.cycle_id" :placeholder="t('hrm.performance.placeholders.cycle')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.employee')" required />
          <Select v-model="apEmployee" :options="employees" option-value="id" class="w-full" :invalid="!!apErrors.employee_id" :placeholder="t('hrm.performance.placeholders.employee')">
            <template #option="{ option }">{{ option.first_name }} {{ option.last_name }} ({{ option.employee_id }})</template>
          </Select>
        </div>
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.reviewer')" />
          <Select v-model="apReviewer" :options="employees" option-value="id" show-clear class="w-full" :placeholder="t('hrm.performance.placeholders.reviewer')">
            <template #option="{ option }">{{ option.first_name }} {{ option.last_name }}</template>
          </Select>
        </div>
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.employeeComments')" />
          <Textarea v-model="apComments" rows="4" class="w-full" :placeholder="t('hrm.performance.placeholders.employeeComments')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="apprDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="apprSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Review dialog -->
    <Dialog
      v-model:visible="reviewDialog"
      modal
      :header="reviewTarget ? t('hrm.performance.appraisals.actions.review') + ' — ' + (reviewTarget.employee?.first_name ?? '') + ' ' + (reviewTarget.employee?.last_name ?? '') : ''"
      :style="{ width: '32rem' }"
    >
      <div class="space-y-4">
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.overallScore')" />
          <InputNumber v-model="reviewScore" :min="0" :max="100" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" :placeholder="t('hrm.performance.placeholders.overallScore')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.performance.appraisals.fields.managerComments')" />
          <Textarea v-model="reviewComments" rows="5" class="w-full" :placeholder="t('hrm.performance.placeholders.managerComments')" />
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="reviewDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="onReview" />
      </template>
    </Dialog>
  </div>
</template>
