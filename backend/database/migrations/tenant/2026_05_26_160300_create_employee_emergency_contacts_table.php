<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_emergency_contacts')) {
            return;
        }

        Schema::create('employee_emergency_contacts', function (Blueprint $table) {
            // 1:1 with employees. Address fields are intentionally NOT here —
            // they live in employee_addresses with type='emergency'.
            $table->uuid('employee_id')->primary();

            $table->string('father_name', 160)->nullable();
            $table->string('father_occupation', 160)->nullable();
            $table->string('mother_name', 160)->nullable();
            $table->string('mother_occupation', 160)->nullable();

            $table->string('phone_number', 32)->nullable();
            $table->string('home_phone', 32)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_emergency_contacts');
    }
};
