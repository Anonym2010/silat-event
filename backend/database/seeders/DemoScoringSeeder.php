<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoScoringSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $judges = collect();
            for ($number = 1; $number <= 6; $number++) {
                $email = $number === 1
                    ? 'juri.demo@silatevent.test'
                    : "juri.demo{$number}@silatevent.test";
                $judges->push(User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $number === 1 ? 'Juri Demo' : "Juri Demo {$number}",
                        'password' => 'JuriDemo#2026',
                        'role' => 'judge',
                    ]
                ));
            }

            $owner = User::where('email', 'official@silatevent.test')->firstOrFail();
            $category = DB::table('categories')
                ->where('name', 'Dewasa Putra - Kelas C')
                ->firstOrFail();
            $athletes = collect();

            foreach ([
                ['name' => 'Pesilat Demo Merah', 'birth_date' => '2005-01-01'],
                ['name' => 'Pesilat Demo Biru', 'birth_date' => '2005-06-01'],
            ] as $athleteData) {
                $athleteId = DB::table('athletes')->where([
                    'name' => $athleteData['name'],
                    'contingent_name' => 'Kontingen Demo',
                ])->value('id');

                if (!$athleteId) {
                    $athleteId = DB::table('athletes')->insertGetId([
                        'user_id' => $owner->id,
                        'contingent_name' => 'Kontingen Demo',
                        'name' => $athleteData['name'],
                        'gender' => 'male',
                        'birth_date' => $athleteData['birth_date'],
                        'weight' => 58,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('registrations')->updateOrInsert(
                    ['athlete_id' => $athleteId, 'category_id' => $category->id],
                    [
                        'user_id' => $owner->id,
                        'status' => 'approved',
                        'payment_status' => 'verified',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $athletes->push($athleteId);
            }

            $matchId = DB::table('matches')->where('match_number', 'DEMO-01')->value('id');
            if (!$matchId) {
                $matchId = DB::table('matches')->insertGetId([
                    'match_number' => 'DEMO-01',
                    'category_id' => $category->id,
                    'tatami' => 'Gelanggang Demo',
                    'scheduled_at' => '2026-12-12 09:00:00',
                    'athlete_a_id' => $athletes[0],
                    'athlete_b_id' => $athletes[1],
                    'status' => 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($judges->take(3) as $judge) {
                DB::table('match_judges')->updateOrInsert(
                    ['match_id' => $matchId, 'judge_id' => $judge->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            $performanceId = DB::table('jurus_performances')
                ->where('entry_name', 'Penampilan Jurus Demo')
                ->where('contingent_name', 'Kontingen Demo')
                ->value('id');
            if (!$performanceId) {
                $performanceId = DB::table('jurus_performances')->insertGetId([
                    'category_type' => 'tunggal',
                    'age_division' => 'dewasa',
                    'judge_count' => 6,
                    'score_min' => 9,
                    'score_max' => 10,
                    'aggregation_method' => 'median',
                    'phase' => 'final',
                    'entry_name' => 'Penampilan Jurus Demo',
                    'contingent_name' => 'Kontingen Demo',
                    'performers' => 'Pesilat Demo Merah',
                    'scheduled_at' => '2026-12-12 13:00:00',
                    'duration_seconds' => 180,
                    'supervisor_deductions' => 0,
                    'technical_notes' => 'Data uji/demo, bukan hasil pertandingan resmi.',
                    'disqualified' => false,
                    'created_by' => User::where('role', 'admin')->value('id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($judges as $judge) {
                DB::table('jurus_scores')->updateOrInsert(
                    ['performance_id' => $performanceId, 'judge_id' => $judge->id],
                    ['judge_deductions' => 0, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
    }
}
