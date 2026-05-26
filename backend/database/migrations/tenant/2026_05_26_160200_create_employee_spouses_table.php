<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_spouses')) {
            return;
        }

        Schema::create('employee_spouses', function (Blueprint $table) {
            // 1:1 with employees — employee_id is the PK.
            $table->uuid('employee_id')->primary();

            $table->string('name', 160)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('education', 32)->nullable();   // matches `spouseEducationLevels` in the wizard
            $table->string('occupation', 160)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_spouses');
    }
};
