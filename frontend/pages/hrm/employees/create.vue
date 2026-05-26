<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Position } from '~/types/hrm'
import type { GeoUnit } from '~/composables/useCambodiaGeo'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const geo = useCambodiaGeo()
const toast = useToast()
const { t, locale } = useI18n()
const geoLabel = (u: GeoUnit) => (locale.value === 'km' && u.name_kh ? u.name_kh : u.name)

// ── Wizard state ─────────────────────────────────────────────
const activeStep = ref<string>('1')

// Dropdown panel height that snugly fits its items: 38px per row up to ~10,
// then caps at 380px and lets the virtual scroller take over. Padding (~8px)
// covers the filter input padding so a 1-item list doesn't show whitespace.
const dropdownHeight = (count: number): string => {
  if (count <= 0) return '120px' // room for the empty/loading message
  const visible = Math.min(count, 10)
  return `${visible * 38 + 8}px`
}

// ── Option sources ───────────────────────────────────────────
const genders = computed(() =>
  ['male', 'female', 'other', 'prefer_not_to_say'].map((v) => ({
    label: t(`hrm.employees.genders.${v}`),
    value: v,
  }))
)

const identificationTypes = computed(() =>
  ['national_id', 'passport', 'drivers_license', 'family_book', 'other'].map((v) => ({
    label: t(`hrm.employees.identificationTypes.${v}`),
    value: v,
  }))
)

const religions = computed(() =>
  ['buddhism', 'christianity', 'islam', 'hinduism', 'other'].map((v) => ({
    label: t(`hrm.employees.religions.${v}`),
    value: v,
  }))
)

const maritalStatuses = computed(() =>
  ['single', 'married', 'divorced', 'widowed', 'separated'].map((v) => ({
    label: t(`hrm.employees.maritalStatuses.${v}`),
    value: v,
  }))
)

// Blood groups are universal labels — no i18n needed for the values.
const bloodGroups = computed(() =>
  ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'].map((v) => ({ label: v, value: v })),
)

const spouseEducationLevels = computed(() =>
  ['none', 'primary', 'secondary', 'high_school', 'diploma', 'bachelor', 'master', 'phd'].map((v) => ({
    label: t(`hrm.employees.educationLevels.${v}`),
    value: v,
  }))
)

// Degree options for the employee's own education detail — shown in dual
// English/Khmer format the way Cambodian HR forms typically render them.
const degreeLevels = [
  { value: 'none',        label: 'None / គ្មាន' },
  { value: 'primary',     label: 'Primary / បឋមសិក្សា' },
  { value: 'secondary',   label: 'Secondary / មធ្យមសិក្សាបឋមភូមិ' },
  { value: 'high_school', label: 'High School / មធ្យមសិក្សាទុតិយភូមិ' },
  { value: 'diploma',     label: 'Diploma / សញ្ញាបត្រ' },
  { value: 'associate',   label: 'Associate Degree / បរិញ្ញាបត្ររង' },
  { value: 'bachelor',    label: "Bachelor's Degree / បរិញ្ញាបត្រ" },
  { value: 'master',      label: "Master's Degree / អនុបណ្ឌិត" },
  { value: 'phd',         label: 'Doctor of Philosophy / បណ្ឌិត' },
]

// Contract types — dual-language to match other Cambodian-HR forms.
const contractTypes = [
  { value: 'work',        label: 'Work Contract / កិច្ចសន្យាការងារ' },
  { value: 'fdc',         label: 'Fixed-Duration Contract / កិច្ចសន្យាមានកំណត់ពេលវេលា' },
  { value: 'udc',         label: 'Undetermined-Duration Contract / កិច្ចសន្យាគ្មានកំណត់ពេលវេលា' },
  { value: 'probation',   label: 'Probation / សាកល្បង' },
  { value: 'internship',  label: 'Internship / កម្មសិក្សា' },
  { value: 'consulting',  label: 'Consulting / ប្រឹក្សាយោបល់' },
]

const { data: deptData } = await useAsyncData('hrm-emp-depts', () => hrm.listDepartments({ per_page: 200 }))
const { data: posData }  = await useAsyncData('hrm-emp-positions', () => hrm.listPositions({ per_page: 200 }))
const departments = computed(() => deptData.value?.data?.data ?? [])
const allPositions = computed<Position[]>(() => posData.value?.data?.data ?? [])

