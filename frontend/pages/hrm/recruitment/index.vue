<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Application, Interview, Vacancy } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const tab = ref<'vacancies' | 'applications' | 'interviews'>('vacancies')

const { data: deptData } = await useAsyncData('hrm-rec-depts', () => hrm.listDepartments({ per_page: 200 }))
const { data: posData }  = await useAsyncData('hrm-rec-positions', () => hrm.listPositions({ per_page: 200 }))
const departments = computed(() => deptData.value?.data?.data ?? [])
const positions   = computed(() => posData.value?.data?.data ?? [])

// ---------------- Vacancies ----------------
const vacPage = ref(1)
const vacStatus = ref<string | null>(null)
const { data: vacData, refresh: refreshVacancies, pending: vacPending } = await useAsyncData(
  'hrm-vacancies',
  () => hrm.listVacancies({ status: vacStatus.value || undefined, page: vacPage.value, per_page: 25 }),
  { watch: [vacPage, vacStatus] },
)
const vacancies = computed<Vacancy[]>(() => vacData.value?.data?.data ?? [])
const vacMeta = computed(() => vacData.value?.data)

const vacDialog = ref(false)
const editingVac = ref<Vacancy | null>(null)
const vacSaving = ref(false)
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

const vacSchema = toTypedSchema(z.object({
  title: z.string().min(2).max(200),
  reference: z.string().min(1).max(32),
  department_id: z.string().uuid().nullable().optional(),
  position_id: z.string().uuid().nullable().optional(),
  description: z.string().optional().or(z.literal('')),
  requirements: z.string().optional().or(z.literal('')),
  location: z.string().max(120).optional().or(z.literal('')),
  salary_min: z.coerce.number().min(0).nullable().optional(),
  salary_max: z.coerce.number().min(0).nullable().optional(),
  employment_type: z.enum(['full_time', 'part_time', 'contract', 'intern']),
  status: z.string().optional().or(z.literal('')),
  opens_at: z.preprocess(datePreprocess, z.string().nullable().optional()),
  closes_at: z.preprocess(datePreprocess, z.string().nullable().optional()),
}))
const { defineField: vField, handleSubmit: handleVac, errors: vErrors, resetForm: resetVac, setValues: setVac } = useForm({
  validationSchema: vacSchema,
  initialValues: {
    title: '', reference: '',
    department_id: null, position_id: null,
    description: '', requirements: '', location: '',
    salary_min: null, salary_max: null,
    employment_type: 'full_time',
    status: 'draft', opens_at: '', closes_at: '',
  },
})
const [vTitle] = vField('title')
const [vReference] = vField('reference')
const [vDept] = vField('department_id')
const [vPosition] = vField('position_id')
const [vDescription] = vField('description')
const [vRequirements] = vField('requirements')
const [vLocation] = vField('location')
const [vSalaryMin] = vField('salary_min')
const [vSalaryMax] = vField('salary_max')
const [vEmpType] = vField('employment_type')
const [vStatus] = vField('status')
const [vOpens] = vField('opens_at')
const [vCloses] = vField('closes_at')

const employmentTypes = computed(() => (['full_time', 'part_time', 'contract', 'intern'] as const).map((v) => ({
  label: t(`hrm.employees.employmentTypes.${v}`), value: v,
})))
const vacancyStatuses = ['draft', 'open', 'closed', 'filled']

const openVacCreate = () => {
  editingVac.value = null
  resetVac()
  vacDialog.value = true
}
const openVacEdit = (row: Vacancy) => {
  editingVac.value = row
  setVac({
    title: row.title, reference: row.reference,
    department_id: row.department_id ?? null,
    position_id: row.position_id ?? null,
    description: row.description ?? '',
    requirements: row.requirements ?? '',
    location: row.location ?? '',
    salary_min: row.salary_min != null ? Number(row.salary_min) : null,
    salary_max: row.salary_max != null ? Number(row.salary_max) : null,
    employment_type: row.employment_type,
    status: row.status,
    opens_at: row.opens_at ?? '',
    closes_at: row.closes_at ?? '',
  })
  vacDialog.value = true
}
const onSaveVac = handleVac(
  async (values) => {
    vacSaving.value = true
    try {
      const payload = { ...values }
      if (editingVac.value) {
        await hrm.updateVacancy(editingVac.value.id, payload)
        toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.vacancyUpdated'), life: 2000 })
      } else {
        await hrm.createVacancy(payload)
        toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.vacancyCreated'), life: 2000 })
      }
      vacDialog.value = false
      await refreshVacancies()
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } }).data
      toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
    } finally {
      vacSaving.value = false
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
const onDeleteVac = (row: Vacancy) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.title }),
    header: t('hrm.common.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteVacancy(row.id)
      toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.vacancyDeleted'), life: 2000 })
      await refreshVacancies()
    },
  })
}

