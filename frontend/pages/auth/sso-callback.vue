<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'auth', middleware: 'guest' })

const route   = useRoute()
const router  = useRouter()
const iam     = useIamApi()
const auth    = useAuthStore()
const toast   = useToast()

const error = ref<string | null>(null)
const tenantHandle = (route.query.tenant as string) || auth.tenant

onMounted(async () => {
  const code  = route.query.code  as string | undefined
  const state = route.query.state as string | undefined
  if (!code || !state) {
    error.value = 'Missing code/state in callback — restart the SSO flow.'
    return
  }
  if (tenantHandle) auth.setTenant(tenantHandle)
  try {
    const res = await iam.ssoCallback({ code, state })
    if (!res.success) throw new Error(res.message || 'SSO sign-in failed')
    auth.setSession({ user: res.data.user, token: res.data.token, tenant: tenantHandle || '' })
    toast.add({ severity: 'success', summary: `Welcome ${res.data.user.name}`, life: 2500 })
    await router.push('/')
  } catch (e: unknown) {
    error.value = (e as { data?: { message?: string } }).data?.message || (e as Error).message || 'SSO sign-in failed'
  }
})
</script>

<template>
  <Card class="shadow-2xl">
    <template #title>Completing sign-in&hellip;</template>
    <template #content>
      <div v-if="!error" class="flex items-center gap-3 py-4">
        <ProgressSpinner style="width:32px;height:32px" />
        <span class="text-sm text-surface-500">Exchanging authorization code for a session token.</span>
      </div>
      <Message v-else severity="error" :closable="false">{{ error }}</Message>
      <div v-if="error" class="pt-4">
        <NuxtLink to="/auth/login" class="text-primary-600 hover:underline text-sm">← Back to sign-in</NuxtLink>
      </div>
    </template>
  </Card>
</template>
