<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->string('nik', 32)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('nik');
            $table->string('photo_path')->nullable()->after('weight');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('identity_document_path')->nullable()->after('payment_proof');
            $table->string('health_document_path')->nullable()->after('identity_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn(['identity_document_path', 'health_document_path']));
        Schema::table('athletes', fn (Blueprint $table) => $table->dropColumn(['nik', 'phone', 'photo_path']));
    }
};
