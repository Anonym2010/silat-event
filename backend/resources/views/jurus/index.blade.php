@extends('layouts.app')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <div><h1>Penilaian Jurus</h1><p class="muted">Setiap juri mengirim nilai sendiri. Kelas dewasa mengikuti skala dan median IPSI; untuk kelas anak, gunakan skala dan metode rekap yang ditetapkan Technical Delegate.</p></div>
    @if(auth()->user()->role === 'admin')<a class="btn" href="{{ route('jurus.create') }}">+ Tambah Penampilan</a>@endif
</div>
@forelse($performances as $performance)
    <section class="card">
        <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div>
                <h2>{{ $performance->entry_name }} — {{ ucfirst(str_replace('_', ' ', $performance->category_type)) }}</h2>
                <p>{{ $performance->contingent_name }} · {{ ucfirst(str_replace('_', ' ', $performance->age_division)) }} · {{ ucfirst($performance->phase) }}</p>
                <p class="muted">Pesilat: {{ $performance->performers }}</p>
                @if($performance->scheduled_at)<p class="muted">Jadwal: {{ \Carbon\Carbon::parse($performance->scheduled_at)->format('d M Y H:i') }}</p>@endif
            </div>
            <div>
                <strong>{{ $performance->disqualified ? 'Diskualifikasi' : ($performance->complete ? 'Nilai lengkap' : 'Menunggu nilai') }}</strong>
                <p>{{ $performance->submitted_count }} / {{ $performance->judge_count }} juri mengirim</p>
                @if(auth()->user()->role === 'admin')
                    @if($performance->complete && !$performance->disqualified)
                        @if($performance->aggregation_method === 'individual')
                            <p>Metode peringkat/rekap belum ditetapkan; tampilkan nilai juri satu per satu di rincian.</p>
                        @else
                            <p>{{ ucfirst($performance->aggregation_method) }} nilai juri: <strong>{{ number_format($performance->aggregate_score, 2) }}</strong></p>
                            <p>Rekap setelah pengurangan supervisor: <strong>{{ number_format($performance->total_score, 2) }}</strong></p>
                        @endif
                        @if($performance->rank)
                            <p>Urutan sementara sesuai kriteria tiebreak: <strong>{{ $performance->rank }}</strong></p>
                            @if($performance->rank_tied)
                                <p class="muted">Masih seri setelah tiebreak tertulis; lakukan undian sesuai peraturan.</p>
                            @endif
                        @elseif($performance->complete && !$performance->disqualified && in_array($performance->age_division, ['remaja', 'dewasa', 'master'], true) && $performance->category_type !== 'tunggal_bebas' && !$performance->duration_seconds)
                            <p class="muted">Catat durasi aktual untuk menerapkan tiebreak waktu sesuai aturan.</p>
                        @elseif(in_array($performance->age_division, ['pra_usia_dini', 'usia_dini', 'pra_remaja'], true))
                            <p class="muted">Pasal 10 tidak mengatur tiebreak; peringkat kelas anak perlu keputusan Technical Delegate.</p>
                        @endif
                        @if($performance->aggregation_method !== 'individual')
                            <p class="muted">Metode rekap: {{ ucfirst($performance->aggregation_method) }} nilai setiap juri setelah pengurangan juri; pengurangan supervisor diterapkan sesudah rekap.</p>
                            @if(in_array($performance->age_division, ['remaja', 'dewasa', 'master'], true))
                                <p class="muted">Urutan pengurangan terhadap median tidak dirinci dalam teks Pasal 12; konfirmasi metode ini kepada Technical Delegate sebelum menetapkan hasil resmi.</p>
                            @endif
                        @endif
                    @elseif($performance->disqualified)
                        <p>{{ $performance->disqualification_reason }}</p>
                        <p class="muted">Prosedur tiebreak bila dua peserta diskualifikasi memerlukan keputusan Technical Delegate dan tidak dihitung otomatis.</p>
                    @endif
                    <p class="muted">Skala nilai: {{ number_format($performance->score_min, 2) }}–{{ number_format($performance->score_max, 2) }} · Durasi: {{ $performance->duration_seconds ? $performance->duration_seconds . ' detik' : 'belum dicatat' }}
                        @if($performance->time_delta !== null)
                            · Selisih dari waktu target: {{ $performance->time_delta }} detik
                        @endif
                        @if($performance->supervisor_deductions > 0)
                            · Pengurangan supervisor: {{ $performance->supervisor_deductions }} × 0,50
                        @endif
                    </p>
                    @if($performance->scores->isNotEmpty())
                        <details><summary>Rincian nilai juri</summary><ul>
                            @foreach($performance->scores as $score)
                                <li>{{ $score->judge_name }}:
                                    @if($score->submitted_at)
                                        {{ number_format($score->base_score - $score->judge_deductions * 0.01, 2) }} (nilai {{ number_format($score->base_score, 2) }}@if($score->judge_deductions > 0), pengurangan juri {{ $score->judge_deductions }} × 0,01 @endif)
                                    @else Belum mengirim @endif
                                </li>
                            @endforeach
                        </ul></details>
                    @endif
                    <details>
                        <summary>Rekap durasi / keputusan pengawas</summary>
                        <form method="post" action="{{ route('jurus.outcome', $performance->id) }}">@csrf @method('PATCH')
                            <div class="grid">
                                <div><label>Durasi aktual (detik)</label><input type="number" name="duration_seconds" min="1" max="600" value="{{ $performance->duration_seconds }}"></div>
                                @if(in_array($performance->age_division, ['remaja', 'dewasa', 'master'], true))
                                    <div><label>Jumlah pengurangan −0,50 oleh pengawas/dewan wasit juri</label><input type="number" name="supervisor_deductions" min="0" max="20" value="{{ $performance->supervisor_deductions }}" required></div>
                                @else
                                    <input type="hidden" name="supervisor_deductions" value="0">
                                @endif
                                <div><label><input type="checkbox" name="disqualified" value="1" @checked($performance->disqualified)> Diskualifikasi telah diputuskan</label>
                                    <input name="disqualification_reason" maxlength="500" placeholder="Alasan keputusan" value="{{ $performance->disqualification_reason }}">
                                </div>
                            </div>
                            <button class="btn">Simpan Rekap Pengawas</button>
                        </form>
                    </details>
                @else
                    <p>Nilai Anda: {{ $performance->own_submitted ? number_format($performance->own_score - $performance->own_deductions * 0.01, 2) : 'belum dikirim' }}</p>
                @endif
            </div>
        </div>
        @if(auth()->user()->role === 'judge' && !$performance->own_submitted && !$performance->disqualified)
            <form method="post" action="{{ route('jurus.score', $performance->id) }}">@csrf @method('PATCH')
                <div class="grid">
                    <div><label>Nilai ({{ number_format($performance->score_min, 2) }}–{{ number_format($performance->score_max, 2) }})</label><input type="number" name="base_score" min="{{ $performance->score_min }}" max="{{ $performance->score_max }}" step="0.01" required></div>
                    @if(in_array($performance->age_division, ['remaja', 'dewasa', 'master'], true) && in_array($performance->category_type, ['tunggal', 'regu'], true))
                        <div><label>Pengurangan oleh juri (jumlah kejadian × 0,01)</label><input type="number" name="judge_deductions" min="0" max="100" value="0" required></div>
                    @endif
                </div>
                <button class="btn">Kirim Nilai (tidak dapat diubah)</button>
            </form>
        @endif
    </section>
@empty
    <div class="card">Belum ada penampilan Jurus yang ditugaskan.</div>
@endforelse
@endsection
