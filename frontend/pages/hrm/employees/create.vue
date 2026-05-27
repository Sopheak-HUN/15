<script setup lang="ts">
// Thin page wrapper. The full 7-step wizard lives in the reusable
// <EmployeeWizardForm> component so the edit page can render the same
// form pre-populated. See components/hrm/EmployeeWizardForm.vue.
definePageMeta({ middleware: 'auth' })
</script>

<template>
  <HrmEmployeeWizardForm />
</template>
