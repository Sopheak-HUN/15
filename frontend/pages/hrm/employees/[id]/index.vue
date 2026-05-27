<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Employee, EmployeeAddress, EmployeeDocument, EmployeeNote, LeaveBalance } from '~/types/hrm'
import type { GeoUnit } from '~/composables/useCambodiaGeo'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const employeeId = route.params.id as string

const hrm = useHrmApi()
const geo = useCambodiaGeo()
const uploads = useUpload()
const toast = useToast()
const confirm = useConfirm()
const { t, locale } = useI18n()

const tab = ref<'profile' | 'notes' | 'documents' | 'leave'>('profile')

// Pick the right name based on current locale (Khmer when available).
const geoNameOf = (units: GeoUnit[], code?: string | null): string => {
  if (!code) return '—'
  const hit = units.find((u) => u.id === code)
  if (!hit) return code
  return locale.value === 'km' && hit.name_kh ? hit.name_kh : hit.name
}

const { data: empData, pending: empPending } = await useAsyncData(
  `hrm-employee-${employeeId}`,
  () => hrm.showEmployee(employeeId),
)
const employee = computed<Employee | null>(() => empData.value?.data ?? null)

// ── Geo label caches ─────────────────────────────────────────
// Detail view shows province/district/commune/village NAMES, not raw
// MEF codes. We fetch the lists once per (parent) on mount; the
// composable caches them globally so repeats are free.
const provinces = ref<GeoUnit[]>([])
const districtsByProv = reactive<Record<string, GeoUnit[]>>({})
const communesByDist = reactive<Record<string, GeoUnit[]>>({})
const villagesByCom  = reactive<Record<string, GeoUnit[]>>({})

const loadGeoLabelsFor = async (addr?: EmployeeAddress | null) => {
  if (!addr) return
  if (addr.province_code && !districtsByProv[addr.province_code]) {
    districtsByProv[addr.province_code] = await geo.listDistricts(addr.province_code)
  }
  if (addr.district_code && !communesByDist[addr.district_code]) {
    communesByDist[addr.district_code] = await geo.listCommunes(addr.district_code)
  }
  if (addr.commune_code && !villagesByCom[addr.commune_code]) {
    villagesByCom[addr.commune_code] = await geo.listVillages(addr.commune_code)
  }
}

onMounted(async () => {
  provinces.value = await geo.listProvinces()
  if (employee.value) {
    await Promise.all([
      loadGeoLabelsFor(employee.value.current_address),
      loadGeoLabelsFor(employee.value.permanent_address),
      loadGeoLabelsFor(employee.value.emergency_address),
    ])
  }
})

// Build a display string for an address. Skips empty parts; falls back
// to the raw code if the human name hasn't loaded yet (rare).
const addrDisplay = (addr?: EmployeeAddress | null): string => {
  if (!addr) return '—'
  const parts = [
    addr.home_number,
    addr.street,
    addr.village_code  ? geoNameOf(villagesByCom[addr.commune_code ?? ''] ?? [], addr.village_code) : null,
    addr.commune_code  ? geoNameOf(communesByDist[addr.district_code ?? ''] ?? [], addr.commune_code) : null,
    addr.district_code ? geoNameOf(districtsByProv[addr.province_code ?? ''] ?? [], addr.district_code) : null,
    addr.province_code ? geoNameOf(provinces.value, addr.province_code) : null,
  ].filter((x) => x && x !== '—' && x !== '')
  return parts.length ? parts.join(', ') : '—'
}

// Map dual-language wizard labels back to readable badges in the detail view.
const CONTRACT_LABEL: Record<string, string> = {
  work: 'Work Contract',
  fdc: 'Fixed-Duration Contract',
  udc: 'Undetermined-Duration Contract',
  probation: 'Probation',
  internship: 'Internship',
  consulting: 'Consulting',
}
const EDUCATION_LABEL: Record<string, string> = {
  none: 'None',
  primary: 'Primary',
  secondary: 'Secondary',
  high_school: 'High School',
  diploma: 'Diploma',
  associate: 'Associate Degree',
  bachelor: "Bachelor's Degree",
  master: "Master's Degree",
  phd: 'Doctor of Philosophy',
}

const fmt = (v?: string | number | null) => (v === null || v === undefined || v === '' ? '—' : String(v))

// ----- Date conversion helpers -----
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

// ----- Notes -----
const categoryFilter = ref<string | null>(null)
const { data: notesData, refresh: refreshNotes, pending: notesPending } = await useAsyncData(
  `hrm-employee-${employeeId}-notes`,
  () => hrm.listEmployeeNotes({ employee_id: employeeId, category: categoryFilter.value || undefined, per_page: 100 }),
  { watch: [categoryFilter] },
)
const notes = computed<EmployeeNote[]>(() => notesData.value?.data?.data ?? [])

