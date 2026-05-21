<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Suggestion } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

const hrm = useHrmApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const page = ref(1)
const statusFilter = ref<string | null>(null)
const categoryFilter = ref<string | null>(null)

const { data, refresh, pending } = await useAsyncData(
  'hrm-suggestions',
  () => hrm.listSuggestions({
    status: statusFilter.value || undefined,
    category: categoryFilter.value || undefined,
    page: page.value, per_page: 25,
  }),
  { watch: [page, statusFilter, categoryFilter] },
)
const rows = computed<Suggestion[]>(() => data.value?.data?.data ?? [])
const meta = computed(() => data.value?.data)

const statuses   = ['new', 'acknowledged', 'actioned', 'dismissed']
const categories = ['general', 'idea', 'concern', 'whistleblower']

const dialogOpen = ref(false)
const saving     = ref(false)
const schema = toTypedSchema(z.object({
  title: z.string().min(2).max(200),
  body: z.string().min(2),
  category: z.string().max(64),
  is_anonymous: z.boolean(),
}))
const { defineField, handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { title: '', body: '', category: 'general', is_anonymous: false },
})
const [title] = defineField('title')
const [body] = defineField('body')
const [category] = defineField('category')
const [anon] = defineField('is_anonymous')

const openCreate = () => { resetForm(); dialogOpen.value = true }
const onSubmit = handleSubmit(async (values) => {
  saving.value = true
  try {
    await hrm.submitSuggestion(values)
    toast.add({ severity: 'success', summary: t('hrm.suggestions.toast.submitted'), life: 2000 })
    dialogOpen.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    saving.value = false
  }
})

// Transition dialog
const transDialog = ref(false)
const transTarget = ref<Suggestion | null>(null)
const transAction = ref<'acknowledge' | 'action' | 'dismiss'>('acknowledge')
const transResponse = ref('')
const openTransition = (row: Suggestion, action: 'acknowledge' | 'action' | 'dismiss') => {
  transTarget.value = row
  transAction.value = action
  transResponse.value = ''
  transDialog.value = true
}
const onTransition = async () => {
  if (!transTarget.value) return
  try {
    await hrm.transitionSuggestion(transTarget.value.id, {
      action: transAction.value,
      response: transResponse.value || undefined,
    })
    toast.add({ severity: 'success', summary: t('hrm.suggestions.toast.transitioned'), life: 2000 })
    transDialog.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('hrm.common.saveFailed'), detail: data?.message, life: 5000 })
  }
}

const onDelete = (row: Suggestion) => {
  confirm.require({
    message: t('hrm.common.confirmDelete', { name: row.title }),
    header: t('hrm.common.confirmHeader'),
    acceptClass: 'p-button-danger',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      await hrm.deleteSuggestion(row.id)
      toast.add({ severity: 'success', summary: t('hrm.suggestions.toast.deleted'), life: 2000 })
      await refresh()
    },
  })
}

const statusSeverity = (s: string) => {
  if (s === 'actioned') return 'success'
  if (s === 'dismissed') return 'secondary'
  if (s === 'acknowledged') return 'info'
  return 'warn'
}

