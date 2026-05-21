<script setup lang="ts">
defineProps<{
  label: string
  required?: boolean
  for?: string
}>()
</script>

<template>
  <label :for="$props.for" class="block text-sm font-medium mb-1">
    {{ label }}
    <span v-if="required" class="text-red-500 ml-0.5" aria-hidden="true">*</span>
  </label>
</template>
