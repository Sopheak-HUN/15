<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { AttendanceSettings, NumberingSettings, CodeNumberingType } from '~/composables/useSettingApi'

// Tenant settings hub. Time Management is the first slice — it
// surfaces the values that AttendanceService used to hardcode (late
// thresholds, working days, working hours). Future tabs (Branding,
// Workflow, Notifications) land here when their pain points show up.
//
// Read needs `iam.roles.view` and write needs `iam.roles.edit` — same
// proxy used by the rest of the IAM-admin surface.
definePageMeta({
  middleware: 'auth',
  requires: ['iam.roles.view', 'iam.roles.edit'],  // either gets you in
})

const settingApi = useSettingApi()
const toast = useToast()
const { t } = useI18n()
const { has } = usePermissions()

const canEdit = computed(() => has('iam.roles.edit'))
const activeTab = ref<'time' | 'numbering'>('time')

// ── Time Management form ─────────────────────────────────────────
const saving = ref(false)
const loaded = ref(false)

// Day list rendered as a row of checkboxes. Order matches the ISO
// week so a Cambodian or Western tenant both feel at home.
const DAY_OPTIONS = [
  { value: 'mon', label: 'Mon' },
  { value: 'tue', label: 'Tue' },
  { value: 'wed', label: 'Wed' },
  { value: 'thu', label: 'Thu' },
  { value: 'fri', label: 'Fri' },
  { value: 'sat', label: 'Sat' },
  { value: 'sun', label: 'Sun' },
] as const

const timeSchema = toTypedSchema(z.object({
  morning_late_after: z.string().regex(/^\d{2}:\d{2}:\d{2}$/),
  afternoon_late_after: z.string().regex(/^\d{2}:\d{2}:\d{2}$/),
  working_days: z.array(z.enum(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])).min(1),
  work_start_time: z.string().regex(/^\d{2}:\d{2}:\d{2}$/),
  work_end_time: z.string().regex(/^\d{2}:\d{2}:\d{2}$/),
}))

const { defineField, handleSubmit, errors, setValues } = useForm({
  validationSchema: timeSchema,
  initialValues: {
    morning_late_after: '09:00:00',
    afternoon_late_after: '13:30:00',
    working_days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as ('mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun')[],
    work_start_time: '08:00:00',
    work_end_time: '17:00:00',
  },
})
const [morningLate] = defineField('morning_late_after')
const [afternoonLate] = defineField('afternoon_late_after')
const [workingDays] = defineField('working_days')
const [workStart] = defineField('work_start_time')
const [workEnd] = defineField('work_end_time')

// Toggle a day in / out of the working_days array without mutating the
// computed ref in place (vee-validate's wrapper expects whole-array
// reassignment to detect change).
const toggleDay = (day: string) => {
  const cur = (workingDays.value ?? []) as string[]
  workingDays.value = (cur.includes(day) ? cur.filter((d) => d !== day) : [...cur, day]) as typeof workingDays.value
}

// PrimeVue DatePicker with time-only mode binds to a Date object. We
// keep the form values as "HH:MM:SS" strings (matching the backend
// shape) and convert in both directions through a per-field shim.
const timeStringToDate = (s: string | undefined): Date | null => {
  if (!s) return null
  const [hh, mm, ss] = s.split(':').map(Number)
  const d = new Date()
  d.setHours(hh ?? 0, mm ?? 0, ss ?? 0, 0)
  return d
}
const dateToTimeString = (d: Date | null | undefined): string => {
  if (!d) return '00:00:00'
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

// Bidirectional refs for each TimePicker — round-trip Date ⇄ "HH:MM:SS"
// without polluting the form value.
const morningDate = computed({
  get: () => timeStringToDate(morningLate.value),
  set: (v: Date | null) => { morningLate.value = dateToTimeString(v) },
})
const afternoonDate = computed({
  get: () => timeStringToDate(afternoonLate.value),
  set: (v: Date | null) => { afternoonLate.value = dateToTimeString(v) },
})
const workStartDate = computed({
  get: () => timeStringToDate(workStart.value),
  set: (v: Date | null) => { workStart.value = dateToTimeString(v) },
})
const workEndDate = computed({
  get: () => timeStringToDate(workEnd.value),
  set: (v: Date | null) => { workEnd.value = dateToTimeString(v) },
})

const fetchTimeSettings = async () => {
  try {
    const resp = await settingApi.getAttendanceSettings()
    if (resp?.data) setValues(resp.data as AttendanceSettings)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('common.loadFailed'), detail: data?.message, life: 5000 })
  } finally {
    loaded.value = true
  }
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    await settingApi.updateAttendanceSettings(values as AttendanceSettings)
    toast.add({ severity: 'success', summary: t('settings.toast.saved'), life: 2500 })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    saving.value = false
  }
})