const statusSeverity = (s: string) => {
  if (['open', 'hired', 'completed', 'filled', 'approved'].includes(s)) return 'success'
  if (['rejected', 'cancelled'].includes(s)) return 'danger'
  if (['offer', 'interview'].includes(s)) return 'info'
  if (['draft', 'pending', 'applied', 'screening'].includes(s)) return 'warn'
  return 'secondary'
}

// ---------------- Applications (Kanban) ----------------
// Kanban view fetches the full pipeline at once and groups client-side
// by status. We cap at 500 cards — beyond that a kanban becomes unusable
// regardless of pagination, and the vacancy filter is the right escape
// hatch. If a tenant ever hits this ceiling we'll add a server-side
// "summary by status" endpoint.
const appVacancy = ref<string | null>(null)
const appSearch  = ref<string>('')
const { data: appData, refresh: refreshApps, pending: appPending } = await useAsyncData(
  'hrm-applications',
  () => hrm.listApplications({
    vacancy_id: appVacancy.value || undefined,
    per_page: 500,
  }),
  { watch: [appVacancy] },
)
const allApps = computed<Application[]>(() => appData.value?.data?.data ?? [])
// `apps` retained for unchanged consumers (interview filter + dialog).
const apps = allApps

// Candidate search runs purely client-side over the already-fetched list.
const filteredApps = computed<Application[]>(() => {
  const term = appSearch.value.trim().toLowerCase()
  if (!term) return allApps.value
  return allApps.value.filter((a) =>
    (a.first_name + ' ' + a.last_name).toLowerCase().includes(term)
    || a.email.toLowerCase().includes(term)
    || (a.vacancy?.title ?? '').toLowerCase().includes(term)
  )
})