// ── Date helper for DatePicker (Date → 'YYYY-MM-DD') ─────────
const datePreprocess = (val: unknown) => {
  if (val instanceof Date) {
    const y = val.getFullYear()
    const m = String(val.getMonth() + 1).padStart(2, '0')
    const d = String(val.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
  }
  if (typeof val === 'string' && val.trim() !== '') return val.split('T')[0]
  return null
}

// ── Step 1 schema (rebuilds on locale change so error messages translate) ─
const schema = computed(() => {
  const req = t('hrm.common.required')
  // Helper: covers both missing (required_error) and wrong type (invalid_type_error)
  //   so fields whose initial value is null/undefined still show our translated message.
  const reqStr = () => z.string({ required_error: req, invalid_type_error: req })
  return toTypedSchema(
    z.object({
      first_name:    reqStr().min(1, req),
      last_name:     reqStr().min(1, req),
      first_name_kh: reqStr().min(1, req),
      last_name_kh:  reqStr().min(1, req),
      gender:        reqStr().min(1, req),
      date_of_birth: z.preprocess(datePreprocess, reqStr().min(1, req)),
      joined_date:   z.preprocess(datePreprocess, reqStr().min(1, req)),
      nssf_id:       reqStr().min(1, req),
      department_id: reqStr().uuid(req),
      position_id:   reqStr().uuid(req),
      nationality:   reqStr().min(1, req),
      role_name:     reqStr().min(1, req),
      office_phone:  z.string().nullable().optional(),
      phone:         reqStr().min(1, req),
      email:         reqStr().email(t('hrm.common.invalidEmail')).min(1, req),
      bank_account:  z.string().nullable().optional(),
      salary:        z.coerce.number().min(0).nullable().optional(),
      photo:         z.any().refine((f) => f instanceof File, { message: req }),
      // Step 2 — Current address
      home_number:   z.string().nullable().optional(),
      street:        z.string().nullable().optional(),
      province_id:   reqStr().min(1, req),
      district_id:   reqStr().min(1, req),
      commune_id:    reqStr().min(1, req),
      village_id:    reqStr().min(1, req),
      lat:           z.number().min(-90).max(90).nullable().optional(),
      lng:           z.number().min(-180).max(180).nullable().optional(),
      // Step 3 — Permanent address (same shape as current)
      perm_home_number: z.string().nullable().optional(),
      perm_street:      z.string().nullable().optional(),
      perm_province_id: reqStr().min(1, req),
      perm_district_id: reqStr().min(1, req),
      perm_commune_id:  reqStr().min(1, req),
      perm_village_id:  reqStr().min(1, req),
      perm_lat:         z.number().min(-90).max(90).nullable().optional(),
      perm_lng:         z.number().min(-180).max(180).nullable().optional(),
      // Step 4 — Contact information
      identification_type:  reqStr().min(1, req),
      id_card_number:       reqStr().min(1, req),
      id_issued_date:       z.preprocess(datePreprocess, z.string().nullable().optional()),
      id_issued_by:         z.string().nullable().optional(),
      id_issued_place:      z.string().nullable().optional(),
      religion:             reqStr().min(1, req),
      marital_status:       reqStr().min(1, req),
      blood_group:          z.string().nullable().optional(),
      spouse_name:          z.string().nullable().optional(),
      spouse_date_of_birth: z.preprocess(datePreprocess, z.string().nullable().optional()),
      spouse_education:     z.string().nullable().optional(),
      spouse_occupation:    z.string().nullable().optional(),
      children_count:       z.coerce.number().int().min(0, req),
      contact_phone:        z.string().nullable().optional(),
      // Step 5 — Relative / Emergency contact
      er_father_name:       reqStr().min(1, req),
      er_mother_name:       reqStr().min(1, req),
      er_father_occupation: reqStr().min(1, req),
      er_mother_occupation: reqStr().min(1, req),
      er_home:              z.string().nullable().optional(),
      er_street:            z.string().nullable().optional(),
      er_province_id:       reqStr().min(1, req),
      er_district_id:       reqStr().min(1, req),
      er_commune_id:        reqStr().min(1, req),
      er_village_id:        reqStr().min(1, req),
      er_group:             z.string().nullable().optional(),
      er_phone_number:      reqStr().min(1, req),
      er_home_phone:        z.string().nullable().optional(),
      // Step 6 — Education detail
      education_level:   z.string().nullable().optional(),
      major_subject:     z.string().nullable().optional(),
      education_status:  z.string().nullable().optional(),
      university_school: z.string().nullable().optional(),
      // Step 7 — Employee contract (final)
      contract_type:    reqStr().min(1, req),
      contract_start:   z.preprocess(datePreprocess, reqStr().min(1, req)),
      contract_end:     z.preprocess(datePreprocess, reqStr().min(1, req)),
      contract_comment: z.string().nullable().optional(),
    })
  )
})

// Keys that belong to each step — used by per-step validation.
const STEP_KEYS = {
  '1': [
    'first_name', 'last_name', 'first_name_kh', 'last_name_kh',
    'gender', 'date_of_birth', 'joined_date', 'nssf_id',
    'department_id', 'position_id', 'nationality', 'role_name',
    'office_phone', 'phone', 'email', 'bank_account', 'salary', 'photo',
  ],
  '2': [
    'home_number', 'street', 'province_id', 'district_id',
    'commune_id', 'village_id', 'lat', 'lng',
  ],
  '3': [
    'perm_home_number', 'perm_street', 'perm_province_id', 'perm_district_id',
    'perm_commune_id', 'perm_village_id', 'perm_lat', 'perm_lng',
  ],
  '4': [
    'identification_type', 'id_card_number', 'id_issued_date', 'id_issued_by', 'id_issued_place',
    'religion', 'marital_status', 'blood_group',
    'spouse_name', 'spouse_date_of_birth', 'spouse_education', 'spouse_occupation',
    'children_count', 'contact_phone',
  ],
  '5': [
    'er_father_name', 'er_mother_name', 'er_father_occupation', 'er_mother_occupation',
    'er_home', 'er_street',
    'er_province_id', 'er_district_id', 'er_commune_id', 'er_village_id',
    'er_group', 'er_phone_number', 'er_home_phone',
  ],
  '6': [
    'education_level', 'major_subject', 'education_status', 'university_school',
  ],
  '7': [
    'contract_type', 'contract_start', 'contract_end', 'contract_comment',
  ],
} as const

const today = new Date().toISOString().split('T')[0]

const { defineField, handleSubmit, errors, validateField, setFieldValue, values, isSubmitting } = useForm({
  validationSchema: schema,
  initialValues: {
    first_name: '',
    last_name: '',
    first_name_kh: '',
    last_name_kh: '',
    gender: '',
    date_of_birth: null as unknown as string,
    joined_date: today,
    nssf_id: '',
    department_id: undefined,
    position_id: undefined,
    nationality: '',
    role_name: '',
    office_phone: '',
    phone: '',
    email: '',
    bank_account: '',
    salary: null,
    photo: undefined as unknown as File,
    home_number: '',
    street: '',
    province_id: '',
    district_id: '',
    commune_id: '',
    village_id: '',
    lat: null,
    lng: null,
    perm_home_number: '',
    perm_street: '',
    perm_province_id: '',
    perm_district_id: '',
    perm_commune_id: '',
    perm_village_id: '',
    perm_lat: null,
    perm_lng: null,
    identification_type: '',
    id_card_number: '',
    id_issued_date: null,
    id_issued_by: '',
    id_issued_place: '',
    religion: '',
    marital_status: '',
    blood_group: '',
    spouse_name: '',
    spouse_date_of_birth: null,
    spouse_education: '',
    spouse_occupation: '',
    children_count: 0,
    contact_phone: '',
    er_father_name: '',
    er_mother_name: '',
    er_father_occupation: '',
    er_mother_occupation: '',
    er_home: '',
    er_street: '',
    er_province_id: '',
    er_district_id: '',
    er_commune_id: '',
    er_village_id: '',
    er_group: '',
    er_phone_number: '',
    er_home_phone: '',
    education_level: '',
    major_subject: '',
    education_status: '',
    university_school: '',
    contract_type: '',
    contract_start: today,
    contract_end: null,
    contract_comment: '',
  },
})

const [firstName]    = defineField('first_name')
const [lastName]     = defineField('last_name')
const [firstNameKh]  = defineField('first_name_kh')
const [lastNameKh]   = defineField('last_name_kh')
const [gender]       = defineField('gender')
const [dob]          = defineField('date_of_birth')
const [joinedDate]   = defineField('joined_date')
const [nssfId]       = defineField('nssf_id')
const [departmentId] = defineField('department_id')
const [positionId]   = defineField('position_id')
const [nationality]  = defineField('nationality')
const [roleName]     = defineField('role_name')
const [officePhone]  = defineField('office_phone')
const [phone]        = defineField('phone')
const [email]        = defineField('email')
const [bankAccount]  = defineField('bank_account')
const [salary]       = defineField('salary')
const [homeNumber]   = defineField('home_number')
const [street]       = defineField('street')
const [provinceId]   = defineField('province_id')
const [districtId]   = defineField('district_id')
const [communeId]    = defineField('commune_id')
const [villageId]    = defineField('village_id')
const [lat]          = defineField('lat')
const [lng]          = defineField('lng')
const [permHomeNumber] = defineField('perm_home_number')
const [permStreet]     = defineField('perm_street')
const [permProvinceId] = defineField('perm_province_id')
const [permDistrictId] = defineField('perm_district_id')
const [permCommuneId]  = defineField('perm_commune_id')
const [permVillageId]  = defineField('perm_village_id')
const [permLat]        = defineField('perm_lat')
const [permLng]        = defineField('perm_lng')
const [idType]            = defineField('identification_type')
const [idCardNumber]      = defineField('id_card_number')
const [idIssuedDate]      = defineField('id_issued_date')
const [idIssuedBy]        = defineField('id_issued_by')
const [idIssuedPlace]     = defineField('id_issued_place')
const [religion]          = defineField('religion')
const [maritalStatus]     = defineField('marital_status')
const [bloodGroup]        = defineField('blood_group')
const [spouseName]        = defineField('spouse_name')
const [spouseDob]         = defineField('spouse_date_of_birth')
const [spouseEducation]   = defineField('spouse_education')
const [spouseOccupation]  = defineField('spouse_occupation')
const [childrenCount]     = defineField('children_count')
const [contactPhone]      = defineField('contact_phone')
const [erFatherName]       = defineField('er_father_name')
const [erMotherName]       = defineField('er_mother_name')
const [erFatherOccupation] = defineField('er_father_occupation')
const [erMotherOccupation] = defineField('er_mother_occupation')
const [erHome]             = defineField('er_home')
const [erStreet]           = defineField('er_street')
const [erProvinceId]       = defineField('er_province_id')
const [erDistrictId]       = defineField('er_district_id')
const [erCommuneId]        = defineField('er_commune_id')
const [erVillageId]        = defineField('er_village_id')
const [erGroup]            = defineField('er_group')
const [erPhoneNumber]      = defineField('er_phone_number')
const [erHomePhone]        = defineField('er_home_phone')
const [educationLevel]   = defineField('education_level')
const [majorSubject]     = defineField('major_subject')
const [educationStatus]  = defineField('education_status')
const [universitySchool] = defineField('university_school')
const [contractType]    = defineField('contract_type')
const [contractStart]   = defineField('contract_start')
const [contractEnd]     = defineField('contract_end')
const [contractComment] = defineField('contract_comment')

// ── Position filter (client-side, by selected department) ────
const filteredPositions = computed(() => {
  if (!departmentId.value) return allPositions.value
  return allPositions.value.filter((p) => p.department_id === departmentId.value)
})

// Reset position when department changes if it no longer matches
watch(departmentId, (newDept, oldDept) => {
  if (newDept === oldDept) return
  if (positionId.value && !filteredPositions.value.some((p) => p.id === positionId.value)) {
    setFieldValue('position_id', undefined)
  }
})

// ── Photo upload with preview ────────────────────────────────
const photoPreview = ref<string | null>(null)
const photoFile = ref<File | null>(null)
const photoInput = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

const acceptPhoto = (file: File | null) => {
  if (!file) return
  if (!['image/jpeg', 'image/png'].includes(file.type)) {
    toast.add({ severity: 'warn', summary: t('hrm.employees.wizard.errors.photoInvalidType'), life: 3500 })
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.add({ severity: 'warn', summary: t('hrm.employees.wizard.errors.photoTooLarge'), life: 3500 })
    return
  }
  photoFile.value = file
  setFieldValue('photo', file)
  const reader = new FileReader()
  reader.onload = () => { photoPreview.value = reader.result as string }
  reader.readAsDataURL(file)
}

const onPhotoChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  acceptPhoto(target.files?.[0] ?? null)
}
const onPhotoDrop = (e: DragEvent) => {
  e.preventDefault()
  isDragging.value = false
  acceptPhoto(e.dataTransfer?.files?.[0] ?? null)
}
const clearPhoto = () => {
  photoFile.value = null
  photoPreview.value = null
  setFieldValue('photo', undefined as unknown as File)
  if (photoInput.value) photoInput.value.value = ''
}

