<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const setupLoading = ref(false)
const verifyLoading = ref(false)
const setupDone = ref(false)

const schema = toTypedSchema(z.object({
  code: z.string().length(6, 'Code must be 6 digits').regex(/^\d+$/, 'Digits only'),
}))
const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: { code: '' },
})
const [code, codeAttrs] = defineField('code')

const startSetup = async () => {
  setupLoading.value = true
  try {
    await iam.setupMfa()
    setupDone.value = true
    toast.add({
      severity: 'info',
      summary: 'MFA setup initialized',
      detail: 'Backend currently returns a stub — verify any 6-digit code to complete the demo flow.',
      life: 6000,
    })
  } catch {
    toast.add({ severity: 'error', summary: 'MFA setup failed', life: 4000 })
  } finally {
    setupLoading.value = false
  }
}

const onVerify = handleSubmit(async (values) => {
  verifyLoading.value = true
  try {
    await iam.verifyMfa({ code: values.code })
    toast.add({ severity: 'success', summary: 'MFA verified', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Verification failed', life: 4000 })
  } finally {
    verifyLoading.value = false
  }
})
</script>

<template>
  <div class="max-w-xl space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Two-Factor Authentication</h1>
      <p class="text-surface-500 mt-1">
        Strengthen your account with a time-based one-time password.
      </p>
    </div>

    <Message v-if="!setupDone" severity="info" :closable="false">
      The backend MFA endpoints are currently stubs (TOTP not yet wired). This page exercises the full flow once they ship.
    </Message>

    <Card>
      <template #title>Step 1 &mdash; Generate a setup token</template>
      <template #content>
        <p class="text-sm text-surface-500 mb-4">
          Calls <code>POST /api/auth/mfa/setup</code>. In production this will return a TOTP secret and provisioning URI for your authenticator app.
        </p>
        <Button
          label="Start MFA setup"
          icon="pi pi-shield"
          :loading="setupLoading"
          :disabled="setupDone"
          @click="startSetup"
        />
        <Tag v-if="setupDone" severity="success" value="Setup initialized" class="ml-2" />
      </template>
    </Card>

    <Card>
      <template #title>Step 2 &mdash; Enter the 6-digit code</template>
      <template #content>
        <form class="space-y-4" @submit.prevent="onVerify">
          <InputOtp v-model="code" v-bind="codeAttrs" :length="6" integer-only />
          <small v-if="errors.code" class="text-red-600 block">{{ errors.code }}</small>
          <Button type="submit" label="Verify" icon="pi pi-check" :loading="verifyLoading" :disabled="!setupDone" />
        </form>
      </template>
    </Card>
  </div>
</template>
