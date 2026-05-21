<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Permission, Role } from '~/types/iam'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const { data: rolesData, refresh: refreshRoles, pending: rolesPending } =
  await useAsyncData('iam-roles', () => iam.listRoles())
const { data: permsData, pending: permsPending } =
  await useAsyncData('iam-permissions', () => iam.listPermissions())

const roles      = computed<Role[]>(() => rolesData.value?.data ?? [])
const allPerms   = computed<Permission[]>(() => permsData.value?.data ?? [])

const dialogOpen = ref(false)
const editing    = ref<Role | null>(null)
const saving     = ref(false)

const schema = computed(() => toTypedSchema(z.object({
  name: z.string().min(2, t('roles.errors.nameRequired')).max(80),
  description: z.string().max(255).optional().nullable(),
  parent_role_id: z.string().uuid().nullable().optional(),
})))
const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { name: '', description: '', parent_role_id: null },
})
const [nameField, nameAttrs]     = defineField('name')
const [descField, descAttrs]     = defineField('description')
const [parentField, parentAttrs] = defineField('parent_role_id')

// Eligible parents: every role except the one being edited (server also
// rejects cycles, but we prune the obvious self-reference up front).
const parentOptions = computed(() =>
  roles.value
    .filter((r) => !editing.value || r.id !== editing.value.id)
    .map((r) => ({ label: r.name, value: r.id })),
)

const openCreate = () => {
  editing.value = null
  resetForm({ values: { name: '', description: '', parent_role_id: null } })
  dialogOpen.value = true
}

const openEdit = (role: Role) => {
  editing.value = role
  setValues({
    name: role.name,
    description: role.description ?? '',
    parent_role_id: role.parent_role_id ?? null,
  })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = {
      name: values.name,
      description: values.description ?? undefined,
      parent_role_id: values.parent_role_id ?? null,
    }
    if (editing.value) {
      await iam.updateRole(editing.value.id, payload)
      toast.add({ severity: 'success', summary: t('roles.toast.updated'), life: 2000 })
    } else {
      await iam.createRole(payload)
      toast.add({ severity: 'success', summary: t('roles.toast.created'), life: 2000 })
    }
    dialogOpen.value = false
    await refreshRoles()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('roles.toast.saveFailed'), detail: data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
})

const onDelete = (role: Role) => {
  confirm.require({
    message: t('roles.confirmDelete', { name: role.name }),
    header: t('roles.confirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await iam.deleteRole(role.id)
        toast.add({ severity: 'success', summary: t('roles.toast.deleted'), life: 2000 })
        await refreshRoles()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: t('roles.toast.deleteFailed'), detail: data?.message, life: 4000 })
      }
    },
  })
}

// Permission-assignment side dialog
const permDialog = ref(false)
const permRole = ref<Role | null>(null)
const permSelected = ref<string[]>([])
const permSaving = ref(false)
const inheritedPerms = ref<Permission[]>([])

const openPerms = async (role: Role) => {
  permRole.value = role
  permSelected.value = (role.permissions ?? []).map((p) => p.id)
  inheritedPerms.value = []
  permDialog.value = true

  if (role.parent_role_id) {
    try {
      const res = await iam.showRole(role.id)
      const direct = new Set((role.permissions ?? []).map((p) => p.id))
      inheritedPerms.value = (res.data.effective_permissions ?? []).filter((p) => !direct.has(p.id))
    } catch {
      // Non-fatal — the dialog still works without the inherited view.
    }
  }
}

