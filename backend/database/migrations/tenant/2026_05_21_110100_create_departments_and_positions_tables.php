<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code', 32)->unique();
                $table->uuid('parent_id')->nullable();
                $table->uuid('manager_id')->nullable(); // employees.id, FK added once employees table exists
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

            // Self-referencing FK must be added in a separate ALTER, otherwise
            // Postgres can't yet match the constraint to the just-declared PK.
            Schema::table('departments', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('departments')->nullOnDelete();
                $table->index('parent_id');
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('code', 32)->unique();
                $table->uuid('department_id')->nullable();
                $table->decimal('min_salary', 14, 2)->nullable();
                $table->decimal('max_salary', 14, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                $table->index('department_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
