<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Employee, EmployeeDocument, EmployeeNote, LeaveBalance } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const employeeId = route.params.id as string

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const tab = ref<'notes' | 'documents' | 'leave'>('notes')

const { data: empData, pending: empPending } = await useAsyncData(
  `hrm-employee-${employeeId}`,
  () => hrm.showEmployee(employeeId),
)
const employee = computed<Employee | null>(() => empData.value?.data ?? null)

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

const openDocCreate = () => { resetDoc(); docDialog.value = true }
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
</script>

<template>
  <div class="space-y-6">
    <NuxtLink to="/hrm/employees" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1">
      <i class="pi pi-arrow-left text-xs" /> {{ t('hrm.employees.title') }}
    </NuxtLink>

    <div v-if="empPending" class="py-16 text-center"><ProgressSpinner /></div>
    <div v-else-if="!employee" class="py-16 text-center text-surface-500">Not found.</div>
    <template v-else>
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">{{ employee.first_name }} {{ employee.last_name }}</h1>
            <Tag :value="employee.employee_id" />
            <Tag :value="employee.status" :severity="employee.status === 'active' ? 'success' : 'danger'" />
          </div>
          <p class="text-surface-500 mt-1">
            <span>{{ employee.email }}</span>
            <span v-if="employee.department"> · {{ employee.department.name }}</span>
            <span v-if="employee.position"> · {{ employee.position.title }}</span>
          </p>
        </div>
      </div>

      <Tabs v-model:value="tab">
        <TabList>
          <Tab value="notes">{{ t('hrm.notes.tabs.notes') }}</Tab>
          <Tab value="documents">{{ t('hrm.notes.tabs.documents') }}</Tab>
          <Tab value="leave">{{ t('nav.leave') }}</Tab>
        </TabList>
        <TabPanels>
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
                  <Column :header="t('hrm.notes.documents.fields.filePath')">
                    <template #body="{ data }">
                      <code class="font-mono text-xs text-surface-500">{{ data.file_path }}</code>
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
        <div>
          <FormLabel :label="t('hrm.notes.documents.fields.filePath')" required />
          <InputText v-model="dFilePath" class="w-full font-mono text-xs" :invalid="!!dErrors.file_path" :placeholder="t('hrm.notes.placeholders.filePath')" />
          <small class="text-surface-500 text-xs">{{ t('hrm.notes.documents.fields.filePathHint') }}</small>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <FormLabel :label="t('hrm.notes.documents.fields.mimeType')" />
            <InputText v-model="dMime" class="w-full font-mono text-xs" :placeholder="t('hrm.notes.placeholders.mimeType')" />
          </div>
          <div>
            <FormLabel :label="t('hrm.notes.documents.fields.sizeBytes')" />
            <InputNumber v-model="dSize" class="w-full" :placeholder="t('hrm.notes.placeholders.sizeBytes')" />
          </div>
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
  </div>
</template>
