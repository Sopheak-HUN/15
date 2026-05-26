<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->string('code', 16)->primary();          // '01020101'
            $table->string('commune_code', 16);
            $table->string('name_kh');
            $table->string('name_en');
            $table->timestamps();

            $table->foreign('commune_code')
                ->references('code')->on('communes')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->index('commune_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
