<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MatchController extends Controller
{
    private const JUDGE_COUNT = 3;

    private const SCORE_FIELDS = [
        'hands_a', 'feet_a', 'falls_a', 'teguran_1_a', 'teguran_2_a', 'peringatan_1_a', 'peringatan_2_a',
        'hands_b', 'feet_b', 'falls_b', 'teguran_1_b', 'teguran_2_b', 'peringatan_1_b', 'peringatan_2_b',
    ];

    public function index()
    {
        $role = auth()->user()->role;
        abort_unless(in_array($role, ['admin', 'judge', 'participant', 'official'], true), 403);

        $query = DB::table('matches')
            ->join('categories', 'categories.id', '=', 'matches.category_id')
            ->leftJoin('athletes as athlete_a', 'athlete_a.id', '=', 'matches.athlete_a_id')
            ->leftJoin('athletes as athlete_b', 'athlete_b.id', '=', 'matches.athlete_b_id')
            ->select(
                'matches.*',
                'categories.name as category_name',
                'athlete_a.name as athlete_a_name',
                'athlete_b.name as athlete_b_name'
            );

        if ($role === 'judge') {
            $query->join('match_judges', 'match_judges.match_id', '=', 'matches.id')
                ->where('match_judges.judge_id', auth()->id());
        }

        $matches = $query->orderBy('matches.scheduled_at')->orderBy('matches.id')->get();
        $assignments = DB::table('match_judges')
            ->join('users as judge', 'judge.id', '=', 'match_judges.judge_id')
            ->whereIn('match_judges.match_id', $matches->pluck('id'))
            ->select('match_judges.*', 'judge.name as judge_name')
            ->orderBy('judge.name')
            ->get()
            ->groupBy('match_id');
        $roundScores = DB::table('match_judge_round_scores')
            ->join('match_judges', 'match_judges.id', '=', 'match_judge_round_scores.match_judge_id')
            ->whereIn('match_judges.match_id', $matches->pluck('id'))
            ->select('match_judge_round_scores.*', 'match_judges.match_id', 'match_judges.judge_id')
            ->orderBy('match_judge_round_scores.round')
            ->get()
            ->groupBy('match_judge_id');

        foreach ($matches as $match) {
            $matchAssignments = $assignments->get($match->id, collect());
            $judgeResults = $matchAssignments->filter(fn ($assignment) => $assignment->submitted_at !== null)
                ->map(function ($assignment) use ($roundScores, $match) {
                    $totals = [
                        'points_a' => 0,
                        'points_b' => 0,
                        'penalties_a' => 0,
                        'penalties_b' => 0,
                    ];
                    foreach ($roundScores->get($assignment->id, collect()) as $roundScore) {
                        $totals['points_a'] += $roundScore->hands_a + 2 * $roundScore->feet_a + 3 * $roundScore->falls_a;
                        $totals['points_b'] += $roundScore->hands_b + 2 * $roundScore->feet_b + 3 * $roundScore->falls_b;
                        $totals['penalties_a'] += $roundScore->teguran_1_a + 2 * $roundScore->teguran_2_a
                            + 5 * $roundScore->peringatan_1_a + 10 * $roundScore->peringatan_2_a;
                        $totals['penalties_b'] += $roundScore->teguran_1_b + 2 * $roundScore->teguran_2_b
                            + 5 * $roundScore->peringatan_1_b + 10 * $roundScore->peringatan_2_b;
                    }
                    $totals['net_a'] = $totals['points_a'] - $totals['penalties_a'];
                    $totals['net_b'] = $totals['points_b'] - $totals['penalties_b'];
                    $totals['vote'] = $totals['net_a'] === $totals['net_b']
                        ? null
                        : ($totals['net_a'] > $totals['net_b'] ? $match->athlete_a_id : $match->athlete_b_id);

                    return (object) [
                        'judge_id' => $assignment->judge_id,
                        'judge_name' => $assignment->judge_name,
                        ...$totals,
                    ];
                });

            $votesA = $judgeResults->where('vote', $match->athlete_a_id)->count();
            $votesB = $judgeResults->where('vote', $match->athlete_b_id)->count();
            $match->judge_results = $judgeResults;
            $match->votes_a = $votesA;
            $match->votes_b = $votesB;
            $match->provisional_winner_id = $votesA >= 2
                ? $match->athlete_a_id
                : ($votesB >= 2 ? $match->athlete_b_id : null);
            $match->provisional_result = $votesA >= 2
                ? 'Merah unggul suara mayoritas'
                : ($votesB >= 2 ? 'Biru unggul suara mayoritas' : 'Belum ada mayoritas');
        }

        return view('matches.index', compact('matches', 'assignments', 'roundScores'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $categories = DB::table('categories')->orderBy('name')->get();
        $athletes = DB::table('athletes')
            ->join('registrations', 'registrations.athlete_id', '=', 'athletes.id')
            ->where('registrations.status', 'approved')
            ->where('registrations.payment_status', 'verified')
            ->select('athletes.id', 'athletes.name', 'athletes.contingent_name', 'registrations.category_id')
            ->orderBy('athletes.name')
            ->get();
        $judges = User::where('role', 'judge')->orderBy('name')->get();

        return view('matches.create', compact('categories', 'athletes', 'judges'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'match_number' => ['required', 'string', 'max:30', 'unique:matches,match_number'],
            'category_id' => ['required', 'exists:categories,id'],
            'tatami' => ['required', 'string', 'max:50'],
            'scheduled_at' => ['required', 'date'],
            'athlete_a_id' => ['required', 'different:athlete_b_id', 'exists:athletes,id'],
            'athlete_b_id' => ['required', 'exists:athletes,id'],
            'judge_ids' => ['required', 'array', 'size:' . self::JUDGE_COUNT],
            'judge_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('role', 'judge')],
        ]);

        foreach (['athlete_a_id', 'athlete_b_id'] as $athleteKey) {
            $eligible = DB::table('registrations')
                ->where('athlete_id', $data[$athleteKey])
                ->where('category_id', $data['category_id'])
                ->where('status', 'approved')
                ->where('payment_status', 'verified')
                ->exists();

            if (!$eligible) {
                return back()->withErrors([
                    $athleteKey => 'Pesilat harus terdaftar pada kategori ini dan pendaftarannya sudah disetujui serta dibayar.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($data) {
            $matchId = DB::table('matches')->insertGetId([
                'match_number' => $data['match_number'],
                'category_id' => $data['category_id'],
                'tatami' => $data['tatami'],
                'scheduled_at' => $data['scheduled_at'],
                'athlete_a_id' => $data['athlete_a_id'],
                'athlete_b_id' => $data['athlete_b_id'],
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            DB::table('match_judges')->insert(array_map(
                fn (int $judgeId) => [
                    'match_id' => $matchId,
                    'judge_id' => $judgeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $data['judge_ids']
            ));
        });

        return redirect()->route('matches.index')->with('success', 'Jadwal Tanding berhasil dibuat dan ditugaskan kepada tiga juri.');
    }

    public function createJudge(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'judge',
        ]);

        return redirect()->route('matches.create')->with('success', 'Akun juri berhasil dibuat.');
    }

    public function score(Request $request, int $match)
    {
        abort_unless(auth()->user()->role === 'judge', 403);
        $assignment = DB::table('match_judges')
            ->where('match_id', $match)
            ->where('judge_id', auth()->id())
            ->first();
        abort_unless($assignment, 403);
        abort_if($assignment->submitted_at !== null, 409, 'Skor sudah dikirim dan tidak dapat diubah.');

        $rules = [];
        foreach ([1, 2, 3] as $round) {
            foreach (self::SCORE_FIELDS as $field) {
                $rules["rounds.{$round}.{$field}"] = ['required', 'integer', 'min:0', 'max:99'];
            }
            foreach (['disqualified_a', 'disqualified_b'] as $field) {
                $rules["rounds.{$round}.{$field}"] = ['nullable', 'boolean'];
            }
        }
        $data = $request->validate($rules);

        DB::transaction(function () use ($assignment, $data) {
            $locked = DB::table('match_judges')
                ->where('id', $assignment->id)
                ->whereNull('submitted_at')
                ->lockForUpdate()
                ->first();
            abort_unless($locked, 409, 'Skor sudah dikirim dan tidak dapat diubah.');

            $now = now();
            foreach ([1, 2, 3] as $round) {
                $entry = $data['rounds'][$round];
                $row = [
                    'match_judge_id' => $assignment->id,
                    'round' => $round,
                    'disqualified_a' => (bool) ($entry['disqualified_a'] ?? false),
                    'disqualified_b' => (bool) ($entry['disqualified_b'] ?? false),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach (self::SCORE_FIELDS as $field) {
                    $row[$field] = $entry[$field];
                }
                DB::table('match_judge_round_scores')->insert($row);
            }

            DB::table('match_judges')
                ->where('id', $assignment->id)
                ->whereNull('submitted_at')
                ->update(['submitted_at' => $now, 'updated_at' => $now]);
        });

        return redirect()->route('matches.index')->with('success', 'Skor tiga ronde berhasil dikirim dan terkunci.');
    }

    public function confirmResult(Request $request, int $match)
    {
        $this->ensureAdmin();

        $record = DB::table('matches')->where('id', $match)->first();
        abort_unless($record, 404);
        abort_if($record->official_result_at !== null, 409, 'Hasil pertandingan sudah disahkan dan terkunci.');

        $submittedCount = DB::table('match_judges')
            ->where('match_id', $match)
            ->whereNotNull('submitted_at')
            ->count();
        abort_unless($submittedCount === self::JUDGE_COUNT, 409, 'Tunggu sampai ketiga juri mengirim skor sebelum mengesahkan hasil.');

        $data = $request->validate([
            'result_type' => ['required', Rule::in(['menang_angka', 'menang_teknik', 'diskualifikasi', 'mengundurkan_diri', 'seri', 'lainnya'])],
            'winner_id' => ['nullable', 'integer', Rule::in([$record->athlete_a_id, $record->athlete_b_id])],
            'official_result_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['result_type'] === 'seri' && !empty($data['winner_id'])) {
            return back()->withErrors(['winner_id' => 'Hasil seri tidak boleh memiliki pemenang.'])->withInput();
        }
        if ($data['result_type'] !== 'seri' && empty($data['winner_id'])) {
            return back()->withErrors(['winner_id' => 'Pilih pemenang untuk hasil pertandingan ini.'])->withInput();
        }

        $majorityWinner = $this->provisionalWinner($match, $record->athlete_a_id, $record->athlete_b_id);
        if ($majorityWinner !== null && (int) ($data['winner_id'] ?? 0) !== (int) $majorityWinner
            && trim($data['official_result_notes'] ?? '') === '') {
            return back()->withErrors([
                'official_result_notes' => 'Catatan wajib diisi bila hasil resmi berbeda dari mayoritas skor juri.',
            ])->withInput();
        }

        $updated = DB::table('matches')
            ->where('id', $match)
            ->whereNull('official_result_at')
            ->update([
                'winner_id' => $data['winner_id'] ?? null,
                'result_type' => $data['result_type'],
                'official_result_notes' => $data['official_result_notes'] ?? null,
                'official_result_recorded_by' => auth()->id(),
                'official_result_at' => now(),
                'status' => $data['result_type'] === 'seri' ? 'draw' : 'completed',
                'updated_at' => now(),
            ]);
        abort_unless($updated === 1, 409, 'Hasil pertandingan sudah disahkan dan terkunci.');

        return redirect()->route('matches.index')->with('success', 'Hasil akhir Tanding disahkan dan dikunci.');
    }

    private function provisionalWinner(int $matchId, int $athleteA, int $athleteB): ?int
    {
        $assignments = DB::table('match_judges')
            ->where('match_id', $matchId)
            ->whereNotNull('submitted_at')
            ->get();
        $votesA = 0;
        $votesB = 0;

        foreach ($assignments as $assignment) {
            $scores = DB::table('match_judge_round_scores')
                ->where('match_judge_id', $assignment->id)
                ->get();
            $netA = $scores->sum(fn ($score) => $score->hands_a + 2 * $score->feet_a + 3 * $score->falls_a
                - $score->teguran_1_a - 2 * $score->teguran_2_a - 5 * $score->peringatan_1_a - 10 * $score->peringatan_2_a);
            $netB = $scores->sum(fn ($score) => $score->hands_b + 2 * $score->feet_b + 3 * $score->falls_b
                - $score->teguran_1_b - 2 * $score->teguran_2_b - 5 * $score->peringatan_1_b - 10 * $score->peringatan_2_b);

            if ($netA > $netB) {
                $votesA++;
            } elseif ($netB > $netA) {
                $votesB++;
            }
        }

        return $votesA >= 2 ? $athleteA : ($votesB >= 2 ? $athleteB : null);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }
}
