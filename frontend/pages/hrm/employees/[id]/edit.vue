<script setup lang="ts">
import type { Employee } from '~/types/hrm'

definePageMeta({ middleware: 'auth' })

// Fetch the employee BEFORE rendering the wizard so the form sees a
// non-null `initial` prop at setup time (VeeValidate's `useForm`
// captures initialValues synchronously). The v-if below keeps the
// wizard mounted only once the data is ready.
const route = useRoute()
const id = route.params.id as string
const hrm = useHrmApi()

const { data, error, pending } = await useAsyncData(
  `hrm-employee-${id}`,
  () => hrm.showEmployee(id),
)
const employee = computed<Employee | null>(() => data.value?.data ?? null)
</script>

<template>
  <div>
    <HrmEmployeeWizardForm v-if="employee" mode="edit" :initial="employee" />
    <div v-else-if="pending" class="py-20 text-center text-surface-500">
      <i class="pi pi-spin pi-spinner text-2xl mb-2" />
      <div class="text-sm">Loading employee…</div>
    </div>
    <div v-else-if="error" class="py-20 text-center">
      <div class="text-red-500 text-sm">{{ error.message }}</div>
      <NuxtLink to="/hrm/employees" class="mt-3 inline-block text-primary-600 hover:underline text-sm">
        ← Back to employees
      </NuxtLink>
    </div>
  </div>
</template>
