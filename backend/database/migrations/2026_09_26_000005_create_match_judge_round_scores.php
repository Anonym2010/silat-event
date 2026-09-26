<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_judge_round_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_judge_id')->constrained('match_judges')->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedTinyInteger('hands_a')->default(0);
            $table->unsignedTinyInteger('feet_a')->default(0);
            $table->unsignedTinyInteger('falls_a')->default(0);
            $table->unsignedTinyInteger('teguran_1_a')->default(0);
            $table->unsignedTinyInteger('teguran_2_a')->default(0);
            $table->unsignedTinyInteger('peringatan_1_a')->default(0);
            $table->unsignedTinyInteger('peringatan_2_a')->default(0);
            $table->boolean('disqualified_a')->default(false);
            $table->unsignedTinyInteger('hands_b')->default(0);
            $table->unsignedTinyInteger('feet_b')->default(0);
            $table->unsignedTinyInteger('falls_b')->default(0);
            $table->unsignedTinyInteger('teguran_1_b')->default(0);
            $table->unsignedTinyInteger('teguran_2_b')->default(0);
            $table->unsignedTinyInteger('peringatan_1_b')->default(0);
            $table->unsignedTinyInteger('peringatan_2_b')->default(0);
            $table->boolean('disqualified_b')->default(false);
            $table->timestamps();
            $table->unique(['match_judge_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_judge_round_scores');
    }
};
