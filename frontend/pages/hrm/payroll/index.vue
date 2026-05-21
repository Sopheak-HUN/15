<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { PayComponent, Payslip, PayrollPeriod } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const tab = ref<'periods' | 'components' | 'payslips'>('periods')

// ----------------- Pay components -----------------
const { data: compsData, refresh: refreshComps } = await useAsyncData('hrm-pay-components', () => hrm.listPayComponents())
const components = computed<PayComponent[]>(() => compsData.value?.data ?? [])

const compDialog  = ref(false)
const editingComp = ref<PayComponent | null>(null)
const compSaving  = ref(false)

const compSchema = toTypedSchema(z.object({
  name: z.string().min(2).max(120),
  code: z.string().min(1).max(32),
  kind: z.enum(['earning', 'deduction']),
  calculation: z.enum(['fixed', 'percentage_of_base']),
  amount: z.coerce.number(),
  is_taxable: z.boolean(),
  is_active: z.boolean(),
}))
const { defineField: cField, handleSubmit: handleComp, errors: cErrors, resetForm: resetComp, setValues: setComp } = useForm({
  validationSchema: compSchema,
  initialValues: { name: '', code: '', kind: 'earning', calculation: 'fixed', amount: 0, is_taxable: true, is_active: true },
})
const [cName] = cField('name')
const [cCode] = cField('code')
const [cKind] = cField('kind')
const [cCalc] = cField('calculation')
const [cAmount] = cField('amount')
const [cTaxable] = cField('is_taxable')
const [cActive] = cField('is_active')

const kindOptions = computed(() => [
  { label: t('hrm.payroll.components.kinds.earning'), value: 'earning' },
  { label: t('hrm.payroll.components.kinds.deduction'), value: 'deduction' },
])
const calcOptions = computed(() => [
  { label: t('hrm.payroll.components.calculations.fixed'), value: 'fixed' },
  { label: t('hrm.payroll.components.calculations.percentage_of_base'), value: 'percentage_of_base' },
])

const openCompCreate = () => {
  editingComp.value = null
  resetComp()
  compDialog.value = true
}
const openCompEdit = (row: PayComponent) => {
  editingComp.value = row
  setComp({
    name: row.name, code: row.code,
    kind: row.kind, calculation: row.calculation,
    amount: Number(row.amount), is_taxable: row.is_taxable, is_active: row.is_active,
  })
  compDialog.value = true
}
const onSaveComp = handleComp(async (values) => {
  compSaving.value = true
  try {
    if (editingComp.value) {
      await hrm.updatePayComponent(editingComp.value.id, values)
      toast.add({ severity: 'success', summary: t('hrm.payroll.toast.componentUpdated'), life: 2000 })
    } else {
      await hrm.createPayComponent(values)
      toast.add({ severity: 'success', summary: t('hrm.payroll.toast.componentCreated'), life: 2000 })
    }
    compDialog.value = false
    await refreshComps()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    compSaving.value = false
  }
})
const onDeleteComp = (row: PayComponent) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.name }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deletePayComponent(row.id)
      toast.add({ severity: 'success', summary: t('hrm.payroll.toast.componentDeleted'), life: 2000 })
      await refreshComps()
    },
  })
}

// ----------------- Periods -----------------
const periodPage = ref(1)
const { data: periodsData, refresh: refreshPeriods, pending: periodsPending } = await useAsyncData(
  'hrm-payroll-periods',
  () => hrm.listPayrollPeriods({ page: periodPage.value, per_page: 25 }),
  { watch: [periodPage] },
)
const periods = computed<PayrollPeriod[]>(() => periodsData.value?.data?.data ?? [])
const periodMeta = computed(() => periodsData.value?.data)

const periodDialog = ref(false)
const periodSaving = ref(false)
const periodSchema = toTypedSchema(z.object({
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  label: z.string().max(80).optional().or(z.literal('')),
}))
const { defineField: pField, handleSubmit: handlePeriod, errors: pErrors, resetForm: resetPeriod } = useForm({
  validationSchema: periodSchema,
  initialValues: { start_date: '', end_date: '', label: '' },
})
const [pStart] = pField('start_date')
const [pEnd]   = pField('end_date')
const [pLabel] = pField('label')

