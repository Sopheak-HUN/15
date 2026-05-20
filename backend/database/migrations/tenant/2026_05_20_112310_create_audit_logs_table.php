<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('audit_logs')){
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->string('user_id')->nullable();
                $table->string('action');
                $table->uuidMorphs('auditable');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();
                
                $table->index('tenant_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
