<script setup lang="ts">
const { t, locale } = useI18n()

useHead({
  htmlAttrs: { lang: locale },
  title: () => t('app.title'),
  bodyAttrs: { class: 'antialiased bg-surface-0 dark:bg-surface-950 text-surface-900 dark:text-surface-100' },
})
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
  <Toast position="top-right" />
  <ConfirmDialog />
</template>