// ── Numbering (code prefix) form ────────────────────────────────
// Each row in NUMBERING_TYPES is one card with its own prefix /
// digits / start-from / next-number. Editing here drives the
// backend CodeGeneratorService — saving updates the counter source
// of truth for new employees, quotations, invoices, assets.
const NUMBERING_TYPES: CodeNumberingType[] = ['employee', 'quotation', 'invoice', 'asset']

const numberingLoaded = ref(false)
const numberingSaving = ref(false)
const numbering = ref<NumberingSettings>({
  employee:  { prefix: 'TT-EMP-', start_from: 1, digits: 4, next_number: 1 },
  quotation: { prefix: 'TT-QUO-', start_from: 1, digits: 6, next_number: 1 },
  invoice:   { prefix: 'TT-INV-', start_from: 1, digits: 6, next_number: 1 },
  asset:     { prefix: 'TT-AST-', start_from: 1, digits: 4, next_number: 1 },
})

// Live preview of "the next code that will be minted" — uses the
// in-form values so the admin sees the impact of their edit before
// saving.
const previewCode = (type: CodeNumberingType): string => {
  const cfg = numbering.value[type]
  if (!cfg) return ''
  const n = Number.isFinite(cfg.next_number) ? cfg.next_number : cfg.start_from
  const digits = Math.max(1, Math.min(10, Number(cfg.digits) || 1))
  return `${cfg.prefix ?? ''}${String(n).padStart(digits, '0')}`
}

const fetchNumberingSettings = async () => {
  try {
    const resp = await settingApi.getNumberingSettings()
    if (resp?.data) numbering.value = resp.data
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('common.loadFailed'), detail: data?.message, life: 5000 })
  } finally {
    numberingLoaded.value = true
  }
}

const onSaveNumbering = async () => {
  // Coerce numeric fields — number inputs sometimes leak strings
  // through, and Laravel's `integer` rule will reject those.
  const payload: NumberingSettings = NUMBERING_TYPES.reduce((acc, type) => {
    const c = numbering.value[type]
    acc[type] = {
      prefix: String(c.prefix ?? '').trim(),
      start_from: (Number(c.start_from) === 0 ? 0 : 1),
      digits: Math.max(1, Math.min(10, Number(c.digits) || 1)),
      next_number: Math.max(0, Number(c.next_number) || 0),
    }
    return acc
  }, {} as NumberingSettings)

  numberingSaving.value = true
  try {
    const resp = await settingApi.updateNumberingSettings(payload)
    if (resp?.data) numbering.value = resp.data
    toast.add({ severity: 'success', summary: t('settings.toast.saved'), life: 2500 })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    numberingSaving.value = false
  }
}