// ── Cambodia geography: cascading loaders ────────────────────
const provinces = ref<GeoUnit[]>([])
const districts = ref<GeoUnit[]>([])
const communes  = ref<GeoUnit[]>([])
const villages  = ref<GeoUnit[]>([])
const loadingProvinces = ref(false)
const loadingDistricts = ref(false)
const loadingCommunes  = ref(false)
const loadingVillages  = ref(false)

onMounted(async () => {
  loadingProvinces.value = true
  try { provinces.value = await geo.listProvinces() }
  finally { loadingProvinces.value = false }
})

watch(provinceId, async (val) => {
  setFieldValue('district_id', '')
  setFieldValue('commune_id', '')
  setFieldValue('village_id', '')
  districts.value = []
  communes.value = []
  villages.value = []
  if (!val) return
  loadingDistricts.value = true
  try { districts.value = await geo.listDistricts(val) } finally { loadingDistricts.value = false }
})

watch(districtId, async (val) => {
  setFieldValue('commune_id', '')
  setFieldValue('village_id', '')
  communes.value = []
  villages.value = []
  if (!val) return
  loadingCommunes.value = true
  try { communes.value = await geo.listCommunes(val) } finally { loadingCommunes.value = false }
})

watch(communeId, async (val) => {
  setFieldValue('village_id', '')
  villages.value = []
  if (!val) return
  loadingVillages.value = true
  try { villages.value = await geo.listVillages(val) } finally { loadingVillages.value = false }
})

// ── Permanent address: independent cascading loaders ────────
// Mirrors the current-address state but with its own refs so the two
// dropdown sets don't share options (district list for province "01" can
// legitimately appear in both sides simultaneously).
const permProvinces = ref<GeoUnit[]>([])
const permDistricts = ref<GeoUnit[]>([])
const permCommunes  = ref<GeoUnit[]>([])
const permVillages  = ref<GeoUnit[]>([])
const loadingPermDistricts = ref(false)
const loadingPermCommunes  = ref(false)
const loadingPermVillages  = ref(false)

// Provinces are the same list for both forms — reuse the already-loaded one
// to avoid a duplicate fetch.
watch(provinces, (val) => { permProvinces.value = val }, { immediate: true })

watch(permProvinceId, async (val) => {
  setFieldValue('perm_district_id', '')
  setFieldValue('perm_commune_id', '')
  setFieldValue('perm_village_id', '')
  permDistricts.value = []
  permCommunes.value = []
  permVillages.value = []
  if (!val) return
  loadingPermDistricts.value = true
  try { permDistricts.value = await geo.listDistricts(val) } finally { loadingPermDistricts.value = false }
})

watch(permDistrictId, async (val) => {
  setFieldValue('perm_commune_id', '')
  setFieldValue('perm_village_id', '')
  permCommunes.value = []
  permVillages.value = []
  if (!val) return
  loadingPermCommunes.value = true
  try { permCommunes.value = await geo.listCommunes(val) } finally { loadingPermCommunes.value = false }
})

