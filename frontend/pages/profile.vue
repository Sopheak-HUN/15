<script setup lang="ts">
import type { Attendance, AttendanceStats, Employee, EmployeePromotion, LeaveBalance, LeaveRequest } from '~/types/hrm'

// Personal profile dashboard. Every authenticated user lands here from
// the avatar menu; everything is sourced from endpoints they can already
// reach (no new perms needed). When the user has no linked employee
// profile we render a graceful empty state instead of a stack of
// blank cards.
definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const auth = useAuthStore()
const { t, locale } = useI18n()
const router = useRouter()

// ── Current employee + department info ───────────────────────────
const { data: meData, pending: mePending } = await useAsyncData('profile-me', () => hrm.me())
const employee = computed<Employee | null>(() => meData.value?.data ?? null)
const department = computed(() => employee.value?.department ?? null)
// Career journal — already eager-loaded by /api/hrm/me. Read-only here.
const promotions = computed<EmployeePromotion[]>(() => employee.value?.promotions ?? [])

const promoTypeSeverity = (type: string) =>
  type === 'promotion' ? 'success'
    : type === 'demotion' ? 'danger'
    : type === 'lateral' ? 'info'
    : 'warn'

const formatMoney = (v?: string | number | null) => {
  if (v === null || v === undefined || v === '') return null
  const n = Number(v)
  if (Number.isNaN(n)) return String(v)
  return n.toLocaleString()
}

