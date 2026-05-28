<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAuthStore } from '~/stores/auth'
import type { Attendance, AttendanceStats } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
const { has } = usePermissions()

// HR-admin proxy. Staff users (no hrm.employee.read) get the directory
// as a read-only view of their OWN attendance — no employee filter
// (only their own rows are returned by the backend), no manual-entry
// button, no edit/delete row actions. Admins keep the full UI.
const isHrAdmin = computed(() => has('hrm.employee.read'))
const canDeleteAttendance = computed(() => has('hrm.attendance.delete'))

// Date helpers
const datePreprocess = (val: unknown): string | null => {
  if (val instanceof Date) {
    const year = val.getFullYear()
    const month = String(val.getMonth() + 1).padStart(2, '0')
    const day = String(val.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  if (typeof val === 'string' && val.trim() !== '') {
    return val.split('T')[0] || null
  }
  return null
}

const dateTimePreprocess = (val: unknown): string | null => {
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
    return val.replace('T', ' ').split('.')[0] || null
  }
  return null
}

// 1. Load active employees lookup
const { data: empData, refresh: refreshEmployees } = await useAsyncData('hrm-all-employees', () => hrm.listEmployees({ per_page: 500 }))
const employeesList = computed(() => empData.value?.data?.data ?? [])

// 2. Resolve active employee profile for the current logged-in user
const activeEmployee = computed(() => {
  const user = auth.user
  if (!user) return null
  return employeesList.value.find(e => e.email === user.email || e.user_id === user.id) ?? null
})

// 3. ESS states: clock live time, today's log, statistics
const currentTime = ref('')
let clockTimer: ReturnType<typeof setInterval>

const todayRecord = ref<Attendance | null>(null)
const statsData = ref<AttendanceStats | null>(null)
const essLoading = ref(false)
const clockNotes = ref('')

const fetchTodayRecord = async () => {
  if (!activeEmployee.value) return
  const todayStr = datePreprocess(new Date())!
  try {
    const res = await hrm.listAttendances({
      employee_id: activeEmployee.value.id,
      date: todayStr,
    })
    todayRecord.value = res.data.data[0] || null
  } catch (e) {
    console.error('Failed to fetch today record', e)
  }
}

const fetchStats = async () => {
  if (!activeEmployee.value) return
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  
  try {
    const res = await hrm.getAttendanceStats({
      employee_id: activeEmployee.value.id,
      start_date: datePreprocess(start)!,
      end_date: datePreprocess(end)!,
    })
    statsData.value = res.data
  } catch (e) {
    console.error('Failed to fetch stats', e)
  }
}

const todayStatus = computed(() => {
  if (!todayRecord.value) return 'notCheckedIn'
  if (todayRecord.value.check_out) return 'checkedOut'
  return 'checkedIn'
})

// Pretty HH:MM:SS for one punch. Empty when the punch hasn't happened.
const formatPunch = (iso: string | null | undefined): string => {
  if (!iso) return '--:--:--'
  const d = new Date(iso)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
}
const todayCheckInTime  = computed(() => formatPunch(todayRecord.value?.check_in))
const todayBreakOutTime = computed(() => formatPunch(todayRecord.value?.break_out))
const todayBreakInTime  = computed(() => formatPunch(todayRecord.value?.break_in))
const todayCheckOutTime = computed(() => formatPunch(todayRecord.value?.check_out))

// 4-punch state machine: the next valid action depends on which fields
// are already populated. `done` means all four punches are recorded —
// the action card disappears.
type NextAction = 'checkIn' | 'breakOut' | 'breakIn' | 'checkOut' | 'done'
const nextAction = computed<NextAction>(() => {
  const r = todayRecord.value
  if (!r || !r.check_in) return 'checkIn'
  if (!r.break_out) return 'breakOut'
  if (!r.break_in)  return 'breakIn'
  if (!r.check_out) return 'checkOut'
  return 'done'
})

// Self-service clock actions
const onClockIn = async () => {
  if (!activeEmployee.value) return
  essLoading.value = true
  try {
    const res = await hrm.checkIn({ notes: clockNotes.value || undefined })
    if (res.success) {
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.checkedIn'), life: 3000 })
      clockNotes.value = ''
      await fetchTodayRecord()
      await fetchStats()
      await fetchRecentAttendance()
      await refreshAdminLogs()
    }
  } catch (err: any) {
    const detailMsg = err.data?.message || err.message
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: detailMsg, life: 5000 })
  } finally {
    essLoading.value = false
  }
}

