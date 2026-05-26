<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_contracts')) {
            return;
        }

        Schema::create('employee_contracts', function (Blueprint $table) {
            // 1:N — wizard seeds one row; renewals/extensions add more.
            $table->uuid('id')->primary();
            $table->uuid('employee_id');

            // matches `contractTypes` in the wizard
            // (work / fdc / udc / probation / internship / consulting).
            $table->string('type', 32);

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('comment')->nullable();

            // active / expired / terminated — set by EmployeeService when the
            // wizard creates the initial contract.
            $table->string('status', 16)->default('active');

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
