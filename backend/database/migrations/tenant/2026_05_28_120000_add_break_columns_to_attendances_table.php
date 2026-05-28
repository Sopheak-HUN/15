<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends attendances with the 4-punch day model used by Cambodian
 * HR practice: check_in → break_out (lunch) → break_in (back from
 * lunch) → check_out. Each "half" gets its own status (morning,
 * afternoon) so a "half-day" can be a real outcome of timestamps
 * rather than a manual entry.
 *
 * Legacy `status` column stays in place as the overall worst-of-the-two
 * fallback — older queries keep working until consumers switch to the
 * per-half fields.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) return;

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'break_out')) {
                $table->dateTime('break_out')->nullable()->after('check_in');
            }
            if (! Schema::hasColumn('attendances', 'break_in')) {
                $table->dateTime('break_in')->nullable()->after('break_out');
            }
            if (! Schema::hasColumn('attendances', 'morning_status')) {
                // present / late / absent / on_leave — same vocabulary
                // as the existing `status` column so the UI can share
                // the severity helper.
                $table->string('morning_status', 32)->nullable()->after('check_out');
            }
            if (! Schema::hasColumn('attendances', 'afternoon_status')) {
                $table->string('afternoon_status', 32)->nullable()->after('morning_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) return;

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['break_out', 'break_in', 'morning_status', 'afternoon_status'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
