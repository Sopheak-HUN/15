<script setup lang="ts">
const { locale, locales, setLocale, t } = useI18n()

const items = computed(() =>
  (locales.value as { code: string; name: string }[]).map((l) => ({
    label: l.name,
    icon: l.code === locale.value ? 'pi pi-check' : 'pi pi-globe',
    command: () => setLocale(l.code as any),
  })),
)

type PopupMenu = { toggle: (event: Event) => void }
const menu = ref<PopupMenu | null>(null)
const currentName = computed(() => {
  const found = (locales.value as { code: string; name: string }[]).find(
    (l) => l.code === locale.value,
  )
  return found?.name ?? locale.value
})
</script>

<template>
  <div>
    <button
      type="button"
      class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 text-sm"
      :aria-label="t('locale.label')"
      @click="(e) => menu?.toggle(e)"
    >
      <i class="pi pi-globe text-surface-500" />
      <span class="hidden sm:block font-medium">{{ currentName }}</span>
      <i class="pi pi-angle-down text-xs text-surface-500" />
    </button>
    <Menu ref="menu" :model="items" :popup="true" />
  </div>
</template>
