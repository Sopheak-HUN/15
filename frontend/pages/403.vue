<script setup lang="ts">
definePageMeta({ middleware: 'auth', layout: 'default' })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const from = computed(() => (route.query.from as string | undefined) ?? null)
</script>

<template>
  <div class="min-h-[60vh] flex flex-col items-center justify-center text-center p-6">
    <div class="size-20 rounded-full bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-300 grid place-items-center mb-6">
      <i class="pi pi-lock text-3xl" />
    </div>
    <h1 class="text-2xl font-semibold tracking-tight">{{ t('errors.forbidden.title') }}</h1>
    <p class="text-surface-500 mt-2 max-w-md">{{ t('errors.forbidden.subtitle') }}</p>
    <p v-if="from" class="text-xs text-surface-400 mt-3 font-mono">{{ from }}</p>
    <div class="mt-8 flex gap-2">
      <Button :label="t('errors.forbidden.goHome')" icon="pi pi-home" @click="router.push('/')" />
      <Button :label="t('errors.forbidden.back')" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.back()" />
    </div>
  </div>
</template>
