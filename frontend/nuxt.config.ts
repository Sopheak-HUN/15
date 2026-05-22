import tailwindcss from '@tailwindcss/vite'
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'

const ErpPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '{indigo.50}',  100: '{indigo.100}', 200: '{indigo.200}',
      300: '{indigo.300}', 400: '{indigo.400}', 500: '{indigo.500}',
      600: '{indigo.600}', 700: '{indigo.700}', 800: '{indigo.800}',
      900: '{indigo.900}', 950: '{indigo.950}',
    },
    colorScheme: {
      light: { surface: { 0: '#ffffff',
        50: '{slate.50}',  100: '{slate.100}', 200: '{slate.200}',
        300: '{slate.300}', 400: '{slate.400}', 500: '{slate.500}',
        600: '{slate.600}', 700: '{slate.700}', 800: '{slate.800}',
        900: '{slate.900}', 950: '{slate.950}' } },
      dark:  { surface: { 0: '#ffffff',
        50: '{zinc.50}',   100: '{zinc.100}',  200: '{zinc.200}',
        300: '{zinc.300}', 400: '{zinc.400}',  500: '{zinc.500}',
        600: '{zinc.600}', 700: '{zinc.700}',  800: '{zinc.800}',
        900: '{zinc.900}', 950: '{zinc.950}' } },
    },
  },
})

export default defineNuxtConfig({
  compatibilityDate: '2026-05-21',
  devtools: { enabled: true },
  // SPA mode: auth/tenant context lives in localStorage and middleware/data
  // fetching only make sense client-side. Avoids hydration-mismatch flicker.
  ssr: false,
  typescript: { strict: true, typeCheck: false },
  modules: [
    '@pinia/nuxt',
    '@primevue/nuxt-module',
    '@vueuse/nuxt',
    '@nuxtjs/i18n',
  ],
  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'en',
    locales: [
      { code: 'en', name: 'English',  file: 'en.json' },
      { code: 'km', name: 'ខ្មែរ',     file: 'km.json' },
    ],
    lazy: true,
    langDir: 'locales',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'erp.locale',
      fallbackLocale: 'en',
      redirectOn: 'no prefix',
    },
    bundle: { optimizeTranslationDirective: false },
  },
  vite: { plugins: [tailwindcss()] },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
    },
  },
  primevue: {
    options: {
      ripple: true,
      theme: {
        preset: ErpPreset,
        options: {
          darkModeSelector: '.dark',
          cssLayer: { name: 'primevue', order: 'tailwindcss, primevue' },
        },
      },
    },
  },
  app: {
    head: {
      title: 'ERP — Enterprise Resource Planning',
      htmlAttrs: { lang: 'en' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    },
  },
})
