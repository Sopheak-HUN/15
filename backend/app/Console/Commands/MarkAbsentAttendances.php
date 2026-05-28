<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenants\Modules\HRM\Services\AttendanceService;
use Illuminate\Console\Command;

/**
 * Backfill absent rows for the previous day (or a date passed via the
 * `--date=YYYY-MM-DD` option) across every tenant.
 *
 * Cron suggestion: run a few minutes after midnight so the previous
 * day is complete:
 *
 *   0 1 * * *  php artisan attendance:mark-absent
 *
 * Wire up via `app/Console/Kernel.php` or, in Laravel 11+, in the
 * scheduler closure inside `routes/console.php`.
 */
class MarkAbsentAttendances extends Command
{
    protected $signature = 'attendance:mark-absent {--date= : YYYY-MM-DD; defaults to yesterday}';
    protected $description = 'Insert/update absent rows for employees who did not punch in on the given date.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();
        $this->info("Marking absent rows for {$date} across all tenants…");

        $grandTotal = 0;
        Tenant::all()->each(function (Tenant $tenant) use ($date, &$grandTotal) {
            $tenant->run(function () use ($date, $tenant, &$grandTotal) {
                /** @var AttendanceService $svc */
                $svc = app(AttendanceService::class);
                $touched = $svc->markAbsentForDate($date);
                $this->line(sprintf('  tenant=%s  rows_touched=%d', $tenant->getTenantKey(), $touched));
                $grandTotal += $touched;
            });
        });

        $this->info("Done. Total rows touched: {$grandTotal}");
        return Command::SUCCESS;
    }
}