// Pipeline columns — the five-stage linear funnel. Rejected/Withdrawn
// are intentionally NOT board columns (they'd clutter the active view);
// users can still move applications there via the pencil/transition
// dialog on each card, and the List view shows everything.
//
// `pill` is a flat color class for the column header (matches the
// screenshot's grouping: blue for early stages, amber for active
// pipeline, green for the final win). Pure cosmetic — backend cares
// only about the `key`.
interface KanbanColumn {
  key: string
  pill: string  // Tailwind classes for the column-header chip
}
const pipelineColumns: KanbanColumn[] = [
  { key: 'applied',    pill: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' },
  { key: 'screening',  pill: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' },
  { key: 'interview',  pill: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
  { key: 'offer',      pill: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
  { key: 'hired',      pill: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
]
// Full status set — used by the transition dialog and the List view.
const applicationStatuses = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn']

// Board (default kanban) vs. List (flat data-table fallback). Persisted
// in localStorage so the user's preference survives a page reload.
const viewMode = ref<'board' | 'list'>('board')
if (import.meta.client) {
  const saved = localStorage.getItem('hrm.recruitment.viewMode')
  if (saved === 'board' || saved === 'list') viewMode.value = saved
  watch(viewMode, (v) => localStorage.setItem('hrm.recruitment.viewMode', v))
}

// ── Card-level display helpers ────────────────────────────────
// Relative time-ago for the card footer. Falls back to a localized
// date string past 30 days so we don't surface absurd values like
// "215 days ago" — recruiters care about staleness, not exact age.
const timeAgo = (iso?: string | null): string => {
  if (!iso) return ''
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60)        return t('hrm.recruitment.applications.timeAgo.justNow')
  if (diff < 3600)      return t('hrm.recruitment.applications.timeAgo.mAgo', { n: Math.floor(diff / 60) })
  if (diff < 86400)     return t('hrm.recruitment.applications.timeAgo.hAgo', { n: Math.floor(diff / 3600) })
  if (diff < 30 * 86400) return t('hrm.recruitment.applications.timeAgo.dAgo', { n: Math.floor(diff / 86400) })
  return new Date(iso).toLocaleDateString()
}

// Show a NEW badge on cards created within the last 48 hours. Keeps
// the funnel scannable — recruiters often want to know what just came
// in without having to sort.
const isNewApp = (iso?: string | null): boolean => {
  if (!iso) return false
  return (Date.now() - new Date(iso).getTime()) < 48 * 3600 * 1000
}

// Group applications by status. Pre-seed every known status (including
// the off-board rejected/withdrawn) so the List view and column count
// chips always have a stable shape.
const appsByStatus = computed<Record<string, Application[]>>(() => {
  const buckets: Record<string, Application[]> = {}
  for (const k of applicationStatuses) buckets[k] = []
  for (const a of filteredApps.value) {
    if (buckets[a.status]) buckets[a.status].push(a)
  }
  return buckets
})

// ── Drag-and-drop ─────────────────────────────────────────────
// Native HTML5: dragstart sets the payload, dragover.prevent on the
// column allows drop, drop fires the transition. We optimistically
// move the card immediately (so it feels instant) and roll back if
// the backend rejects the transition (e.g. illegal status path).
const draggingId   = ref<string | null>(null)
const draggingFrom = ref<string | null>(null)
const dragOverCol  = ref<string | null>(null)

const onDragStart = (e: DragEvent, app: Application) => {
  draggingId.value   = app.id
  draggingFrom.value = app.status
  // dataTransfer is required for the drag to actually fire on Firefox.
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', app.id)
  }
}

const onDragEnd = () => {
  draggingId.value   = null
  draggingFrom.value = null
  dragOverCol.value  = null
}

const onColDragOver = (e: DragEvent, colKey: string) => {
  if (!draggingId.value) return
  e.preventDefault()
  dragOverCol.value = colKey
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
}

const onColDragLeave = (colKey: string) => {
  if (dragOverCol.value === colKey) dragOverCol.value = null
}

const onColDrop = async (e: DragEvent, colKey: string) => {
  e.preventDefault()
  const id   = draggingId.value
  const from = draggingFrom.value
  dragOverCol.value = null
  draggingId.value = null
  draggingFrom.value = null
  if (!id || !from || from === colKey) return

  // Optimistic move: mutate the in-memory list so the card jumps
  // columns immediately. allApps is derived from appData.value.data.data
  // which is reactive, so editing it in place works.
  const list = appData.value?.data?.data
  if (!list) return
  const row = list.find((a: Application) => a.id === id)
  if (!row) return
  const prevStatus = row.status
  row.status = colKey

  try {
    await hrm.transitionApplication(id, colKey)
    toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.applicationTransitioned'), life: 1800 })
  } catch (err: unknown) {
    // Roll back the optimistic move.
    row.status = prevStatus
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}

// Bulk-convert lives on the Hired column header now (the multi-select
// table is gone). Converts every unlinked hired application visible in
// the current filter.
const hiredUnlinked = computed(() =>
  appsByStatus.value.hired.filter((a) => !a.employee_id))

const appDialog = ref(false)
const appSaving = ref(false)
const appSchema = toTypedSchema(z.object({
  vacancy_id: z.string().uuid(),
  first_name: z.string().min(1).max(80),
  last_name: z.string().min(1).max(80),
  email: z.string().email(),
  phone: z.string().max(32).optional().or(z.literal('')),
  resume_path: z.string().max(255).optional().or(z.literal('')),
  cover_letter_path: z.string().max(255).optional().or(z.literal('')),
  expected_salary: z.coerce.number().min(0).nullable().optional(),
}))
const { defineField: aField, handleSubmit: handleApp, errors: aErrors, resetForm: resetApp } = useForm({
  validationSchema: appSchema,
  initialValues: { vacancy_id: '', first_name: '', last_name: '', email: '', phone: '', resume_path: '', cover_letter_path: '', expected_salary: null },
})
const [aVacancy] = aField('vacancy_id')
const [aFirstName] = aField('first_name')
const [aLastName] = aField('last_name')
const [aEmail] = aField('email')
const [aPhone] = aField('phone')
const [aResume] = aField('resume_path')
const [aSalary] = aField('expected_salary')

const openAppCreate = () => { resetApp(); appDialog.value = true }
const onCreateApp = handleApp(
  async (values) => {
    appSaving.value = true
    try {
      await hrm.submitApplication(values)
      toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.applicationSubmitted'), life: 2000 })
      appDialog.value = false
      await refreshApps()
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } }).data
      toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
    } finally {
      appSaving.value = false
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

// Transition dialog
const transDialog = ref(false)
const transTarget = ref<Application | null>(null)
const transStatus = ref<string>('')
const openTransition = (row: Application) => {
  transTarget.value = row
  transStatus.value = row.status
  transDialog.value = true
}
const onTransition = async () => {
  if (!transTarget.value) return
  try {
    await hrm.transitionApplication(transTarget.value.id, transStatus.value)
    toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.applicationTransitioned'), life: 2000 })
    transDialog.value = false
    await refreshApps()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}

const convertApp = (row: Application) => {
  confirm.require({
    message: t('hrm.recruitment.applications.convertConfirm', { candidate: `${row.first_name} ${row.last_name}` }),
    header: t('hrm.recruitment.applications.actions.convert'),
    acceptClass: 'p-button-primary',
    accept: async () => {
      try {
        const res = await hrm.convertApplication(row.id)
        const key = res.linkedExisting ? 'convertedExisting' : 'convertedFresh'
        toast.add({
          severity: 'success',
          summary: t('hrm.recruitment.toast.applicationConverted'),
          detail: t(`hrm.recruitment.applications.${key}`, { employeeId: res.data.employee_id }),
          life: 4000,
        })
        await refreshApps()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
      }
    },
  })
}

const revertApp = (row: Application) => {
  confirm.require({
    message: t('hrm.recruitment.applications.revertConfirm', { convertedAt: row.converted_at ?? '' }),
    header: t('hrm.recruitment.applications.actions.revert'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await hrm.revertApplicationConversion(row.id)
        toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.applicationReverted'), life: 2500 })
        await refreshApps()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
      }
    },
  })
}

// Bulk-convert every unlinked hire visible in the current filter. The
// button lives on the Hired column header — there's no row-selection
// model in the kanban view, so the action is "convert all visible
// hired-and-unlinked candidates" instead of a checkbox-driven subset.
const bulkConvert = () => {
  const ids = hiredUnlinked.value.map((a) => a.id)
  if (!ids.length) {
    toast.add({
      severity: 'warn',
      summary: t('hrm.recruitment.applications.actions.bulkConvert'),
      detail: t('hrm.recruitment.applications.bulkConvertEmpty'),
      life: 4000,
    })
    return
  }
  confirm.require({
    header: t('hrm.recruitment.applications.bulkConvertHeader'),
    message: t('hrm.recruitment.applications.bulkConvertBody', { count: ids.length }),
    acceptClass: 'p-button-primary',
    accept: async () => {
      try {
        const res = await hrm.bulkConvertApplications(ids)
        toast.add({
          severity: res.errors.length ? 'warn' : 'success',
          summary: t('hrm.recruitment.toast.applicationConverted'),
          detail: t('hrm.recruitment.applications.bulkResult', {
            converted: res.converted,
            linked: res.alreadyLinked.length,
            ineligible: res.ineligible.length,
            missing: res.missing.length,
            errors: res.errors.length,
          }),
          life: 6000,
        })
        await refreshApps()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
      }
    },
  })
}

// ---------------- Interviews ----------------
const intvPage = ref(1)
const intvApplication = ref<string | null>(null)
const { data: intvData, refresh: refreshInterviews, pending: intvPending } = await useAsyncData(
  'hrm-interviews',
  () => hrm.listInterviews({ application_id: intvApplication.value || undefined, page: intvPage.value, per_page: 25 }),
  { watch: [intvPage, intvApplication] },
)
const interviews = computed<Interview[]>(() => intvData.value?.data?.data ?? [])
const intvMeta = computed(() => intvData.value?.data)

const intvDialog = ref(false)
const intvSaving = ref(false)

const dateTimePreprocess = (val: unknown) => {
  if (val instanceof Date) {
    const year = val.getFullYear()
    const month = String(val.getMonth() + 1).padStart(2, '0')
    const day = String(val.getDate()).padStart(2, '0')
    const hours = String(val.getHours()).padStart(2, '0')
    const minutes = String(val.getMinutes()).padStart(2, '0')
    const seconds = String(val.getSeconds()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
  }
  if (typeof val === 'string' && val.trim() !== '') {
    return val
  }
  return null
}

const intvSchema = toTypedSchema(z.object({
  application_id: z.string().uuid(),
  scheduled_at: z.preprocess(dateTimePreprocess, z.string().min(1, 'Scheduled date/time is required')),
  duration_minutes: z.coerce.number().min(5).max(480),
  mode: z.enum(['virtual', 'onsite', 'phone']),
  location: z.string().max(255).optional().or(z.literal('')),
  round_label: z.string().max(80).optional().or(z.literal('')),
}))
const { defineField: iField, handleSubmit: handleIntv, errors: iErrors, resetForm: resetIntv } = useForm({
  validationSchema: intvSchema,
  initialValues: { application_id: '', scheduled_at: '', duration_minutes: 45, mode: 'virtual', location: '', round_label: '' },
})
const [iApp] = iField('application_id')
const [iSched] = iField('scheduled_at')
const [iDuration] = iField('duration_minutes')
const [iMode] = iField('mode')
const [iLocation] = iField('location')
const [iRound] = iField('round_label')

const interviewModes = computed(() => (['virtual', 'onsite', 'phone'] as const).map((v) => ({
  label: t(`hrm.recruitment.interviews.modes.${v}`), value: v,
})))

const openIntvCreate = () => { resetIntv(); intvDialog.value = true }
const onCreateIntv = handleIntv(
  async (values) => {
    intvSaving.value = true
    try {
      await hrm.createInterview(values)
      toast.add({ severity: 'success', summary: t('hrm.recruitment.toast.interviewScheduled'), life: 2000 })
      intvDialog.value = false
      await refreshInterviews()
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } }).data
      toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
    } finally {
      intvSaving.value = false
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
const onDeleteIntv = (row: Interview) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.round_label ?? row.id }),
    header: t('hrm.common.confirmHeader'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteInterview(row.id)
      await refreshInterviews()
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.recruitment.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.recruitment.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="tab === 'vacancies'"    :label="t('hrm.recruitment.vacancies.new')"  icon="pi pi-plus" @click="openVacCreate" />
        <Button v-if="tab === 'applications'" :label="t('hrm.recruitment.applications.new')" icon="pi pi-plus" @click="openAppCreate" />
        <Button v-if="tab === 'interviews'"   :label="t('hrm.recruitment.interviews.new')" icon="pi pi-plus" @click="openIntvCreate" />
      </div>
    </div>

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="vacancies">{{ t('hrm.recruitment.tabs.vacancies') }}</Tab>
        <Tab value="applications">{{ t('hrm.recruitment.tabs.applications') }}</Tab>
        <Tab value="interviews">{{ t('hrm.recruitment.tabs.interviews') }}</Tab>
      </TabList>
      <TabPanels>
        <!-- Vacancies -->
        <TabPanel value="vacancies">
          <Card>
            <template #content>
              <div class="flex flex-wrap items-center gap-3 mb-4">
                <Select v-model="vacStatus" :options="vacancyStatuses" :placeholder="t('hrm.common.status')" show-clear class="w-44" />
              </div>
              <DataTable :value="vacancies" :loading="vacPending" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.recruitment.vacancies.empty') }}</div>
                </template>
                <Column :header="t('hrm.recruitment.vacancies.columns.reference')">
                  <template #body="{ data }"><code class="font-mono text-xs">{{ data.reference }}</code></template>
                </Column>
                <Column field="title" :header="t('hrm.recruitment.vacancies.columns.title')" />
                <Column :header="t('hrm.recruitment.vacancies.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.vacancies.columns.opens')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ formatDate(data.opens_at) }}</span></template>
                </Column>
                <Column :header="t('hrm.recruitment.vacancies.columns.closes')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ formatDate(data.closes_at) }}</span></template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '140px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openVacEdit(data)" />
                    <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDeleteVac(data)" />
                  </template>
                </Column>
              </DataTable>
              <Paginator v-if="vacMeta && vacMeta.last_page > 1" :rows="vacMeta.per_page" :total-records="vacMeta.total" :first="(vacMeta.current_page - 1) * vacMeta.per_page" @page="(e) => vacPage = e.page + 1" />
            </template>
          </Card>
        </TabPanel>

        <!-- Applications — Candidate Pipeline (kanban + list) -->
        <TabPanel value="applications">
          <Card>
            <template #content>
              <!-- Title bar: matches the screenshot's "Candidate Pipeline"
                   intro + Board/List view toggle + quick actions. -->
              <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-5">
                <div>
                  <h2 class="text-xl font-semibold tracking-tight">{{ t('hrm.recruitment.applications.pipeline.title') }}</h2>
                  <p class="text-sm text-surface-500 mt-0.5">{{ t('hrm.recruitment.applications.pipeline.subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                  <!-- View toggle. Two segmented buttons, Board on left. -->
                  <div class="inline-flex rounded-md border border-surface-200 dark:border-surface-700 p-0.5 bg-surface-0 dark:bg-surface-900">
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded inline-flex items-center gap-1.5 transition-colors"
                      :class="viewMode === 'board'
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'text-surface-500 hover:text-surface-700 dark:hover:text-surface-300'"
                      @click="viewMode = 'board'"
                    >
                      <i class="pi pi-th-large text-xs" /> {{ t('hrm.recruitment.applications.pipeline.viewBoard') }}
                    </button>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded inline-flex items-center gap-1.5 transition-colors"
                      :class="viewMode === 'list'
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'text-surface-500 hover:text-surface-700 dark:hover:text-surface-300'"
                      @click="viewMode = 'list'"
                    >
                      <i class="pi pi-list text-xs" /> {{ t('hrm.recruitment.applications.pipeline.viewList') }}
                    </button>
                  </div>
                  <Button
                    :label="t('hrm.recruitment.applications.new')"
                    icon="pi pi-user-plus"
                    severity="success"
                    @click="openAppCreate"
                  />
                  <Button
                    :label="t('hrm.recruitment.applications.pipeline.postJob')"
                    icon="pi pi-plus"
                    severity="secondary"
                    outlined
                    @click="openVacCreate"
                  />
                </div>
              </div>

              <!-- Filter row: prominent search + vacancy filter. -->
              <div class="flex flex-col sm:flex-row gap-3 mb-5">
                <IconField icon-position="left" class="flex-1">
                  <InputIcon class="pi pi-search" />
                  <InputText
                    v-model="appSearch"
                    :placeholder="t('hrm.recruitment.applications.searchPlaceholder')"
                    class="w-full"
                  />
                </IconField>
                <Select
                  v-model="appVacancy"
                  :options="vacancies"
                  option-label="title"
                  option-value="id"
                  :placeholder="t('hrm.recruitment.applications.pipeline.allVacancies')"
                  show-clear
                  class="sm:w-72"
                />
              </div>

              <div v-if="appPending" class="text-xs text-surface-500 inline-flex items-center gap-2 mb-2">
                <i class="pi pi-spin pi-spinner" /> {{ t('hrm.common.loading') }}
              </div>

              <!-- ─────────── Board view ─────────── -->
              <div v-if="viewMode === 'board'" class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1">
                <div
                  v-for="col in pipelineColumns"
                  :key="col.key"
                  class="flex-shrink-0 w-72 rounded-xl bg-surface-50 dark:bg-surface-900/60 p-3 transition-colors"
                  :class="dragOverCol === col.key ? 'ring-2 ring-emerald-400 bg-emerald-50/30 dark:bg-emerald-950/20' : ''"
                  @dragover="(e) => onColDragOver(e, col.key)"
                  @dragleave="onColDragLeave(col.key)"
                  @drop="(e) => onColDrop(e, col.key)"
                >
                  <!-- Column header: status pill + count + hired-only quick action -->
                  <div class="flex items-center justify-between mb-3">
                    <div
                      class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wider"
                      :class="col.pill"
                    >
                      <span>{{ t(`hrm.recruitment.applications.kanban.statuses.${col.key}`) }}</span>
                      <span class="opacity-70">({{ appsByStatus[col.key].length }})</span>
                    </div>
                    <Button
                      v-if="col.key === 'hired' && hiredUnlinked.length"
                      icon="pi pi-users"
                      text rounded size="small" severity="success"
                      :aria-label="t('hrm.recruitment.applications.actions.bulkConvert')"
                      :title="t('hrm.recruitment.applications.actions.bulkConvert')"
                      @click="bulkConvert"
                    />
                  </div>

                  <!-- Cards -->
                  <div class="space-y-3 min-h-[6rem]">
                    <template v-if="appsByStatus[col.key].length">
                      <div
                        v-for="card in appsByStatus[col.key]"
                        :key="card.id"
                        draggable="true"
                        class="group rounded-lg bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 p-3 shadow-sm cursor-grab active:cursor-grabbing transition-all"
                        :class="draggingId === card.id ? 'opacity-40' : 'hover:shadow-md hover:-translate-y-0.5'"
                        @dragstart="(e) => onDragStart(e, card)"
                        @dragend="onDragEnd"
                      >
                        <!-- Avatar + title block -->
                        <div class="flex items-start gap-2.5">
                          <div class="size-8 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 grid place-items-center text-[11px] font-semibold uppercase flex-shrink-0">
                            {{ (card.first_name?.[0] ?? '') + (card.last_name?.[0] ?? '') }}
                          </div>
                          <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                              <div class="text-sm font-semibold truncate">
                                {{ card.first_name }} {{ card.last_name }}
                              </div>
                              <span
                                v-if="isNewApp(card.created_at)"
                                class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 flex-shrink-0"
                              >
                                {{ t('hrm.recruitment.applications.pipeline.newBadge') }}
                              </span>
                            </div>
                            <div v-if="card.vacancy" class="text-[11px] text-surface-500 truncate mt-0.5">
                              <code class="font-mono">{{ card.vacancy.reference }}</code>
                              <span class="mx-1">·</span>
                              <span>{{ card.vacancy.title }}</span>
                            </div>
                          </div>
                        </div>

                        <!-- Footer: email + time + link badge -->
                        <div class="mt-3 pt-3 border-t border-surface-100 dark:border-surface-700/60 flex items-center justify-between gap-2 text-[11px] text-surface-500">
                          <div class="inline-flex items-center gap-1 min-w-0">
                            <i class="pi pi-envelope text-[10px] flex-shrink-0" />
                            <span class="truncate">{{ card.email }}</span>
                          </div>
                          <div class="inline-flex items-center gap-1 flex-shrink-0">
                            <i class="pi pi-clock text-[10px]" />
                            <span>{{ timeAgo(card.created_at) }}</span>
                          </div>
                        </div>

                        <!-- Hover actions -->
                        <div class="mt-1 flex items-center justify-between gap-2">
                          <Tag
                            v-if="card.employee"
                            severity="success"
                            :value="card.employee.employee_id"
                            icon="pi pi-link"
                            class="!text-[10px] !py-0"
                          />
                          <span v-else />
                          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button
                              v-if="card.status === 'hired' && !card.employee_id"
                              icon="pi pi-user-plus"
                              text rounded size="small" severity="success"
                              :aria-label="t('hrm.recruitment.applications.actions.convert')"
                              :title="t('hrm.recruitment.applications.actions.convert')"
                              @click.stop="convertApp(card)"
                            />
                            <Button
                              v-if="card.employee_id && card.converted_at"
                              icon="pi pi-undo"
                              text rounded size="small" severity="warn"
                              :aria-label="t('hrm.recruitment.applications.actions.revert')"
                              :title="t('hrm.recruitment.applications.actions.revert')"
                              @click.stop="revertApp(card)"
                            />
                            <Button
                              icon="pi pi-pencil"
                              text rounded size="small" severity="secondary"
                              :aria-label="t('hrm.recruitment.applications.actions.transition')"
                              :title="t('hrm.recruitment.applications.actions.transition')"
                              @click.stop="openTransition(card)"
                            />
                          </div>
                        </div>
                      </div>
                    </template>
                    <div
                      v-else
                      class="border-2 border-dashed border-surface-300 dark:border-surface-700 rounded-lg py-8 px-3 text-center text-xs text-surface-400"
                    >
                      {{ t('hrm.recruitment.applications.pipeline.dropHereTo', {
                        status: t(`hrm.recruitment.applications.kanban.statuses.${col.key}`)
                      }) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- ─────────── List view ─────────── -->
              <!-- Flat DataTable view that includes rejected/withdrawn too,
                   for when you need to find a candidate by name without
                   scrolling the board. -->
              <DataTable
                v-else
                :value="filteredApps"
                :loading="appPending"
                data-key="id"
                striped-rows
                paginator
                :rows="25"
                class="text-sm"
              >
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.recruitment.applications.empty') }}</div>
                </template>
                <Column :header="t('hrm.recruitment.applications.columns.candidate')">
                  <template #body="{ data }">
                    <div class="font-medium">{{ data.first_name }} {{ data.last_name }}</div>
                    <span class="text-xs text-surface-500">{{ data.email }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.applications.columns.vacancy')">
                  <template #body="{ data }">
                    <div v-if="data.vacancy">
                      <div class="text-sm">{{ data.vacancy.title }}</div>
                      <code class="text-xs font-mono text-surface-500">{{ data.vacancy.reference }}</code>
                    </div>
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.applications.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.applications.columns.linked')">
                  <template #body="{ data }">
                    <Tag v-if="data.employee" severity="success" :value="data.employee.employee_id" />
                    <span v-else class="text-xs text-surface-400">{{ t('hrm.recruitment.applications.linkedNo') }}</span>
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.applications.columns.submitted')">
                  <template #body="{ data }">
                    <span class="font-mono text-xs">{{ timeAgo(data.created_at) }}</span>
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '180px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" :title="t('hrm.recruitment.applications.actions.transition')" @click="openTransition(data)" />
                    <Button v-if="data.status === 'hired' && !data.employee_id" icon="pi pi-user-plus" text rounded severity="success" :title="t('hrm.recruitment.applications.actions.convert')" @click="convertApp(data)" />
                    <Button v-if="data.employee_id && data.converted_at" icon="pi pi-undo" text rounded severity="warn" :title="t('hrm.recruitment.applications.actions.revert')" @click="revertApp(data)" />
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>
        </TabPanel>

        <!-- Interviews -->
        <TabPanel value="interviews">
          <Card>
            <template #content>
              <div class="flex flex-wrap items-center gap-3 mb-4">
                <Select v-model="intvApplication" :options="apps" option-value="id" :placeholder="t('hrm.recruitment.applications.columns.candidate')" show-clear class="w-72">
                  <template #option="{ option }">{{ option.first_name }} {{ option.last_name }}</template>
                </Select>
              </div>
              <DataTable :value="interviews" :loading="intvPending" data-key="id" striped-rows class="text-sm">
                <template #empty>
                  <div class="py-10 text-center text-surface-500">{{ t('hrm.recruitment.interviews.empty') }}</div>
                </template>
                <Column :header="t('hrm.recruitment.interviews.columns.candidate')">
                  <template #body="{ data }">
                    <span v-if="data.application">{{ data.application.first_name }} {{ data.application.last_name }}</span>
                  </template>
                </Column>
                <Column field="round_label" :header="t('hrm.recruitment.interviews.columns.round')" />
                <Column :header="t('hrm.recruitment.interviews.columns.scheduled')">
                  <template #body="{ data }"><span class="font-mono text-xs">{{ formatDateTime(data.scheduled_at) }}</span></template>
                </Column>
                <Column :header="t('hrm.recruitment.interviews.columns.mode')">
                  <template #body="{ data }">
                    <Tag :value="t(`hrm.recruitment.interviews.modes.${data.mode}`)" />
                  </template>
                </Column>
                <Column :header="t('hrm.recruitment.interviews.columns.status')">
                  <template #body="{ data }">
                    <Tag :value="t(`hrm.recruitment.interviews.statuses.${data.status}`)" :severity="statusSeverity(data.status)" />
                  </template>
                </Column>
                <Column header="" body-class="text-right" :style="{ width: '120px' }">
                  <template #body="{ data }">
                    <Button icon="pi pi-trash" text rounded severity="danger" @click="onDeleteIntv(data)" />
                  </template>
                </Column>
              </DataTable>
              <Paginator v-if="intvMeta && intvMeta.last_page > 1" :rows="intvMeta.per_page" :total-records="intvMeta.total" :first="(intvMeta.current_page - 1) * intvMeta.per_page" @page="(e) => intvPage = e.page + 1" />
            </template>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Vacancy dialog -->
    <Dialog
      v-model:visible="vacDialog"
      modal
      :header="editingVac ? t('hrm.employees.dialog.editTitle', { name: editingVac.title }) : t('hrm.recruitment.vacancies.new')"
      :style="{ width: '46rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSaveVac">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.title')" required />
            <InputText v-model="vTitle" class="w-full" :invalid="!!vErrors.title" :placeholder="t('hrm.recruitment.placeholders.vacancyTitle')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.reference')" required />
            <InputText v-model="vReference" class="w-full font-mono" :invalid="!!vErrors.reference" :placeholder="t('hrm.recruitment.placeholders.reference')" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.department')" />
            <Select v-model="vDept" :options="departments" option-label="name" option-value="id" show-clear class="w-full" :placeholder="t('hrm.recruitment.placeholders.department')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.position')" />
            <Select v-model="vPosition" :options="positions" option-label="title" option-value="id" show-clear class="w-full" :placeholder="t('hrm.recruitment.placeholders.position')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.recruitment.vacancies.fields.description')" />
          <Textarea v-model="vDescription" rows="3" class="w-full" :placeholder="t('hrm.recruitment.placeholders.description')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.recruitment.vacancies.fields.requirements')" />
          <Textarea v-model="vRequirements" rows="3" class="w-full" :placeholder="t('hrm.recruitment.placeholders.requirements')" />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.location')" />
            <InputText v-model="vLocation" class="w-full" :placeholder="t('hrm.recruitment.placeholders.location')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.salaryMin')" />
            <InputNumber v-model="vSalaryMin" mode="decimal" class="w-full" :placeholder="t('hrm.recruitment.placeholders.salaryMin')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.salaryMax')" />
            <InputNumber v-model="vSalaryMax" mode="decimal" class="w-full" :placeholder="t('hrm.recruitment.placeholders.salaryMax')" />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.employmentType')" required />
            <Select v-model="vEmpType" :options="employmentTypes" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.recruitment.placeholders.employmentType')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.opensAt')" />
            <DatePicker v-model="vOpens as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.vacancies.fields.closesAt')" />
            <DatePicker v-model="vCloses as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.recruitment.vacancies.fields.status')" />
          <Select v-model="vStatus" :options="vacancyStatuses" class="w-full" :placeholder="t('hrm.recruitment.placeholders.status')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="vacDialog = false" />
          <Button type="submit" :label="editingVac ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="vacSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Application dialog -->
    <Dialog v-model:visible="appDialog" modal :header="t('hrm.recruitment.applications.new')" :style="{ width: '36rem' }">
      <form class="space-y-4" @submit.prevent="onCreateApp">
        <div>
          <FormLabel :label="t('hrm.recruitment.applications.columns.vacancy')" required />
          <Select v-model="aVacancy" :options="vacancies" option-label="title" option-value="id" class="w-full" :invalid="!!aErrors.vacancy_id" :placeholder="t('hrm.recruitment.placeholders.vacancy')" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.firstName')" required />
            <InputText v-model="aFirstName" class="w-full" :placeholder="t('hrm.employees.placeholders.firstName')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.lastName')" required />
            <InputText v-model="aLastName" class="w-full" :placeholder="t('hrm.employees.placeholders.lastName')" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.employees.fields.email')" required />
            <InputText v-model="aEmail" type="email" class="w-full" :placeholder="t('hrm.employees.placeholders.email')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.employees.fields.phone')" />
            <InputText v-model="aPhone" class="w-full" :placeholder="t('hrm.employees.placeholders.phone')" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.placeholders.resumePathLabel')" />
            <InputText v-model="aResume" class="w-full font-mono text-xs" :placeholder="t('hrm.recruitment.placeholders.resumePath')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.placeholders.expectedSalaryLabel')" />
            <InputNumber v-model="aSalary" mode="decimal" class="w-full" :placeholder="t('hrm.recruitment.placeholders.expectedSalary')" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="appDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="appSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Transition dialog -->
    <Dialog
      v-model:visible="transDialog"
      modal
      :header="transTarget ? t('hrm.recruitment.applications.transitionPrompt', { candidate: `${transTarget.first_name} ${transTarget.last_name}` }) : ''"
      :style="{ width: '28rem' }"
    >
      <Select v-model="transStatus" :options="applicationStatuses" class="w-full" :placeholder="t('hrm.recruitment.placeholders.status')" />
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="transDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="onTransition" />
      </template>
    </Dialog>

    <!-- Interview dialog -->
    <Dialog v-model:visible="intvDialog" modal :header="t('hrm.recruitment.interviews.new')" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onCreateIntv">
        <div>
          <FormLabel :label="t('hrm.recruitment.applications.columns.candidate')" required />
          <Select v-model="iApp" :options="apps" option-value="id" class="w-full" :invalid="!!iErrors.application_id" :placeholder="t('hrm.recruitment.placeholders.candidate')">
            <template #option="{ option }">{{ option.first_name }} {{ option.last_name }} — {{ option.vacancy?.title }}</template>
          </Select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.interviews.columns.scheduled')" required />
            <DatePicker v-model="iSched as any" show-time hour-format="24" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.dateTime')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.placeholders.durationLabel')" required />
            <InputNumber v-model="iDuration" class="w-full" :placeholder="t('hrm.recruitment.placeholders.duration')" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.recruitment.interviews.columns.mode')" required />
            <Select v-model="iMode" :options="interviewModes" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.recruitment.placeholders.mode')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.recruitment.interviews.columns.round')" />
            <InputText v-model="iRound" class="w-full" :placeholder="t('hrm.recruitment.placeholders.round')" />
          </div>
        </div>
        <div>
          <FormLabel :label="t('hrm.recruitment.placeholders.locationLabel')" />
          <InputText v-model="iLocation" class="w-full" :placeholder="t('hrm.recruitment.placeholders.locationDetail')" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="intvDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="intvSaving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
