<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'parent_role_id')) {
                $table->uuid('parent_role_id')->nullable()->after('description');
                $table->foreign('parent_role_id')
                    ->references('id')
                    ->on('roles')
                    ->nullOnDelete();
                $table->index('parent_role_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['parent_role_id']);
            $table->dropIndex(['parent_role_id']);
            $table->dropColumn('parent_role_id');
        });
    }
};
