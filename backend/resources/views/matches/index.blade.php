@extends('layouts.app')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <div>
        <h1>Jadwal & Skoring Tanding</h1>
        <p class="muted">Juri mencatat poin tangan (1), kaki (2), jatuhan (3), dan sanksi per ronde. Admin merekap kiriman tiap juri.</p>
    </div>
    @if(auth()->user()->role === 'admin')<a class="btn" href="{{ route('matches.create') }}">+ Atur Jadwal</a>@endif
</div>
@forelse($matches as $match)
    @php
        $matchAssignments = $assignments->get($match->id, collect());
    @endphp
    <section class="card">
        <h2>{{ $match->match_number }} — {{ $match->category_name }}</h2>
        <p><strong>Merah:</strong> {{ $match->athlete_a_name }} &nbsp; <strong>Biru:</strong> {{ $match->athlete_b_name }}</p>
        <p class="muted">{{ $match->scheduled_at ? \Carbon\Carbon::parse($match->scheduled_at)->format('d M Y H:i') : 'Belum dijadwalkan' }} · {{ $match->tatami }}</p>
        <p><strong>Pengumpulan juri:</strong> {{ $matchAssignments->whereNotNull('submitted_at')->count() }} / {{ $matchAssignments->count() }}</p>

        @if(auth()->user()->role === 'admin')
            @if($match->official_result_at)
                <div class="alert">
                    <strong>Hasil akhir disahkan:</strong> {{ str_replace('_', ' ', ucfirst($match->result_type)) }}
                    @if($match->winner_id)
                        — {{ $match->winner_id == $match->athlete_a_id ? $match->athlete_a_name : $match->athlete_b_name }}
                    @else
                        — Seri
                    @endif
                    @if($match->official_result_notes)<br>Catatan: {{ $match->official_result_notes }}@endif
                    <br><small>Disahkan pada {{ \Carbon\Carbon::parse($match->official_result_at)->format('d M Y H:i') }}</small>
                </div>
            @elseif($matchAssignments->whereNotNull('submitted_at')->count() === 3)
                <div class="card">
                    <h3>Hasil sementara: {{ $match->provisional_result }}</h3>
                    <p>Suara berdasarkan perbandingan net tiap juri: Merah <strong>{{ $match->votes_a }}</strong>, Biru <strong>{{ $match->votes_b }}</strong>. Suara seri tidak dihitung untuk salah satu pihak.</p>
                    <p class="muted">Ini hanya usulan berdasarkan rekap tiga juri, bukan keputusan otomatis berdasarkan ketentuan agregasi nilai resmi. Admin wajib mengesahkan hasil.</p>
                    <form method="post" action="{{ route('matches.confirm-result', $match->id) }}">@csrf @method('PATCH')
                        <div class="grid">
                            <div>
                                <label>Keputusan akhir admin</label>
                                <select name="result_type" required>
                                    <option value="">Pilih hasil akhir</option>
                                    <option value="menang_angka">Menang angka</option>
                                    <option value="menang_teknik">Menang teknik</option>
                                    <option value="diskualifikasi">Diskualifikasi</option>
                                    <option value="mengundurkan_diri">Mengundurkan diri</option>
                                    <option value="seri">Seri</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label>Pemenang (kosongkan bila seri)</label>
                                <select name="winner_id">
                                    <option value="">Pilih pemenang</option>
                                    <option value="{{ $match->athlete_a_id }}" @selected($match->provisional_winner_id === $match->athlete_a_id)>{{ $match->athlete_a_name }} (Merah)</option>
                                    <option value="{{ $match->athlete_b_id }}" @selected($match->provisional_winner_id === $match->athlete_b_id)>{{ $match->athlete_b_name }} (Biru)</option>
                                </select>
                            </div>
                            <div><label>Catatan / alasan bila berbeda dari hasil sementara</label><textarea name="official_result_notes" maxlength="2000"></textarea></div>
                        </div>
                        <button class="btn">Sahkan & Kunci Hasil</button>
                    </form>
                </div>
            @else
                <p class="muted">Hasil akhir dapat disahkan setelah seluruh tiga juri mengirim skor.</p>
            @endif
            <details>
                <summary>Rekap skor juri</summary>
                @foreach($matchAssignments as $assignment)
                    @php
                        $judgeRounds = $roundScores->get($assignment->id, collect());
                    @endphp
                    <div style="padding:14px 0;border-bottom:1px solid #e6ece8">
                        <h3>{{ $assignment->judge_name }} — {{ $assignment->submitted_at ? 'Terkirim' : 'Belum mengirim' }}</h3>
                        @if($assignment->submitted_at)
                            @php
                                $totalPointsA = 0;
                                $totalPointsB = 0;
                                $totalPenaltyA = 0;
                                $totalPenaltyB = 0;
                            @endphp
                            @foreach($judgeRounds as $roundScore)
                                @php
                                    $pointsA = $roundScore->hands_a + 2 * $roundScore->feet_a + 3 * $roundScore->falls_a;
                                    $pointsB = $roundScore->hands_b + 2 * $roundScore->feet_b + 3 * $roundScore->falls_b;
                                    $penaltyA = $roundScore->teguran_1_a + 2 * $roundScore->teguran_2_a + 5 * $roundScore->peringatan_1_a + 10 * $roundScore->peringatan_2_a;
                                    $penaltyB = $roundScore->teguran_1_b + 2 * $roundScore->teguran_2_b + 5 * $roundScore->peringatan_1_b + 10 * $roundScore->peringatan_2_b;
                                    $totalPointsA += $pointsA;
                                    $totalPointsB += $pointsB;
                                    $totalPenaltyA += $penaltyA;
                                    $totalPenaltyB += $penaltyB;
                                @endphp
                                <p><strong>Ronde {{ $roundScore->round }}</strong> —
                                    Merah: teknik {{ $pointsA }}, pengurangan {{ $penaltyA }}, net {{ $pointsA - $penaltyA }}{{ $roundScore->disqualified_a ? ', diskualifikasi ditandai' : '' }};
                                    Biru: teknik {{ $pointsB }}, pengurangan {{ $penaltyB }}, net {{ $pointsB - $penaltyB }}{{ $roundScore->disqualified_b ? ', diskualifikasi ditandai' : '' }}.
                                </p>
                            @endforeach
                            <p><strong>Total rekap juri ini:</strong>
                                Merah {{ $totalPointsA }} teknik − {{ $totalPenaltyA }} pengurangan = {{ $totalPointsA - $totalPenaltyA }};
                                Biru {{ $totalPointsB }} teknik − {{ $totalPenaltyB }} pengurangan = {{ $totalPointsB - $totalPenaltyB }}.
                            </p>
                        @endif
                    </div>
                @endforeach
                <p class="muted">Rekap ini menampilkan kiriman setiap juri. Hasil sementara dihitung sebagai suara mayoritas perbandingan net masing-masing juri dan tetap harus disahkan admin.</p>
            </details>
        @elseif(auth()->user()->role === 'judge')
            @php
                $myAssignment = $matchAssignments->firstWhere('judge_id', auth()->id());
            @endphp
            @if($match->official_result_at)
                <p><strong>Hasil akhir disahkan:</strong> {{ str_replace('_', ' ', ucfirst($match->result_type)) }}
                    @if($match->winner_id)
                        — {{ $match->winner_id == $match->athlete_a_id ? $match->athlete_a_name : $match->athlete_b_name }}
                    @else
                        — Seri
                    @endif
                </p>
            @endif
            @if($myAssignment && $myAssignment->submitted_at)
                <p>Skor tiga ronde Anda sudah terkirim dan terkunci.</p>
                @foreach($roundScores->get($myAssignment->id, collect()) as $roundScore)
                    @php
                        $pointsA = $roundScore->hands_a + 2 * $roundScore->feet_a + 3 * $roundScore->falls_a;
                        $pointsB = $roundScore->hands_b + 2 * $roundScore->feet_b + 3 * $roundScore->falls_b;
                        $penaltyA = $roundScore->teguran_1_a + 2 * $roundScore->teguran_2_a + 5 * $roundScore->peringatan_1_a + 10 * $roundScore->peringatan_2_a;
                        $penaltyB = $roundScore->teguran_1_b + 2 * $roundScore->teguran_2_b + 5 * $roundScore->peringatan_1_b + 10 * $roundScore->peringatan_2_b;
                    @endphp
                    <p>Ronde {{ $roundScore->round }} — Merah: teknik {{ $pointsA }}, pengurangan {{ $penaltyA }}; Biru: teknik {{ $pointsB }}, pengurangan {{ $penaltyB }}.</p>
                @endforeach
            @elseif($myAssignment)
                <form method="post" action="{{ route('matches.score', $match->id) }}">@csrf @method('PATCH')
                    @foreach([1, 2, 3] as $round)
                        <fieldset style="margin:18px 0;padding:14px;border:1px solid #dbe4df;border-radius:6px">
                            <legend><strong>Ronde {{ $round }}</strong></legend>
                            <div class="grid">
                                @foreach(['a' => ['Merah', $match->athlete_a_name], 'b' => ['Biru', $match->athlete_b_name]] as $color => [$label, $athleteName])
                                    <div>
                                        <h3>{{ $label }} — {{ $athleteName }}</h3>
                                        @foreach(['hands' => 'Serangan tangan sah (1 poin per kejadian)', 'feet' => 'Serangan kaki sah (2 poin per kejadian)', 'falls' => 'Jatuhan sah (3 poin per kejadian)', 'teguran_1' => 'Teguran I (−1 per kejadian)', 'teguran_2' => 'Teguran II (−2 per kejadian)', 'peringatan_1' => 'Peringatan I (−5 per kejadian)', 'peringatan_2' => 'Peringatan II (−10 per kejadian)'] as $field => $caption)
                                            <label>{{ $caption }}</label>
                                            <input type="number" name="rounds[{{ $round }}][{{ $field }}_{{ $color }}]" min="0" max="99" value="{{ old("rounds.$round.{$field}_{$color}", 0) }}" required>
                                        @endforeach
                                        <label><input type="checkbox" name="rounds[{{ $round }}][disqualified_{{ $color }}]" value="1"> Diskualifikasi pada ronde ini (termasuk Peringatan III)</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <button class="btn">Kirim Skor Tiga Ronde (terkunci setelah dikirim)</button>
                </form>
            @endif
        @endif
    </section>
@empty
    <div class="card">Belum ada jadwal Tanding.</div>
@endforelse
@endsection
