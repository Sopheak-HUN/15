<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Khmer-script names
            $table->string('first_name_kh', 120)->nullable()->after('last_name');
            $table->string('last_name_kh', 120)->nullable()->after('first_name_kh');

            // Cambodian-HR identifiers separate from base employment
            $table->string('nssf_id', 64)->nullable()->after('employee_id');
            $table->string('role_name', 120)->nullable()->after('position_id');
            $table->string('office_phone', 32)->nullable()->after('phone');
            $table->string('contact_phone', 32)->nullable()->after('office_phone');
            $table->string('nationality', 64)->nullable()->after('country');

            // Personal
            $table->string('religion', 32)->nullable();
            $table->string('marital_status', 16)->nullable();
            $table->string('blood_group', 8)->nullable();
            $table->unsignedSmallInteger('children_count')->default(0);

            // Identification document (one primary card per employee; richer
            // history can live in a future employee_identifications table).
            // id_card_number is the same column previously called `national_id`
            // semantically — keep `national_id` for legacy callers and add
            // `id_card_number` as the canonical encrypted store going forward.
            $table->string('identification_type', 32)->nullable();
            $table->text('id_card_number')->nullable();        // encrypted in model
            $table->date('id_issued_date')->nullable();
            $table->string('id_issued_by', 160)->nullable();
            $table->string('id_issued_place', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'first_name_kh', 'last_name_kh',
                'nssf_id', 'role_name', 'office_phone', 'contact_phone', 'nationality',
                'religion', 'marital_status', 'blood_group', 'children_count',
                'identification_type', 'id_card_number',
                'id_issued_date', 'id_issued_by', 'id_issued_place',
            ]);
        });
    }
};
