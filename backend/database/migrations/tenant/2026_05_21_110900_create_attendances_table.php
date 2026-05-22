<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->date('date');
                $table->dateTime('check_in')->nullable();
                $table->dateTime('check_out')->nullable();
                $table->string('status', 32)->default('present'); // present, late, absent, half_day, on_leave
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->index(['date']);
                $table->index(['status']);
                $table->index(['employee_id', 'date']);
            });

            // Scoped partial unique constraint: only allow one non-deleted attendance record per employee per day.
            DB::statement('CREATE UNIQUE INDEX attendances_employee_date_unique ON attendances (employee_id, date) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
