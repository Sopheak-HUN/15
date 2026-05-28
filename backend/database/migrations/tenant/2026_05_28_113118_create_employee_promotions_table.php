<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captures every position / department / salary change for an employee.
 * One row per event, ordered by `effective_date`. The current position
 * on the employees row is the "live" value; this table is the journal.
 *
 * `type` distinguishes upward moves from lateral transfers and rare
 * downward changes so HR dashboards can filter by intent — the schema
 * itself doesn't enforce that `new_salary > previous_salary` for a
 * `promotion`, since real-world data is messy.
 *
 * Sensitive fields (previous_salary, new_salary) are encrypted at rest
 * via the model cast — matches the encryption strategy on the
 * employees table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_promotions')) return;

        Schema::create('employee_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->date('effective_date');
            $table->enum('type', ['promotion', 'lateral', 'demotion', 'salary_adjustment'])
                ->default('promotion');

            // Position + department snapshots — both before and after.
            // Set null OnDelete so deleting a position/department doesn't
            // erase the audit trail.
            $table->foreignUuid('previous_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignUuid('new_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignUuid('previous_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('new_department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Plaintext display labels — kept alongside the FK so the
            // history stays readable even if the position/department row
            // is later renamed or deleted.
            $table->string('previous_role_name', 120)->nullable();
            $table->string('new_role_name', 120)->nullable();

            // Encrypted at the model level (see EmployeePromotion casts).
            $table->text('previous_salary')->nullable();
            $table->text('new_salary')->nullable();
            $table->string('currency', 3)->nullable();

            $table->text('reason')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('employees')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_promotions');
    }
};
