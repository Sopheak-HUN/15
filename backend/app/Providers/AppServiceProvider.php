<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Models\PassportClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::useClientModel(PassportClient::class);

        // Pin Passport keys to the un-tenancied storage path. Stancl's
        // FilesystemTenancyBootstrapper rewrites storage_path() to a
        // tenant-suffixed path, which would otherwise break key loading.
        Passport::loadKeysFrom(base_path('storage'));
    }
}
