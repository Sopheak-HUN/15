<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->string('code', 16)->primary();          // '010201'
            $table->string('district_code', 16);
            $table->string('name_kh');
            $table->string('name_en');
            $table->timestamps();

            $table->foreign('district_code')
                ->references('code')->on('districts')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->index('district_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
