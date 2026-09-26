<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurus_performances', function (Blueprint $table) {
            $table->string('age_division', 30)->default('dewasa')->after('category_type');
            $table->unsignedTinyInteger('judge_count')->default(6)->after('age_division');
            $table->decimal('score_min', 5, 2)->default(9)->after('judge_count');
            $table->decimal('score_max', 5, 2)->default(10)->after('score_min');
            $table->string('aggregation_method', 20)->default('median')->after('score_max');
        });
    }

    public function down(): void
    {
        Schema::table('jurus_performances', function (Blueprint $table) {
            $table->dropColumn([
                'age_division',
                'judge_count',
                'score_min',
                'score_max',
                'aggregation_method',
            ]);
        });
    }
};