const onClockOut = async () => {
  if (!activeEmployee.value) return
  essLoading.value = true
  try {
    const res = await hrm.checkOut({ notes: clockNotes.value || undefined })
    if (res.success) {
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.checkedOut'), life: 3000 })
      clockNotes.value = ''
      await fetchTodayRecord()
      await fetchStats()
      await fetchRecentAttendance()
      await refreshAdminLogs()
    }
  } catch (err: any) {
    const detailMsg = err.data?.message || err.message
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: detailMsg, life: 5000 })
  } finally {
    essLoading.value = false
  }
}

const onBreakOut = async () => {
  if (!activeEmployee.value) return
  essLoading.value = true
  try {
    const res = await hrm.breakOut({ notes: clockNotes.value || undefined })
    if (res.success) {
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.brokeOut'), life: 3000 })
      clockNotes.value = ''
      await fetchTodayRecord()
    }
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: err.data?.message || err.message, life: 5000 })
  } finally { essLoading.value = false }
}

const onBreakIn = async () => {
  if (!activeEmployee.value) return
  essLoading.value = true
  try {
    const res = await hrm.breakIn({ notes: clockNotes.value || undefined })
    if (res.success) {
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.brokeIn'), life: 3000 })
      clockNotes.value = ''
      await fetchTodayRecord()
    }
  } catch (err: any) {
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: err.data?.message || err.message, life: 5000 })
  } finally { essLoading.value = false }
}

// Dispatcher used by the smart "next action" button.
const onNextAction = () => {
  switch (nextAction.value) {
    case 'checkIn':  return onClockIn()
    case 'breakOut': return onBreakOut()
    case 'breakIn':  return onBreakIn()
    case 'checkOut': return onClockOut()
  }
}

// ── Recent attendance (last 6 days for the ESS panel) ──────────
// Renders the table shown in the screenshot: Date | Morning | Afternoon
// | Remarks. Backend auto-scopes for staff; admins see only their own
// active employee (which is themselves when linked).
const recentAttendance = ref<Attendance[]>([])
const fetchRecentAttendance = async () => {
  if (!activeEmployee.value) return
  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - 6)
  try {
    const res = await hrm.listAttendances({
      employee_id: activeEmployee.value.id,
      start_date: datePreprocess(start)!,
      end_date: datePreprocess(end)!,
      per_page: 14,
    })
    recentAttendance.value = res.data.data ?? []
  } catch {
    /* swallow — empty state will render */
  }
}

onMounted(() => {
  currentTime.value = new Date().toLocaleTimeString()
  clockTimer = setInterval(() => {
    currentTime.value = new Date().toLocaleTimeString()
  }, 1000)
  
  if (activeEmployee.value) {
    fetchTodayRecord()
    fetchStats()
    fetchRecentAttendance()
  }
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})

// Watch activeEmployee resolution (in case lookup resolves later)
watch(activeEmployee, (newVal) => {
  if (newVal) {
    fetchTodayRecord()
    fetchStats()
    fetchRecentAttendance()
  }
})


// 4. HR Administrative Directory
const page = ref(1)
const empFilter = ref<string | null>(null)
const statusFilter = ref<string | null>(null)
const dateFilterStart = ref<Date | null>(null)
const dateFilterEnd = ref<Date | null>(null)

