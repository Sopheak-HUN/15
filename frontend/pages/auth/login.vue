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
const loading = ref(false)

const schema = toTypedSchema(z.object({
  tenant: z.string().min(1, 'Tenant handle is required'),
  email: z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
}))

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
    if (!res.success) throw new Error(res.message || 'Login failed')
    auth.setSession({ user: res.data.user, token: res.data.token, tenant: values.tenant })
    toast.add({ severity: 'success', summary: 'Welcome back', detail: res.data.user.name, life: 2000 })
    const redirect = (route.query.redirect as string) || '/'
    await router.push(redirect)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: 'Sign in failed',
      detail: data?.message ?? 'Invalid credentials or unknown tenant.',
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
      <div class="text-xl font-semibold">Sign in to your workspace</div>
      <p class="text-sm font-normal text-surface-500 mt-1">
        No workspace yet? <NuxtLink to="/auth/onboard" class="text-primary-600 hover:underline">Create one</NuxtLink>
      </p>
    </template>

    <template #content>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="tenant" class="block text-sm font-medium mb-1">Workspace handle</label>
          <InputText
            id="tenant"
            v-model="tenant"
            v-bind="tenantAttrs"
            placeholder="acme"
            class="w-full"
            :invalid="!!errors.tenant"
            autocomplete="organization"
          />
          <small v-if="errors.tenant" class="text-red-600">{{ errors.tenant }}</small>
        </div>

        <div>
          <label for="email" class="block text-sm font-medium mb-1">Email</label>
          <InputText
            id="email"
            v-model="email"
            v-bind="emailAttrs"
            type="email"
            placeholder="you@example.com"
            class="w-full"
            :invalid="!!errors.email"
            autocomplete="email"
          />
          <small v-if="errors.email" class="text-red-600">{{ errors.email }}</small>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium mb-1">Password</label>
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

        <Button type="submit" label="Sign in" icon="pi pi-sign-in" :loading="loading" class="w-full" />

        <Button
          type="button"
          severity="secondary"
          text
          size="small"
          icon="pi pi-bolt"
          label="Use demo credentials"
          class="w-full"
          @click="fillDemo"
        />
      </form>
    </template>
  </Card>
</template>