onMounted(() => {
  fetchTimeSettings()
  fetchNumberingSettings()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ t('settings.title') }}</h1>
      <p class="text-surface-500 mt-1">{{ t('settings.subtitle') }}</p>
    </div>

    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="time">
          <i class="pi pi-clock mr-1 text-xs" />
          {{ t('settings.time.tab') }}
        </Tab>
        <Tab value="numbering">
          <i class="pi pi-hashtag mr-1 text-xs" />
          {{ t('settings.numbering.tab') }}
        </Tab>
      </TabList>
      <TabPanels>
        <!-- ─────────── Time Management ─────────── -->
        <TabPanel value="time">
          <Card>
            <template #content>
              <div class="flex items-center justify-between mb-5">
                <div>
                  <h2 class="text-base font-semibold">{{ t('settings.time.title') }}</h2>
                  <p class="text-sm text-surface-500 mt-0.5">{{ t('settings.time.subtitle') }}</p>
                </div>
              </div>

              <div v-if="!loaded" class="py-12 text-center"><ProgressSpinner /></div>

              <form v-else class="space-y-6" @submit.prevent="onSave">
                <!-- Late thresholds -->
                <fieldset class="space-y-3">
                  <legend class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-2">
                    {{ t('settings.time.sections.lateThresholds') }}
                  </legend>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <FormLabel :label="t('settings.time.fields.morningLate')" required />
                      <DatePicker
                        v-model="morningDate as any"
                        time-only
                        hour-format="24"
                        show-seconds
                        :disabled="!canEdit"
                        class="w-full"
                      />
                      <small class="text-xs text-surface-500 block mt-1">{{ t('settings.time.hints.morningLate') }}</small>
                    </div>
                    <div>
                      <FormLabel :label="t('settings.time.fields.afternoonLate')" required />
                      <DatePicker
                        v-model="afternoonDate as any"
                        time-only
                        hour-format="24"
                        show-seconds
                        :disabled="!canEdit"
                        class="w-full"
                      />
                      <small class="text-xs text-surface-500 block mt-1">{{ t('settings.time.hints.afternoonLate') }}</small>
                    </div>
                  </div>
                </fieldset>

                <!-- Working hours -->
                <fieldset class="space-y-3">
                  <legend class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-2">
                    {{ t('settings.time.sections.workingHours') }}
                  </legend>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <FormLabel :label="t('settings.time.fields.workStart')" required />
                      <DatePicker
                        v-model="workStartDate as any"
                        time-only
                        hour-format="24"
                        show-seconds
                        :disabled="!canEdit"
                        class="w-full"
                      />
                    </div>
                    <div>
                      <FormLabel :label="t('settings.time.fields.workEnd')" required />
                      <DatePicker
                        v-model="workEndDate as any"
                        time-only
                        hour-format="24"
                        show-seconds
                        :disabled="!canEdit"
                        class="w-full"
                      />
                      <small v-if="errors.work_end_time" class="text-xs text-red-500 block mt-1">{{ errors.work_end_time }}</small>
                    </div>
                  </div>
                </fieldset>

                <!-- Working days -->
                <fieldset class="space-y-3">
                  <legend class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-2">
                    {{ t('settings.time.sections.workingDays') }}
                  </legend>
                  <p class="text-xs text-surface-500 mb-2">{{ t('settings.time.hints.workingDays') }}</p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="d in DAY_OPTIONS"
                      :key="d.value"
                      type="button"
                      :disabled="!canEdit"
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border cursor-pointer transition-colors text-sm disabled:cursor-not-allowed disabled:opacity-60"
                      :class="((workingDays as unknown as string[]) ?? []).includes(d.value)
                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300'
                        : 'bg-surface-0 border-surface-200 text-surface-500 dark:bg-surface-900 dark:border-surface-700 hover:border-surface-300 dark:hover:border-surface-600'"
                      @click="toggleDay(d.value)"
                    >
                      <i
                        v-if="((workingDays as unknown as string[]) ?? []).includes(d.value)"
                        class="pi pi-check text-[10px]"
                      />
                      <span>{{ t(`settings.time.days.${d.value}`) }}</span>
                    </button>
                  </div>
                  <small v-if="errors.working_days" class="text-xs text-red-500 block mt-1">{{ errors.working_days }}</small>
                </fieldset>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-2 border-t border-surface-200 dark:border-surface-800">
                  <p v-if="!canEdit" class="text-xs text-surface-400 inline-flex items-center gap-1">
                    <i class="pi pi-lock text-[10px]" />
                    {{ t('settings.readOnlyHint') }}
                  </p>
                  <Button
                    v-if="canEdit"
                    type="submit"
                    :label="t('common.save')"
                    icon="pi pi-check"
                    :loading="saving"
                    class="ml-auto"
                  />
                </div>
              </form>
            </template>
          </Card>
        </TabPanel>

        <!-- ─────────── Numbering / Code prefixes ─────────── -->
        <TabPanel value="numbering">
          <Card>
            <template #content>
              <div class="flex items-center justify-between mb-5">
                <div>
                  <h2 class="text-base font-semibold">{{ t('settings.numbering.title') }}</h2>
                  <p class="text-sm text-surface-500 mt-0.5">{{ t('settings.numbering.subtitle') }}</p>
                </div>
              </div>

              <div v-if="!numberingLoaded" class="py-12 text-center"><ProgressSpinner /></div>

              <form v-else class="space-y-5" @submit.prevent="onSaveNumbering">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                  <div
                    v-for="type in NUMBERING_TYPES"
                    :key="type"
                    class="rounded-lg border border-surface-200 dark:border-surface-800 p-4 bg-surface-0 dark:bg-surface-900"
                  >
                    <div class="flex items-center justify-between mb-3">
                      <h3 class="text-sm font-semibold inline-flex items-center gap-2">
                        <i
                          :class="{
                            employee:  'pi pi-user',
                            quotation: 'pi pi-file-edit',
                            invoice:   'pi pi-receipt',
                            asset:     'pi pi-box',
                          }[type]"
                          class="text-primary-600"
                        />
                        {{ t(`settings.numbering.types.${type}`) }}
                      </h3>
                      <!-- Live preview chip -->
                      <span class="text-xs font-mono px-2 py-1 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        {{ previewCode(type) }}
                      </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                      <div class="col-span-2">
                        <FormLabel :label="t('settings.numbering.fields.prefix')" required />
                        <InputText
                          v-model="numbering[type].prefix"
                          :disabled="!canEdit"
                          maxlength="32"
                          :placeholder="t('settings.numbering.fields.prefixPlaceholder')"
                          class="w-full"
                        />
                        <small class="text-xs text-surface-500 block mt-1">{{ t('settings.numbering.hints.prefix') }}</small>
                      </div>

                      <div>
                        <FormLabel :label="t('settings.numbering.fields.digits')" required />
                        <InputNumber
                          v-model="numbering[type].digits"
                          :disabled="!canEdit"
                          :min="1"
                          :max="10"
                          show-buttons
                          button-layout="horizontal"
                          decrement-button-icon="pi pi-minus"
                          increment-button-icon="pi pi-plus"
                          input-class="w-full text-center"
                          class="w-full"
                        />
                      </div>

                      <div>
                        <FormLabel :label="t('settings.numbering.fields.startFrom')" required />
                        <Select
                          v-model="numbering[type].start_from"
                          :options="[{ label: '0', value: 0 }, { label: '1', value: 1 }]"
                          option-label="label"
                          option-value="value"
                          :disabled="!canEdit"
                          class="w-full"
                        />
                      </div>

                      <div class="col-span-2">
                        <FormLabel :label="t('settings.numbering.fields.nextNumber')" required />
                        <InputNumber
                          v-model="numbering[type].next_number"
                          :disabled="!canEdit"
                          :min="0"
                          show-buttons
                          button-layout="horizontal"
                          decrement-button-icon="pi pi-minus"
                          increment-button-icon="pi pi-plus"
                          input-class="w-full text-center"
                          class="w-full"
                        />
                        <small class="text-xs text-amber-600 dark:text-amber-400 block mt-1 inline-flex items-center gap-1">
                          <i class="pi pi-exclamation-triangle text-[10px]" />
                          {{ t('settings.numbering.hints.nextNumber') }}
                        </small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-2 border-t border-surface-200 dark:border-surface-800">
                  <p v-if="!canEdit" class="text-xs text-surface-400 inline-flex items-center gap-1">
                    <i class="pi pi-lock text-[10px]" />
                    {{ t('settings.readOnlyHint') }}
                  </p>
                  <Button
                    v-if="canEdit"
                    type="submit"
                    :label="t('common.save')"
                    icon="pi pi-check"
                    :loading="numberingSaving"
                    class="ml-auto"
                  />
                </div>
              </form>
            </template>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
