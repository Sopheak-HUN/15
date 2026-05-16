# Skill: Frontend Architecture & Design Standards

## Context
Use this skill when developing components, pages, or state management in the Nuxt 3 frontend project. Adhering to these standards ensures a premium, high-performance UI that integrates seamlessly with the multi-tenant backend.

## Guidelines

### 1. Framework & Core Tools
- **Framework**: Nuxt 3 (Latest stable version).
- **TypeScript**: Mandatory strict mode. All props, emits, and composables must be typed.
- **Styling**: Tailwind CSS 4+ (Latest). Use utility-first approach with customized design tokens.
- **UI System**: PrimeVue for complex components (DataTables, Calendars, Dialogs).

### 2. Component Design (Atomic)
- **Base Components**: Wrap PrimeVue components to match the project's premium design language.
- **Props & Emits**:
  ```typescript
  interface Props {
    title: string;
    loading?: boolean;
  }
  const props = withDefaults(defineProps<Props>(), {
    loading: false
  });
  ```
- **Templates**: Keep templates clean. Move complex logic to the `<script setup>` or composables.

### 3. API Integration
- **Tenant Context**: All API requests must include the `X-Tenant-ID` header or use a tenant-scoped subdomain.
- **Data Fetching**: Use Nuxt's `useFetch` or `useAsyncData` with proper error handling.
- **Error Handling**: Implement global error interceptors to handle 401 (Auth) and 403 (Tenant Access) errors.

### 4. State Management (Pinia)
- Use Pinia for global state (User info, Tenant config, Shared settings).
- Avoid putting large datasets in global state; use local state or Nuxt's data fetching instead.

## Best Practices
- **Mobile First**: Always design for mobile responsiveness first using Tailwind's `sm:`, `md:`, `lg:` prefixes.
- **Performance**: Optimize images and use lazy-loading for heavy components.
- **Aesthetics**: Use smooth transitions, subtle shadows, and premium color palettes (avoid default colors).
- **Accessibility**: Ensure all interactive elements have proper ARIA labels and keyboard support.

## Troubleshooting
- **Tailwind Classes Not Applying**: Check `tailwind.config.ts` and ensure the CSS entry point is imported in `nuxt.config.ts`.
- **Hydration Mismatch**: Avoid browser-only APIs (like `localStorage` or `window`) directly in the `setup` block; use `onMounted`.
