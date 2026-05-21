<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('employee_id', 32)->unique();    // human-readable, e.g. TT-0042

                // Identity
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone', 32)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('gender', 16)->nullable();

                // Encrypted PII (cast on model: 'encrypted')
                $table->text('national_id')->nullable();
                $table->text('bank_account')->nullable();
                $table->text('tax_id')->nullable();

                // Address
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country', 64)->nullable();

                // Org placement
                $table->uuid('department_id')->nullable();
                $table->uuid('position_id')->nullable();
                $table->uuid('manager_id')->nullable();         // employees.id (self-FK added below)
                $table->uuid('user_id')->nullable();            // users.id link for ESS

                // Employment
                $table->date('hire_date')->nullable();
                $table->date('termination_date')->nullable();
                $table->string('employment_type', 32)->default('full_time');
                $table->string('status', 32)->default('active');

                // Compensation (encrypted to limit access via DB-level grep)
                $table->text('base_salary')->nullable();
                $table->string('currency', 8)->default('USD');
                $table->string('pay_frequency', 16)->default('monthly');

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
                $table->index(['status']);
                $table->index(['department_id', 'status']);
            });

            // Self-referencing FK in a follow-up ALTER (see departments migration
            // for the reason — Postgres can't bind a self-FK to a not-yet-visible PK).
            Schema::table('employees', function (Blueprint $table) {
                $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
            });

            // Backfill the departments.manager_id FK now that employees exists.
            Schema::table('departments', function (Blueprint $table) {
                $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
            });

            // Partial unique index on email — only enforce uniqueness for live rows.
            // Lets terminated/reverted employees free their email for re-hires.
            DB::statement('CREATE UNIQUE INDEX employees_email_active_unique ON employees (email) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        // Drop the FK that crosses migrations before tearing down employees.
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                try { $table->dropForeign(['manager_id']); } catch (\Throwable $e) { /* already gone */ }
            });
        }
        Schema::dropIfExists('employees');
    }
};
