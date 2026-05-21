<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { SsoProvider } from '~/types/iam'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const { data: provData, refresh, pending } = await useAsyncData('sso-providers', () => iam.listSsoProviders())
const providers = computed<SsoProvider[]>(() => provData.value?.data ?? [])

const dialogOpen = ref(false)
const editing    = ref<SsoProvider | null>(null)
const saving     = ref(false)

const schema = toTypedSchema(z.object({
  name:           z.string().min(2).max(120),
  protocol:       z.enum(['oidc', 'saml']),
  issuer:         z.string().max(255).optional().or(z.literal('')),
  client_id:      z.string().max(255).optional().or(z.literal('')),
  client_secret:  z.string().optional().or(z.literal('')),
  discovery_url:  z.string().url().optional().or(z.literal('')),
  redirect_uri:   z.string().url().optional().or(z.literal('')),
  scopes_csv:     z.string().optional().or(z.literal('')),
  is_active:      z.boolean(),
}))

const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    name: '', protocol: 'oidc',
    issuer: '', client_id: '', client_secret: '',
    discovery_url: '', redirect_uri: '',
    scopes_csv: 'openid,profile,email',
    is_active: true,
  },
})
const [name, nameAttrs]                 = defineField('name')
const [protocol, protocolAttrs]         = defineField('protocol')
const [issuer, issuerAttrs]             = defineField('issuer')
const [clientId, clientIdAttrs]         = defineField('client_id')
const [clientSecret, clientSecretAttrs] = defineField('client_secret')
const [discoveryUrl, discoveryAttrs]    = defineField('discovery_url')
const [redirectUri, redirectAttrs]      = defineField('redirect_uri')
const [scopesCsv, scopesAttrs]          = defineField('scopes_csv')
const [isActive, isActiveAttrs]         = defineField('is_active')

const protocolOptions = computed(() => [
  { label: t('sso.protocols.oidc'), value: 'oidc' },
  { label: t('sso.protocols.saml'), value: 'saml' },
])

const openCreate = () => {
  editing.value = null
  resetForm({ values: {
    name: '', protocol: 'oidc', issuer: '', client_id: '', client_secret: '',
    discovery_url: '', redirect_uri: '', scopes_csv: 'openid,profile,email', is_active: true,
  } })
  dialogOpen.value = true
}

const openEdit = (p: SsoProvider) => {
  editing.value = p
  setValues({
    name: p.name,
    protocol: p.protocol,
    issuer: p.issuer ?? '',
    client_id: p.client_id ?? '',
    client_secret: '',
    discovery_url: p.discovery_url ?? '',
    redirect_uri: p.redirect_uri ?? '',
    scopes_csv: (p.scopes ?? []).join(','),
    is_active: p.is_active,
  })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = {
      name: values.name,
      protocol: values.protocol,
      issuer: values.issuer || null,
      client_id: values.client_id || null,
      // Empty secret on edit = "leave unchanged" — only send when filled.
      client_secret: values.client_secret ? values.client_secret : undefined,
      discovery_url: values.discovery_url || null,
      redirect_uri: values.redirect_uri || null,
      scopes: values.scopes_csv ? values.scopes_csv.split(',').map((s) => s.trim()).filter(Boolean) : null,
      is_active: values.is_active,
    }
    if (editing.value) {
      await iam.updateSsoProvider(editing.value.id, payload)
      toast.add({ severity: 'success', summary: t('sso.toast.updated'), life: 2000 })
    } else {
      await iam.createSsoProvider(payload)
      toast.add({ severity: 'success', summary: t('sso.toast.created'), life: 2000 })
    }
    dialogOpen.value = false
    await refresh()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('sso.toast.saveFailed'), detail: data?.message, life: 5000 })
  } finally {
    saving.value = false
  }
})

