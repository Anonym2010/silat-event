<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboards_are_specific_to_each_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Panitia')
            ->assertSee('Atur Jadwal');

        auth()->logout();

        $this->actingAs(User::factory()->create(['role' => 'judge']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Juri')
            ->assertSee('Skoring Tanding')
            ->assertSee('Penampilan Jurus Saya')
            ->assertDontSee('Daftarkan Pesilat');

        auth()->logout();

        $this->actingAs(User::factory()->create(['role' => 'official']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Official')
            ->assertSee('Pendaftaran Pesilat Saya')
            ->assertSee('Daftarkan Pesilat');
    }

    public function test_admin_schedules_tanding_and_assigns_three_judges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(3)->create(['role' => 'judge']);
        [$categoryId, $athleteA, $athleteB] = $this->createEligibleAthletes();

        $this->actingAs($admin)->post(route('matches.store'), [
            'match_number' => 'A-01',
            'category_id' => $categoryId,
            'tatami' => 'Gelanggang 1',
            'scheduled_at' => '2026-12-12T09:00',
            'athlete_a_id' => $athleteA,
            'athlete_b_id' => $athleteB,
            'judge_ids' => $judges->pluck('id')->all(),
        ])->assertRedirect(route('matches.index'));

        $this->assertDatabaseHas('matches', [
            'match_number' => 'A-01',
            'athlete_a_id' => $athleteA,
            'athlete_b_id' => $athleteB,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseCount('match_judges', 3);
    }

    public function test_assigned_tanding_judge_submits_three_rounds_and_admin_can_recap(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(3)->create(['role' => 'judge']);
        [$categoryId, $athleteA, $athleteB] = $this->createEligibleAthletes();
        $matchId = $this->createMatch($categoryId, $athleteA, $athleteB);
        DB::table('match_judges')->insert(array_map(
            fn (User $judge) => [
                'match_id' => $matchId,
                'judge_id' => $judge->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $judges->all()
        ));

        $payload = $this->tandingScorePayload();
        $payload['rounds'][1]['hands_a'] = 2;
        $payload['rounds'][1]['feet_a'] = 1;
        $payload['rounds'][1]['falls_a'] = 1;
        $payload['rounds'][1]['teguran_1_a'] = 1;

        $this->actingAs(User::factory()->create(['role' => 'judge']))
            ->patch(route('matches.score', $matchId), $payload)
            ->assertForbidden();

        $this->actingAs($judges[0])
            ->patch(route('matches.score', $matchId), $payload)
            ->assertRedirect(route('matches.index'));

        $this->assertDatabaseCount('match_judge_round_scores', 3);
        $this->assertDatabaseHas('match_judge_round_scores', [
            'match_judge_id' => DB::table('match_judges')->where('match_id', $matchId)->where('judge_id', $judges[0]->id)->value('id'),
            'round' => 1,
            'hands_a' => 2,
            'feet_a' => 1,
            'falls_a' => 1,
            'teguran_1_a' => 1,
        ]);

        $this->actingAs($judges[0])
            ->patch(route('matches.score', $matchId), $payload)
            ->assertStatus(409);

        $this->actingAs($judges[0])->get(route('matches.index'))
            ->assertOk()
            ->assertSee('Skor tiga ronde Anda sudah terkirim')
            ->assertDontSee($judges[1]->name);

        $oppositePayload = $this->tandingScorePayload();
        $oppositePayload['rounds'][1]['hands_b'] = 10;
        $this->actingAs($judges[1])
            ->patch(route('matches.score', $matchId), $payload)
            ->assertRedirect(route('matches.index'));
        $this->actingAs($judges[2])
            ->patch(route('matches.score', $matchId), $oppositePayload)
            ->assertRedirect(route('matches.index'));

        $this->actingAs($admin)->get(route('matches.index'))
            ->assertOk()
            ->assertSee($judges[0]->name)
            ->assertSee('Hasil sementara: Merah unggul suara mayoritas')
            ->assertSeeText('Suara berdasarkan perbandingan net tiap juri: Merah 2, Biru 1');

        $this->actingAs($admin)->patch(route('matches.confirm-result', $matchId), [
            'result_type' => 'menang_angka',
            'winner_id' => $athleteB,
        ])->assertSessionHasErrors('official_result_notes');

        $this->actingAs($admin)->patch(route('matches.confirm-result', $matchId), [
            'result_type' => 'menang_angka',
            'winner_id' => $athleteA,
        ])->assertRedirect(route('matches.index'));

        $this->assertDatabaseHas('matches', [
            'id' => $matchId,
            'winner_id' => $athleteA,
            'result_type' => 'menang_angka',
            'status' => 'completed',
        ]);
    }

    public function test_jurus_uses_six_judges_median_and_supervisor_deduction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(6)->create(['role' => 'judge']);
        $this->actingAs($admin)->post(route('jurus.store'), [
            'category_type' => 'tunggal',
            'age_division' => 'dewasa',
            'phase' => 'final',
            'entry_name' => 'Atlet Uji',
            'contingent_name' => 'Kontingen Uji',
            'performers' => 'Atlet Uji',
            'duration_seconds' => 180,
            'supervisor_deductions' => 2,
            'judge_ids' => $judges->pluck('id')->all(),
        ])->assertRedirect(route('jurus.index'));

        $performanceId = DB::table('jurus_performances')->value('id');
        $this->assertDatabaseCount('jurus_scores', 6);

        foreach ($judges as $index => $judge) {
            $this->actingAs($judge)->patch(route('jurus.score', $performanceId), [
                'base_score' => '9.50',
                'judge_deductions' => $index === 0 ? 1 : 0,
            ])->assertRedirect(route('jurus.index'));
        }

        $this->actingAs($admin)->get(route('jurus.index'))
            ->assertOk()
            ->assertSee('Median nilai juri:')
            ->assertSee('9.50')
            ->assertSee('8.50');
    }

    public function test_judges_only_see_their_own_score_and_cannot_change_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(6)->create(['role' => 'judge']);
        $this->actingAs($admin)->post(route('jurus.store'), [
            'category_type' => 'ganda',
            'age_division' => 'dewasa',
            'phase' => 'penyisihan',
            'entry_name' => 'Duo Uji',
            'contingent_name' => 'Kontingen Uji',
            'performers' => "Pesilat Satu\nPesilat Dua",
            'supervisor_deductions' => 0,
            'judge_ids' => $judges->pluck('id')->all(),
        ])->assertRedirect(route('jurus.index'));

        $performanceId = DB::table('jurus_performances')->value('id');
        foreach ([[$judges[0], '9.75'], [$judges[1], '9.88']] as [$judge, $score]) {
            $this->actingAs($judge)->patch(route('jurus.score', $performanceId), [
                'base_score' => $score,
            ])->assertRedirect(route('jurus.index'));
        }

        $this->actingAs($judges[0])->get(route('jurus.index'))
            ->assertOk()
            ->assertSee('9.75')
            ->assertDontSee('9.88');

        $this->actingAs($judges[0])->patch(route('jurus.score', $performanceId), [
            'base_score' => '9.90',
        ])->assertStatus(409);
    }

    public function test_youth_jurus_uses_even_panel_and_technical_delegate_scoring_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(4)->create(['role' => 'judge']);
        $payload = [
            'category_type' => 'solo_kreatif',
            'age_division' => 'pra_remaja',
            'phase' => 'penyisihan',
            'entry_name' => 'Solo Kreatif Uji',
            'contingent_name' => 'Kontingen Uji',
            'performers' => 'Pesilat Uji',
            'duration_seconds' => 90,
            'supervisor_deductions' => 0,
            'score_min' => 0,
            'score_max' => 100,
            'aggregation_method' => 'individual',
            'judge_ids' => $judges->pluck('id')->all(),
        ];

        $this->actingAs($admin)->post(route('jurus.store'), $payload)
            ->assertRedirect(route('jurus.index'));

        $this->assertDatabaseHas('jurus_performances', [
            'category_type' => 'solo_kreatif',
            'age_division' => 'pra_remaja',
            'judge_count' => 4,
            'score_min' => 0,
            'score_max' => 100,
            'aggregation_method' => 'individual',
        ]);

        $performanceId = DB::table('jurus_performances')->where('entry_name', 'Solo Kreatif Uji')->value('id');
        $this->actingAs($judges[0])->patch(route('jurus.score', $performanceId), [
            'base_score' => '85.50',
        ])->assertRedirect(route('jurus.index'));
        $this->actingAs($admin)->get(route('jurus.index'))
            ->assertOk()
            ->assertSee('1 / 4 juri mengirim')
            ->assertSee('Solo Kreatif');

        $payload['entry_name'] = 'Tunggal Bebas Anak';
        $payload['category_type'] = 'tunggal_bebas';
        $this->actingAs($admin)->post(route('jurus.store'), $payload)
            ->assertSessionHasErrors('category_type');
    }

    public function test_registration_documents_are_only_available_to_the_owner_or_admin(): void
    {
        Storage::fake('local');
        [, $athleteId] = $this->createEligibleAthletes();
        $athlete = DB::table('athletes')->where('id', $athleteId)->first();
        $owner = User::findOrFail($athlete->user_id);
        $registrationId = DB::table('registrations')->where('athlete_id', $athleteId)->value('id');
        Storage::disk('local')->put('athletes/photos/private-test.jpg', 'test photo');
        DB::table('athletes')->where('id', $athleteId)->update(['photo_path' => 'athletes/photos/private-test.jpg']);

        $this->actingAs($owner)
            ->get(route('registrations.document', [$registrationId, 'photo']))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('registrations.document', [$registrationId, 'photo']))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('registrations.document', [$registrationId, 'photo']))
            ->assertOk();
    }

    private function createMatch(int $categoryId, int $athleteA, int $athleteB): int
    {
        return DB::table('matches')->insertGetId([
            'match_number' => 'TEST-01',
            'category_id' => $categoryId,
            'tatami' => 'Gelanggang 1',
            'scheduled_at' => '2026-12-12 10:00:00',
            'athlete_a_id' => $athleteA,
            'athlete_b_id' => $athleteB,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tandingScorePayload(): array
    {
        $rounds = [];
        foreach ([1, 2, 3] as $round) {
            foreach ([
                'hands_a', 'feet_a', 'falls_a', 'teguran_1_a', 'teguran_2_a', 'peringatan_1_a', 'peringatan_2_a',
                'hands_b', 'feet_b', 'falls_b', 'teguran_1_b', 'teguran_2_b', 'peringatan_1_b', 'peringatan_2_b',
            ] as $field) {
                $rounds[$round][$field] = 0;
            }
        }

        return ['rounds' => $rounds];
    }

    private function createEligibleAthletes(): array
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Dewasa Putra - Kelas C',
            'gender' => 'male',
            'age_min' => 18,
            'age_max' => 35,
            'weight_min' => 55,
            'weight_max' => 60,
            'fee' => 250000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $athleteIds = [];

        foreach (['Atlet Satu', 'Atlet Dua'] as $name) {
            $owner = User::factory()->create();
            $athleteId = DB::table('athletes')->insertGetId([
                'user_id' => $owner->id,
                'contingent_name' => 'Kontingen Uji',
                'name' => $name,
                'gender' => 'male',
                'birth_date' => '2005-01-01',
                'weight' => 58,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('registrations')->insert([
                'user_id' => $owner->id,
                'athlete_id' => $athleteId,
                'category_id' => $categoryId,
                'status' => 'approved',
                'payment_status' => 'verified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $athleteIds[] = $athleteId;
        }

        return [$categoryId, ...$athleteIds];
    }
}
