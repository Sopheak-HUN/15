<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'auth', middleware: 'guest' })

const iam = useIamApi()
const auth = useAuthStore()
const toast = useToast()
const router = useRouter()
const { t } = useI18n()
const loading = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  name: z.string().min(2, t('auth.onboard.errors.nameRequired')).max(120),
  handle: z.string()
    .min(2, t('auth.onboard.errors.handleMin'))
    .max(40)
    .regex(/^[a-z0-9][a-z0-9-]*$/, t('auth.onboard.errors.handlePattern')),
})))

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: { name: '', handle: '' },
})
const [name, nameAttrs]     = defineField('name')
const [handle, handleAttrs] = defineField('handle')

const onSubmit = handleSubmit(async (values) => {
  loading.value = true
  try {
    const res = await iam.onboardTenant(values)
    auth.setTenant(res.tenant.handle)
    toast.add({
      severity: 'success',
      summary: t('auth.onboard.tenantCreated'),
      detail: t('auth.onboard.seedHint', { handle: res.tenant.handle }),
      life: 6000,
    })
    await router.push({ path: '/auth/login', query: { tenant: res.tenant.handle } })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: t('auth.onboard.failed'),
      detail: data?.message ?? t('auth.onboard.failedDetail'),
      life: 6000,
    })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Card class="shadow-2xl">
    <template #title>
      <div class="text-xl font-semibold">{{ t('auth.onboard.title') }}</div>
      <p class="text-sm font-normal text-surface-500 mt-1">
        {{ t('auth.onboard.haveAccount') }}
        <NuxtLink to="/auth/login" class="text-primary-600 hover:underline">{{ t('auth.onboard.signInLink') }}</NuxtLink>
      </p>
    </template>

    <template #content>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="name" class="block text-sm font-medium mb-1">{{ t('auth.onboard.companyName') }}</label>
          <InputText
            id="name"
            v-model="name"
            v-bind="nameAttrs"
            :placeholder="t('auth.onboard.companyPlaceholder')"
            class="w-full"
            :invalid="!!errors.name"
            autocomplete="organization"
          />
          <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
        </div>

        <div>
          <label for="handle" class="block text-sm font-medium mb-1">{{ t('auth.onboard.workspaceHandle') }}</label>
          <InputGroup>
            <InputGroupAddon class="!text-xs text-surface-500">https://</InputGroupAddon>
            <InputText
              id="handle"
              v-model="handle"
              v-bind="handleAttrs"
              placeholder="acme"
              class="w-full"
              :invalid="!!errors.handle"
              autocomplete="off"
            />
          </InputGroup>
          <small class="text-surface-500 text-xs">
            <i18n-t keypath="auth.onboard.handleHelp" tag="span">
              <template #header><code>tenant</code></template>
            </i18n-t>
          </small>
          <div v-if="errors.handle" class="text-red-600 text-xs mt-1">{{ errors.handle }}</div>
        </div>

        <Button type="submit" :label="t('auth.onboard.create')" icon="pi pi-check" :loading="loading" class="w-full" />
      </form>
    </template>
  </Card>
</template>