const openPeriodCreate = () => { resetPeriod(); periodDialog.value = true }
const onCreatePeriod = handlePeriod(async (values) => {
  periodSaving.value = true
  try {
    await hrm.createPayrollPeriod({
      start_date: values.start_date,
      end_date: values.end_date,
      label: values.label || undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.payroll.toast.periodCreated'), life: 2000 })
    periodDialog.value = false
    await refreshPeriods()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    periodSaving.value = false
  }
})

const processPeriod = (row: PayrollPeriod) => {
  confirm.require({
    message: t('hrm.payroll.periods.processConfirm', { label: row.label }),
    header: t('hrm.payroll.periods.process'),
    acceptClass: 'p-button-primary',
    accept: async () => {
      try {
        await hrm.processPayrollPeriod(row.id)
        toast.add({ severity: 'success', summary: t('hrm.payroll.toast.periodProcessed'), life: 2500 })
        await refreshPeriods()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 4000 })
      }
    },
  })
}

// ----------------- Payslips -----------------
const payslipPage = ref(1)
const payslipPeriodFilter = ref<string | null>(null)
const { data: payslipsData, pending: payslipsPending } = await useAsyncData(
  'hrm-payslips',
  () => hrm.listPayslips({ period_id: payslipPeriodFilter.value || undefined, page: payslipPage.value, per_page: 50 }),
  { watch: [payslipPage, payslipPeriodFilter] },
)
const payslips = computed<Payslip[]>(() => payslipsData.value?.data?.data ?? [])
const payslipMeta = computed(() => payslipsData.value?.data)

const payslipDetailDialog = ref(false)
const payslipDetail = ref<Payslip | null>(null)
const openPayslip = (p: Payslip) => { payslipDetail.value = p; payslipDetailDialog.value = true }

