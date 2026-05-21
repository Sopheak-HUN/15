<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('status');
            }
            if (! Schema::hasColumn('tenants', 'primary_color')) {
                $table->string('primary_color', 16)->nullable()->after('logo_path');
            }
            if (! Schema::hasColumn('tenants', 'secondary_color')) {
                $table->string('secondary_color', 16)->nullable()->after('primary_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'primary_color', 'secondary_color']);
        });
    }
};
