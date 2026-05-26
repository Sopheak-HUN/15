<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Object-storage key for the employee photo. Format:
            //   tenants/{handle}/employees/{employee_uuid}/photo.{ext}
            // Never store the bytes locally — see .task/hrm/task.md "Photo
            // upload" for the MinIO/S3 + presigned-URL flow.
            $table->string('photo_path', 255)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
