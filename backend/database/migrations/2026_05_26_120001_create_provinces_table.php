<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('code', 16)->primary();   // MEF code, e.g. '01'
            $table->string('name_kh');
            $table->string('name_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