watch(permCommuneId, async (val) => {
  setFieldValue('perm_village_id', '')
  permVillages.value = []
  if (!val) return
  loadingPermVillages.value = true
  try { permVillages.value = await geo.listVillages(val) } finally { loadingPermVillages.value = false }
})

// ── Emergency / relative address: independent cascading loaders ──
const erProvinces = ref<GeoUnit[]>([])
const erDistricts = ref<GeoUnit[]>([])
const erCommunes  = ref<GeoUnit[]>([])
const erVillages  = ref<GeoUnit[]>([])
const loadingErDistricts = ref(false)
const loadingErCommunes  = ref(false)
const loadingErVillages  = ref(false)

// Reuse the shared province list.
watch(provinces, (val) => { erProvinces.value = val }, { immediate: true })

watch(erProvinceId, async (val) => {
  setFieldValue('er_district_id', '')
  setFieldValue('er_commune_id', '')
  setFieldValue('er_village_id', '')
  erDistricts.value = []
  erCommunes.value = []
  erVillages.value = []
  if (!val) return
  loadingErDistricts.value = true
  try { erDistricts.value = await geo.listDistricts(val) } finally { loadingErDistricts.value = false }
})

watch(erDistrictId, async (val) => {
  setFieldValue('er_commune_id', '')
  setFieldValue('er_village_id', '')
  erCommunes.value = []
  erVillages.value = []
  if (!val) return
  loadingErCommunes.value = true
  try { erCommunes.value = await geo.listCommunes(val) } finally { loadingErCommunes.value = false }
})

watch(erCommuneId, async (val) => {
  setFieldValue('er_village_id', '')
  erVillages.value = []
  if (!val) return
  loadingErVillages.value = true
  try { erVillages.value = await geo.listVillages(val) } finally { loadingErVillages.value = false }
})

// ── "Same as current address" toggle ────────────────────────
// When checked, copies every Step-2 value into Step-3 fields and disables
// the permanent inputs. Watchers on the current-address fields keep them
// in sync as long as the toggle stays on.
const sameAsCurrent = ref(false)

const copyCurrentToPerm = async () => {
  setFieldValue('perm_home_number', homeNumber.value ?? '')
  setFieldValue('perm_street', street.value ?? '')
  setFieldValue('perm_lat', lat.value ?? null)
  setFieldValue('perm_lng', lng.value ?? null)
  // Geo is cascading — set parents first, await the child fetches, then
  // set children. Otherwise the watchers race and reset the children to ''.
  setFieldValue('perm_province_id', provinceId.value ?? '')
  if (provinceId.value) {
    permDistricts.value = await geo.listDistricts(provinceId.value)
  }
  setFieldValue('perm_district_id', districtId.value ?? '')
  if (districtId.value) {
    permCommunes.value = await geo.listCommunes(districtId.value)
  }
  setFieldValue('perm_commune_id', communeId.value ?? '')
  if (communeId.value) {
    permVillages.value = await geo.listVillages(communeId.value)
  }
  setFieldValue('perm_village_id', villageId.value ?? '')
}

watch(sameAsCurrent, (on) => { if (on) void copyCurrentToPerm() })
// Keep perm in lockstep with current while the toggle is on.
watch(
  [homeNumber, street, provinceId, districtId, communeId, villageId, lat, lng],
  () => { if (sameAsCurrent.value) void copyCurrentToPerm() },
)

// ── Google Maps embed (legacy iframe — no API key required) ──
const mapSrc = computed(() => {
  if (lat.value == null || lng.value == null) return null
  return `https://maps.google.com/maps?q=${lat.value},${lng.value}&z=15&output=embed`
})

const permMapSrc = computed(() => {
  if (permLat.value == null || permLng.value == null) return null
  return `https://maps.google.com/maps?q=${permLat.value},${permLng.value}&z=15&output=embed`
})

const useMyLocation = () => {
  if (!navigator.geolocation) {
    toast.add({ severity: 'warn', summary: 'Geolocation not supported', life: 3000 })
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      setFieldValue('lat', Number(pos.coords.latitude.toFixed(6)))
      setFieldValue('lng', Number(pos.coords.longitude.toFixed(6)))
    },
    (err) => toast.add({ severity: 'warn', summary: 'Location error', detail: err.message, life: 3500 }),
    { enableHighAccuracy: true, timeout: 8000 },
  )
}

const usePermMyLocation = () => {
  if (!navigator.geolocation) {
    toast.add({ severity: 'warn', summary: 'Geolocation not supported', life: 3000 })
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      setFieldValue('perm_lat', Number(pos.coords.latitude.toFixed(6)))
      setFieldValue('perm_lng', Number(pos.coords.longitude.toFixed(6)))
    },
    (err) => toast.add({ severity: 'warn', summary: 'Location error', detail: err.message, life: 3500 }),
    { enableHighAccuracy: true, timeout: 8000 },
  )
}

// ── Navigation: validate ONLY the current step's keys ────────
const validateStep = async (step: '1' | '2'): Promise<boolean> => {
  const results = await Promise.all(
    STEP_KEYS[step].map((k) => validateField(k as never) as Promise<{ valid: boolean }>),
  )
  return results.every((r) => r.valid)
}

const goNext = async (next: string) => {
  const current = activeStep.value as '1' | '2'
  const ok = await validateStep(current)
  if (!ok) {
    const stepErr = STEP_KEYS[current].find((k) => errors.value[k as never])
    if (stepErr) {
      toast.add({
        severity: 'warn',
        summary: t('hrm.common.required'),
        detail: `${stepErr}: ${errors.value[stepErr as never]}`,
        life: 3500,
      })
    }
    return
  }
  activeStep.value = next
}