// ── This-month attendance + recent attendance ────────────────────
// "This month" stats power the headline "Present days" tile. The list
// drives the small recent-activity card on the right column.
const today = new Date()
const monthStart = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`
const monthEnd = (() => {
  // End-of-month: day 0 of NEXT month → last day of THIS month.
  const d = new Date(today.getFullYear(), today.getMonth() + 1, 0)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
})()

const { data: statsData } = await useAsyncData(
  'profile-attendance-stats',
  async () => {
    if (!employee.value?.id) return null
    try {
      return await hrm.getAttendanceStats({
        employee_id: employee.value.id,
        start_date: monthStart,
        end_date: monthEnd,
      })
    } catch {
      // Staff users with hrm.attendance.read can hit /stats; if perms
      // aren't there or the call fails, we just hide the tile.
      return null
    }
  },
  { watch: [employee] },
)
const stats = computed<AttendanceStats | null>(() => statsData.value?.data ?? null)

const { data: attData } = await useAsyncData(
  'profile-attendance-recent',
  async () => {
    if (!employee.value?.id) return null
    try {
      return await hrm.listAttendances({
        employee_id: employee.value.id,
        start_date: monthStart,
        end_date: monthEnd,
        per_page: 10,
      })
    } catch {
      return null
    }
  },
  { watch: [employee] },
)
const recentAttendance = computed<Attendance[]>(() => attData.value?.data?.data ?? [])

// Pick today's attendance row so we can show check-in/out status in
// the header tile. Backend can return `date` as either "YYYY-MM-DD"
// (raw DATE) or "YYYY-MM-DDTHH:MM:SS.SSSSSSZ" (when cast); compare on
// the first 10 chars to handle both.
const todaysRecord = computed<Attendance | null>(() => {
  const today = new Date().toISOString().slice(0, 10)
  return recentAttendance.value.find((a) => String(a.date).slice(0, 10) === today) ?? null
})

// ── Leave balances + recent leave requests ───────────────────────
// Balances and requests both 200 for staff because they have
// hrm.leave.read. The list endpoint also auto-scopes to the caller's
// own employee on the backend.
const { data: balancesData } = await useAsyncData(
  'profile-balances',
  async () => {
    if (!employee.value?.id) return null
    try {
      return await hrm.employeeLeaveBalances(employee.value.id)
    } catch {
      return null
    }
  },
  { watch: [employee] },
)
const balances = computed<LeaveBalance[]>(() => balancesData.value?.data ?? [])

const { data: requestsData } = await useAsyncData(
  'profile-requests-recent',
  async () => {
    if (!employee.value?.id) return null
    try {
      return await hrm.listLeaveRequests({ employee_id: employee.value.id, per_page: 5 })
    } catch {
      return null
    }
  },
  { watch: [employee] },
)
const recentRequests = computed<LeaveRequest[]>(() => requestsData.value?.data?.data ?? [])

// ── Aggregations for the headline tiles ──────────────────────────
const totalUsed = computed(() =>
  balances.value.reduce((sum, b) => sum + Number(b.used ?? 0), 0))
const totalAvailable = computed(() =>
  balances.value.reduce((sum, b) =>
    sum + Math.max(0, Number(b.balance ?? 0) - Number(b.used ?? 0) - Number(b.pending ?? 0)),
  0))

// Severity helpers for tags
const statusSeverity = (s: string) =>
  s === 'approved' || s === 'present' ? 'success'
    : s === 'rejected' || s === 'absent' ? 'danger'
    : s === 'pending' || s === 'late' ? 'warn'
    : s === 'on_leave' ? 'info'
    : 'secondary'

const fmt = (v?: string | number | null) =>
  v === null || v === undefined || v === '' ? '—' : String(v)

// Localized full name in display order. Khmer locale gets the Khmer
// names first (since social norms put the surname first in km, but our
// data already stores given/family, we keep the same order).
const fullName = computed(() => {
  const e = employee.value
  if (!e) return ''
  if (locale.value === 'km' && (e.first_name_kh || e.last_name_kh)) {
    return `${e.first_name_kh ?? ''} ${e.last_name_kh ?? ''}`.trim()
  }
  return `${e.first_name} ${e.last_name}`.trim()
})

const availableFor = (b: LeaveBalance) =>
  Math.max(0, Number(b.balance ?? 0) - Number(b.used ?? 0) - Number(b.pending ?? 0))

const percentUsed = (b: LeaveBalance) => {
  const balance = Number(b.balance ?? 0)
  if (balance <= 0) return 0
  const used = Number(b.used ?? 0)
  return Math.min(100, Math.round((used / balance) * 100))
}
</script>

<template>
  <div class="space-y-6">
    <!-- Loading state -->
    <div v-if="mePending" class="py-20 text-center">
      <ProgressSpinner />
    </div>

    <!-- No linked employee -->
    <div v-else-if="!employee" class="py-20 text-center">
      <div class="size-16 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-300 grid place-items-center mx-auto mb-4">
        <i class="pi pi-user text-2xl" />
      </div>
      <h2 class="text-lg font-semibold">{{ t('profile.unlinked.title') }}</h2>
      <p class="text-surface-500 mt-1 max-w-md mx-auto">{{ t('profile.unlinked.subtitle') }}</p>
    </div>

    <template v-else>
      <!-- ─────────── Header ─────────── -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <img
            v-if="employee.photo_url"
            :src="employee.photo_url"
            :alt="fullName"
            class="w-20 h-20 rounded-full object-cover ring-2 ring-surface-200 dark:ring-surface-700 flex-shrink-0"
          >
          <div
            v-else
            class="w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300 grid place-items-center text-2xl font-semibold uppercase ring-2 ring-primary-200 dark:ring-primary-900 flex-shrink-0"
          >
            {{ (employee.first_name?.[0] ?? '') + (employee.last_name?.[0] ?? '') }}
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-semibold tracking-tight truncate">{{ fullName }}</h1>
              <Tag v-if="employee.employee_id" :value="employee.employee_id" />
              <Tag
                v-if="employee.status"
                :value="employee.status"
                :severity="employee.status === 'active' ? 'success' : 'danger'"
              />
              <Tag
                v-if="auth.roleName"
                :value="auth.roleName"
                severity="info"
                icon="pi pi-shield"
              />
            </div>
            <p class="text-surface-500 mt-1 truncate">
              <span>{{ employee.email }}</span>
              <span v-if="department"> · {{ department.name }}</span>
              <span v-if="employee.position"> · {{ employee.position.title }}</span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <Button
            v-if="auth.permissions.includes('hrm.employee.write')"
            :label="t('profile.editProfile')"
            icon="pi pi-pencil"
            severity="secondary"
            outlined
            @click="router.push(`/hrm/employees/${employee.id}/edit`)"
          />
        </div>
      </div>

      <!-- ─────────── Quick stats tiles ─────────── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Leave used -->
        <Card>
          <template #content>
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="text-xs text-surface-500 uppercase tracking-wider">{{ t('profile.tiles.leaveUsed') }}</div>
                <div class="text-2xl font-bold mt-1">{{ totalUsed }}</div>
                <div class="text-[11px] text-surface-400">{{ t('profile.tiles.daysThisYear') }}</div>
              </div>
              <div class="size-10 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 grid place-items-center">
                <i class="pi pi-calendar-clock" />
              </div>
            </div>
          </template>
        </Card>
        <!-- Leave available -->
        <Card>
          <template #content>
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="text-xs text-surface-500 uppercase tracking-wider">{{ t('profile.tiles.leaveAvailable') }}</div>
                <div class="text-2xl font-bold mt-1">{{ totalAvailable }}</div>
                <div class="text-[11px] text-surface-400">{{ t('profile.tiles.daysRemaining') }}</div>
              </div>
              <div class="size-10 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 grid place-items-center">
                <i class="pi pi-check-circle" />
              </div>
            </div>
          </template>
        </Card>
        <!-- Attendance this month -->
        <Card>
          <template #content>
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="text-xs text-surface-500 uppercase tracking-wider">{{ t('profile.tiles.attendance') }}</div>
                <div class="text-2xl font-bold mt-1">
                  {{ stats ? stats.present : '—' }}
                </div>
                <div class="text-[11px] text-surface-400">{{ t('profile.tiles.presentThisMonth') }}</div>
              </div>
              <div class="size-10 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 grid place-items-center">
                <i class="pi pi-clock" />
              </div>
            </div>
          </template>
        </Card>
        <!-- Today's status -->
        <Card>
          <template #content>
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="text-xs text-surface-500 uppercase tracking-wider">{{ t('profile.tiles.todayStatus') }}</div>
                <div class="text-base font-semibold mt-1 truncate capitalize">
                  <template v-if="todaysRecord">
                    {{ todaysRecord.status.replace('_', ' ') }}
                  </template>
                  <template v-else>
                    {{ t('profile.tiles.notClockedIn') }}
                  </template>
                </div>
                <div v-if="todaysRecord?.check_in" class="text-[11px] text-surface-400 font-mono">
                  {{ formatDateTime(todaysRecord.check_in) }}
                </div>
              </div>
              <div
                class="size-10 rounded-lg grid place-items-center"
                :class="todaysRecord
                  ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300'
                  : 'bg-surface-100 dark:bg-surface-800 text-surface-500'"
              >
                <i :class="todaysRecord ? 'pi pi-check' : 'pi pi-clock'" />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- ─────────── Two-column body ─────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- LEFT: Personal + Department -->
        <div class="lg:col-span-1 space-y-4">
          <!-- Personal info -->
          <Card>
            <template #content>
              <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                {{ t('profile.sections.personal') }}
              </h2>
              <dl class="space-y-3 text-sm">
                <div>
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.email') }}</dt>
                  <dd class="font-medium break-all">{{ fmt(employee.email) }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.phone') }}</dt>
                  <dd class="font-mono">{{ fmt(employee.phone) }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.dateOfBirth') }}</dt>
                  <dd class="font-mono">{{ formatDate(employee.date_of_birth) }}</dd>
                </div>
                <div v-if="employee.gender">
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.gender') }}</dt>
                  <dd>{{ t(`hrm.employees.genders.${employee.gender}`) }}</dd>
                </div>
                <div v-if="employee.nationality">
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.nationality') }}</dt>
                  <dd>{{ fmt(employee.nationality) }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.hireDate') }}</dt>
                  <dd class="font-mono">{{ formatDate(employee.hire_date) }}</dd>
                </div>
                <div v-if="employee.employment_type">
                  <dt class="text-xs text-surface-500">{{ t('hrm.employees.fields.employmentType') }}</dt>
                  <dd>{{ t(`hrm.employees.employmentTypes.${employee.employment_type}`) }}</dd>
                </div>
              </dl>
            </template>
          </Card>

          <!-- Department info -->
          <Card v-if="department">
            <template #content>
              <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                {{ t('profile.sections.department') }}
              </h2>
              <div class="flex items-center gap-3 mb-3 pb-3 border-b border-surface-200 dark:border-surface-800">
                <div class="size-10 rounded-lg bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300 grid place-items-center">
                  <i class="pi pi-sitemap" />
                </div>
                <div class="min-w-0">
                  <div class="font-semibold truncate">{{ department.name }}</div>
                  <code v-if="department.code" class="font-mono text-xs text-surface-500">{{ department.code }}</code>
                </div>
              </div>
              <p v-if="department.description" class="text-sm text-surface-600 dark:text-surface-300 mb-4 leading-relaxed">
                {{ department.description }}
              </p>
              <div v-if="department.manager">
                <div class="text-xs text-surface-500 mb-2">{{ t('profile.sections.manager') }}</div>
                <div class="flex items-start gap-3">
                  <div class="size-9 rounded-full bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300 grid place-items-center text-xs font-semibold uppercase flex-shrink-0">
                    {{ (department.manager.first_name?.[0] ?? '') + (department.manager.last_name?.[0] ?? '') }}
                  </div>
                  <div class="min-w-0 text-sm space-y-1">
                    <div class="font-medium">{{ department.manager.first_name }} {{ department.manager.last_name }}</div>
                    <div v-if="department.manager.employee_id">
                      <code class="font-mono text-xs text-surface-500">{{ department.manager.employee_id }}</code>
                    </div>
                    <a
                      v-if="department.manager.email"
                      :href="`mailto:${department.manager.email}`"
                      class="flex items-center gap-1.5 text-xs text-surface-500 hover:text-primary-600 hover:underline min-w-0"
                    >
                      <i class="pi pi-envelope text-[10px] flex-shrink-0" />
                      <span class="truncate">{{ department.manager.email }}</span>
                    </a>
                    <div v-if="department.manager.phone" class="flex items-center gap-1.5 text-xs text-surface-500">
                      <i class="pi pi-phone text-[10px] flex-shrink-0" />
                      <span class="font-mono">{{ department.manager.phone }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- RIGHT: Leave + Attendance -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Leave balances -->
          <Card>
            <template #content>
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400">
                  {{ t('profile.sections.leaveBalances') }}
                </h2>
                <NuxtLink to="/hrm/leave" class="text-xs text-primary-600 hover:underline inline-flex items-center gap-1">
                  {{ t('profile.sections.requestLeave') }} <i class="pi pi-arrow-right text-[10px]" />
                </NuxtLink>
              </div>
              <div v-if="balances.length === 0" class="text-center text-sm text-surface-400 py-6">
                {{ t('profile.empty.balances') }}
              </div>
              <div v-else class="space-y-4">
                <div v-for="b in balances" :key="b.id">
                  <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                      <!-- Color comes from leave_type.color (e.g. "emerald") —
                           those are Tailwind palette names. Map a few common
                           ones to bg-* classes; fall back to primary. -->
                      <span
                        class="size-2.5 rounded-full"
                        :class="{
                          'bg-emerald-500': b.leave_type?.color === 'emerald',
                          'bg-rose-500':    b.leave_type?.color === 'rose',
                          'bg-amber-500':   b.leave_type?.color === 'amber',
                          'bg-sky-500':     b.leave_type?.color === 'sky',
                          'bg-indigo-500':  b.leave_type?.color === 'indigo',
                          'bg-stone-500':   b.leave_type?.color === 'stone',
                          'bg-primary-500': !b.leave_type?.color || !['emerald','rose','amber','sky','indigo','stone'].includes(b.leave_type.color),
                        }"
                      />
                      <span class="text-sm font-medium">{{ b.leave_type?.name ?? b.leave_type_id }}</span>
                      <code class="font-mono text-[10px] text-surface-400">{{ b.year }}</code>
                    </div>
                    <div class="text-xs">
                      <span class="font-mono font-semibold">{{ availableFor(b) }}</span>
                      <span class="text-surface-400"> / {{ Number(b.balance) }} {{ t('profile.tiles.days') }}</span>
                    </div>
                  </div>
                  <div class="h-1.5 rounded-full bg-surface-100 dark:bg-surface-800 overflow-hidden">
                    <div
                      class="h-full bg-primary-500 transition-all"
                      :style="{ width: `${percentUsed(b)}%` }"
                    />
                  </div>
                  <div class="flex items-center gap-3 mt-1 text-[10px] text-surface-500">
                    <span>{{ t('profile.balances.used') }}: <strong class="font-mono">{{ Number(b.used) }}</strong></span>
                    <span v-if="Number(b.pending) > 0" class="text-amber-600 dark:text-amber-400">
                      {{ t('profile.balances.pending') }}: <strong class="font-mono">{{ Number(b.pending) }}</strong>
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- Recent leave requests -->
          <Card>
            <template #content>
              <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                {{ t('profile.sections.recentLeave') }}
              </h2>
              <div v-if="recentRequests.length === 0" class="text-center text-sm text-surface-400 py-6">
                {{ t('profile.empty.requests') }}
              </div>
              <div v-else class="divide-y divide-surface-200 dark:divide-surface-800">
                <div v-for="r in recentRequests" :key="r.id" class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-medium">{{ r.leave_type?.name ?? '—' }}</span>
                      <Tag :value="r.status" :severity="statusSeverity(r.status)" class="!text-[10px] !py-0" />
                    </div>
                    <div class="text-xs text-surface-500 font-mono">
                      {{ formatDate(r.start_date) }} → {{ formatDate(r.end_date) }} · {{ Number(r.days) }} {{ t('profile.tiles.days') }}
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>

          <!-- My career history -->
          <Card v-if="promotions.length">
            <template #content>
              <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                {{ t('profile.sections.career') }}
              </h2>
              <ol class="relative space-y-4">
                <div class="absolute left-2.5 top-2 bottom-2 w-px bg-surface-200 dark:bg-surface-700" />
                <li v-for="p in promotions" :key="p.id" class="relative pl-9">
                  <div
                    class="absolute left-0 top-0.5 size-5 rounded-full grid place-items-center text-[9px]"
                    :class="{
                      'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': p.type === 'promotion',
                      'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300':             p.type === 'lateral',
                      'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300':         p.type === 'demotion',
                      'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300':    p.type === 'salary_adjustment',
                    }"
                  >
                    <i :class="{
                      'pi pi-arrow-up':    p.type === 'promotion',
                      'pi pi-arrow-right': p.type === 'lateral',
                      'pi pi-arrow-down':  p.type === 'demotion',
                      'pi pi-dollar':      p.type === 'salary_adjustment',
                    }" />
                  </div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <Tag :value="t(`hrm.career.types.${p.type}`)" :severity="promoTypeSeverity(p.type)" class="!text-[10px] !py-0" />
                    <span class="font-mono text-xs text-surface-500">{{ formatDate(p.effective_date) }}</span>
                  </div>
                  <div v-if="p.previous_position || p.new_position || p.previous_role_name || p.new_role_name" class="text-sm mt-1">
                    <span class="text-surface-500">{{ p.previous_position?.title ?? p.previous_role_name ?? '—' }}</span>
                    <i class="pi pi-arrow-right text-[10px] mx-2 text-surface-400" />
                    <span class="font-semibold">{{ p.new_position?.title ?? p.new_role_name ?? '—' }}</span>
                  </div>
                  <div v-if="p.new_salary != null" class="text-xs text-surface-500 mt-0.5">
                    <span v-if="p.previous_salary != null">{{ formatMoney(p.previous_salary) }}</span>
                    <span v-else>—</span>
                    <i class="pi pi-arrow-right text-[10px] mx-1" />
                    <span class="font-mono font-semibold text-surface-700 dark:text-surface-300">{{ formatMoney(p.new_salary) }}</span>
                    <code v-if="p.currency" class="font-mono text-[10px] text-surface-400 ml-1">{{ p.currency }}</code>
                  </div>
                </li>
              </ol>
            </template>
          </Card>

          <!-- Recent attendance -->
          <Card>
            <template #content>
              <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                {{ t('profile.sections.recentAttendance') }}
              </h2>
              <div v-if="recentAttendance.length === 0" class="text-center text-sm text-surface-400 py-6">
                {{ t('profile.empty.attendance') }}
              </div>
              <div v-else class="divide-y divide-surface-200 dark:divide-surface-800">
                <div v-for="a in recentAttendance" :key="a.id" class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <div class="font-mono text-sm">{{ formatDate(a.date) }}</div>
                    <div class="text-xs text-surface-500">
                      <span v-if="a.check_in">{{ t('hrm.attendance.clockIn') }}: <span class="font-mono">{{ formatDateTime(a.check_in) }}</span></span>
                      <span v-if="a.check_in && a.check_out"> · </span>
                      <span v-if="a.check_out">{{ t('hrm.attendance.clockOut') }}: <span class="font-mono">{{ formatDateTime(a.check_out) }}</span></span>
                      <span v-if="!a.check_in && !a.check_out">—</span>
                    </div>
                  </div>
                  <Tag :value="a.status" :severity="statusSeverity(a.status)" class="!text-[10px] !py-0" />
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>
