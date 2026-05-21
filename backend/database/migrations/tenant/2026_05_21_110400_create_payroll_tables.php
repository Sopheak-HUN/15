<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pay_components')) {
            Schema::create('pay_components', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');                       // e.g. "Income Tax", "Housing Allowance"
                $table->string('code', 32)->unique();
                $table->enum('kind', ['earning', 'deduction']);
                $table->enum('calculation', ['fixed', 'percentage_of_base']);
                $table->decimal('amount', 14, 4);              // value OR percentage (0..100)
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_pay_components')) {
            Schema::create('employee_pay_components', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('pay_component_id');
                $table->decimal('override_amount', 14, 4)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('pay_component_id')->references('id')->on('pay_components')->cascadeOnDelete();
                $table->unique(['employee_id', 'pay_component_id', 'effective_from']);
            });
        }

        if (! Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('label');                       // "May 2026"
                $table->string('status', 32)->default('draft'); // workflow_statuses(hrm.payroll_period)
                $table->timestamp('processed_at')->nullable();
                $table->uuid('processed_by')->nullable();      // users.id
                $table->timestamps();

                $table->unique(['start_date', 'end_date']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('payroll_period_id');
                $table->uuid('employee_id');
                $table->decimal('gross_earnings', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2)->default(0);
                $table->string('currency', 8)->default('USD');
                $table->json('line_items')->nullable();        // breakdown for the PDF
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->unique(['payroll_period_id', 'employee_id']);
                $table->index('employee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('pay_components');
    }
};
