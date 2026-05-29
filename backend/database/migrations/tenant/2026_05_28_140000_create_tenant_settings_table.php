<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant key/value settings. Each setting is namespaced by `group`
 * so the UI can render coherent sections (attendance, branding, etc.)
 * and `value` is JSON so we can store strings, arrays, booleans without
 * a schema change every time a new toggle lands.
 *
 * Unique on (group, key) to keep upserts simple. Soft-deletes are not
 * needed — settings are configuration, not records.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_settings')) return;

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group', 64);
            $table->string('key', 128);
            // jsonb on Postgres — typed lookups + indexing for the day
            // we want "find tenants where X". Driver-agnostic in Eloquent.
            $table->jsonb('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
