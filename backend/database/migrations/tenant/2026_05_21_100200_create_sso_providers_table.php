<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sso_providers')) {
            Schema::create('sso_providers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');                       // human label, e.g. "Acme Google Workspace"
                $table->enum('protocol', ['oidc', 'saml']);
                $table->string('issuer')->nullable();         // OIDC issuer URL or SAML EntityID
                $table->string('client_id')->nullable();
                $table->text('client_secret')->nullable();    // encrypted at app layer via cast
                $table->string('discovery_url')->nullable();  // OIDC well-known endpoint
                $table->string('redirect_uri')->nullable();
                $table->json('scopes')->nullable();           // ["openid","profile","email"]
                $table->json('metadata')->nullable();         // protocol-specific extras (SAML cert, etc.)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['protocol', 'issuer']);
            });
        }

        if (! Schema::hasColumn('users', 'sso_provider_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('sso_provider_id')->nullable()->after('mfa_enabled');
                $table->string('sso_subject')->nullable()->after('sso_provider_id'); // provider's stable user id (sub claim)
                $table->index(['sso_provider_id', 'sso_subject']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sso_subject')) {
                $table->dropColumn('sso_subject');
            }
            if (Schema::hasColumn('users', 'sso_provider_id')) {
                $table->dropIndex(['sso_provider_id', 'sso_subject']);
                $table->dropColumn('sso_provider_id');
            }
        });
        Schema::dropIfExists('sso_providers');
    }
};
