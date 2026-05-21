<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('employee_notes')) {
            Schema::create('employee_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('author_id')->nullable();  // employees.id (manager)
                $table->string('category', 32)->default('general'); // general | performance | disciplinary | praise
                $table->string('title')->nullable();
                $table->text('body');
                $table->boolean('is_private')->default(true);
                $table->boolean('is_disciplinary')->default(false);
                $table->date('incident_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('author_id')->references('id')->on('employees')->nullOnDelete();
                $table->index(['employee_id', 'category']);
            });
        }

        if (! Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->string('title');
                $table->string('category', 32)->default('contract'); // contract | id | certificate | other
                $table->string('file_path');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->uuid('uploaded_by')->nullable();  // users.id
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->index(['employee_id', 'category']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_notes');
    }
};
