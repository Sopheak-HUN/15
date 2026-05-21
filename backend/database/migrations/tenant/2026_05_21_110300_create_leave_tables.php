<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code', 32)->unique();      // ANNUAL | SICK | UNPAID | ...
                $table->decimal('default_balance', 7, 2)->default(0); // days accrued per cycle
                $table->boolean('is_paid')->default(true);
                $table->boolean('accrues')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->string('color', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('leave_type_id');
                $table->unsignedSmallInteger('year');
                $table->decimal('balance', 7, 2)->default(0);
                $table->decimal('used', 7, 2)->default(0);
                $table->decimal('pending', 7, 2)->default(0);
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
                $table->unique(['employee_id', 'leave_type_id', 'year']);
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('leave_type_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days', 7, 2);
                $table->string('reason')->nullable();
                $table->string('status', 32)->default('pending'); // workflow_statuses(hrm.leave)
                $table->uuid('approved_by')->nullable();          // employees.id
                $table->timestamp('approved_at')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
                $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
                $table->index(['employee_id', 'status']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
