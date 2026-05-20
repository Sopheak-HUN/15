# File Upload & Storage Implementation Standards

## 1. Backend Implementation (Laravel)

### Form Request Validation
Every file upload endpoint must use a dedicated Form Request that validates file existence, size, and extensions.

```php
namespace App\Tenants\Modules\Common\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authed via middleware
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB Limit
                'mimes:jpg,jpeg,png,pdf,docx,xlsx', // White-listed extensions
            ],
            'is_public' => 'boolean',
        ];
    }
}
```

### Storage & Service Layer Pattern
All files must be processed and persisted via a dedicated Service to ensure consistency, isolation, and auditability.

```php
namespace App\Tenants\Modules\Common\Services;

use App\Tenants\Modules\Common\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Store an uploaded file in tenant-isolated storage.
     */
    public function upload(
        UploadedFile $file, 
        string $userId, 
        string $folder = 'uploads', 
        bool $isPublic = false
    ): Attachment {
        // 1. Generate unique file name to avoid collision
        $extension = $file->getClientOriginalExtension();
        $uniqueName = (string) Str::uuid() . '.' . $extension;

        // 2. Resolve tenant-isolated path depending on the calling system/module
        // The $folder parameter is defined dynamically by each system (e.g. 'hrm/recruitment/application/resume')
        $directory = $folder;

        // 3. Store file securely
        $path = $file->storeAs($directory, $uniqueName, 'local');

        if (!$path) {
            throw new \RuntimeException("Failed to store uploaded file.");
        }

        // 4. Calculate checksum for integrity verification
        $checksum = hash_file('sha256', $file->getRealPath());

        // 5. Create metadata entry in database
        return Attachment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => tenant('id'),
            'user_id' => $userId,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'checksum' => $checksum,
            'is_public' => $isPublic,
        ]);
    }
}
```

---

## 2. Frontend Implementation (Nuxt 3 & PrimeVue)

Use the custom API composable to send requests with the mandatory `X-Tenant-Handle` header.

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const isUploading = ref(false);
const uploadProgress = ref(0);

// Inject active tenant identifier
const { activeTenant } = useTenant();
const api = useApi();

const onUploadFile = async (event: any) => {
  const file = event.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);
  formData.append('is_public', 'false');

  isUploading.value = true;
  uploadProgress.value = 0;

  try {
    const response = await api.post('/v1/attachments/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-Tenant-Handle': activeTenant.value.handle
      },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        }
      }
    });

    toast.add({ severity: 'success', summary: 'Success', detail: 'File uploaded successfully', life: 3000 });
  } catch (error: any) {
    toast.add({ severity: 'error', summary: 'Upload Failed', detail: error.message || 'An error occurred', life: 5000 });
  } finally {
    isUploading.value = false;
  }
};
</script>

<template>
  <div class="card p-6 rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 shadow-sm">
    <h3 class="text-xl font-bold mb-4">Upload Documents</h3>
    
    <FileUpload 
      mode="basic" 
      name="file" 
      accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg" 
      :maxFileSize="10485760" 
      customUpload 
      @uploader="onUploadFile"
      :disabled="isUploading"
      chooseLabel="Select File"
      class="p-button-outlined"
    />

    <div v-if="isUploading" class="mt-4">
      <ProgressBar :value="uploadProgress" class="h-2" />
      <span class="text-sm text-surface-500 mt-1 block">Uploading... {{ uploadProgress }}%</span>
    </div>
  </div>
</template>
```

---

## 3. QA Testing & Verification Standards (Pest PHP)

Every file upload service must have automated feature tests verifying upload authorization, validation failures, storage output location, and strict tenant isolation (P0).

```php
use App\Tenants\Modules\Common\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->tenantA = createTenant();
    $this->tenantB = createTenant();
});

it('allows authorized user to upload document and verifies storage path', function () {
    $this->tenantA->run(function () {
        $user = createUser();
        $file = UploadedFile::fake()->create('contract.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attachments/upload", [
                'file' => $file,
                'is_public' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'fileName', 'filePath', 'fileSize', 'mimeType']
            ]);

        $attachment = Attachment::first();
        expect($attachment)->not->toBeNull();
        expect($attachment->file_name)->toBe('contract.pdf');

        // Assert file exists in tenant-isolated storage folder on the scoped disk
        Storage::disk('local')->assertExists($attachment->file_path);
    });
});

it('prevents tenant B from accessing or downloading files belonging to tenant A', function () {
    // 1. Upload file under Tenant A
    $attachmentId = $this->tenantA->run(function () {
        $user = createUser();
        $file = UploadedFile::fake()->create('payroll_secrecy.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/attachments/upload", ['file' => $file]);
        
        return $response->json('data.id');
    });

    // 2. Attempt to download or view file from Tenant B context
    $this->tenantB->run(function () use ($attachmentId) {
        $otherUser = createUser();
        
        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/attachments/{$attachmentId}/download");

        // Assert Tenant B gets 404 (or 403) to prevent resource enumeration (IDOR)
        $response->assertStatus(404);
    });
});
```
