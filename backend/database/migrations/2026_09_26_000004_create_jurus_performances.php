<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurus_performances', function (Blueprint $table) {
            $table->id();
            $table->string('category_type', 30);
            $table->string('phase', 20);
            $table->string('entry_name', 150);
            $table->string('contingent_name', 150);
            $table->text('performers');
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedTinyInteger('supervisor_deductions')->default(0);
            $table->text('technical_notes')->nullable();
            $table->boolean('disqualified')->default(false);
            $table->string('disqualification_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('jurus_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('jurus_performances')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('base_score', 4, 2)->nullable();
            $table->unsignedTinyInteger('judge_deductions')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_id', 'judge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurus_scores');
        Schema::dropIfExists('jurus_performances');
    }
};