const detailOpen = ref(false)
const detail = ref<Suggestion | null>(null)
const openDetail = (row: Suggestion) => { detail.value = row; detailOpen.value = true }
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('hrm.suggestions.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('hrm.suggestions.subtitle') }}</p>
      </div>
      <Button :label="t('hrm.suggestions.new')" icon="pi pi-plus" @click="openCreate" />
    </div>

    <Card>
      <template #content>
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <Select v-model="statusFilter" :options="statuses" :placeholder="t('hrm.common.status')" show-clear class="w-44">
            <template #option="{ option }">{{ t(`hrm.suggestions.statuses.${option}`) }}</template>
            <template #value="{ value }">{{ value ? t(`hrm.suggestions.statuses.${value}`) : '' }}</template>
          </Select>
          <Select v-model="categoryFilter" :options="categories" :placeholder="t('hrm.suggestions.form.category')" show-clear class="w-44" />
        </div>

        <DataTable :value="rows" :loading="pending" data-key="id" striped-rows class="text-sm">
          <template #empty>
            <div class="py-10 text-center text-surface-500">{{ t('hrm.suggestions.empty') }}</div>
          </template>
          <Column :header="t('hrm.suggestions.columns.title')">
            <template #body="{ data }">
              <div class="flex items-start gap-2">
                <Button :label="data.title" text size="small" class="!p-0 !justify-start !text-left" @click="openDetail(data)" />
                <Chip v-if="data.is_anonymous" :label="t('hrm.suggestions.anonymous')" icon="pi pi-eye-slash" class="!text-xs" />
              </div>
            </template>
          </Column>
          <Column field="category" :header="t('hrm.suggestions.columns.category')">
            <template #body="{ data }"><Tag :value="data.category" /></template>
          </Column>
          <Column :header="t('hrm.suggestions.columns.status')">
            <template #body="{ data }">
              <Tag :value="t(`hrm.suggestions.statuses.${data.status}`)" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="t('hrm.suggestions.columns.submitted')">
            <template #body="{ data }">
              <span class="text-xs text-surface-500">{{ data.created_at }}</span>
            </template>
          </Column>
          <Column header="" body-class="text-right !py-2" :style="{ width: '240px' }">
            <template #body="{ data }">
              <Button v-if="data.status === 'new'" icon="pi pi-thumbs-up" text rounded severity="info" :aria-label="t('hrm.suggestions.actions.acknowledge')" @click="openTransition(data, 'acknowledge')" />
              <Button v-if="data.status === 'acknowledged'" icon="pi pi-check" text rounded severity="success" :aria-label="t('hrm.suggestions.actions.action')" @click="openTransition(data, 'action')" />
              <Button v-if="data.status === 'new' || data.status === 'acknowledged'" icon="pi pi-times" text rounded severity="secondary" :aria-label="t('hrm.suggestions.actions.dismiss')" @click="openTransition(data, 'dismiss')" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="onDelete(data)" />
            </template>
          </Column>
        </DataTable>

        <Paginator v-if="meta && meta.last_page > 1" :rows="meta.per_page" :total-records="meta.total" :first="(meta.current_page - 1) * meta.per_page" @page="(e) => page = e.page + 1" />
      </template>
    </Card>

    <!-- New suggestion dialog -->
    <Dialog v-model:visible="dialogOpen" modal :header="t('hrm.suggestions.new')" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <FormLabel :label="t('hrm.suggestions.form.title')" required />
          <InputText v-model="title" class="w-full" :invalid="!!errors.title" :placeholder="t('hrm.suggestions.placeholders.title')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.suggestions.form.category')" required />
          <Select v-model="category" :options="categories" class="w-full" :placeholder="t('hrm.suggestions.placeholders.category')" />
        </div>
        <div>
          <FormLabel :label="t('hrm.suggestions.form.body')" required />
          <Textarea v-model="body" rows="6" class="w-full" :invalid="!!errors.body" :placeholder="t('hrm.suggestions.placeholders.body')" />
        </div>
        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="anon" input-id="sug-anon" />
          <label for="sug-anon" class="text-sm">{{ t('hrm.suggestions.form.isAnonymous') }}</label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Transition dialog -->
    <Dialog
      v-model:visible="transDialog"
      modal
      :header="t(`hrm.suggestions.actions.${transAction === 'action' ? 'action' : transAction}`)"
      :style="{ width: '28rem' }"
    >
      <div>
        <FormLabel :label="t('hrm.suggestions.actions.response')" />
        <Textarea v-model="transResponse" rows="4" class="w-full" :placeholder="t('hrm.suggestions.placeholders.response')" />
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="transDialog = false" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="onTransition" />
      </template>
    </Dialog>

    <!-- Detail dialog -->
    <Dialog v-model:visible="detailOpen" modal :header="detail?.title" :style="{ width: '36rem' }">
      <div v-if="detail" class="space-y-3">
        <div class="flex items-center gap-2">
          <Tag :value="detail.category" />
          <Tag :value="t(`hrm.suggestions.statuses.${detail.status}`)" :severity="statusSeverity(detail.status)" />
          <Chip v-if="detail.is_anonymous" :label="t('hrm.suggestions.anonymous')" icon="pi pi-eye-slash" />
        </div>
        <div class="whitespace-pre-wrap text-sm">{{ detail.body }}</div>
        <Divider v-if="detail.response" />
        <div v-if="detail.response" class="text-sm text-surface-500">
          <div class="text-xs uppercase font-medium mb-1">{{ t('hrm.suggestions.actions.response') }}</div>
          <div class="whitespace-pre-wrap">{{ detail.response }}</div>
        </div>
      </div>
    </Dialog>
  </div>
</template>
