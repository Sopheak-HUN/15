<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_addresses')) {
            return;
        }

        Schema::create('employee_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            // `current` (where they live now), `permanent` (registered/ID),
            // `emergency` (parents/next-of-kin).
            $table->string('type', 16);

            $table->string('home_number', 64)->nullable();
            $table->string('street', 255)->nullable();

            // Cambodia administrative geography — store the raw MEF codes
            // (see backend/database/migrations/2026_05_26_120001_create_provinces_table.php).
            // No FK because those tables live in the central/landlord DB.
            $table->string('province_code', 16)->nullable();
            $table->string('district_code', 16)->nullable();
            $table->string('commune_code', 16)->nullable();
            $table->string('village_code', 16)->nullable();

            // Free-form group label used in Cambodian addresses for the
            // village sub-unit (e.g. "ក្រុមទី 4").
            $table->string('group', 32)->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['employee_id', 'type'], 'employee_addresses_employee_type_unique');
            $table->index('province_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_addresses');
    }
};
