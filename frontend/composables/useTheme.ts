import { useColorMode } from '@vueuse/core'

/**
 * Dark mode toggle keyed to <html class="dark">.
 * PrimeVue's `darkModeSelector: '.dark'` in nuxt.config reads the same class.
 */
export function useTheme() {
  const mode = useColorMode({
    selector: 'html',
    attribute: 'class',
    modes: { light: '', dark: 'dark' },
    storageKey: 'erp-theme',
  })

  const toggle = () => {
    mode.value = mode.value === 'dark' ? 'light' : 'dark'
  }

  return { mode, toggle }
}
