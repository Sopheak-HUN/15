<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employee_educations')) {
            return;
        }

        Schema::create('employee_educations', function (Blueprint $table) {
            // 1:N — the wizard seeds one row but multiple degrees are typical
            // over a career, so the table is shaped for history.
            $table->uuid('id')->primary();
            $table->uuid('employee_id');

            $table->string('level', 32)->nullable();        // matches `degreeLevels` in the wizard
            $table->string('major_subject', 160)->nullable();
            $table->string('status', 32)->nullable();        // ongoing / completed / dropped, etc.
            $table->string('university_school', 200)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_educations');
    }
};
