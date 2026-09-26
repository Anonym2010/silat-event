<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JurusController extends Controller
{
    private const CATEGORIES = ['tunggal', 'tunggal_bebas', 'solo_kreatif', 'ganda', 'regu'];
    private const ADULT_CATEGORIES = ['tunggal', 'tunggal_bebas', 'ganda', 'regu'];
    private const YOUTH_CATEGORIES = ['tunggal', 'solo_kreatif', 'ganda', 'regu'];
    private const ADULT_DIVISIONS = ['remaja', 'dewasa', 'master'];
    private const YOUTH_DIVISIONS = ['pra_usia_dini', 'usia_dini', 'pra_remaja'];
    private const PHASES = ['penyisihan', 'semifinal', 'final'];
    private const JUDGE_COUNT = 6;

    public function index()
    {
        $isJudge = auth()->user()->role === 'judge';
        abort_unless($isJudge || auth()->user()->role === 'admin', 403);
        $query = DB::table('jurus_performances')
            ->leftJoin('jurus_scores', 'jurus_scores.performance_id', '=', 'jurus_performances.id')
            ->leftJoin('users as judge', 'judge.id', '=', 'jurus_scores.judge_id')
            ->select(
                'jurus_performances.*',
                'jurus_scores.judge_id',
                'jurus_scores.base_score',
                'jurus_scores.judge_deductions',
                'jurus_scores.submitted_at',
                'judge.name as judge_name'
            )
            ->orderBy('jurus_performances.category_type')
            ->orderBy('jurus_performances.phase')
            ->orderBy('jurus_performances.scheduled_at')
            ->orderBy('jurus_performances.id');

        if ($isJudge) {
            $query->where('jurus_scores.judge_id', auth()->id());
        }

        $counts = DB::table('jurus_scores')
            ->select('performance_id')
            ->selectRaw('COUNT(*) as assigned_count')
            ->selectRaw('SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as submitted_count')
            ->groupBy('performance_id')
            ->get()
            ->keyBy('performance_id');
        $rows = $query->get()->groupBy('id');
        $performances = $rows->map(function (Collection $scoreRows) use ($counts, $isJudge) {
            $performance = $scoreRows->first();
            $scores = $scoreRows->filter(fn ($row) => $row->judge_id !== null);
            $submitted = $scores->filter(fn ($row) => $row->submitted_at !== null);
            $count = $counts->get($performance->id);
            $assignedCount = (int) ($count->assigned_count ?? 0);
            $submittedCount = (int) ($count->submitted_count ?? 0);
            $complete = $assignedCount === (int) $performance->judge_count
                && $submittedCount === (int) $performance->judge_count;
            $adjusted = $submitted
                ->map(fn ($row) => (float) $row->base_score - ((int) $row->judge_deductions * 0.01))
                ->sort()
                ->values();
            $aggregate = match ($performance->aggregation_method) {
                'median' => $complete ? $this->median($adjusted) : null,
                'average' => $complete ? (float) $adjusted->avg() : null,
                default => null,
            };
            $totalScore = $complete && !$isJudge && !$performance->disqualified && $aggregate !== null
                ? round($aggregate - ((int) $performance->supervisor_deductions * 0.50), 2)
                : ($performance->disqualified ? 0.0 : null);
            $judgePenalty = $submitted->sum(fn ($row) => (int) $row->judge_deductions) * 0.01;
            $totalPenalty = round($judgePenalty + ((int) $performance->supervisor_deductions * 0.50), 2);
            $standardDeviation = $complete ? $this->standardDeviation($adjusted) : null;
            $ownScore = $scores->firstWhere('judge_id', auth()->id());

            return (object) [
                'id' => $performance->id,
                'category_type' => $performance->category_type,
                'age_division' => $performance->age_division,
                'judge_count' => $performance->judge_count,
                'score_min' => $performance->score_min,
                'score_max' => $performance->score_max,
                'aggregation_method' => $performance->aggregation_method,
                'phase' => $performance->phase,
                'entry_name' => $performance->entry_name,
                'contingent_name' => $performance->contingent_name,
                'performers' => $performance->performers,
                'scheduled_at' => $performance->scheduled_at,
                'duration_seconds' => $performance->duration_seconds,
                'supervisor_deductions' => $performance->supervisor_deductions,
                'technical_notes' => $performance->technical_notes,
                'disqualified' => (bool) $performance->disqualified,
                'disqualification_reason' => $performance->disqualification_reason,
                'scores' => $scores,
                'submitted_count' => $submittedCount,
                'assigned_count' => $assignedCount,
                'complete' => $complete,
                'median_score' => $performance->aggregation_method === 'median' ? $aggregate : null,
                'aggregate_score' => $aggregate,
                'total_score' => $totalScore,
                'total_penalty' => $totalPenalty,
                'standard_deviation' => $standardDeviation,
                'time_delta' => in_array($performance->age_division, self::ADULT_DIVISIONS, true)
                    ? $this->timeDelta($performance->category_type, $performance->phase, $performance->duration_seconds)
                    : null,
                'rank' => null,
                'rank_tied' => false,
                'own_submitted' => $ownScore?->submitted_at !== null,
                'own_score' => $ownScore?->base_score,
                'own_deductions' => $ownScore?->judge_deductions,
            ];
        });

        if (!$isJudge) {
            $this->rank($performances);
        }

        return view('jurus.index', compact('performances'));
    }

    public function create()
    {
        $this->ensureAdmin();
        $judges = User::where('role', 'judge')->orderBy('name')->get();

        return view('jurus.create', compact('judges'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'category_type' => ['required', Rule::in(self::CATEGORIES)],
            'age_division' => ['required', Rule::in([...self::ADULT_DIVISIONS, ...self::YOUTH_DIVISIONS])],
            'phase' => ['required', Rule::in(self::PHASES)],
            'entry_name' => ['required', 'string', 'max:150'],
            'contingent_name' => ['required', 'string', 'max:150'],
            'performers' => ['required', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'supervisor_deductions' => ['nullable', 'integer', 'min:0', 'max:20'],
            'technical_notes' => ['nullable', 'string', 'max:2000'],
            'disqualified' => ['nullable', 'boolean'],
            'disqualification_reason' => ['nullable', 'required_if:disqualified,1', 'string', 'max:500'],
            'judge_ids' => ['required', 'array', 'min:4', 'max:12'],
            'judge_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('role', 'judge')],
            'score_min' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'score_max' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'aggregation_method' => ['nullable', Rule::in(['median', 'average', 'individual'])],
        ]);

        $isYouth = in_array($data['age_division'], self::YOUTH_DIVISIONS, true);
        $allowedCategories = $isYouth ? self::YOUTH_CATEGORIES : self::ADULT_CATEGORIES;
        if (!in_array($data['category_type'], $allowedCategories, true)) {
            return back()->withErrors([
                'category_type' => $isYouth
                    ? 'Kelas anak memakai nomor Tunggal, Solo Kreatif, Ganda, atau Regu; bukan Tunggal Bebas.'
                    : 'Solo Kreatif hanya tersedia untuk kelas anak.',
            ])->withInput();
        }

        $judgeCount = count($data['judge_ids']);
        if (($isYouth && ($judgeCount < 4 || $judgeCount % 2 !== 0))
            || (!$isYouth && $judgeCount !== self::JUDGE_COUNT)) {
            return back()->withErrors([
                'judge_ids' => $isYouth
                    ? 'Kelas anak membutuhkan minimal empat juri dengan jumlah genap.'
                    : 'Kategori Remaja/Dewasa/Master menggunakan enam juri.',
            ])->withInput();
        }

        if ($isYouth && (!isset($data['score_min'], $data['score_max']) || ($data['aggregation_method'] ?? null) === null)) {
            return back()->withErrors([
                'score_min' => 'Pasal 10 tidak menentukan skala atau rumus rekap kelas anak. Isi skala dan metode yang telah ditetapkan Technical Delegate.',
            ])->withInput();
        }
        if ($isYouth && (float) $data['score_max'] <= (float) $data['score_min']) {
            return back()->withErrors(['score_max' => 'Nilai maksimum harus lebih besar daripada nilai minimum.'])->withInput();
        }
        if ($isYouth && (int) ($data['supervisor_deductions'] ?? 0) !== 0) {
            return back()->withErrors([
                'supervisor_deductions' => 'Pasal 10 tidak menetapkan pengurangan supervisor untuk kelas anak; jangan masukkan potongan tanpa ketetapan Technical Delegate.',
            ])->withInput();
        }

        if ($data['category_type'] === 'ganda' && count(array_filter(preg_split('/\R/', trim($data['performers'])))) < 2) {
            return back()->withErrors(['performers' => 'Jurus Ganda harus mencantumkan minimal dua pesilat, satu nama per baris.'])->withInput();
        }

        if ($data['category_type'] === 'solo_kreatif'
            && (!isset($data['duration_seconds']) || (int) $data['duration_seconds'] < 60 || (int) $data['duration_seconds'] > 120)) {
            return back()->withErrors([
                'duration_seconds' => 'Solo Kreatif kelas anak berlangsung 1–2 menit menurut Pasal 10.',
            ])->withInput();
        }

        if ($data['category_type'] === 'regu' && count(array_filter(preg_split('/\R/', trim($data['performers'])))) < 3) {
            return back()->withErrors(['performers' => 'Jurus Regu harus mencantumkan minimal tiga pesilat, satu nama per baris.'])->withInput();
        }

        DB::transaction(function () use ($data, $isYouth, $judgeCount) {
            $performanceId = DB::table('jurus_performances')->insertGetId([
                'category_type' => $data['category_type'],
                'age_division' => $data['age_division'],
                'judge_count' => $judgeCount,
                'score_min' => $isYouth ? $data['score_min'] : 9,
                'score_max' => $isYouth ? $data['score_max'] : 10,
                'aggregation_method' => $isYouth ? $data['aggregation_method'] : 'median',
                'phase' => $data['phase'],
                'entry_name' => $data['entry_name'],
                'contingent_name' => $data['contingent_name'],
                'performers' => trim($data['performers']),
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'supervisor_deductions' => $isYouth ? 0 : ($data['supervisor_deductions'] ?? 0),
                'technical_notes' => $data['technical_notes'] ?? null,
                'disqualified' => (bool) ($data['disqualified'] ?? false),
                'disqualification_reason' => $data['disqualification_reason'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            DB::table('jurus_scores')->insert(array_map(
                fn (int $judgeId) => [
                    'performance_id' => $performanceId,
                    'judge_id' => $judgeId,
                    'judge_deductions' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $data['judge_ids']
            ));
        });

        return redirect()->route('jurus.index')->with(
            'success',
            "Penampilan Jurus dibuat dan ditugaskan kepada {$judgeCount} juri."
        );
    }

    public function score(Request $request, int $performance)
    {
        abort_unless(auth()->user()->role === 'judge', 403);

        $assignment = DB::table('jurus_scores')
            ->where('performance_id', $performance)
            ->where('judge_id', auth()->id())
            ->first();
        abort_unless($assignment, 403);
        abort_unless($assignment->submitted_at === null, 409, 'Nilai sudah dikirim dan tidak dapat diubah.');

        $jurus = DB::table('jurus_performances')->where('id', $performance)->first();
        abort_unless($jurus, 404);
        abort_if($jurus->disqualified, 409, 'Penampilan yang didiskualifikasi tidak dapat menerima nilai.');

        $rules = [
            'base_score' => [
                'required',
                'numeric',
                'between:' . $jurus->score_min . ',' . $jurus->score_max,
                'regex:/^\d{1,3}(\.\d{1,2})?$/',
            ],
        ];
        if (in_array($jurus->age_division, self::ADULT_DIVISIONS, true)
            && in_array($jurus->category_type, ['tunggal', 'regu'], true)) {
            $rules['judge_deductions'] = ['required', 'integer', 'min:0', 'max:100'];
        } else {
            $rules['judge_deductions'] = ['prohibited'];
        }

        $data = $request->validate($rules);

        $updated = DB::table('jurus_scores')
            ->where('id', $assignment->id)
            ->whereNull('submitted_at')
            ->update([
                'base_score' => $data['base_score'],
                'judge_deductions' => $data['judge_deductions'] ?? 0,
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);
        abort_unless($updated === 1, 409, 'Nilai sudah dikirim dan tidak dapat diubah.');

        $submittedCount = DB::table('jurus_scores')
            ->where('performance_id', $performance)
            ->whereNotNull('submitted_at')
            ->count();

        return redirect()->route('jurus.index')->with(
            'success',
            $submittedCount === (int) $jurus->judge_count
                ? 'Semua juri telah mengirim nilai. Rekap kategori ini telah diperbarui.'
                : "Nilai Anda tersimpan ({$submittedCount} dari {$jurus->judge_count} juri)."
        );
    }

    public function updateOutcome(Request $request, int $performance)
    {
        $this->ensureAdmin();

        $jurus = DB::table('jurus_performances')->where('id', $performance)->first();
        abort_unless($jurus, 404);

        $data = $request->validate([
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'supervisor_deductions' => ['required', 'integer', 'min:0', 'max:20'],
            'disqualified' => ['nullable', 'boolean'],
            'disqualification_reason' => ['nullable', 'required_if:disqualified,1', 'string', 'max:500'],
        ]);

        if (in_array($jurus->age_division, self::YOUTH_DIVISIONS, true)
            && (int) $data['supervisor_deductions'] !== 0) {
            return back()->withErrors([
                'supervisor_deductions' => 'Pasal 10 tidak menentukan pengurangan kelas anak; gunakan hanya ketetapan Technical Delegate.',
            ])->withInput();
        }

        DB::table('jurus_performances')
            ->where('id', $performance)
            ->update([
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'supervisor_deductions' => $data['supervisor_deductions'],
                'disqualified' => (bool) ($data['disqualified'] ?? false),
                'disqualification_reason' => $data['disqualification_reason'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()->route('jurus.index')->with('success', 'Durasi dan keputusan pengawas Jurus berhasil direkap.');
    }

    private function rank(Collection $performances): void
    {
        foreach ($performances->filter(fn ($item) => in_array($item->age_division, self::ADULT_DIVISIONS, true))
            ->groupBy(fn ($item) => $item->age_division . '|' . $item->category_type . '|' . $item->phase) as $group) {
            $ranked = $group->filter(fn ($item) => $item->complete
                && $item->total_score !== null
                && !$item->disqualified
                && ($item->category_type === 'tunggal_bebas' || $item->duration_seconds !== null))
                ->sort(function ($left, $right) {
                    if ($left->total_score !== $right->total_score) {
                        return ($right->total_score ?? 0) <=> ($left->total_score ?? 0);
                    }
                    if ($left->total_penalty !== $right->total_penalty) {
                        return $left->total_penalty <=> $right->total_penalty;
                    }
                    if ($left->category_type !== 'tunggal_bebas' && $left->time_delta !== $right->time_delta) {
                        return ($left->time_delta ?? PHP_INT_MAX) <=> ($right->time_delta ?? PHP_INT_MAX);
                    }
                    return ($left->standard_deviation ?? 0) <=> ($right->standard_deviation ?? 0);
                })
                ->values();

            $rank = 0;
            $previous = null;
            foreach ($ranked as $index => $item) {
                $key = [
                    $item->total_score,
                    $item->total_penalty,
                    $item->category_type === 'tunggal_bebas' ? null : $item->time_delta,
                    $item->standard_deviation,
                ];
                if ($key !== $previous) {
                    $rank = $index + 1;
                    $previous = $key;
                }
                $item->rank = $rank;
            }
            foreach ($ranked->groupBy('rank')->filter(fn (Collection $ties) => $ties->count() > 1) as $ties) {
                foreach ($ties as $item) {
                    $item->rank_tied = true;
                }
            }
        }
    }

    private function timeDelta(string $category, string $phase, ?int $duration): ?int
    {
        $target = match ($category) {
            'tunggal' => match ($phase) {
                'penyisihan' => 80,
                'semifinal' => 100,
                'final' => 180,
            },
            'ganda', 'regu' => $phase === 'final' ? 180 : 90,
            default => null,
        };

        return $target !== null && $duration !== null ? abs($duration - $target) : null;
    }

    private function median(Collection $values): float
    {
        $count = $values->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ((float) $values[$middle - 1] + (float) $values[$middle]) / 2
            : (float) $values[$middle];
    }

    private function standardDeviation(Collection $values): float
    {
        $average = $values->avg();
        $variance = $values->map(fn ($value) => ((float) $value - $average) ** 2)->avg();

        return sqrt($variance);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }
}