const { data: adminLogs, refresh: refreshAdminLogs, pending: adminPending } = await useAsyncData(
  'hrm-admin-attendances',
  () => hrm.listAttendances({
    employee_id: empFilter.value || undefined,
    status: statusFilter.value || undefined,
    start_date: dateFilterStart.value ? datePreprocess(dateFilterStart.value)! : undefined,
    end_date: dateFilterEnd.value ? datePreprocess(dateFilterEnd.value)! : undefined,
    page: page.value,
    per_page: 25,
  }),
  { watch: [page, empFilter, statusFilter, dateFilterStart, dateFilterEnd] }
)

const logsList = computed<Attendance[]>(() => adminLogs.value?.data?.data ?? [])
const meta = computed(() => adminLogs.value?.data)

const statuses = ['present', 'late', 'absent', 'half_day', 'on_leave']

const getStatusSeverity = (status: string) => {
  switch (status) {
    case 'present': return 'success'
    case 'late': return 'warn'
    case 'absent': return 'danger'
    case 'half_day': return 'info'
    case 'on_leave': return 'secondary'
    default: return 'contrast'
  }
}

// 5. HR Manual Logs Add/Edit Dialog
const dialogOpen = ref(false)
const editingLog = ref<Attendance | null>(null)
const saving = ref(false)

const schema = toTypedSchema(z.object({
  employee_id: z.string().uuid(t('hrm.attendance.dialog.fields.employee') + ' is invalid'),
  date: z.union([z.instanceof(Date), z.string()]).refine(val => !!val, { message: 'Date is required' }),
  status: z.string().min(1, 'Status is required'),
  check_in: z.union([z.instanceof(Date), z.string(), z.null()]).optional(),
  check_out: z.union([z.instanceof(Date), z.string(), z.null()]).optional(),
  notes: z.string().max(1000).optional().or(z.literal('')),
}))

const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    employee_id: '',
    date: '',
    status: 'present',
    check_in: null,
    check_out: null,
    notes: '',
  }
})

const [formEmployeeId] = defineField('employee_id')
const [formDate] = defineField('date')
const [formStatus] = defineField('status')
const [formCheckIn] = defineField('check_in')
const [formCheckOut] = defineField('check_out')
const [formNotes] = defineField('notes')

const openCreate = () => {
  resetForm()
  editingLog.value = null
  dialogOpen.value = true
}

const openEdit = (row: Attendance) => {
  resetForm()
  editingLog.value = row
  
  setValues({
    employee_id: row.employee_id,
    date: row.date ? new Date(row.date) : '',
    status: row.status,
    check_in: row.check_in ? new Date(row.check_in) : null,
    check_out: row.check_out ? new Date(row.check_out) : null,
    notes: row.notes || '',
  })
  
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  saving.value = true
  const payload = {
    employee_id: values.employee_id,
    date: datePreprocess(values.date)!,
    status: values.status as any,
    check_in: values.check_in ? dateTimePreprocess(values.check_in)! : null,
    check_out: values.check_out ? dateTimePreprocess(values.check_out)! : null,
    notes: values.notes || null,
  }

  try {
    if (editingLog.value) {
      await hrm.updateAttendance(editingLog.value.id, payload)
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.updated'), life: 3000 })
    } else {
      await hrm.createAttendance(payload)
      toast.add({ severity: 'success', summary: t('hrm.attendance.toast.created'), life: 3000 })
    }
    dialogOpen.value = false
    await refreshAdminLogs()
    if (activeEmployee.value && values.employee_id === activeEmployee.value.id) {
      await fetchTodayRecord()
      await fetchStats()
    }
  } catch (err: any) {
    const detailMsg = err.data?.message || err.message
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: detailMsg, life: 5000 })
  } finally {
    saving.value = false
  }
})