const noteDialog = ref(false)
const editingNote = ref<EmployeeNote | null>(null)
const noteSaving = ref(false)
const noteSchema = toTypedSchema(z.object({
  category: z.enum(['general', 'performance', 'disciplinary', 'praise']),
  title: z.string().max(200).optional().or(z.literal('')),
  body: z.string().min(1),
  is_private: z.boolean(),
  is_disciplinary: z.boolean(),
  incident_date: z.preprocess(datePreprocess, z.string().nullable().optional()),
}))
const { defineField: nField, handleSubmit: handleNote, errors: nErrors, resetForm: resetNote, setValues: setNote } = useForm({
  validationSchema: noteSchema,
  initialValues: { category: 'general', title: '', body: '', is_private: true, is_disciplinary: false, incident_date: null },
})
const [nCategory] = nField('category')
const [nTitle] = nField('title')
const [nBody] = nField('body')
const [nPrivate] = nField('is_private')
const [nDisciplinary] = nField('is_disciplinary')
const [nDate] = nField('incident_date')

const noteCategories = computed(() => (['general', 'performance', 'disciplinary', 'praise'] as const).map((v) => ({
  label: t(`hrm.notes.notes.categories.${v}`), value: v,
})))

const openNoteCreate = () => {
  editingNote.value = null
  resetNote()
  noteDialog.value = true
}
const openNoteEdit = (row: EmployeeNote) => {
  editingNote.value = row
  setNote({
    category: row.category,
    title: row.title ?? '',
    body: row.body,
    is_private: row.is_private,
    is_disciplinary: row.is_disciplinary,
    incident_date: parseDate(row.incident_date),
  })
  noteDialog.value = true
}
const onSaveNote = handleNote(async (values) => {
  noteSaving.value = true
  try {
    const payload = { ...values, employee_id: employeeId }
    if (editingNote.value) {
      await hrm.updateEmployeeNote(editingNote.value.id, {
        category: values.category,
        title: values.title || undefined,
        body: values.body,
        is_private: values.is_private,
        is_disciplinary: values.is_disciplinary,
        incident_date: values.incident_date || undefined,
      })
      toast.add({ severity: 'success', summary: t('hrm.notes.toast.noteUpdated'), life: 2000 })
    } else {
      await hrm.createEmployeeNote({
        employee_id: employeeId,
        category: values.category,
        title: values.title || undefined,
        body: values.body,
        is_private: values.is_private,
        is_disciplinary: values.is_disciplinary,
        incident_date: values.incident_date || undefined,
      })
      toast.add({ severity: 'success', summary: t('hrm.notes.toast.noteCreated'), life: 2000 })
    }
    noteDialog.value = false
    await refreshNotes()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    noteSaving.value = false
  }
}, ({ errors }) => {
  const firstError = Object.entries(errors)[0]
  if (firstError) {
    toast.add({
      severity: 'warn',
      summary: 'Form Validation Error',
      detail: `${firstError[0]}: ${firstError[1]}`,
      life: 5000,
    })
  }
})
const onDeleteNote = (row: EmployeeNote) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.title ?? '' }),
    header: t('hrm.common.confirmHeader'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteEmployeeNote(row.id)
      toast.add({ severity: 'success', summary: t('hrm.notes.toast.noteDeleted'), life: 2000 })
      await refreshNotes()
    },
  })
}

// ----- Documents -----
const expiringFilter = ref(false)
const { data: docsData, refresh: refreshDocs, pending: docsPending } = await useAsyncData(
  `hrm-employee-${employeeId}-docs`,
  () => hrm.listEmployeeDocuments({ employee_id: employeeId, expiring_soon: expiringFilter.value || undefined, per_page: 100 }),
  { watch: [expiringFilter] },
)
const docs = computed<EmployeeDocument[]>(() => docsData.value?.data?.data ?? [])

const docDialog = ref(false)
const docSaving = ref(false)
const docSchema = toTypedSchema(z.object({
  title: z.string().min(2).max(200),
  category: z.enum(['contract', 'id', 'certificate', 'other']),
  file_path: z.string().min(1).max(500),
  mime_type: z.string().max(128).optional().or(z.literal('')),
  size_bytes: z.coerce.number().min(0).nullable().optional(),
  issued_at: z.preprocess(datePreprocess, z.string().nullable().optional()),
  expires_at: z.preprocess(datePreprocess, z.string().nullable().optional()),
}))
const { defineField: dField, handleSubmit: handleDoc, errors: dErrors, resetForm: resetDoc } = useForm({
  validationSchema: docSchema,
  initialValues: { title: '', category: 'contract', file_path: '', mime_type: '', size_bytes: null, issued_at: null, expires_at: null },
})
const [dTitle] = dField('title')
const [dCategory] = dField('category')
const [dFilePath] = dField('file_path')
const [dMime] = dField('mime_type')
const [dSize] = dField('size_bytes')
const [dIssued] = dField('issued_at')
const [dExpires] = dField('expires_at')

const docCategories = computed(() => (['contract', 'id', 'certificate', 'other'] as const).map((v) => ({
  label: t(`hrm.notes.documents.categories.${v}`), value: v,
})))

