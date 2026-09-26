<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('match_number', 30)->nullable()->after('id');
            $table->foreignId('referee_id')->nullable()->after('category_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referee_id');
            $table->dropColumn('match_number');
        });
    }
};