const onSubmit = handleSubmit(
  async () => {
    // TODO: replace with real createEmployee() call once the backend payload
    // shape is agreed. Today we just capture the gathered state so the wizard
    // UX is testable end-to-end.
    toast.add({ severity: 'info', summary: 'Wizard captured (frontend only)', life: 2500 })
    // eslint-disable-next-line no-console
    console.log('Wizard payload:', values, photoFile.value)
  },
  ({ errors: validationErrors }) => {
    // Jump back to the earliest step containing an invalid field so the
    // user doesn't have to hunt across collapsed panels.
    const stepEntries = Object.entries(STEP_KEYS) as [keyof typeof STEP_KEYS, readonly string[]][]
    for (const [step, keys] of stepEntries) {
      const firstBad = keys.find((k) => validationErrors[k as never])
      if (firstBad) {
        activeStep.value = step
        toast.add({
          severity: 'warn',
          summary: t('hrm.common.required'),
          detail: `${firstBad}: ${validationErrors[firstBad as never]}`,
          life: 4000,
        })
        return
      }
    }
  },
)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <NuxtLink to="/hrm/employees">
        <Button icon="pi pi-arrow-left" severity="secondary" rounded text />
      </NuxtLink>
      <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ t('hrm.employees.wizard.title') }}</h1>
        <p class="text-surface-500 text-sm mt-0.5">{{ t('hrm.employees.wizard.subtitle') }}</p>
      </div>
    </div>

    <!-- Wizard -->
    <Card class="shadow-sm border border-surface-200/50 dark:border-surface-800">
      <template #content>
        <Stepper v-model:value="activeStep" linear>
          <StepList>
            <Step value="1">{{ t('hrm.employees.wizard.steps.basic') }}</Step>
            <Step value="2">{{ t('hrm.employees.wizard.steps.address') }}</Step>
            <Step value="3">{{ t('hrm.employees.wizard.steps.permanent') }}</Step>
            <Step value="4">{{ t('hrm.employees.wizard.steps.contact') }}</Step>
            <Step value="5">{{ t('hrm.employees.wizard.steps.emergency') }}</Step>
            <Step value="6">{{ t('hrm.employees.wizard.steps.education') }}</Step>
            <Step value="7">{{ t('hrm.employees.wizard.steps.contract') }}</Step>
          </StepList>

          <StepPanels>
            <!-- ───────── STEP 1 ───────── -->
            <StepPanel v-slot="{ activateCallback: _next }" value="1">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('2')">
                <!-- Identity -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.identity') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.firstNameKh')" required />
                    <InputText v-model="firstNameKh" class="w-full" :invalid="!!errors.first_name_kh" :placeholder="t('hrm.employees.placeholders.firstNameKh')" />
                    <small v-if="errors.first_name_kh" class="text-red-500 text-xs mt-1 block">{{ errors.first_name_kh }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.lastNameKh')" required />
                    <InputText v-model="lastNameKh" class="w-full" :invalid="!!errors.last_name_kh" :placeholder="t('hrm.employees.placeholders.lastNameKh')" />
                    <small v-if="errors.last_name_kh" class="text-red-500 text-xs mt-1 block">{{ errors.last_name_kh }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.employeeId')" required />
                    <InputText
                      :model-value="t('hrm.employees.fields.employeeIdAuto')"
                      disabled readonly
                      class="w-full font-mono"
                    />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.gender')" required />
                    <Select v-model="gender" :options="genders" option-label="label" option-value="value" class="w-full" :invalid="!!errors.gender" :placeholder="t('hrm.employees.placeholders.gender')" />
                    <small v-if="errors.gender" class="text-red-500 text-xs mt-1 block">{{ errors.gender }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.dateOfBirth')" required />
                    <DatePicker v-model="dob as any" date-format="yy-mm-dd" show-icon icon-display="input" class="w-full" :invalid="!!errors.date_of_birth" :placeholder="t('common.placeholders.date')" />
                    <small v-if="errors.date_of_birth" class="text-red-500 text-xs mt-1 block">{{ errors.date_of_birth }}</small>
                  </div>
                </div>

                <!-- Employment -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.employment') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.joinedDate')" />
                    <DatePicker v-model="joinedDate as any" date-format="yy-mm-dd" show-icon icon-display="input" class="w-full" :invalid="!!errors.joined_date" :placeholder="t('common.placeholders.date')" />
                    <small v-if="errors.joined_date" class="text-red-500 text-xs mt-1 block">{{ errors.joined_date }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.nssfId')" required />
                    <InputText v-model="nssfId" class="w-full font-mono" :invalid="!!errors.nssf_id" :placeholder="t('hrm.employees.placeholders.nssfId')" />
                    <small v-if="errors.nssf_id" class="text-red-500 text-xs mt-1 block">{{ errors.nssf_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.nationality')" required />
                    <InputText v-model="nationality" class="w-full" :invalid="!!errors.nationality" :placeholder="t('hrm.employees.placeholders.nationality')" />
                    <small v-if="errors.nationality" class="text-red-500 text-xs mt-1 block">{{ errors.nationality }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.department')" required />
                    <Select v-model="departmentId" :options="departments" option-label="name" option-value="id" filter show-clear class="w-full" :invalid="!!errors.department_id" :placeholder="t('hrm.employees.placeholders.department')" />
                    <small v-if="errors.department_id" class="text-red-500 text-xs mt-1 block">{{ errors.department_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.position')" required />
                    <Select v-model="positionId" :options="filteredPositions" option-label="title" option-value="id" filter show-clear :disabled="!departmentId" class="w-full" :invalid="!!errors.position_id" :placeholder="t('hrm.employees.placeholders.position')" />
                    <small v-if="errors.position_id" class="text-red-500 text-xs mt-1 block">{{ errors.position_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.roleName')" required />
                    <InputText v-model="roleName" class="w-full" :invalid="!!errors.role_name" :placeholder="t('hrm.employees.placeholders.roleName')" />
                    <small v-if="errors.role_name" class="text-red-500 text-xs mt-1 block">{{ errors.role_name }}</small>
                  </div>
                </div>

                <!-- Contact -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.contact') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.officePhone')" />
                    <InputText v-model="officePhone" class="w-full" :placeholder="t('hrm.employees.placeholders.officePhone')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.phone')" required />
                    <InputText v-model="phone" class="w-full" :invalid="!!errors.phone" :placeholder="t('hrm.employees.placeholders.phone')" />
                    <small v-if="errors.phone" class="text-red-500 text-xs mt-1 block">{{ errors.phone }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.email')" required />
                    <InputText v-model="email" type="email" class="w-full" :invalid="!!errors.email" :placeholder="t('hrm.employees.placeholders.email')" />
                    <small v-if="errors.email" class="text-red-500 text-xs mt-1 block">{{ errors.email }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.bankAccount')" />
                    <InputText v-model="bankAccount" class="w-full font-mono" :placeholder="t('hrm.employees.placeholders.bankAccount')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.salary')" />
                    <InputNumber v-model="salary" mode="decimal" :min-fraction-digits="0" class="w-full" :placeholder="t('hrm.employees.placeholders.salary')" />
                  </div>
                </div>

                <!-- Photo -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.photo') }}
                  </span>
                </Divider>

                <div>
                  <FormLabel :label="t('hrm.employees.fields.photo')" required />
                  <div
                    class="border-2 border-dashed rounded-xl p-6 transition-colors cursor-pointer"
                    :class="[
                      isDragging ? 'border-primary-500 bg-primary-50/30 dark:bg-primary-950/20' : 'border-surface-300 dark:border-surface-700 hover:border-primary-400',
                      errors.photo && !photoPreview ? 'border-red-400' : '',
                    ]"
                    @click="photoInput?.click()"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop="onPhotoDrop"
                  >
                    <input
                      ref="photoInput"
                      type="file"
                      accept="image/jpeg,image/png"
                      class="hidden"
                      @change="onPhotoChange"
                    >
                    <div v-if="!photoPreview" class="flex flex-col items-center gap-2 text-center text-surface-500">
                      <i class="pi pi-image text-3xl" />
                      <div class="text-sm">{{ t('hrm.employees.placeholders.photoDrop') }}</div>
                      <div class="text-xs">{{ t('hrm.employees.placeholders.photoHint') }}</div>
                    </div>
                    <div v-else class="flex items-center gap-4">
                      <img :src="photoPreview" alt="Preview" class="w-24 h-24 object-cover rounded-lg ring-2 ring-surface-200 dark:ring-surface-700">
                      <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm truncate">{{ photoFile?.name }}</div>
                        <div class="text-xs text-surface-500">{{ ((photoFile?.size ?? 0) / 1024).toFixed(1) }} KB</div>
                      </div>
                      <Button icon="pi pi-times" text rounded severity="danger" @click.stop="clearPhoto" />
                    </div>
                  </div>
                  <small v-if="errors.photo" class="text-red-500 text-xs mt-1 block">{{ errors.photo }}</small>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <NuxtLink to="/hrm/employees">
                    <Button type="button" :label="t('hrm.employees.wizard.actions.cancel')" severity="secondary" text />
                  </NuxtLink>
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 2 — Current Address ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="2">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('3')">
                <!-- Address details -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.addressDetails') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.homeNumber')" />
                    <InputText v-model="homeNumber" class="w-full" :placeholder="t('hrm.employees.placeholders.homeNumber')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.street')" />
                    <InputText v-model="street" class="w-full" :placeholder="t('hrm.employees.placeholders.street')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.province')" required />
                    <Select
                      v-model="provinceId"
                      :options="provinces"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :loading="loadingProvinces"
                      :disabled="loadingProvinces"
                      class="w-full"
                      :invalid="!!errors.province_id"
                      :placeholder="t('hrm.employees.placeholders.province')"
                    />
                    <small v-if="errors.province_id" class="text-red-500 text-xs mt-1 block">{{ errors.province_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.district')" required />
                    <Select
                      v-model="districtId"
                      :options="districts"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!provinceId || loadingDistricts"
                      :loading="loadingDistricts"
                      class="w-full"
                      :invalid="!!errors.district_id"
                      :placeholder="t('hrm.employees.placeholders.district')"
                    />
                    <small v-if="errors.district_id" class="text-red-500 text-xs mt-1 block">{{ errors.district_id }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.commune')" required />
                    <Select
                      v-model="communeId"
                      :options="communes"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!districtId || loadingCommunes"
                      :loading="loadingCommunes"
                      :virtual-scroller-options="communes.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(communes.length)"
                      class="w-full"
                      :invalid="!!errors.commune_id"
                      :placeholder="t('hrm.employees.placeholders.commune')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingCommunes"><i class="pi pi-spin pi-spinner mr-2" />Loading…</template>
                          <template v-else>No matching commune</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.commune_id" class="text-red-500 text-xs mt-1 block">{{ errors.commune_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.village')" required />
                    <Select
                      v-model="villageId"
                      :options="villages"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!communeId || loadingVillages"
                      :loading="loadingVillages"
                      :virtual-scroller-options="villages.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(villages.length)"
                      class="w-full"
                      :invalid="!!errors.village_id"
                      :placeholder="t('hrm.employees.placeholders.village')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingVillages"><i class="pi pi-spin pi-spinner mr-2" />Loading villages…</template>
                          <template v-else>No matching village</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.village_id" class="text-red-500 text-xs mt-1 block">{{ errors.village_id }}</small>
                  </div>
                </div>

                <!-- Location / map -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.location') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-4 items-end">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.lat')" />
                    <InputNumber v-model="lat" :min-fraction-digits="0" :max-fraction-digits="6" class="w-full" :placeholder="t('hrm.employees.placeholders.lat')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.lng')" />
                    <InputNumber v-model="lng" :min-fraction-digits="0" :max-fraction-digits="6" class="w-full" :placeholder="t('hrm.employees.placeholders.lng')" />
                  </div>
                  <Button type="button" icon="pi pi-map-marker" :label="t('hrm.employees.fields.useMyLocation')" severity="secondary" outlined @click="useMyLocation" />
                </div>

                <div class="rounded-xl overflow-hidden border border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900" style="height: 320px;">
                  <iframe
                    v-if="mapSrc"
                    :src="mapSrc"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                  />
                  <div v-else class="h-full flex flex-col items-center justify-center text-surface-400 gap-2">
                    <i class="pi pi-map text-4xl" />
                    <div class="text-sm">{{ t('hrm.employees.fields.mapHint') }}</div>
                  </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '1'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 3 — Permanent Address ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="3">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('4')">
                <!-- Same-as-current toggle -->
                <div class="flex items-center gap-3 rounded-lg border border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900 px-4 py-3">
                  <Checkbox v-model="sameAsCurrent" input-id="same-as-current" binary />
                  <label for="same-as-current" class="text-sm font-medium cursor-pointer select-none">
                    {{ t('hrm.employees.wizard.steps.sameAsCurrent') }}
                  </label>
                </div>

                <!-- Address details -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.addressDetails') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.homeNumber')" />
                    <InputText v-model="permHomeNumber" :disabled="sameAsCurrent" class="w-full" :placeholder="t('hrm.employees.placeholders.homeNumber')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.street')" />
                    <InputText v-model="permStreet" :disabled="sameAsCurrent" class="w-full" :placeholder="t('hrm.employees.placeholders.street')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.province')" required />
                    <Select
                      v-model="permProvinceId"
                      :options="permProvinces"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="sameAsCurrent || loadingProvinces"
                      :loading="loadingProvinces"
                      class="w-full"
                      :invalid="!!errors.perm_province_id"
                      :placeholder="t('hrm.employees.placeholders.province')"
                    />
                    <small v-if="errors.perm_province_id" class="text-red-500 text-xs mt-1 block">{{ errors.perm_province_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.district')" required />
                    <Select
                      v-model="permDistrictId"
                      :options="permDistricts"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="sameAsCurrent || !permProvinceId || loadingPermDistricts"
                      :loading="loadingPermDistricts"
                      class="w-full"
                      :invalid="!!errors.perm_district_id"
                      :placeholder="t('hrm.employees.placeholders.district')"
                    />
                    <small v-if="errors.perm_district_id" class="text-red-500 text-xs mt-1 block">{{ errors.perm_district_id }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.commune')" required />
                    <Select
                      v-model="permCommuneId"
                      :options="permCommunes"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="sameAsCurrent || !permDistrictId || loadingPermCommunes"
                      :loading="loadingPermCommunes"
                      :virtual-scroller-options="permCommunes.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(permCommunes.length)"
                      class="w-full"
                      :invalid="!!errors.perm_commune_id"
                      :placeholder="t('hrm.employees.placeholders.commune')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingPermCommunes"><i class="pi pi-spin pi-spinner mr-2" />Loading…</template>
                          <template v-else>No matching commune</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.perm_commune_id" class="text-red-500 text-xs mt-1 block">{{ errors.perm_commune_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.village')" required />
                    <Select
                      v-model="permVillageId"
                      :options="permVillages"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="sameAsCurrent || !permCommuneId || loadingPermVillages"
                      :loading="loadingPermVillages"
                      :virtual-scroller-options="permVillages.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(permVillages.length)"
                      class="w-full"
                      :invalid="!!errors.perm_village_id"
                      :placeholder="t('hrm.employees.placeholders.village')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingPermVillages"><i class="pi pi-spin pi-spinner mr-2" />Loading villages…</template>
                          <template v-else>No matching village</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.perm_village_id" class="text-red-500 text-xs mt-1 block">{{ errors.perm_village_id }}</small>
                  </div>
                </div>

                <!-- Location / map -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.location') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-4 items-end">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.lat')" />
                    <InputNumber v-model="permLat" :disabled="sameAsCurrent" :min-fraction-digits="0" :max-fraction-digits="6" class="w-full" :placeholder="t('hrm.employees.placeholders.lat')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.lng')" />
                    <InputNumber v-model="permLng" :disabled="sameAsCurrent" :min-fraction-digits="0" :max-fraction-digits="6" class="w-full" :placeholder="t('hrm.employees.placeholders.lng')" />
                  </div>
                  <Button type="button" icon="pi pi-map-marker" :label="t('hrm.employees.fields.useMyLocation')" severity="secondary" outlined :disabled="sameAsCurrent" @click="usePermMyLocation" />
                </div>

                <div class="rounded-xl overflow-hidden border border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-900" style="height: 320px;">
                  <iframe
                    v-if="permMapSrc"
                    :src="permMapSrc"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                  />
                  <div v-else class="h-full flex flex-col items-center justify-center text-surface-400 gap-2">
                    <i class="pi pi-map text-4xl" />
                    <div class="text-sm">{{ t('hrm.employees.fields.mapHint') }}</div>
                  </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '2'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 4 — Contact Information ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="4">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('5')">
                <!-- Identification -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.identification') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.identificationType')" required />
                    <Select
                      v-model="idType"
                      :options="identificationTypes"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :invalid="!!errors.identification_type"
                      :placeholder="t('hrm.employees.placeholders.identificationType')"
                    />
                    <small v-if="errors.identification_type" class="text-red-500 text-xs mt-1 block">{{ errors.identification_type }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.idCardNumber')" required />
                    <InputText v-model="idCardNumber" class="w-full font-mono" :invalid="!!errors.id_card_number" :placeholder="t('hrm.employees.placeholders.idCardNumber')" />
                    <small v-if="errors.id_card_number" class="text-red-500 text-xs mt-1 block">{{ errors.id_card_number }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.issuedDate')" />
                    <DatePicker v-model="idIssuedDate as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.issuedBy')" />
                    <InputText v-model="idIssuedBy" class="w-full" :placeholder="t('hrm.employees.placeholders.issuedBy')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.issuedPlace')" />
                    <InputText v-model="idIssuedPlace" class="w-full" :placeholder="t('hrm.employees.placeholders.issuedPlace')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.religion')" required />
                    <Select
                      v-model="religion"
                      :options="religions"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :invalid="!!errors.religion"
                      :placeholder="t('hrm.employees.placeholders.religion')"
                    />
                    <small v-if="errors.religion" class="text-red-500 text-xs mt-1 block">{{ errors.religion }}</small>
                  </div>
                </div>

                <!-- Personal -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.personal') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.maritalStatus')" required />
                    <Select
                      v-model="maritalStatus"
                      :options="maritalStatuses"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :invalid="!!errors.marital_status"
                      :placeholder="t('hrm.employees.placeholders.maritalStatus')"
                    />
                    <small v-if="errors.marital_status" class="text-red-500 text-xs mt-1 block">{{ errors.marital_status }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.bloodGroup')" />
                    <Select
                      v-model="bloodGroup"
                      :options="bloodGroups"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :placeholder="t('hrm.employees.placeholders.bloodGroup')"
                    />
                  </div>
                </div>

                <!-- Spouse details -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.spouse') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.spouseName')" />
                    <InputText v-model="spouseName" class="w-full" :placeholder="t('hrm.employees.placeholders.spouseName')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.spouseDateOfBirth')" />
                    <DatePicker v-model="spouseDob as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.spouseEducation')" />
                    <Select
                      v-model="spouseEducation"
                      :options="spouseEducationLevels"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :placeholder="t('hrm.employees.placeholders.spouseEducation')"
                    />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.spouseOccupation')" />
                    <InputText v-model="spouseOccupation" class="w-full" :placeholder="t('hrm.employees.placeholders.spouseOccupation')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.children')" required />
                    <InputNumber v-model="childrenCount" :min="0" :max="50" show-buttons class="w-full" :invalid="!!errors.children_count" />
                    <small v-if="errors.children_count" class="text-red-500 text-xs mt-1 block">{{ errors.children_count }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.contactPhone')" />
                    <InputText v-model="contactPhone" class="w-full" :placeholder="t('hrm.employees.placeholders.contactPhone')" />
                  </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '3'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 5 — Relative / Emergency Contact ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="5">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('6')">
                <!-- Parents -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.parents') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.fatherName')" required />
                    <InputText v-model="erFatherName" class="w-full" :invalid="!!errors.er_father_name" :placeholder="t('hrm.employees.placeholders.fatherName')" />
                    <small v-if="errors.er_father_name" class="text-red-500 text-xs mt-1 block">{{ errors.er_father_name }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.motherName')" required />
                    <InputText v-model="erMotherName" class="w-full" :invalid="!!errors.er_mother_name" :placeholder="t('hrm.employees.placeholders.motherName')" />
                    <small v-if="errors.er_mother_name" class="text-red-500 text-xs mt-1 block">{{ errors.er_mother_name }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.fatherOccupation')" required />
                    <InputText v-model="erFatherOccupation" class="w-full" :invalid="!!errors.er_father_occupation" :placeholder="t('hrm.employees.placeholders.fatherOccupation')" />
                    <small v-if="errors.er_father_occupation" class="text-red-500 text-xs mt-1 block">{{ errors.er_father_occupation }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.motherOccupation')" required />
                    <InputText v-model="erMotherOccupation" class="w-full" :invalid="!!errors.er_mother_occupation" :placeholder="t('hrm.employees.placeholders.motherOccupation')" />
                    <small v-if="errors.er_mother_occupation" class="text-red-500 text-xs mt-1 block">{{ errors.er_mother_occupation }}</small>
                  </div>
                </div>

                <!-- Address -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.emergencyAddress') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.home')" />
                    <InputText v-model="erHome" class="w-full" :placeholder="t('hrm.employees.placeholders.home')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.street')" />
                    <InputText v-model="erStreet" class="w-full" :placeholder="t('hrm.employees.placeholders.street')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.province')" required />
                    <Select
                      v-model="erProvinceId"
                      :options="erProvinces"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :loading="loadingProvinces"
                      :disabled="loadingProvinces"
                      class="w-full"
                      :invalid="!!errors.er_province_id"
                      :placeholder="t('hrm.employees.placeholders.province')"
                    />
                    <small v-if="errors.er_province_id" class="text-red-500 text-xs mt-1 block">{{ errors.er_province_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.district')" required />
                    <Select
                      v-model="erDistrictId"
                      :options="erDistricts"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!erProvinceId || loadingErDistricts"
                      :loading="loadingErDistricts"
                      class="w-full"
                      :invalid="!!errors.er_district_id"
                      :placeholder="t('hrm.employees.placeholders.district')"
                    />
                    <small v-if="errors.er_district_id" class="text-red-500 text-xs mt-1 block">{{ errors.er_district_id }}</small>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.commune')" required />
                    <Select
                      v-model="erCommuneId"
                      :options="erCommunes"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!erDistrictId || loadingErCommunes"
                      :loading="loadingErCommunes"
                      :virtual-scroller-options="erCommunes.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(erCommunes.length)"
                      class="w-full"
                      :invalid="!!errors.er_commune_id"
                      :placeholder="t('hrm.employees.placeholders.commune')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingErCommunes"><i class="pi pi-spin pi-spinner mr-2" />Loading…</template>
                          <template v-else>No matching commune</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.er_commune_id" class="text-red-500 text-xs mt-1 block">{{ errors.er_commune_id }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.village')" required />
                    <Select
                      v-model="erVillageId"
                      :options="erVillages"
                      :option-label="geoLabel" option-value="id"
                      :filter-fields="['name', 'name_kh']"
                      filter show-clear
                      :disabled="!erCommuneId || loadingErVillages"
                      :loading="loadingErVillages"
                      :virtual-scroller-options="erVillages.length > 10 ? { itemSize: 38 } : undefined"
                      :scroll-height="dropdownHeight(erVillages.length)"
                      class="w-full"
                      :invalid="!!errors.er_village_id"
                      :placeholder="t('hrm.employees.placeholders.village')"
                    >
                      <template #empty>
                        <div class="px-3 py-2 text-sm text-surface-500">
                          <template v-if="loadingErVillages"><i class="pi pi-spin pi-spinner mr-2" />Loading villages…</template>
                          <template v-else>No matching village</template>
                        </div>
                      </template>
                    </Select>
                    <small v-if="errors.er_village_id" class="text-red-500 text-xs mt-1 block">{{ errors.er_village_id }}</small>
                  </div>
                </div>

                <!-- Contact -->
                <Divider align="left">
                  <span class="text-xs font-semibold tracking-wider uppercase text-surface-400">
                    {{ t('hrm.employees.sections.emergencyContact') }}
                  </span>
                </Divider>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.group')" />
                    <InputText v-model="erGroup" class="w-full" :placeholder="t('hrm.employees.placeholders.group')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.phoneNumber')" required />
                    <InputText v-model="erPhoneNumber" class="w-full" :invalid="!!errors.er_phone_number" :placeholder="t('hrm.employees.placeholders.phoneNumber')" />
                    <small v-if="errors.er_phone_number" class="text-red-500 text-xs mt-1 block">{{ errors.er_phone_number }}</small>
                  </div>
                </div>

                <div>
                  <FormLabel :label="t('hrm.employees.fields.homePhone')" />
                  <InputText v-model="erHomePhone" class="w-full" :placeholder="t('hrm.employees.placeholders.homePhone')" />
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '4'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 6 — Education Detail ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="6">
              <form class="space-y-6 pt-4" @submit.prevent="goNext('7')">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.educationLevel')" />
                    <Select
                      v-model="educationLevel"
                      :options="degreeLevels"
                      option-label="label" option-value="value"
                      show-clear
                      class="w-full"
                      :placeholder="t('hrm.employees.placeholders.educationLevel')"
                    />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.majorSubject')" />
                    <InputText v-model="majorSubject" class="w-full" :placeholder="t('hrm.employees.placeholders.majorSubject')" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.educationStatus')" />
                    <InputText v-model="educationStatus" class="w-full" :placeholder="t('hrm.employees.placeholders.educationStatus')" />
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.universitySchool')" />
                    <InputText v-model="universitySchool" class="w-full" :placeholder="t('hrm.employees.placeholders.universitySchool')" />
                  </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '5'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.next')" icon="pi pi-arrow-right" icon-pos="right" />
                </div>
              </form>
            </StepPanel>

            <!-- ───────── STEP 7 — Employee Contract (FINAL) ───────── -->
            <StepPanel v-slot="{ activateCallback: _ }" value="7">
              <form class="space-y-6 pt-4" @submit.prevent="onSubmit">
                <div>
                  <FormLabel :label="t('hrm.employees.fields.contractType')" required />
                  <Select
                    v-model="contractType"
                    :options="contractTypes"
                    option-label="label" option-value="value"
                    show-clear
                    class="w-full"
                    :invalid="!!errors.contract_type"
                    :placeholder="t('hrm.employees.placeholders.contractType')"
                  />
                  <small v-if="errors.contract_type" class="text-red-500 text-xs mt-1 block">{{ errors.contract_type }}</small>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.startDate')" required />
                    <DatePicker v-model="contractStart as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
                    <small v-if="errors.contract_start" class="text-red-500 text-xs mt-1 block">{{ errors.contract_start }}</small>
                  </div>
                  <div>
                    <FormLabel :label="t('hrm.employees.fields.endDate')" required />
                    <DatePicker v-model="contractEnd as any" date-format="yy-mm-dd" class="w-full" :placeholder="t('common.placeholders.date')" />
                    <small v-if="errors.contract_end" class="text-red-500 text-xs mt-1 block">{{ errors.contract_end }}</small>
                  </div>
                </div>

                <div>
                  <FormLabel :label="t('hrm.employees.fields.comment')" />
                  <Textarea v-model="contractComment" rows="3" auto-resize class="w-full" :placeholder="t('hrm.employees.placeholders.comment')" />
                </div>

                <!-- Footer: Submit (creates employee) instead of Next -->
                <div class="flex justify-between items-center pt-4 border-t border-surface-200 dark:border-surface-800">
                  <Button type="button" :label="t('hrm.employees.wizard.actions.back')" icon="pi pi-arrow-left" severity="secondary" text @click="activeStep = '6'" />
                  <Button type="submit" :label="t('hrm.employees.wizard.actions.submit')" icon="pi pi-check" :loading="isSubmitting" />
                </div>
              </form>
            </StepPanel>
          </StepPanels>
        </Stepper>
      </template>
    </Card>
  </div>
</template>
