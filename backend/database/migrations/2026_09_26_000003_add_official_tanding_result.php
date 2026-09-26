<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('result_type')->nullable()->after('status');
            $table->string('official_result_reference', 100)->nullable()->after('result_type');
            $table->text('official_result_notes')->nullable()->after('official_result_reference');
            $table->foreignId('official_result_recorded_by')->nullable()->after('official_result_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('official_result_at')->nullable()->after('official_result_recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('official_result_recorded_by');
            $table->dropColumn([
                'result_type',
                'official_result_reference',
                'official_result_notes',
                'official_result_at',
            ]);
        });
    }
};