const onDelete = (p: SsoProvider) => {
  confirm.require({
    message: t('sso.confirmDelete', { name: p.name }),
    header: t('sso.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await iam.deleteSsoProvider(p.id)
      toast.add({ severity: 'success', summary: t('sso.toast.removed'), life: 2000 })
      await refresh()
    },
  })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('sso.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('sso.subtitle') }}</p>
      </div>
      <Button :label="t('sso.add')" icon="pi pi-plus" @click="openCreate" />
    </div>

    <Card>
      <template #content>
        <DataTable :value="providers" :loading="pending" data-key="id" striped-rows class="text-sm">
          <template #empty>
            <div class="py-10 text-center text-surface-500">
              {{ t('sso.empty') }}
            </div>
          </template>

          <Column field="name" :header="t('sso.columns.name')" sortable />
          <Column :header="t('sso.columns.protocol')">
            <template #body="{ data }">
              <Tag :value="data.protocol.toUpperCase()" :severity="data.protocol === 'oidc' ? 'info' : 'warn'" />
            </template>
          </Column>
          <Column :header="t('sso.columns.issuer')">
            <template #body="{ data }">
              <span class="font-mono text-xs text-surface-500">{{ data.issuer || t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('sso.columns.active')">
            <template #body="{ data }">
              <Tag
                :value="data.is_active ? t('sso.status.enabled') : t('sso.status.disabled')"
                :severity="data.is_active ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column header="" body-class="text-right" :style="{ width: '140px' }">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button icon="pi pi-trash"  text rounded severity="danger"    :aria-label="t('common.delete')" @click="onDelete(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog
      v-model:visible="dialogOpen"
      modal
      :header="editing ? t('sso.dialog.editTitle', { name: editing.name }) : t('sso.dialog.addTitle')"
      :style="{ width: '42rem' }"
    >
      <form class="space-y-4" @submit.prevent="onSave">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">{{ t('sso.fields.displayName') }}</label>
            <InputText v-model="name" v-bind="nameAttrs" class="w-full" :invalid="!!errors.name" />
            <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ t('sso.fields.protocol') }}</label>
            <Select v-model="protocol" v-bind="protocolAttrs" :options="protocolOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">{{ t('sso.fields.issuer') }}</label>
          <InputText v-model="issuer" v-bind="issuerAttrs" placeholder="https://accounts.google.com" class="w-full font-mono text-xs" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">{{ t('sso.fields.clientId') }}</label>
            <InputText v-model="clientId" v-bind="clientIdAttrs" class="w-full font-mono text-xs" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">
              {{ t('sso.fields.clientSecret') }}
              <span v-if="editing" class="text-xs text-surface-400 font-normal">{{ t('sso.fields.clientSecretKeep') }}</span>
            </label>
            <Password v-model="clientSecret" v-bind="clientSecretAttrs" :feedback="false" toggle-mask input-class="w-full font-mono text-xs" class="w-full" />
          </div>
        </div>

        <div v-if="protocol === 'oidc'">
          <label class="block text-sm font-medium mb-1">{{ t('sso.fields.discoveryUrl') }}</label>
          <InputText v-model="discoveryUrl" v-bind="discoveryAttrs" placeholder="https://idp.example.com/.well-known/openid-configuration" class="w-full font-mono text-xs" />
          <small v-if="errors.discovery_url" class="text-red-600">{{ errors.discovery_url }}</small>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">{{ t('sso.fields.redirectUri') }}</label>
          <InputText v-model="redirectUri" v-bind="redirectAttrs" placeholder="https://app.example.com/auth/sso/callback" class="w-full font-mono text-xs" />
        </div>

        <div v-if="protocol === 'oidc'">
          <label class="block text-sm font-medium mb-1">{{ t('sso.fields.scopes') }}</label>
          <InputText v-model="scopesCsv" v-bind="scopesAttrs" class="w-full font-mono text-xs" />
        </div>

        <div class="flex items-center gap-2">
          <ToggleSwitch v-model="isActive" v-bind="isActiveAttrs" input-id="sso-active" />
          <label for="sso-active" class="text-sm">{{ t('sso.fields.active') }}</label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
