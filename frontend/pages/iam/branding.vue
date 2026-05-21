<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const { t } = useI18n()
const saving = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  logo_path: z.string().max(255).optional().or(z.literal('')),
  primary_color: z.string().regex(/^#([0-9a-fA-F]{3}){1,2}$/, t('branding.errors.hexInvalid')).optional().or(z.literal('')),
  secondary_color: z.string().regex(/^#([0-9a-fA-F]{3}){1,2}$/, t('branding.errors.hexInvalid')).optional().or(z.literal('')),
})))

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: {
    logo_path: '',
    primary_color: '#4f46e5',
    secondary_color: '#64748b',
  },
})
const [logoPath, logoAttrs]       = defineField('logo_path')
const [primary, primaryAttrs]     = defineField('primary_color')
const [secondary, secondaryAttrs] = defineField('secondary_color')

const onSubmit = handleSubmit(async (values) => {
  saving.value = true
  try {
    await iam.updateBranding({
      logo_path: values.logo_path || undefined,
      primary_color: values.primary_color || undefined,
      secondary_color: values.secondary_color || undefined,
    })
    toast.add({ severity: 'success', summary: t('branding.saved'), life: 2500 })
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({
      severity: 'error',
      summary: t('branding.saveFailed'),
      detail: data?.message ?? t('branding.saveFailedDetail'),
      life: 6000,
    })
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <div class="space-y-6 max-w-3xl">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ t('branding.title') }}</h1>
      <p class="text-surface-500 mt-1">{{ t('branding.subtitle') }}</p>
    </div>

    <Card>
      <template #content>
        <form class="space-y-6" @submit.prevent="onSubmit">
          <div>
            <label for="logo" class="block text-sm font-medium mb-1">{{ t('branding.logoPath') }}</label>
            <InputText
              id="logo"
              v-model="logoPath"
              v-bind="logoAttrs"
              :placeholder="t('branding.logoPlaceholder')"
              class="w-full"
              :invalid="!!errors.logo_path"
            />
            <small class="text-surface-500 text-xs">{{ t('branding.logoHelp') }}</small>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="primary" class="block text-sm font-medium mb-1">{{ t('branding.primaryColor') }}</label>
              <div class="flex items-center gap-2">
                <ColorPicker v-model="primary" v-bind="primaryAttrs" format="hex" />
                <InputText v-model="primary" class="flex-1 font-mono" :invalid="!!errors.primary_color" />
              </div>
              <small v-if="errors.primary_color" class="text-red-600">{{ errors.primary_color }}</small>
            </div>

            <div>
              <label for="secondary" class="block text-sm font-medium mb-1">{{ t('branding.secondaryColor') }}</label>
              <div class="flex items-center gap-2">
                <ColorPicker v-model="secondary" v-bind="secondaryAttrs" format="hex" />
                <InputText v-model="secondary" class="flex-1 font-mono" :invalid="!!errors.secondary_color" />
              </div>
              <small v-if="errors.secondary_color" class="text-red-600">{{ errors.secondary_color }}</small>
            </div>
          </div>

          <div class="rounded-xl border border-surface-200 dark:border-surface-800 p-6 bg-surface-50 dark:bg-surface-900">
            <p class="text-xs uppercase text-surface-500 mb-3">{{ t('branding.preview') }}</p>
            <div class="flex items-center gap-4">
              <div class="size-12 rounded-xl grid place-items-center text-white font-bold" :style="{ background: primary || '#4f46e5' }">
                {{ t('app.short') }}
              </div>
              <div>
                <div class="font-semibold" :style="{ color: primary || '#4f46e5' }">{{ t('branding.previewPrimary') }}</div>
                <div class="text-sm" :style="{ color: secondary || '#64748b' }">{{ t('branding.previewSecondary') }}</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end">
            <Button type="submit" :label="t('branding.saveBranding')" icon="pi pi-check" :loading="saving" />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