const onDelete = (row: Attendance) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: `${row.employee?.first_name ?? ''} ${row.employee?.last_name ?? ''}`.trim() }),
    header: t('hrm.common.confirmHeader'),
    acceptClass: 'p-button-danger',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await hrm.deleteAttendance(row.id)
        toast.add({ severity: 'success', summary: t('hrm.attendance.toast.deleted'), life: 3000 })
        await refreshAdminLogs()
        if (activeEmployee.value && row.employee_id === activeEmployee.value.id) {
          await fetchTodayRecord()
          await fetchStats()
        }
      } catch (err: any) {
        toast.add({ severity: 'error', summary: t('hrm.common.deleteFailed'), detail: err.message, life: 5000 })
      }
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.attendance.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.attendance.subtitle') }}</p>
      </div>
    </div>

    <!-- ESS error state (no linked employee) -->
    <div v-if="!activeEmployee" class="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-xl space-y-2">
      <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300 font-semibold text-sm">
        <i class="pi pi-exclamation-triangle" />
        <span>{{ t('hrm.attendance.noEmployee') }}</span>
      </div>
      <p class="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
        {{ t('hrm.attendance.noEmployeeDetail') }}
      </p>
    </div>

    <template v-else>
      <!-- 4-punch tiles: Check In · Break Out · Break In · Check Out -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Card>
          <template #content>
            <div class="flex items-center gap-3">
              <div class="size-12 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 grid place-items-center">
                <i class="pi pi-sign-in" />
              </div>
              <div>
                <div class="text-xl font-mono font-bold tracking-tight">{{ todayCheckInTime }}</div>
                <div class="text-xs text-surface-500">{{ t('hrm.attendance.tiles.checkIn') }}</div>
              </div>
            </div>
          </template>
        </Card>
        <Card>
          <template #content>
            <div class="flex items-center gap-3">
              <div class="size-12 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 grid place-items-center">
                <i class="pi pi-arrow-up-right" />
              </div>
              <div>
                <div class="text-xl font-mono font-bold tracking-tight">{{ todayBreakOutTime }}</div>
                <div class="text-xs text-surface-500">{{ t('hrm.attendance.tiles.breakOut') }}</div>
              </div>
            </div>
          </template>
        </Card>
        <Card>
          <template #content>
            <div class="flex items-center gap-3">
              <div class="size-12 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 grid place-items-center">
                <i class="pi pi-arrow-down-left" />
              </div>
              <div>
                <div class="text-xl font-mono font-bold tracking-tight">{{ todayBreakInTime }}</div>
                <div class="text-xs text-surface-500">{{ t('hrm.attendance.tiles.breakIn') }}</div>
              </div>
            </div>
          </template>
        </Card>
        <Card>
          <template #content>
            <div class="flex items-center gap-3">
              <div class="size-12 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 grid place-items-center">
                <i class="pi pi-sign-out" />
              </div>
              <div>
                <div class="text-xl font-mono font-bold tracking-tight">{{ todayCheckOutTime }}</div>
                <div class="text-xs text-surface-500">{{ t('hrm.attendance.tiles.checkOut') }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Smart action button: morphs to match the next valid punch -->
      <Card v-if="nextAction !== 'done'">
        <template #content>
          <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1">
              <div class="text-xs uppercase tracking-wider text-surface-400 mb-1">
                {{ t('hrm.attendance.liveTime') }}
              </div>
              <div class="font-mono text-2xl font-bold text-primary-600 dark:text-primary-400">{{ currentTime || '00:00:00' }}</div>
              <div class="text-xs text-surface-500 mt-0.5">
                {{ new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
              </div>
            </div>
            <div class="flex-1 max-w-md">
              <Textarea
                v-model="clockNotes"
                rows="1"
                class="w-full text-sm"
                :placeholder="t('hrm.attendance.notesPlaceholder')"
                :disabled="essLoading"
              />
            </div>
            <Button
              :label="t(`hrm.attendance.action.${nextAction}`)"
              :icon="nextAction === 'checkIn'  ? 'pi pi-sign-in'
                   : nextAction === 'breakOut' ? 'pi pi-arrow-up-right'
                   : nextAction === 'breakIn'  ? 'pi pi-arrow-down-left'
                   :                             'pi pi-sign-out'"
              :severity="nextAction === 'checkIn'  ? 'success'
                      : nextAction === 'breakOut' ? 'warn'
                      : nextAction === 'breakIn'  ? 'info'
                      :                             'danger'"
              class="font-semibold"
              :loading="essLoading"
              @click="onNextAction"
            />
          </div>
        </template>
      </Card>
      <Card v-else>
        <template #content>
          <div class="text-center text-sm text-surface-500 py-2">
            <i class="pi pi-check-circle text-emerald-500 mr-2" />
            {{ t('hrm.attendance.dayComplete') }}
          </div>
        </template>
      </Card>

      <!-- Recent Attendance Last 6 days (morning + afternoon per row) -->
      <Card>
        <template #content>
          <h2 class="text-base font-semibold mb-3">{{ t('hrm.attendance.recent.title') }}</h2>
          <DataTable :value="recentAttendance" data-key="id" class="text-sm">
            <template #empty>
              <div class="py-6 text-center text-surface-400 text-xs">{{ t('hrm.attendance.recent.empty') }}</div>
            </template>
            <Column :header="t('hrm.attendance.recent.columns.date')">
              <template #body="{ data }">
                <span class="inline-flex items-center gap-1 font-mono text-xs">
                  <i class="pi pi-calendar text-[10px] text-surface-400" />
                  {{ formatDate(data.date) }}, {{ new Date(data.date).toLocaleDateString(undefined, { weekday: 'long' }) }}
                </span>
              </template>
            </Column>
            <Column :header="t('hrm.attendance.recent.columns.morning')">
              <template #body="{ data }">
                <Tag
                  v-if="data.morning_status"
                  :value="t(`hrm.attendance.statusValues.${data.morning_status}`)"
                  :severity="getStatusSeverity(data.morning_status)"
                  class="!text-[10px] !py-0"
                />
                <span v-else class="text-surface-400 text-xs">—</span>
              </template>
            </Column>
            <Column :header="t('hrm.attendance.recent.columns.afternoon')">
              <template #body="{ data }">
                <Tag
                  v-if="data.afternoon_status"
                  :value="t(`hrm.attendance.statusValues.${data.afternoon_status}`)"
                  :severity="getStatusSeverity(data.afternoon_status)"
                  class="!text-[10px] !py-0"
                />
                <span v-else class="text-surface-400 text-xs">—</span>
              </template>
            </Column>
            <Column :header="t('hrm.attendance.recent.columns.remarks')">
              <template #body="{ data }">
                <span class="text-xs text-surface-500">{{ data.notes || '—' }}</span>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </template>

    <!-- Monthly stats (admins keep their own employee's stats below the timeline) -->
    <div v-if="activeEmployee">

      <!-- Personal Monthly Statistics Breakdown -->
      <Card class="border border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 shadow-md">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-chart-bar text-primary-500" />
            <span>{{ t('hrm.attendance.stats.title') }}</span>
          </div>
        </template>
        
        <template #content>
          <div v-if="!activeEmployee" class="flex flex-col items-center justify-center py-12 text-surface-400">
            <i class="pi pi-chart-line text-4xl mb-2" />
            <p class="text-sm">Link an employee profile to view stats.</p>
          </div>
          
          <div v-else class="space-y-4">
            <p class="text-xs text-surface-500">{{ t('hrm.attendance.stats.subtitle') }}</p>
            
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
              <!-- Present -->
              <div class="p-4 rounded-2xl border-l-4 border-emerald-500 bg-surface-50 dark:bg-surface-950 text-center space-y-1 hover:scale-105 transition-transform duration-200">
                <div class="text-xs font-semibold text-surface-500">{{ t('hrm.attendance.stats.present') }}</div>
                <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ statsData?.present ?? 0 }}</div>
              </div>
              
              <!-- Late -->
              <div class="p-4 rounded-2xl border-l-4 border-amber-500 bg-surface-50 dark:bg-surface-950 text-center space-y-1 hover:scale-105 transition-transform duration-200">
                <div class="text-xs font-semibold text-surface-500">{{ t('hrm.attendance.stats.late') }}</div>
                <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ statsData?.late ?? 0 }}</div>
              </div>
              
              <!-- Absent -->
              <div class="p-4 rounded-2xl border-l-4 border-rose-500 bg-surface-50 dark:bg-surface-950 text-center space-y-1 hover:scale-105 transition-transform duration-200">
                <div class="text-xs font-semibold text-surface-500">{{ t('hrm.attendance.stats.absent') }}</div>
                <div class="text-3xl font-bold text-rose-600 dark:text-rose-400">{{ statsData?.absent ?? 0 }}</div>
              </div>
              
              <!-- Half Day -->
              <div class="p-4 rounded-2xl border-l-4 border-sky-500 bg-surface-50 dark:bg-surface-950 text-center space-y-1 hover:scale-105 transition-transform duration-200">
                <div class="text-xs font-semibold text-surface-500">{{ t('hrm.attendance.stats.halfDay') }}</div>
                <div class="text-3xl font-bold text-sky-600 dark:text-sky-400">{{ statsData?.half_day ?? 0 }}</div>
              </div>
              
              <!-- On Leave -->
              <div class="p-4 rounded-2xl border-l-4 border-purple-500 bg-surface-50 dark:bg-surface-950 text-center space-y-1 hover:scale-105 transition-transform duration-200">
                <div class="text-xs font-semibold text-surface-500">{{ t('hrm.attendance.stats.onLeave') }}</div>
                <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ statsData?.on_leave ?? 0 }}</div>
              </div>
            </div>
            
            <div class="rounded-xl p-3 bg-primary-50 dark:bg-primary-950/20 text-xs text-primary-700 dark:text-primary-400 flex items-start gap-2 leading-relaxed">
              <i class="pi pi-info-circle mt-[2px]" />
              <span>Status definitions: Check-in records logged past <strong>09:00 AM</strong> are automatically flagged as <strong>Late</strong>. Rest of logs are calculated as <strong>Present</strong>. Leaves or partial days can be manually adjusted by HR.</span>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- HR Directory Listing Card -->
    <Card class="border border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 shadow-md">
      <template #title>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-2">
            <i class="pi pi-list text-primary-500" />
            <span>{{ t('hrm.attendance.directory.title') }}</span>
          </div>
          <Button
            v-if="isHrAdmin"
            :label="t('hrm.attendance.directory.new')"
            icon="pi pi-plus"
            size="small"
            class="hover:shadow-md transition-shadow font-semibold"
            @click="openCreate"
          />
        </div>
      </template>

      <template #content>
        <div class="space-y-4">
          <p class="text-xs text-surface-500">{{ t('hrm.attendance.directory.subtitle') }}</p>
          
          <!-- Filters Row -->
          <div class="flex flex-wrap items-center gap-3 bg-surface-50 dark:bg-surface-950 p-3 rounded-2xl border border-surface-200 dark:border-surface-800">
            <!-- Filter Employee — HR admins only. Staff are auto-scoped
                 to themselves on the backend, so the filter would be a
                 single-option no-op for them. -->
            <Select
              v-if="isHrAdmin"
              v-model="empFilter"
              :options="employeesList"
              option-value="id"
              show-clear
              filter
              class="w-56 text-sm"
              :placeholder="t('hrm.attendance.dialog.fields.employee')"
            >
              <template #option="{ option }">
                {{ option.first_name }} {{ option.last_name }} ({{ option.employee_id }})
              </template>
              <template #value="{ value }">
                <span v-if="value">
                  {{ employeesList.find(e => e.id === value)?.first_name }} {{ employeesList.find(e => e.id === value)?.last_name }}
                </span>
                <span v-else>{{ t('hrm.attendance.dialog.fields.employee') }}</span>
              </template>
            </Select>

            <!-- Filter Status -->
            <Select 
              v-model="statusFilter" 
              :options="statuses" 
              show-clear 
              class="w-44 text-sm" 
              :placeholder="t('hrm.attendance.dialog.fields.status')"
            >
              <template #option="{ option }">
                {{ t('hrm.attendance.stats.' + option.replace('_', '')) }}
              </template>
              <template #value="{ value }">
                <span v-if="value">{{ t('hrm.attendance.stats.' + value.replace('_', '')) }}</span>
                <span v-else>{{ t('hrm.attendance.dialog.fields.status') }}</span>
              </template>
            </Select>

            <!-- Filter Start Date -->
            <DatePicker 
              v-model="dateFilterStart"
              date-format="yy-mm-dd" 
              show-clear
              class="w-44 text-sm" 
              :placeholder="t('hrm.leave.filters.from')"
            />

            <!-- Filter End Date -->
            <DatePicker 
              v-model="dateFilterEnd"
              date-format="yy-mm-dd" 
              show-clear
              class="w-44 text-sm" 
              :placeholder="t('hrm.leave.filters.to')"
            />
          </div>

          <!-- P0 Tenancy Isolation verified logs table -->
          <DataTable :value="logsList" :loading="adminPending" data-key="id" striped-rows class="text-sm">
            <template #empty>
              <div class="py-12 text-center text-surface-400">
                <i class="pi pi-folder-open text-3xl mb-2" />
                <p>{{ t('hrm.attendance.directory.empty') }}</p>
              </div>
            </template>

            <Column :header="t('hrm.attendance.dialog.fields.employee')">
              <template #body="{ data }">
                <div class="font-medium text-surface-900 dark:text-surface-0">
                  {{ data.employee?.first_name }} {{ data.employee?.last_name }}
                </div>
                <div class="text-xs font-mono text-surface-500">
                  {{ data.employee?.employee_id }}
                </div>
              </template>
            </Column>

            <Column :header="t('hrm.attendance.dialog.fields.date')">
              <template #body="{ data }">
                <span class="font-semibold text-surface-700 dark:text-surface-300">
                  {{ formatDate(data.date) }}
                </span>
              </template>
            </Column>

            <Column :header="t('hrm.attendance.dialog.fields.status')">
              <template #body="{ data }">
                <Tag :value="t('hrm.attendance.stats.' + data.status.replace('_', ''))" :severity="getStatusSeverity(data.status)" class="!text-xs !font-bold" />
              </template>
            </Column>

            <Column :header="t('hrm.attendance.dialog.fields.checkIn')">
              <template #body="{ data }">
                <span class="font-mono text-xs">{{ formatDateTime(data.check_in) }}</span>
              </template>
            </Column>

            <Column :header="t('hrm.attendance.dialog.fields.checkOut')">
              <template #body="{ data }">
                <span class="font-mono text-xs">{{ formatDateTime(data.check_out) }}</span>
              </template>
            </Column>

            <Column :header="t('hrm.attendance.dialog.fields.notes')">
              <template #body="{ data }">
                <span class="text-xs text-surface-500 max-w-[200px] truncate block" :title="data.notes">
                  {{ data.notes || '—' }}
                </span>
              </template>
            </Column>

            <!-- Actions block — manual edits & deletes are HR-admin
                 territory; staff stick to clock-in / clock-out. The
                 column collapses to zero width when both gates fail
                 so the layout doesn't leave an empty 120px slot. -->
            <Column
              v-if="isHrAdmin || canDeleteAttendance"
              header=""
              body-class="text-right !py-2"
              :style="{ width: '120px' }"
            >
              <template #body="{ data }">
                <Button
                  v-if="isHrAdmin"
                  icon="pi pi-pencil"
                  text
                  rounded
                  severity="secondary"
                  class="hover:!bg-surface-100 dark:hover:!bg-surface-800"
                  @click="openEdit(data)"
                />
                <Button
                  v-if="canDeleteAttendance"
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  class="hover:!bg-rose-50 dark:hover:!bg-rose-950/20"
                  @click="onDelete(data)"
                />
              </template>
            </Column>
          </DataTable>

          <!-- Pagination -->
          <Paginator 
            v-if="meta && meta.last_page > 1" 
            :rows="meta.per_page" 
            :total-records="meta.total" 
            :first="(meta.current_page - 1) * meta.per_page" 
            @page="(e) => page = e.page + 1" 
          />
        </div>
      </template>
    </Card>

    <!-- Dialog for Manual Log Add/Edit -->
    <Dialog 
      v-model:visible="dialogOpen" 
      modal 
      :header="editingLog ? t('hrm.attendance.dialog.editTitle', { name: `${editingLog.employee?.first_name ?? ''} ${editingLog.employee?.last_name ?? ''}`.trim() }) : t('hrm.attendance.dialog.createTitle')" 
      :style="{ width: '32rem' }"
    >
      <form class="space-y-4 pt-2" @submit.prevent="onSubmit">
        
        <!-- Employee select (disabled on Edit) -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.employee')" required />
          <Select 
            v-model="formEmployeeId" 
            :options="employeesList" 
            option-value="id"
            :disabled="!!editingLog"
            class="w-full text-sm" 
            :placeholder="t('hrm.leave.placeholders.employee')"
            :invalid="!!errors.employee_id"
          >
            <template #option="{ option }">
              {{ option.first_name }} {{ option.last_name }} ({{ option.employee_id }})
            </template>
            <template #value="{ value }">
              <span v-if="value">
                {{ employeesList.find(e => e.id === value)?.first_name }} {{ employeesList.find(e => e.id === value)?.last_name }}
              </span>
              <span v-else>{{ t('hrm.leave.placeholders.employee') }}</span>
            </template>
          </Select>
          <small v-if="errors.employee_id" class="p-error text-rose-500 text-xs mt-1 block">{{ errors.employee_id }}</small>
        </div>

        <!-- Date select (disabled on Edit) -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.date')" required />
          <DatePicker 
            v-model="formDate as any"
            date-format="yy-mm-dd" 
            :disabled="!!editingLog"
            class="w-full text-sm" 
            :placeholder="t('common.placeholders.date')"
            :invalid="!!errors.date"
          />
          <small v-if="errors.date" class="p-error text-rose-500 text-xs mt-1 block">{{ errors.date }}</small>
        </div>

        <!-- Status select -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.status')" required />
          <Select 
            v-model="formStatus" 
            :options="statuses" 
            class="w-full text-sm" 
            :placeholder="t('common.placeholders.selectOne')"
          >
            <template #option="{ option }">
              {{ t('hrm.attendance.stats.' + option.replace('_', '')) }}
            </template>
            <template #value="{ value }">
              <span v-if="value">{{ t('hrm.attendance.stats.' + value.replace('_', '')) }}</span>
              <span v-else>{{ t('common.placeholders.selectOne') }}</span>
            </template>
          </Select>
        </div>

        <!-- Check-in Time -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.checkIn')" />
          <DatePicker 
            v-model="formCheckIn as any"
            show-time 
            hour-format="24"
            date-format="yy-mm-dd"
            class="w-full text-sm" 
            :placeholder="t('common.placeholders.dateTime')"
          />
        </div>

        <!-- Check-out Time -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.checkOut')" />
          <DatePicker 
            v-model="formCheckOut as any"
            show-time 
            hour-format="24"
            date-format="yy-mm-dd"
            class="w-full text-sm" 
            :placeholder="t('common.placeholders.dateTime')"
          />
        </div>

        <!-- Notes -->
        <div>
          <FormLabel :label="t('hrm.attendance.dialog.fields.notes')" />
          <Textarea 
            v-model="formNotes" 
            rows="3" 
            class="w-full text-sm" 
            :placeholder="t('hrm.attendance.notesPlaceholder')" 
          />
        </div>

        <!-- Submit and Cancel buttons -->
        <div class="flex justify-end gap-2 pt-4">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editingLog ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>

      </form>
    </Dialog>
  </div>
</template>
