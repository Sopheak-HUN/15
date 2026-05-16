# Skill: Frontend UI & Feature Implementation

## Context
Use this skill when building new pages, complex forms, or dashboard features in the Nuxt 3 environment. This ensures that the frontend remains performant, responsive, and adheres to the premium "Enterprise ERP" design language.

## Guidelines

### 1. Composition API & Component Structure
- **Script Setup**: Always use `<script setup lang="ts">`.
- **Atomic Design**: Break down large pages into small, reusable components located in the same module or `components/shared`.
- **Props Validation**: Use TypeScript interfaces and `withDefaults` for all props.
- **Emits**: Explicitly define emits using `defineEmits`.

### 2. UI Development (PrimeVue + Tailwind)
- **PrimeVue Pass-Through (PT)**: Use the `pt` property to style PrimeVue components with Tailwind classes for a unified look.
- **Custom Tokens**: Use CSS variables for brand colors (e.g., `--primary-color`) to support dynamic tenant branding.
- **Layouts**: Use Nuxt layouts (`layouts/default.vue`, `layouts/auth.vue`) to manage persistent UI elements like sidebars and headers.

### 3. Reactive Data Fetching
- **useApi Composable**: Use a custom `useApi` wrapper around `useFetch` to automatically inject the `X-Tenant-ID` header.
- **Lazy Loading**: Use `lazy: true` in `useFetch` for non-critical data to improve initial page load.
- **Watchers**: Use `watch` or `watchEffect` to reactively fetch data when route parameters or filters change.

### 4. Form Handling & Validation
- **VeeValidate / Vuelidate**: Use a validation library for complex forms to ensure consistent error messaging.
- **Debouncing**: Use debounced inputs for search fields to reduce API load.
- **Loading States**: Always show a loading indicator (Skeleton or Spinner) during asynchronous operations.

## Best Practices
- **Composable Logic**: Extract reusable business logic (e.g., `useInvoicing`, `useInventory`) into composables.
- **Performance**: Use `v-once` for static content and `v-memo` for complex lists to optimize rendering.
- **Accessibility**: Use semantic HTML (e.g., `<main>`, `<section>`, `<nav>`) and ensure all interactive elements have focus states.
- **Dark Mode**: Use Tailwind's `dark:` modifier and PrimeVue's theme switching for a first-class dark mode experience.

## Troubleshooting
- **Hydration Errors**: Ensure that the DOM structure generated on the server matches the client. Avoid using `Date.now()` or random IDs in the root template.
- **Z-Index Conflicts**: Use PrimeVue's `ZIndexUtils` or standard Tailwind `z-` classes consistently to avoid overlay issues.
- **State Desync**: If Pinia state is out of sync, check if the store is being reset or if actions are not awaiting promises correctly.
- **Layout Shift**: Set explicit dimensions for images and use skeletons to reserve space for content during loading.
