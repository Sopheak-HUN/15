<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'auth', middleware: 'guest' })

const iam = useIamApi()
const auth = useAuthStore()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const loading = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  tenant: z.string().min(1, t('auth.login.errors.tenantRequired')),
  email: z.string().email(t('auth.login.errors.emailInvalid')),
  password: z.string().min(1, t('auth.login.errors.passwordRequired')),
})))

const { defineField, handleSubmit, errors, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    tenant: (route.query.tenant as string) || auth.tenant || '',
    email: '',
    password: '',
  },
})
const [tenant, tenantAttrs]     = defineField('tenant')
const [email, emailAttrs]       = defineField('email')
const [password, passwordAttrs] = defineField('password')

const fillDemo = () => {
  setValues({ tenant: tenant.value || 'acme', email: 'admin@erp.local', password: 'Admin@1234!' })
}

const onSubmit = handleSubmit(async (values) => {
  loading.value = true
  auth.setTenant(values.tenant)
  try {
    const res = await iam.login({ email: values.email, password: values.password })
    if (!res.success) throw new Error(res.message || t('auth.login.failed'))
    auth.setSession({ user: res.data.user, token: res.data.token, tenant: values.tenant })
    toast.add({ severity: 'success', summary: t('auth.login.welcomeBack'), detail: res.data.user.name, life: 2000 })
    const redirect = (route.query.redirect as string) || '/'
    await router.push(redirect)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: t('auth.login.failed'),
      detail: data?.message ?? t('auth.login.failedDetail'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Card class="shadow-2xl">
    <template #title>
      <div class="text-xl font-semibold">{{ t('auth.login.title') }}</div>
      <p class="text-sm font-normal text-surface-500 mt-1">
        {{ t('auth.login.noWorkspace') }}
        <NuxtLink to="/auth/onboard" class="text-primary-600 hover:underline">{{ t('auth.login.createOne') }}</NuxtLink>
      </p>
    </template>

    <template #content>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="tenant" class="block text-sm font-medium mb-1">{{ t('auth.login.workspaceHandle') }}</label>
          <InputText
            id="tenant"
            v-model="tenant"
            v-bind="tenantAttrs"
            :placeholder="t('auth.login.workspacePlaceholder')"
            class="w-full"
            :invalid="!!errors.tenant"
            autocomplete="organization"
          />
          <small v-if="errors.tenant" class="text-red-600">{{ errors.tenant }}</small>
        </div>

        <div>
          <label for="email" class="block text-sm font-medium mb-1">{{ t('common.email') }}</label>
          <InputText
            id="email"
            v-model="email"
            v-bind="emailAttrs"
            type="email"
            :placeholder="t('auth.login.emailPlaceholder')"
            class="w-full"
            :invalid="!!errors.email"
            autocomplete="email"
          />
          <small v-if="errors.email" class="text-red-600">{{ errors.email }}</small>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium mb-1">{{ t('common.password') }}</label>
          <Password
            id="password"
            v-model="password"
            v-bind="passwordAttrs"
            :feedback="false"
            toggle-mask
            input-class="w-full"
            class="w-full"
            :invalid="!!errors.password"
            input-id="password"
            input-autocomplete="current-password"
          />
          <small v-if="errors.password" class="text-red-600">{{ errors.password }}</small>
        </div>

        <Button type="submit" :label="t('auth.login.signIn')" icon="pi pi-sign-in" :loading="loading" class="w-full" />

        <Button
          type="button"
          severity="secondary"
          text
          size="small"
          icon="pi pi-bolt"
          :label="t('auth.login.useDemo')"
          class="w-full"
          @click="fillDemo"
        />
      </form>
    </template>
  </Card>
</template>
