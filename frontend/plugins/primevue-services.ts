import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'

/**
 * Registers PrimeVue plugin services so `useToast()` and `useConfirm()`
 * (and the global `<Toast>` / `<ConfirmDialog>` outlets in app.vue) resolve.
 * The Nuxt module auto-imports the components but not the services.
 */
export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(ToastService)
  nuxtApp.vueApp.use(ConfirmationService)
})
