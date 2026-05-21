<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('appraisal_cycles')) {
            Schema::create('appraisal_cycles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date');
                $table->json('rating_scale')->nullable(); // [{value:1,label:"Below"},...]
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appraisals')) {
            Schema::create('appraisals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cycle_id');
                $table->uuid('employee_id');
                $table->uuid('reviewer_id')->nullable();   // employees.id (manager)
                $table->string('status', 32)->default('draft'); // workflow_statuses(hrm.appraisal)
                $table->decimal('overall_score', 5, 2)->nullable();
                $table->text('manager_comments')->nullable();
                $table->text('employee_comments')->nullable();
                $table->json('responses')->nullable();     // freeform competency answers
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->foreign('cycle_id')->references('id')->on('appraisal_cycles')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('reviewer_id')->references('id')->on('employees')->nullOnDelete();
                $table->unique(['cycle_id', 'employee_id']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('appraisal_cycles');
    }
};
