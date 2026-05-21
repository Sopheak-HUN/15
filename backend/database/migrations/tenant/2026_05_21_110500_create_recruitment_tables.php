<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('vacancies')) {
            Schema::create('vacancies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('reference', 32)->unique();    // VAC-2026-0001
                $table->uuid('department_id')->nullable();
                $table->uuid('position_id')->nullable();
                $table->text('description')->nullable();
                $table->text('requirements')->nullable();
                $table->string('location')->nullable();
                $table->decimal('salary_min', 14, 2)->nullable();
                $table->decimal('salary_max', 14, 2)->nullable();
                $table->string('employment_type', 32)->default('full_time');
                $table->string('status', 32)->default('draft'); // workflow_statuses(hrm.vacancy)
                $table->date('opens_at')->nullable();
                $table->date('closes_at')->nullable();
                $table->uuid('hiring_manager_id')->nullable(); // employees.id
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
                $table->foreign('hiring_manager_id')->references('id')->on('employees')->nullOnDelete();
                $table->index('status');
            });
        }

        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vacancy_id');
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone', 32)->nullable();
                $table->string('resume_path')->nullable();
                $table->string('cover_letter_path')->nullable();
                $table->decimal('expected_salary', 14, 2)->nullable();
                $table->string('status', 32)->default('applied'); // workflow_statuses(hrm.application)
                $table->unsignedTinyInteger('rating')->nullable(); // 1..5 aggregate
                $table->uuid('employee_id')->nullable();          // set on convert-to-employee
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('vacancy_id')->references('id')->on('vacancies')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
                $table->index(['vacancy_id', 'status']);
                $table->index('email');
            });
        }

        if (! Schema::hasTable('interviews')) {
            Schema::create('interviews', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('application_id');
                $table->timestamp('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(45);
                $table->string('mode', 32)->default('virtual'); // virtual | onsite | phone
                $table->string('location')->nullable();
                $table->string('round_label')->nullable();
                $table->string('status', 32)->default('scheduled'); // scheduled|completed|cancelled
                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
                $table->index(['application_id', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('interview_feedbacks')) {
            Schema::create('interview_feedbacks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('interview_id');
                $table->uuid('reviewer_id')->nullable(); // employees.id
                $table->unsignedTinyInteger('rating')->nullable(); // 1..5
                $table->enum('recommendation', ['hire', 'reject', 'hold'])->nullable();
                $table->text('strengths')->nullable();
                $table->text('weaknesses')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('interview_id')->references('id')->on('interviews')->cascadeOnDelete();
                $table->foreign('reviewer_id')->references('id')->on('employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_feedbacks');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('vacancies');
    }
};
