<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const { t } = useI18n()
const setupLoading = ref(false)
const verifyLoading = ref(false)
const setupData = ref<{ secret: string; provisioning_uri: string } | null>(null)

const schema = computed(() => toTypedSchema(z.object({
  code: z.string().length(6, t('auth.mfa.errors.codeLength')).regex(/^\d+$/, t('auth.mfa.errors.codeDigits')),
})))
const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: { code: '' },
})
const [code, codeAttrs] = defineField('code')

// Render the provisioning URI as a QR code via the public chart endpoint
// (no extra deps). Users can also paste the secret manually.
const qrUrl = computed(() =>
  setupData.value
    ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(setupData.value.provisioning_uri)}`
    : null,
)

const startSetup = async () => {
  setupLoading.value = true
  try {
    const res = await iam.setupMfa() as unknown as { success: boolean; data: { secret: string; provisioning_uri: string } }
    setupData.value = res.data
    toast.add({
      severity: 'success',
      summary: t('auth.mfa.secretGenerated'),
      detail: t('auth.mfa.secretGeneratedDetail'),
      life: 4000,
    })
  } catch {
    toast.add({ severity: 'error', summary: t('auth.mfa.setupFailed'), life: 4000 })
  } finally {
    setupLoading.value = false
  }
}

const onVerify = handleSubmit(async (values) => {
  verifyLoading.value = true
  try {
    await iam.verifyMfa({ code: values.code })
    toast.add({ severity: 'success', summary: t('auth.mfa.enabled'), life: 3000 })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('auth.mfa.verifyFailed'), detail: data?.message, life: 4000 })
  } finally {
    verifyLoading.value = false
  }
})
</script>

<template>
  <div class="max-w-xl space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ t('auth.mfa.title') }}</h1>
      <p class="text-surface-500 mt-1">{{ t('auth.mfa.subtitle') }}</p>
    </div>

    <Card>
      <template #title>{{ t('auth.mfa.step1Title') }}</template>
      <template #content>
        <p class="text-sm text-surface-500 mb-4">
          <i18n-t keypath="auth.mfa.step1Body" tag="span">
            <template #endpoint><code>POST /api/auth/mfa/setup</code></template>
          </i18n-t>
        </p>
        <Button
          v-if="!setupData"
          :label="t('auth.mfa.start')"
          icon="pi pi-shield"
          :loading="setupLoading"
          @click="startSetup"
        />
        <div v-else class="flex items-center gap-6">
          <img :src="qrUrl ?? ''" :alt="t('auth.mfa.qrAlt')" class="rounded-lg border border-surface-200 dark:border-surface-800" />
          <div class="space-y-2">
            <div class="text-xs uppercase text-surface-500">{{ t('auth.mfa.manualEntry') }}</div>
            <code class="text-sm font-mono break-all">{{ setupData.secret }}</code>
            <Tag severity="success" :value="t('auth.mfa.secretStored')" />
          </div>
        </div>
      </template>
    </Card>

    <Card>
      <template #title>{{ t('auth.mfa.step2Title') }}</template>
      <template #content>
        <form class="space-y-4" @submit.prevent="onVerify">
          <InputOtp v-model="code" v-bind="codeAttrs" :length="6" integer-only />
          <small v-if="errors.code" class="text-red-600 block">{{ errors.code }}</small>
          <Button type="submit" :label="t('auth.mfa.verifyEnable')" icon="pi pi-check" :loading="verifyLoading" :disabled="!setupData" />
        </form>
      </template>
    </Card>
  </div>
</template>