const savePerms = async () => {
  if (!permRole.value) return
  permSaving.value = true
  try {
    await iam.syncRolePermissions(permRole.value.id, permSelected.value)
    toast.add({ severity: 'success', summary: t('roles.toast.synced'), life: 2000 })
    permDialog.value = false
    await refreshRoles()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: t('roles.toast.syncFailed'), detail: data?.message, life: 4000 })
  } finally {
    permSaving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ t('roles.title') }}</h1>
        <p class="text-surface-500 mt-1">{{ t('roles.subtitle') }}</p>
      </div>
      <Button :label="t('roles.new')" icon="pi pi-plus" @click="openCreate" />
    </div>

    <Card>
      <template #content>
        <DataTable
          :value="roles"
          :loading="rolesPending"
          striped-rows
          data-key="id"
          paginator
          :rows="10"
          :rows-per-page-options="[10, 25, 50]"
          class="text-sm"
        >
          <template #empty>
            <div class="py-10 text-center text-surface-500">
              {{ t('roles.empty') }}
            </div>
          </template>

          <Column field="name" :header="t('common.name')" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.name }}</span>
            </template>
          </Column>
          <Column field="description" :header="t('common.description')">
            <template #body="{ data }">
              <span class="text-surface-500">{{ data.description || t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('roles.inheritsFrom')">
            <template #body="{ data }">
              <Tag v-if="data.parent" :value="data.parent.name" severity="info" />
              <span v-else class="text-surface-400">{{ t('common.dash') }}</span>
            </template>
          </Column>
          <Column :header="t('roles.permissions')" body-class="!py-2">
            <template #body="{ data }">
              <Chip
                v-if="data.permissions?.length"
                :label="t('roles.assigned', { count: data.permissions.length })"
                icon="pi pi-key"
                class="cursor-pointer !bg-primary-50 dark:!bg-primary-950/40 !text-primary-700 dark:!text-primary-300"
                @click="openPerms(data)"
              />
              <Button
                v-else
                :label="t('common.assign')"
                icon="pi pi-plus-circle"
                text size="small"
                @click="openPerms(data)"
              />
            </template>
          </Column>
          <Column header="" body-class="text-right !py-2" :style="{ width: '160px' }">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button icon="pi pi-trash"  text rounded severity="danger"    :aria-label="t('common.delete')" @click="onDelete(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create / Edit dialog -->
    <Dialog v-model:visible="dialogOpen" modal :header="editing ? t('roles.dialog.editTitle') : t('roles.dialog.createTitle')" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onSave">
        <div>
          <label for="role-name" class="block text-sm font-medium mb-1">{{ t('common.name') }}</label>
          <InputText id="role-name" v-model="nameField" v-bind="nameAttrs" class="w-full" :invalid="!!errors.name" />
          <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
        </div>
        <div>
          <label for="role-desc" class="block text-sm font-medium mb-1">{{ t('common.description') }}</label>
          <Textarea id="role-desc" v-model="descField" v-bind="descAttrs" rows="3" class="w-full" />
          <small v-if="errors.description" class="text-red-600">{{ errors.description }}</small>
        </div>
        <div>
          <label for="role-parent" class="block text-sm font-medium mb-1">{{ t('roles.inheritsFrom') }}</label>
          <Select
            id="role-parent"
            v-model="parentField"
            v-bind="parentAttrs"
            :options="parentOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('roles.noParent')"
            show-clear
            class="w-full"
          />
          <small class="text-surface-500 text-xs">{{ t('roles.parentHelp') }}</small>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? t('common.save') : t('common.create')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Permission assignment dialog -->
    <Dialog v-model:visible="permDialog" modal :header="t('roles.dialog.permsTitle', { name: permRole?.name ?? '' })" :style="{ width: '40rem' }">
      <div v-if="permsPending" class="py-8 text-center text-surface-500">
        <ProgressSpinner style="width:32px;height:32px" />
      </div>
      <div v-else class="space-y-3 max-h-[60vh] overflow-auto pr-2">
        <Message v-if="inheritedPerms.length" severity="info" :closable="false" class="!my-0">
          <span class="text-sm">
            {{ inheritedPerms.length === 1
              ? t('roles.inheritedNoticeOne', { count: inheritedPerms.length, parent: permRole?.parent?.name ?? '' })
              : t('roles.inheritedNoticeOther', { count: inheritedPerms.length, parent: permRole?.parent?.name ?? '' }) }}
          </span>
        </Message>
        <div
          v-for="perm in allPerms"
          :key="perm.id"
          class="flex items-start gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-900"
        >
          <Checkbox v-model="permSelected" :value="perm.id" :input-id="`perm-${perm.id}`" />
          <label :for="`perm-${perm.id}`" class="cursor-pointer flex-1">
            <div class="font-mono text-sm">{{ perm.name }}</div>
            <div v-if="perm.description" class="text-xs text-surface-500 mt-0.5">{{ perm.description }}</div>
          </label>
          <Tag
            v-if="inheritedPerms.some((p) => p.id === perm.id)"
            :value="t('roles.inheritedBadge')"
            severity="info"
            class="!text-[10px]"
          />
        </div>
        <div v-if="!allPerms.length" class="py-8 text-center text-surface-500">
          <i18n-t keypath="roles.dialog.emptyCatalog" tag="span">
            <template #cmd><code>php artisan tenants:seed</code></template>
          </i18n-t>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="permDialog = false" />
        <Button :label="t('roles.dialog.saveAssignments')" icon="pi pi-check" :loading="permSaving" @click="savePerms" />
      </template>
    </Dialog>
  </div>
</template>
