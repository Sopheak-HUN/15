<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Cambodian-HR convention for leave forms: full day or half day.
            // Half day forces end_date = start_date and days = 0.5; full day
            // uses the existing inclusive-calendar-day calculation.
            $table->string('duration_type', 16)->default('full_day')->after('days');

            // Optional delegation — the colleague who'll cover the work
            // while the requester is out.
            $table->uuid('assign_to')->nullable()->after('reason');

            // S3 object key for the supporting document the requester
            // uploaded (medical certificate, travel confirmation, etc.).
            // Format: tenants/{handle}/employees/{id}/leave-references/{nanoid}.{ext}
            $table->string('reference_path', 255)->nullable()->after('assign_to');

            $table->foreign('assign_to')->references('id')->on('employees')->nullOnDelete();
            $table->index('assign_to');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            try { $table->dropForeign(['assign_to']); } catch (\Throwable $e) { /* already gone */ }
            $table->dropColumn(['duration_type', 'assign_to', 'reference_path']);
        });
    }
};
