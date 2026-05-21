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
const loading = ref(false)

const schema = toTypedSchema(z.object({
  name: z.string().min(2, 'Company name is required').max(120),
  handle: z.string()
    .min(2, 'Handle must be at least 2 characters')
    .max(40)
    .regex(/^[a-z0-9][a-z0-9-]*$/, 'Use lowercase letters, digits, and dashes only'),
}))

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
      summary: 'Tenant created',
      detail: 'Run "php artisan tenants:seed --tenants=' + res.tenant.handle + '" then sign in.',
      life: 6000,
    })
    await router.push({ path: '/auth/login', query: { tenant: res.tenant.handle } })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: 'Onboarding failed',
      detail: data?.message ?? 'Unable to create tenant — handle may already be in use.',
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
      <div class="text-xl font-semibold">Create your workspace</div>
      <p class="text-sm font-normal text-surface-500 mt-1">
        Already have a workspace? <NuxtLink to="/auth/login" class="text-primary-600 hover:underline">Sign in</NuxtLink>
      </p>
    </template>

    <template #content>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label for="name" class="block text-sm font-medium mb-1">Company name</label>
          <InputText
            id="name"
            v-model="name"
            v-bind="nameAttrs"
            placeholder="Acme Corp"
            class="w-full"
            :invalid="!!errors.name"
            autocomplete="organization"
          />
          <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
        </div>

        <div>
          <label for="handle" class="block text-sm font-medium mb-1">Workspace handle</label>
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
          <small class="text-surface-500 text-xs">Used as the tenant identifier in the <code>tenant</code> header.</small>
          <div v-if="errors.handle" class="text-red-600 text-xs mt-1">{{ errors.handle }}</div>
        </div>

        <Button type="submit" label="Create workspace" icon="pi pi-check" :loading="loading" class="w-full" />
      </form>
    </template>
  </Card>
</template>