// File picker state — the dialog now uploads the file to MinIO via a
// presigned PUT BEFORE the user fills metadata. On select we:
//   1. Upload the bytes to tenants/{handle}/employees/{id}/documents/{nanoid}.{ext}
//   2. Pre-fill file_path with the returned key (read-only display)
//   3. Pre-fill mime_type + size_bytes from the file
//   4. Pre-fill title from the file's basename if empty
const docFileInput = ref<HTMLInputElement | null>(null)
const docFileName = ref<string | null>(null)
const docUploading = ref(false)

const onDocFileChange = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  docUploading.value = true
  try {
    const result = await uploads.uploadEmployeeDocument(employeeId, file)
    docFileName.value = file.name
    // setFieldValue equivalents through the defineField refs
    dFilePath.value = result.key
    dMime.value     = result.mime
    dSize.value     = result.size
    if (!dTitle.value) {
      dTitle.value = file.name.replace(/\.[^.]+$/, '')
    }
    toast.add({ severity: 'success', summary: t('hrm.notes.toast.fileUploaded'), life: 1800 })
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : String(err)
    toast.add({ severity: 'error', summary: t('hrm.notes.toast.uploadFailed'), detail: msg, life: 4500 })
  } finally {
    docUploading.value = false
    if (docFileInput.value) docFileInput.value.value = ''
  }
}

const openDocCreate = () => {
  resetDoc()
  docFileName.value = null
  docDialog.value = true
}
const onCreateDoc = handleDoc(async (values) => {
  docSaving.value = true
  try {
    await hrm.createEmployeeDocument({
      employee_id: employeeId,
      title: values.title,
      category: values.category,
      file_path: values.file_path,
      mime_type: values.mime_type || undefined,
      size_bytes: values.size_bytes ?? undefined,
      issued_at: values.issued_at || undefined,
      expires_at: values.expires_at || undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.notes.toast.docCreated'), life: 2000 })
    docDialog.value = false
    await refreshDocs()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    docSaving.value = false
  }
}, ({ errors }) => {
  const firstError = Object.entries(errors)[0]
  if (firstError) {
    toast.add({
      severity: 'warn',
      summary: 'Form Validation Error',
      detail: `${firstError[0]}: ${firstError[1]}`,
      life: 5000,
    })
  }
})
const onDeleteDoc = (row: EmployeeDocument) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.title }),
    header: t('hrm.common.confirmHeader'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await hrm.deleteEmployeeDocument(row.id)
      toast.add({ severity: 'success', summary: t('hrm.notes.toast.docDeleted'), life: 2000 })
      await refreshDocs()
    },
  })
}

// ----- Leave balances -----
const { data: balData } = await useAsyncData(
  `hrm-employee-${employeeId}-balances`,
  () => hrm.employeeLeaveBalances(employeeId),
)
const balances = computed<LeaveBalance[]>(() => balData.value?.data ?? [])

// ----- Create user account -----
// Provisions a tenant `users` row linked to this employee. Backend
// rejects the call if `employee.user_id` is already set, so the button
// is hidden once the link exists.
const iam = useIamApi()
const userDialog = ref(false)
const userSaving = ref(false)
const userShowPassword = ref(false)
const userRoles = ref<{ id: string; name: string; description?: string | null }[]>([])
const userRolesLoading = ref(false)

const userSchema = toTypedSchema(z.object({
  email: z.string().email().max(160),
  password: z.string().min(8).max(128),
  role_id: z.string().uuid({ message: 'Role is required' }),
}))
const {
  defineField: uField,
  handleSubmit: handleUser,
  errors: uErrors,
  resetForm: resetUser,
  setFieldValue: setUserField,
} = useForm({
  validationSchema: userSchema,
  initialValues: { email: '', password: '', role_id: '' },
})
const [uEmail] = uField('email')
const [uPassword] = uField('password')
const [uRoleId] = uField('role_id')

// Generate a 12-char password with mixed case, digits, and symbols.
// Frontend-only — the backend trusts whatever it receives (after the
// min:8 validator) since the User model auto-hashes via the `hashed` cast.
const generatePassword = () => {
  const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'      // no I/O for legibility
  const lower = 'abcdefghijkmnopqrstuvwxyz'     // no l
  const digits = '23456789'                      // no 0/1
  const symbols = '!@#$%^&*-_=+'
  const all = upper + lower + digits + symbols
  const len = 12
  // Guarantee at least one of each pool so we always satisfy the typical
  // "must contain upper/lower/digit/symbol" policy if the backend ever
  // enforces it.
  const required = [
    upper[Math.floor(Math.random() * upper.length)],
    lower[Math.floor(Math.random() * lower.length)],
    digits[Math.floor(Math.random() * digits.length)],
    symbols[Math.floor(Math.random() * symbols.length)],
  ]
  const rest = Array.from({ length: len - required.length }, () =>
    all[Math.floor(Math.random() * all.length)])
  const pw = [...required, ...rest]
    // Fisher-Yates shuffle so the required pool isn't always at the front
    .map((c) => ({ c, k: Math.random() }))
    .sort((a, b) => a.k - b.k)
    .map((x) => x.c)
    .join('')
  setUserField('password', pw)
  userShowPassword.value = true
}

