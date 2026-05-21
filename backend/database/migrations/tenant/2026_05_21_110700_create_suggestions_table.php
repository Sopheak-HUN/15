<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('suggestions')) {
            return;
        }

        Schema::create('suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id')->nullable();           // null = anonymous
            $table->string('category', 64)->default('general'); // idea | concern | whistleblower | ...
            $table->string('title');
            $table->text('body');
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 32)->default('new');       // new | acknowledged | actioned | dismissed
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('employees')->nullOnDelete();
            $table->index(['status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
