<script setup lang="ts">
const { mode, toggle } = useTheme()
const { t } = useI18n()
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gradient-to-br from-surface-50 via-surface-0 to-primary-50 dark:from-surface-950 dark:via-surface-900 dark:to-surface-900">
    <header class="flex items-center justify-between p-6">
      <NuxtLink to="/" class="flex items-center gap-2 font-semibold text-lg">
        <span class="size-8 rounded-lg bg-primary-600 grid place-items-center text-white">
          <i class="pi pi-bolt text-sm" />
        </span>
        <span>{{ t('app.brand') }}</span>
      </NuxtLink>
      <div class="flex items-center gap-2">
        <LocaleSwitcher />
        <Button
          :icon="mode === 'dark' ? 'pi pi-sun' : 'pi pi-moon'"
          text rounded severity="secondary"
          :aria-label="t('theme.toggle')"
          @click="() => toggle()"
        />
      </div>
    </header>

    <main class="flex-1 grid place-items-center px-4 pb-12">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </main>

    <footer class="text-center text-xs text-surface-500 pb-6">
      {{ t('app.copyright', { year: new Date().getFullYear() }) }}
    </footer>
  </div>
</template>