const copyPassword = async () => {
  if (!uPassword.value) return
  try {
    await navigator.clipboard.writeText(String(uPassword.value))
    toast.add({ severity: 'success', summary: t('hrm.users.toast.passwordCopied'), life: 1500 })
  } catch {
    /* ignore — clipboard may be blocked in non-secure contexts */
  }
}

const openUserDialog = async () => {
  if (!employee.value) return
  resetUser()
  setUserField('email', employee.value.email ?? '')
  userShowPassword.value = false
  userDialog.value = true
  // Lazy-load roles the first time the dialog is opened.
  if (userRoles.value.length === 0) {
    userRolesLoading.value = true
    try {
      const resp = await iam.listRoles()
      userRoles.value = (resp?.data ?? []) as { id: string; name: string; description?: string | null }[]
    } catch {
      toast.add({ severity: 'error', summary: t('hrm.users.toast.rolesLoadFailed'), life: 4000 })
    } finally {
      userRolesLoading.value = false
    }
  }
}

const onSaveUser = handleUser(async (values) => {
  userSaving.value = true
  try {
    await hrm.createUserForEmployee(employeeId, {
      email: values.email,
      password: values.password,
      role_id: values.role_id,
    })
    toast.add({ severity: 'success', summary: t('hrm.users.toast.created'), life: 2500 })
    userDialog.value = false
    // Refresh the employee record so the "User account: <email>" chip
    // shows up and the button hides.
    const fresh = await hrm.showEmployee(employeeId)
    if (fresh?.data && empData.value) empData.value.data = fresh.data
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: t('hrm.common.saveFailed'),
      detail: data?.message,
      life: 5000,
    })
  } finally {
    userSaving.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <NuxtLink to="/hrm/employees" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1">
      <i class="pi pi-arrow-left text-xs" /> {{ t('hrm.employees.title') }}
    </NuxtLink>

    <div v-if="empPending" class="py-16 text-center"><ProgressSpinner /></div>
    <div v-else-if="!employee" class="py-16 text-center text-surface-500">Not found.</div>
    <template v-else>
      <!-- Header: avatar + names + chips + edit link -->
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <img
            v-if="employee.photo_url"
            :src="employee.photo_url"
            :alt="`${employee.first_name} ${employee.last_name}`"
            class="w-16 h-16 rounded-full object-cover ring-2 ring-surface-200 dark:ring-surface-700 flex-shrink-0"
          >
          <div
            v-else
            class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300 flex items-center justify-center text-xl font-semibold uppercase ring-2 ring-primary-200 dark:ring-primary-900 flex-shrink-0"
          >
            {{ (employee.first_name?.[0] ?? '') + (employee.last_name?.[0] ?? '') }}
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl font-semibold tracking-tight truncate">{{ employee.first_name }} {{ employee.last_name }}</h1>
              <span v-if="employee.first_name_kh || employee.last_name_kh" class="text-surface-500">
                ({{ employee.first_name_kh }} {{ employee.last_name_kh }})
              </span>
              <Tag :value="employee.employee_id" />
              <Tag :value="employee.status" :severity="employee.status === 'active' ? 'success' : 'danger'" />
              <Tag
                v-if="employee.user"
                :value="`${t('hrm.users.account')}: ${employee.user.email}`"
                severity="info"
                icon="pi pi-user"
              />
            </div>
            <p class="text-surface-500 mt-1 truncate">
              <span>{{ employee.email }}</span>
              <span v-if="employee.department"> · {{ employee.department.name }}</span>
              <span v-if="employee.position"> · {{ employee.position.title }}</span>
              <span v-if="employee.role_name"> · {{ employee.role_name }}</span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <Button
            v-if="!employee.user"
            :label="t('hrm.users.createAccount')"
            icon="pi pi-user-plus"
            severity="info"
            @click="openUserDialog"
          />
          <NuxtLink :to="`/hrm/employees/${employee.id}/edit`">
            <Button :label="t('common.edit')" icon="pi pi-pencil" severity="secondary" outlined />
          </NuxtLink>
        </div>
      </div>

      <Tabs v-model:value="tab">
        <TabList>
          <Tab value="profile">{{ t('hrm.employees.tabs.profile') }}</Tab>
          <Tab value="notes">{{ t('hrm.notes.tabs.notes') }}</Tab>
          <Tab value="documents">{{ t('hrm.notes.tabs.documents') }}</Tab>
          <Tab value="leave">{{ t('nav.leave') }}</Tab>
        </TabList>
        <TabPanels>
          <!-- ─────────── Profile ─────────── -->
          <TabPanel value="profile">
            <div class="space-y-4">
              <!-- Identity & Contact -->
              <Card>
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                    {{ t('hrm.employees.sections.identity') }} / {{ t('hrm.employees.sections.contact') }}
                  </h2>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.firstName') }}</dt><dd class="font-medium">{{ fmt(employee.first_name) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.lastName') }}</dt><dd class="font-medium">{{ fmt(employee.last_name) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.firstNameKh') }}</dt><dd class="font-medium">{{ fmt(employee.first_name_kh) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.lastNameKh') }}</dt><dd class="font-medium">{{ fmt(employee.last_name_kh) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.dateOfBirth') }}</dt><dd class="font-mono">{{ formatDate(employee.date_of_birth) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.gender') }}</dt><dd>{{ employee.gender ? t(`hrm.employees.genders.${employee.gender}`) : '—' }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.nationality') }}</dt><dd>{{ fmt(employee.nationality) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.nssfId') }}</dt><dd class="font-mono text-xs">{{ fmt(employee.nssf_id) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.roleName') }}</dt><dd>{{ fmt(employee.role_name) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.phone') }}</dt><dd class="font-mono">{{ fmt(employee.phone) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.officePhone') }}</dt><dd class="font-mono">{{ fmt(employee.office_phone) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.email') }}</dt><dd>{{ fmt(employee.email) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.hireDate') }}</dt><dd class="font-mono">{{ formatDate(employee.hire_date) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.employmentType') }}</dt><dd>{{ employee.employment_type ? t(`hrm.employees.employmentTypes.${employee.employment_type}`) : '—' }}</dd></div>
                  </dl>
                </template>
              </Card>

              <!-- Identification document -->
              <Card>
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">{{ t('hrm.employees.sections.identification') }}</h2>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.identificationType') }}</dt><dd>{{ employee.identification_type ? t(`hrm.employees.identificationTypes.${employee.identification_type}`) : '—' }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.idCardNumber') }}</dt><dd class="font-mono text-xs">{{ fmt(employee.id_card_number) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.issuedDate') }}</dt><dd class="font-mono">{{ formatDate(employee.id_issued_date) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.issuedBy') }}</dt><dd>{{ fmt(employee.id_issued_by) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.issuedPlace') }}</dt><dd>{{ fmt(employee.id_issued_place) }}</dd></div>
                  </dl>
                </template>
              </Card>

              <!-- Personal -->
              <Card>
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">{{ t('hrm.employees.sections.personal') }}</h2>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.religion') }}</dt><dd>{{ employee.religion ? t(`hrm.employees.religions.${employee.religion}`) : '—' }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.maritalStatus') }}</dt><dd>{{ employee.marital_status ? t(`hrm.employees.maritalStatuses.${employee.marital_status}`) : '—' }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.bloodGroup') }}</dt><dd>{{ fmt(employee.blood_group) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.children') }}</dt><dd>{{ fmt(employee.children_count) }}</dd></div>
                  </dl>
                  <!-- Spouse (only when present) -->
                  <div v-if="employee.spouse && employee.spouse.name" class="mt-6 pt-4 border-t border-surface-200 dark:border-surface-800">
                    <h3 class="text-xs font-semibold tracking-wider uppercase text-surface-400 mb-3">{{ t('hrm.employees.sections.spouse') }}</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                      <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.spouseName') }}</dt><dd class="font-medium">{{ fmt(employee.spouse.name) }}</dd></div>
                      <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.spouseDateOfBirth') }}</dt><dd class="font-mono">{{ formatDate(employee.spouse.date_of_birth) }}</dd></div>
                      <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.spouseEducation') }}</dt><dd>{{ employee.spouse.education ? (EDUCATION_LABEL[employee.spouse.education] ?? employee.spouse.education) : '—' }}</dd></div>
                      <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.spouseOccupation') }}</dt><dd>{{ fmt(employee.spouse.occupation) }}</dd></div>
                    </dl>
                  </div>
                </template>
              </Card>

              <!-- Addresses (2-col grid: current + permanent) -->
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card>
                  <template #content>
                    <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                      {{ t('hrm.employees.wizard.steps.address') }}
                    </h2>
                    <p class="text-sm">{{ addrDisplay(employee.current_address) }}</p>
                    <p v-if="employee.current_address?.lat && employee.current_address?.lng" class="text-xs text-surface-500 font-mono mt-2">
                      {{ employee.current_address.lat }}, {{ employee.current_address.lng }}
                    </p>
                  </template>
                </Card>
                <Card>
                  <template #content>
                    <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                      {{ t('hrm.employees.wizard.steps.permanent') }}
                    </h2>
                    <p class="text-sm">{{ addrDisplay(employee.permanent_address) }}</p>
                    <p v-if="employee.permanent_address?.lat && employee.permanent_address?.lng" class="text-xs text-surface-500 font-mono mt-2">
                      {{ employee.permanent_address.lat }}, {{ employee.permanent_address.lng }}
                    </p>
                  </template>
                </Card>
              </div>

              <!-- Emergency contact + address -->
              <Card v-if="employee.emergency_contact || employee.emergency_address">
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">
                    {{ t('hrm.employees.wizard.steps.emergency') }}
                  </h2>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.fatherName') }}</dt><dd class="font-medium">{{ fmt(employee.emergency_contact?.father_name) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.fatherOccupation') }}</dt><dd>{{ fmt(employee.emergency_contact?.father_occupation) }}</dd></div>
                    <div></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.motherName') }}</dt><dd class="font-medium">{{ fmt(employee.emergency_contact?.mother_name) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.motherOccupation') }}</dt><dd>{{ fmt(employee.emergency_contact?.mother_occupation) }}</dd></div>
                    <div></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.phoneNumber') }}</dt><dd class="font-mono">{{ fmt(employee.emergency_contact?.phone_number) }}</dd></div>
                    <div><dt class="text-surface-500 text-xs">{{ t('hrm.employees.fields.homePhone') }}</dt><dd class="font-mono">{{ fmt(employee.emergency_contact?.home_phone) }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3 mt-2">
                      <dt class="text-surface-500 text-xs">{{ t('hrm.employees.sections.addressDetails') }}</dt>
                      <dd>{{ addrDisplay(employee.emergency_address) }}</dd>
                    </div>
                  </dl>
                </template>
              </Card>

              <!-- Education -->
              <Card v-if="employee.educations && employee.educations.length">
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">{{ t('hrm.employees.wizard.steps.education') }}</h2>
                  <ul class="divide-y divide-surface-200 dark:divide-surface-800">
                    <li v-for="edu in employee.educations" :key="edu.id" class="py-3 first:pt-0 last:pb-0">
                      <div class="flex items-baseline justify-between gap-4">
                        <div class="font-medium">{{ edu.level ? (EDUCATION_LABEL[edu.level] ?? edu.level) : '—' }}</div>
                        <div class="text-xs text-surface-500">{{ fmt(edu.status) }}</div>
                      </div>
                      <div class="text-sm text-surface-700 dark:text-surface-300 mt-0.5">{{ fmt(edu.major_subject) }}</div>
                      <div class="text-xs text-surface-500 mt-0.5">{{ fmt(edu.university_school) }}</div>
                    </li>
                  </ul>
                </template>
              </Card>

              <!-- Contracts -->
              <Card v-if="employee.contracts && employee.contracts.length">
                <template #content>
                  <h2 class="text-sm font-semibold tracking-wider uppercase text-surface-400 mb-4">{{ t('hrm.employees.wizard.steps.contract') }}</h2>
                  <DataTable :value="employee.contracts" data-key="id" striped-rows class="text-sm">
                    <Column header="">
                      <template #body="{ data }">
                        <span class="font-medium">{{ CONTRACT_LABEL[data.type] ?? data.type }}</span>
                      </template>
                    </Column>
                    <Column field="start_date" header="Start"><template #body="{ data }"><span class="font-mono text-xs">{{ formatDate(data.start_date) }}</span></template></Column>
                    <Column field="end_date" header="End"><template #body="{ data }"><span class="font-mono text-xs">{{ formatDate(data.end_date) }}</span></template></Column>
                    <Column field="status" header="Status">
                      <template #body="{ data }">
                        <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : data.status === 'terminated' ? 'danger' : 'secondary'" />
                      </template>
                    </Column>
                    <Column header="">
                      <template #body="{ data }">
                        <span class="text-xs text-surface-500 line-clamp-2">{{ data.comment }}</span>
                      </template>
                    </Column>
                  </DataTable>
                </template>
              </Card>
            </div>
          </TabPanel>

          <!-- Notes -->
          <TabPanel value="notes">
            <Card>
              <template #content>
                <div class="flex flex-wrap items-center gap-3 mb-4">
                  <Select
                    v-model="categoryFilter"
                    :options="noteCategories"
                    option-label="label"
                    option-value="value"
                    :placeholder="t('hrm.notes.notes.fields.category')"
                    show-clear
                    class="w-48"
                  />
                  <Button :label="t('hrm.notes.notes.new')" icon="pi pi-plus" class="ml-auto" @click="openNoteCreate" />
                </div>
                <DataTable :value="notes" :loading="notesPending" data-key="id" striped-rows class="text-sm">
                  <template #empty>
                    <div class="py-10 text-center text-surface-500">{{ t('hrm.notes.notes.empty') }}</div>
                  </template>
                  <Column :header="t('hrm.notes.notes.fields.category')">
                    <template #body="{ data }">
                      <Tag
                        :value="t(`hrm.notes.notes.categories.${data.category}`)"
                        :severity="data.category === 'disciplinary' ? 'danger' : data.category === 'praise' ? 'success' : 'info'"
                      />
                    </template>
                  </Column>
                  <Column field="title" :header="t('hrm.notes.notes.fields.title')" />
                  <Column :header="t('hrm.notes.notes.fields.body')">
                    <template #body="{ data }">
                      <div class="whitespace-pre-wrap text-xs text-surface-700 dark:text-surface-300 line-clamp-3">{{ data.body }}</div>
                    </template>
                  </Column>
                  <Column :header="t('hrm.notes.notes.fields.incidentDate')">
                    <template #body="{ data }">
                      <span class="font-mono text-xs">{{ formatDate(data.incident_date) }}</span>
                    </template>
                  </Column>
                  <Column header="" body-class="text-right" :style="{ width: '140px' }">
                    <template #body="{ data }">
                      <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openNoteEdit(data)" />
                      <Button icon="pi pi-trash"  text rounded severity="danger"    @click="onDeleteNote(data)" />
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </TabPanel>

          <!-- Documents -->
          <TabPanel value="documents">
            <Card>
              <template #content>
                <div class="flex flex-wrap items-center gap-3 mb-4">
                  <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="expiringFilter" input-id="doc-expiring" />
                    <label for="doc-expiring" class="text-sm">{{ t('hrm.notes.documents.expiringSoon') }}</label>
                  </div>
                  <Button :label="t('hrm.notes.documents.new')" icon="pi pi-plus" class="ml-auto" @click="openDocCreate" />
                </div>
                <DataTable :value="docs" :loading="docsPending" data-key="id" striped-rows class="text-sm">
                  <template #empty>
                    <div class="py-10 text-center text-surface-500">{{ t('hrm.notes.documents.empty') }}</div>
                  </template>
                  <Column field="title" :header="t('hrm.notes.documents.fields.title')" />
                  <Column :header="t('hrm.notes.documents.fields.category')">
                    <template #body="{ data }">
                      <Tag :value="t(`hrm.notes.documents.categories.${data.category}`)" />
                    </template>
                  </Column>
                  <Column :header="t('hrm.notes.documents.fields.file')">
                    <template #body="{ data }">
                      <a
                        v-if="data.download_url"
                        :href="data.download_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 text-primary-600 hover:underline text-xs"
                      >
                        <i class="pi pi-download text-[10px]" />
                        {{ t('hrm.notes.documents.actions.download') }}
                      </a>
                      <code v-else class="font-mono text-xs text-surface-500">{{ data.file_path }}</code>
                    </template>
                  </Column>
                  <Column :header="t('hrm.notes.documents.fields.issuedAt')">
                    <template #body="{ data }">
                      <span class="font-mono text-xs">{{ formatDate(data.issued_at) }}</span>
                    </template>
                  </Column>
                  <Column :header="t('hrm.notes.documents.fields.expiresAt')">
                    <template #body="{ data }">
                      <span class="font-mono text-xs">{{ formatDate(data.expires_at) }}</span>
                    </template>
                  </Column>
                  <Column header="" body-class="text-right" :style="{ width: '100px' }">
                    <template #body="{ data }">
                      <Button icon="pi pi-trash" text rounded severity="danger" @click="onDeleteDoc(data)" />
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </TabPanel>

          <!-- Leave -->
          <TabPanel value="leave">
            <Card>
              <template #content>
                <DataTable :value="balances" data-key="id" striped-rows class="text-sm">
                  <template #empty>
                    <div class="py-10 text-center text-surface-500">{{ t('hrm.common.noResults') }}</div>
                  </template>
                  <Column :header="t('hrm.leave.types.fields.name')">
                    <template #body="{ data }">{{ data.leave_type?.name ?? data.leave_type_id }}</template>
                  </Column>
                  <Column field="year" header="Year" />
                  <Column field="balance" header="Total" />
                  <Column field="used" header="Used" />
                  <Column field="pending" header="Pending" />
                </DataTable>
              </template>
            </Card>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>

    <!-- Note dialog -->
    <Dialog
      v-model:visible="noteDialog"
      modal
      :header="editingNote ? t('hrm.employees.dialog.editTitle', { name: editingNote.title ?? '' }) : t('hrm.notes.notes.new')"
      :style="{ width: '32rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSaveNote">
        <div>
          <FormLabel :label="t('hrm.notes.notes.fields.category')" required />
          <Select v-model="nCategory" :options="noteCategories" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.notes.placeholders.category')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.notes.notes.fields.title')" />
          <InputText v-model="nTitle" class="w-full" :placeholder="t('hrm.notes.placeholders.noteTitle')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.notes.notes.fields.body')" required />
          <Textarea v-model="nBody" rows="6" class="w-full" :invalid="!!nErrors.body" :placeholder="t('hrm.notes.placeholders.noteBody')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.notes.notes.fields.incidentDate')" />
          <DatePicker v-model="nDate as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
        </div>
        <div class="flex gap-6">
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="nPrivate" input-id="note-priv" />
            <label for="note-priv" class="text-sm">{{ t('hrm.notes.notes.fields.isPrivate') }}</label>
          </div>
          <div class="flex items-center gap-2">
            <ToggleSwitch v-model="nDisciplinary" input-id="note-disc" />
            <label for="note-disc" class="text-sm">{{ t('hrm.notes.notes.fields.isDisciplinary') }}</label>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="noteDialog = false" />
          <Button type="submit" :label="editingNote ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="noteSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Document dialog -->
    <Dialog v-model:visible="docDialog" modal :header="t('hrm.notes.documents.new')" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onCreateDoc">
        <div>
          <FormLabel :label="t('hrm.notes.documents.fields.title')" required />
          <InputText v-model="dTitle" class="w-full" :invalid="!!dErrors.title" :placeholder="t('hrm.notes.placeholders.docTitle')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.notes.documents.fields.category')" required />
          <Select v-model="dCategory" :options="docCategories" option-label="label" option-value="value" class="w-full" :placeholder="t('hrm.notes.placeholders.category')" />
        </div>
        <!-- File picker. Selecting a file uploads it to MinIO via a
             presigned PUT and pre-fills file_path / mime / size below. -->
        <div>
          <FormLabel :label="t('hrm.notes.documents.fields.file')" required />
          <div
            class="border-2 border-dashed rounded-lg p-4 transition-colors cursor-pointer"
            :class="[
              docUploading ? 'border-primary-300 bg-primary-50/30 dark:bg-primary-950/20' : 'border-surface-300 dark:border-surface-700 hover:border-primary-400',
              dErrors.file_path && !docFileName ? 'border-red-400' : '',
            ]"
            @click="docFileInput?.click()"
          >
            <input
              ref="docFileInput"
              type="file"
              accept="application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/zip"
              class="hidden"
              @change="onDocFileChange"
            >
            <div v-if="docUploading" class="flex items-center justify-center gap-2 text-sm text-primary-600">
              <i class="pi pi-spin pi-spinner" />
              <span>{{ t('hrm.notes.documents.uploading') }}</span>
            </div>
            <div v-else-if="!docFileName" class="text-center text-surface-500">
              <i class="pi pi-cloud-upload text-2xl block mb-1" />
              <div class="text-sm">{{ t('hrm.notes.documents.dropHint') }}</div>
              <div class="text-xs mt-0.5">{{ t('hrm.notes.documents.dropMeta') }}</div>
            </div>
            <div v-else class="flex items-center gap-3">
              <i class="pi pi-file text-2xl text-primary-600" />
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">{{ docFileName }}</div>
                <div class="text-xs text-surface-500">
                  {{ dMime }} · {{ ((dSize ?? 0) / 1024).toFixed(1) }} KB
                </div>
              </div>
              <i class="pi pi-check text-green-600" />
            </div>
          </div>
          <small v-if="dErrors.file_path && !docFileName" class="text-red-500 text-xs mt-1 block">{{ dErrors.file_path }}</small>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.notes.documents.fields.issuedAt')" />
            <DatePicker v-model="dIssued as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.notes.documents.fields.expiresAt')" />
            <DatePicker v-model="dExpires as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="docDialog = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="docSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Create User Account dialog -->
    <Dialog
      v-model:visible="userDialog"
      modal
      :header="t('hrm.users.dialogTitle')"
      :style="{ width: '32rem' }"
      :pt="{ root: { class: '!max-w-[95vw]' } }"
    >
      <form class="space-y-4" @submit.prevent="onSaveUser">
        <div>
          <FormLabel :label="t('hrm.users.fields.email')" required />
          <InputText
            v-model="uEmail"
            type="email"
            class="w-full"
            :invalid="!!uErrors.email"
            :placeholder="t('hrm.users.placeholders.email')"
            autocomplete="off"
          />
          <small v-if="uErrors.email" class="text-red-500 text-xs mt-1 block">{{ uErrors.email }}</small>
        </div>

        <div>
          <FormLabel :label="t('hrm.users.fields.password')" required />
          <div class="flex gap-2">
            <div class="relative flex-1">
              <InputText
                v-model="uPassword"
                :type="userShowPassword ? 'text' : 'password'"
                class="w-full font-mono pr-10"
                :invalid="!!uErrors.password"
                :placeholder="t('hrm.users.placeholders.password')"
                autocomplete="new-password"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 px-3 text-surface-500 hover:text-primary-600"
                :aria-label="userShowPassword ? t('hrm.users.actions.hidePassword') : t('hrm.users.actions.showPassword')"
                @click="userShowPassword = !userShowPassword"
              >
                <i :class="userShowPassword ? 'pi pi-eye-slash' : 'pi pi-eye'" />
              </button>
            </div>
            <Button
              type="button"
              icon="pi pi-refresh"
              :label="t('hrm.users.actions.generate')"
              severity="secondary"
              outlined
              @click="generatePassword"
            />
            <Button
              type="button"
              icon="pi pi-copy"
              severity="secondary"
              text
              :disabled="!uPassword"
              :aria-label="t('hrm.users.actions.copy')"
              @click="copyPassword"
            />
          </div>
          <small v-if="uErrors.password" class="text-red-500 text-xs mt-1 block">{{ uErrors.password }}</small>
          <small v-else class="text-surface-500 text-xs mt-1 block">{{ t('hrm.users.passwordHint') }}</small>
        </div>

        <div>
          <FormLabel :label="t('hrm.users.fields.role')" required />
          <Select
            v-model="uRoleId"
            :options="userRoles"
            option-label="name"
            option-value="id"
            class="w-full"
            :invalid="!!uErrors.role_id"
            :loading="userRolesLoading"
            :placeholder="t('hrm.users.placeholders.role')"
          >
            <template #option="{ option }">
              <div class="flex flex-col">
                <span class="font-medium">{{ option.name }}</span>
                <span v-if="option.description" class="text-xs text-surface-500">{{ option.description }}</span>
              </div>
            </template>
          </Select>
          <small v-if="uErrors.role_id" class="text-red-500 text-xs mt-1 block">{{ uErrors.role_id }}</small>
        </div>
      </form>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text :disabled="userSaving" @click="userDialog = false" />
        <Button :label="t('hrm.users.actions.createAccount')" icon="pi pi-check" :loading="userSaving" @click="onSaveUser" />
      </template>
    </Dialog>
  </div>
</template>
