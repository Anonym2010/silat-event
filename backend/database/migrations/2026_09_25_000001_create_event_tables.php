<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('participant')->after('email'));
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->string('location');
            $table->date('start_date'); $table->date('end_date'); $table->date('registration_deadline'); $table->string('status')->default('open'); $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('gender'); $table->unsignedTinyInteger('age_min'); $table->unsignedTinyInteger('age_max');
            $table->decimal('weight_min', 5, 2); $table->decimal('weight_max', 5, 2); $table->unsignedInteger('fee')->default(0); $table->timestamps();
        });
        Schema::create('athletes', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('contingent_name'); $table->string('name');
            $table->string('gender'); $table->date('birth_date'); $table->decimal('weight', 5, 2); $table->timestamps();
        });
        Schema::create('registrations', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete(); $table->string('status')->default('pending'); $table->string('payment_status')->default('unpaid');
            $table->string('payment_proof')->nullable(); $table->text('rejection_reason')->nullable(); $table->timestamps();
        });
        Schema::create('matches', function (Blueprint $table) {
            $table->id(); $table->foreignId('category_id')->constrained(); $table->string('tatami')->nullable(); $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('athlete_a_id')->nullable()->constrained('athletes')->nullOnDelete(); $table->foreignId('athlete_b_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('athletes')->nullOnDelete(); $table->unsignedSmallInteger('score_a')->nullable(); $table->unsignedSmallInteger('score_b')->nullable();
            $table->string('status')->default('scheduled'); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('matches'); Schema::dropIfExists('registrations'); Schema::dropIfExists('athletes'); Schema::dropIfExists('categories'); Schema::dropIfExists('events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
