<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Permission, Role } from '~/types/iam'

definePageMeta({ middleware: 'auth' })

const iam = useIamApi()
const toast = useToast()
const confirm = useConfirm()

const { data: rolesData, refresh: refreshRoles, pending: rolesPending } =
  await useAsyncData('iam-roles', () => iam.listRoles())
const { data: permsData, pending: permsPending } =
  await useAsyncData('iam-permissions', () => iam.listPermissions())

const roles      = computed<Role[]>(() => rolesData.value?.data ?? [])
const allPerms   = computed<Permission[]>(() => permsData.value?.data ?? [])

const dialogOpen = ref(false)
const editing    = ref<Role | null>(null)
const saving     = ref(false)

const schema = toTypedSchema(z.object({
  name: z.string().min(2, 'Name is required').max(80),
  description: z.string().max(255).optional().nullable(),
}))
const { defineField, handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { name: '', description: '' },
})
const [nameField, nameAttrs]   = defineField('name')
const [descField, descAttrs]   = defineField('description')

const openCreate = () => {
  editing.value = null
  resetForm({ values: { name: '', description: '' } })
  dialogOpen.value = true
}

const openEdit = (role: Role) => {
  editing.value = role
  setValues({ name: role.name, description: role.description ?? '' })
  dialogOpen.value = true
}

const onSave = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = { name: values.name, description: values.description ?? undefined }
    if (editing.value) {
      await iam.updateRole(editing.value.id, payload)
      toast.add({ severity: 'success', summary: 'Role updated', life: 2000 })
    } else {
      await iam.createRole(payload)
      toast.add({ severity: 'success', summary: 'Role created', life: 2000 })
    }
    dialogOpen.value = false
    await refreshRoles()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: 'Save failed', detail: data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
})

const onDelete = (role: Role) => {
  confirm.require({
    message: `Delete role "${role.name}"? This cannot be undone.`,
    header: 'Confirm deletion',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await iam.deleteRole(role.id)
        toast.add({ severity: 'success', summary: 'Role deleted', life: 2000 })
        await refreshRoles()
      } catch (err: unknown) {
        const data = (err as { data?: { message?: string } }).data
        toast.add({ severity: 'error', summary: 'Delete failed', detail: data?.message, life: 4000 })
      }
    },
  })
}

// Permission-assignment side dialog
const permDialog = ref(false)
const permRole = ref<Role | null>(null)
const permSelected = ref<string[]>([])
const permSaving = ref(false)

const openPerms = (role: Role) => {
  permRole.value = role
  permSelected.value = (role.permissions ?? []).map((p) => p.id)
  permDialog.value = true
}

const savePerms = async () => {
  if (!permRole.value) return
  permSaving.value = true
  try {
    await iam.syncRolePermissions(permRole.value.id, permSelected.value)
    toast.add({ severity: 'success', summary: 'Permissions synced', life: 2000 })
    permDialog.value = false
    await refreshRoles()
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } }).data
    toast.add({ severity: 'error', summary: 'Sync failed', detail: data?.message, life: 4000 })
  } finally {
    permSaving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Roles</h1>
        <p class="text-surface-500 mt-1">Define which capabilities each role grants within this tenant.</p>
      </div>
      <Button label="New role" icon="pi pi-plus" @click="openCreate" />
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
              No roles yet. Create your first one above.
            </div>
          </template>

          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.name }}</span>
            </template>
          </Column>
          <Column field="description" header="Description">
            <template #body="{ data }">
              <span class="text-surface-500">{{ data.description || '—' }}</span>
            </template>
          </Column>
          <Column header="Permissions" body-class="!py-2">
            <template #body="{ data }">
              <Chip
                v-if="data.permissions?.length"
                :label="`${data.permissions.length} assigned`"
                icon="pi pi-key"
                class="cursor-pointer !bg-primary-50 dark:!bg-primary-950/40 !text-primary-700 dark:!text-primary-300"
                @click="openPerms(data)"
              />
              <Button
                v-else
                label="Assign"
                icon="pi pi-plus-circle"
                text size="small"
                @click="openPerms(data)"
              />
            </template>
          </Column>
          <Column header="" body-class="text-right !py-2" :style="{ width: '160px' }">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Edit" @click="openEdit(data)" />
              <Button icon="pi pi-trash"  text rounded severity="danger"    aria-label="Delete" @click="onDelete(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create / Edit dialog -->
    <Dialog v-model:visible="dialogOpen" modal :header="editing ? 'Edit role' : 'New role'" :style="{ width: '32rem' }">
      <form class="space-y-4" @submit.prevent="onSave">
        <div>
          <label for="role-name" class="block text-sm font-medium mb-1">Name</label>
          <InputText id="role-name" v-model="nameField" v-bind="nameAttrs" class="w-full" :invalid="!!errors.name" />
          <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
        </div>
        <div>
          <label for="role-desc" class="block text-sm font-medium mb-1">Description</label>
          <Textarea id="role-desc" v-model="descField" v-bind="descAttrs" rows="3" class="w-full" />
          <small v-if="errors.description" class="text-red-600">{{ errors.description }}</small>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" label="Cancel" severity="secondary" text @click="dialogOpen = false" />
          <Button type="submit" :label="editing ? 'Save' : 'Create'" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Permission assignment dialog -->
    <Dialog v-model:visible="permDialog" modal :header="`Permissions — ${permRole?.name}`" :style="{ width: '40rem' }">
      <div v-if="permsPending" class="py-8 text-center text-surface-500">
        <ProgressSpinner style="width:32px;height:32px" />
      </div>
      <div v-else class="space-y-3 max-h-[60vh] overflow-auto pr-2">
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
        </div>
        <div v-if="!allPerms.length" class="py-8 text-center text-surface-500">
          No permissions in the catalog. Run <code>php artisan tenants:seed</code>.
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" text @click="permDialog = false" />
        <Button label="Save assignments" icon="pi pi-check" :loading="permSaving" @click="savePerms" />
      </template>
    </Dialog>
  </div>
</template>