const periodStatusSeverity = (s: string) => s === 'closed' ? 'success' : 'warn'
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.payroll.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.payroll.subtitle') }}</p>
      </div>
      <Button
        v-if="tab === 'periods'"
        :label="t('hrm.payroll.newPeriod')"
        icon="pi pi-plus"
        @click="openPeriodCreate"
      />
      <Button
        v-else-if="tab === 'components'"
        :label="t('hrm.payroll.newComponent')"
        icon="pi pi-plus"
        @click="openCompCreate"
      />
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="periods">{{ t('hrm.payroll.tabs.periods') }}</Tab>
        <Tab value="components">{{ t('hrm.payroll.tabs.components') }}</Tab>
        <Tab value="payslips">{{ t('hrm.payroll.tabs.payslips') }}</Tab>
      </TabList>
      <TabPanels>
        <!-- Periods -->
        <TabPanel value="periods">
          <Card>
            <template #content>
              <DataTable :value="periods" :loading="periodsPending" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.payroll.periods.empty') }}</div>
                </template>
                <Column field="label" :header="t('hrm.payroll.periods.columns.label')" />
                <Column :header="t('hrm.payroll.periods.columns.startDate')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ data.start_date }}</span></template>
                </Column>
                <Column :header="t('hrm.payroll.periods.columns.endDate')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ data.end_date }}</span></template>
                </Column>
                <Column :header="t('hrm.payroll.periods.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="periodStatusSeverity(data.status)" />
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.periods.columns.processedAt')">
                  <template #body="{ data }">
                    <span v-if="data.processed_at" class="text-xs text-surface-500">{{ data.processed_at }}</span>
                    <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '180px' }">
                  <template #body="{ data }">
                    <Button
                      v-if="data.status !== 'closed'"
                      :label="t('hrm.payroll.periods.process')"
                      icon="pi pi-play"
                      size="small"
                      @click="processPeriod(data)"
                    />
                  </template>
                </Column>
              </DataTable>

              <Paginator
                v-if="periodMeta && periodMeta.last_page > 1"
                :rows="periodMeta.per_page"
                :total-records="periodMeta.total"
                :first="(periodMeta.current_page - 1) * periodMeta.per_page"
                @page="(e) => periodPage = e.page + 1"
              />
            </template>
          </Card>
        </TabPanel>

        <!-- Components -->
        <TabPanel value="components">
          <Card>
            <template #content>
              <DataTable :value="components" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.payroll.components.empty') }}</div>
                </template>
                <Column field="name" :header="t('hrm.payroll.components.columns.name')" />
                <Column field="code" :header="t('hrm.payroll.components.columns.code')">
                  <template #body="{ data }"><code class="font-mono text-xs">{{ data.code }}</code></template>
                </Column>
                <Column :header="t('hrm.payroll.components.columns.kind')">
                  <template #body="{ data }">
                    <Tag
                      :value="t(`hrm.payroll.components.kinds.${data.kind}`)"
                      :severity="data.kind === 'earning' ? 'success' : 'warn'"
                    />
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.components.columns.calculation')">
                  <template #body="{ data }">
                    {{ t(`hrm.payroll.components.calculations.${data.calculation}`) }}
                  </template>
                </Column>
                <Column field="amount" :header="t('hrm.payroll.components.columns.amount')">
                  <template #body="{ data }">
                    <span class="font-mono">{{ data.amount }}{{ data.calculation === 'percentage_of_base' ? '%' : '' }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.components.columns.active')">
                  <template #body="{ data }">
                    <i :class="data.is_active ? 'pi pi-check text-emerald-500' : 'pi pi-times text-surface-400'" />
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '140px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openCompEdit(data)" />
                    <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDeleteComp(data)" />
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </TabPanel>

        <!-- Payslips -->
        <TabPanel value="payslips">
          <Card>
            <template #content>
              <div class="flex flex-wrap items-center gap-3 mb-4">
                <Select
                  v-model="payslipPeriodFilter"
                  :options="periods"
                  option-label="label"
                  option-value="id"
                  :placeholder="t('hrm.payroll.tabs.periods')"
                  show-clear
                  class="w-72"
                />
              </div>

              <DataTable :value="payslips" :loading="payslipsPending" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.payroll.payslips.empty') }}</div>
                </template>
                <Column :header="t('hrm.payroll.payslips.columns.employee')">
                  <template #body="{ data }">
                    <div class="font-medium">{{ data.employee?.first_name }} {{ data.employee?.last_name }}</div>
                    <code v-if="data.employee?.employee_id" class="text-xs font-mono text-surface-500">{{ data.employee.employee_id }}</code>
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.payslips.columns.period')">
                  <template #body="{ data }">{{ data.period?.label }}</template>
                </Column>
                <Column :header="t('hrm.payroll.payslips.columns.gross')">
                  <template #body="{ data }">
                    <span class="font-mono">{{ data.gross_earnings }} {{ data.currency }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.payslips.columns.deductions')">
                  <template #body="{ data }">
                    <span class="font-mono">{{ data.total_deductions }} {{ data.currency }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.payroll.payslips.columns.net')">
                  <template #body="{ data }">
                    <span class="font-mono font-semibold text-emerald-600">{{ data.net_pay }} {{ data.currency }}</span>
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '120px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-eye" text rounded severity="secondary" @click="openPayslip(data)" />
                  </template>
                </Column>
              </DataTable>

              <Paginator
                v-if="payslipMeta && payslipMeta.last_page > 1"
                :rows="payslipMeta.per_page"
                :total-records="payslipMeta.total"
                :first="(payslipMeta.current_page - 1) * payslipMeta.per_page"
                @page="(e) => payslipPage = e.page + 1"
              />
            </template>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Component dialog -->
    <Dialog
      v-model:visible="compDialog"
      modal
      :header="editingComp ? t('hrm.employees.dialog.editTitle', { name: editingComp.name }) : t('hrm.payroll.newComponent')"
      :style="{ width: '34rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSaveComp">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.payroll.components.fields.name')" required />
            <InputText v-model="cName" class="w-full" :invalid="!!cErrors.name" :placeholder="t('hrm.payroll.placeholders.compName')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.payroll.components.fields.code')" required />
            <InputText v-model="cCode" class="w-full font-mono" :invalid="!!cErrors.code" :placeholder="t('hrm.payroll.placeholders.compCode')" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.payroll.components.fields.kind')" required />
            <Select v-model="cKind" :options="kindOptions" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.payroll.placeholders.kind')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.payroll.components.fields.calculation')" required />
            <Select v-model="cCalc" :options="calcOptions" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.payroll.placeholders.calculation')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.payroll.components.fields.amount')" required />
          <InputNumber v-model="cAmount" :min-fraction-digits="0" :max-fraction-digits="4" class="w-full" :placeholder="t('hrm.payroll.placeholders.amount')" />
        </div>
        <div class="flex gap-6">
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="cTaxable" input-id="comp-tax" />
            <label for="comp-tax" class="text-sm">{{ t('hrm.payroll.components.fields.isTaxable') }}</label>
          </div>
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="cActive" input-id="comp-active" />
            <label for="comp-active" class="text-sm">{{ t('hrm.payroll.components.fields.isActive') }}</label>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="compDialog = false" />
          <Button type="submit" :label="editingComp ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="compSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Period dialog -->
    <Dialog v-model:visible="periodDialog" modal :header="t('hrm.payroll.newPeriod')" :style="{ width: '28rem' }">
      <form class="space-y-4" @submit.prevent="onCreatePeriod">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.payroll.periods.fields.startDate')" required />
            <DatePicker v-model="pStart" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.payroll.periods.fields.endDate')" required />
            <DatePicker v-model="pEnd" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.payroll.periods.fields.label')" />
          <InputText v-model="pLabel" class="w-full" :placeholder="t('hrm.payroll.placeholders.periodLabel')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="periodDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="periodSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Payslip detail dialog -->
    <Dialog
      v-model:visible="payslipDetailDialog"
      modal
      :header="payslipDetail ? t('hrm.payroll.payslips.detailTitle', { employee: `${payslipDetail.employee?.first_name ?? ''} ${payslipDetail.employee?.last_name ?? ''}`.trim() }) : ''"
      :style="{ width: '36rem' }"
    >
      <div v-if="payslipDetail" class="space-y-4">
        <div class="grid grid-cols-3 gap-4 text-sm">
          <div>
            <div class="text-xs text-surface-500 uppercase">{{ t('hrm.payroll.payslips.columns.period') }}</div>
            <div class="font-medium">{{ payslipDetail.period?.label }}</div>
          </div>
          <div>
            <div class="text-xs text-surface-500 uppercase">{{ t('hrm.payroll.payslips.columns.gross') }}</div>
            <div class="font-mono">{{ payslipDetail.gross_earnings }} {{ payslipDetail.currency }}</div>
          </div>
          <div>
            <div class="text-xs text-surface-500 uppercase">{{ t('hrm.payroll.payslips.columns.net') }}</div>
            <div class="font-mono text-emerald-600 font-semibold">{{ payslipDetail.net_pay }} {{ payslipDetail.currency }}</div>
          </div>
        </div>
        <Divider />
        <DataTable v-if="payslipDetail.line_items?.length" :value="payslipDetail.line_items" class="text-sm">
          <Column field="name" :header="t('hrm.payroll.components.columns.name')" />
          <Column field="kind" :header="t('hrm.payroll.components.columns.kind')">
            <template #body="{ data }">
              <Tag :value="t(`hrm.payroll.components.kinds.${data.kind}`)" :severity="data.kind === 'earning' ? 'success' : 'warn'" />
            </template>
          </Column>
          <Column field="amount" :header="t('hrm.payroll.components.columns.amount')">
            <template #body="{ data }">
              <span class="font-mono">{{ data.amount }}</span>
            </template>
          </Column>
        </DataTable>
      </div>
    </Dialog>
  </div>
</template>
