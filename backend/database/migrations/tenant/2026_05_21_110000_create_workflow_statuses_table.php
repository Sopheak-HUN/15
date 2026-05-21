<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped registry of lifecycle statuses for every module that has a
 * state machine (hrm.application, hrm.leave, hrm.appraisal, hrm.vacancy,
 * hrm.employee, hrm.payroll_period — and anything FMS/Inventory adds later).
 *
 * Per `skills/hrm/rules.md`, status flow MUST NOT be hardcoded in models;
 * `WorkflowStatusService` consults this table and applies transition rules.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('workflow_statuses')) {
            return;
        }

        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module', 64);   // e.g. "hrm.application"
            $table->string('key', 64);      // e.g. "applied", "hired"
            $table->string('label');
            $table->string('color', 32)->nullable();   // hex or token name
            $table->string('icon', 64)->nullable();    // pi-style or heroicon
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->json('allowed_transitions')->nullable(); // array of keys
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module', 'key']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_statuses');
    }
};
