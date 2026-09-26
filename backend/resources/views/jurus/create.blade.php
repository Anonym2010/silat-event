@extends('layouts.app')
@section('content')
<h1>Tambah Penampilan Jurus</h1>
<p class="muted">Untuk Remaja/Dewasa/Master dipakai enam juri dan skala 9,00–10,00. Pengurangan −0,01 oleh juri berlaku pada Tunggal/Regu; pengurangan −0,50 dicatat pengawas/dewan wasit juri. Kelas anak mengikuti minimal empat juri dengan jumlah genap; Pasal 10 tidak menetapkan skala atau cara rekap, jadi isi berdasarkan ketetapan Technical Delegate.</p>
<section class="card">
    @if($judges->count() < 4)<p class="muted">Perlu minimal empat akun juri untuk penampilan ini. Saat ini tersedia {{ $judges->count() }}.</p>@endif
    <form method="post" action="{{ route('jurus.store') }}">@csrf
        <div class="grid">
            <div><label>Kelompok usia</label><select id="age_division" name="age_division" required>
                <option value="">Pilih kelompok usia</option>
                @foreach(['pra_usia_dini' => 'Pra Usia Dini', 'usia_dini' => 'Usia Dini', 'pra_remaja' => 'Pra Remaja', 'remaja' => 'Remaja', 'dewasa' => 'Dewasa', 'master' => 'Master'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('age_division') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div><label>Kategori</label><select name="category_type" required>
                <option value="">Pilih kategori</option>
                @foreach(['tunggal' => 'Tunggal', 'tunggal_bebas' => 'Tunggal Bebas (Remaja ke atas)', 'solo_kreatif' => 'Solo Kreatif (kelas anak)', 'ganda' => 'Ganda', 'regu' => 'Regu'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('category_type') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div><label>Tahap</label><select name="phase" required>
                <option value="">Pilih tahap</option>
                @foreach(['penyisihan' => 'Penyisihan', 'semifinal' => 'Semifinal', 'final' => 'Final'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('phase') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div><label>Nama penampilan / regu</label><input name="entry_name" maxlength="150" value="{{ old('entry_name') }}" required></div>
            <div><label>Kontingen</label><input name="contingent_name" maxlength="150" value="{{ old('contingent_name') }}" required></div>
            <div><label>Nama pesilat (satu nama per baris)</label><textarea name="performers" maxlength="2000" required>{{ old('performers') }}</textarea></div>
            <div><label>Jadwal penampilan (opsional)</label><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></div>
            <div><label>Durasi penampilan, detik (Solo Kreatif wajib 60–120 detik)</label><input id="duration_seconds" type="number" name="duration_seconds" min="1" max="600" value="{{ old('duration_seconds') }}"></div>
            <div id="youth-score-settings" hidden>
                <label>Skala minimum (sesuai ketetapan Technical Delegate)</label><input type="number" name="score_min" min="0" max="99.99" step="0.01" value="{{ old('score_min') }}">
                <label>Skala maksimum</label><input type="number" name="score_max" min="0.01" max="100" step="0.01" value="{{ old('score_max') }}">
                <label>Metode rekap yang ditetapkan Technical Delegate</label>
                <select name="aggregation_method">
                    <option value="">Pilih metode rekap</option>
                    <option value="median" @selected(old('aggregation_method') === 'median')>Median</option>
                    <option value="average" @selected(old('aggregation_method') === 'average')>Rata-rata</option>
                    <option value="individual" @selected(old('aggregation_method') === 'individual')>Tampilkan nilai tiap juri saja</option>
                </select>
            </div>
            <div id="adult-scoring-info"><label>Pengurangan supervisor (jumlah kejadian × 0,50)</label><input type="number" name="supervisor_deductions" min="0" max="20" value="{{ old('supervisor_deductions', 0) }}"></div>
            <div><label>Catatan teknis</label><textarea name="technical_notes" maxlength="2000">{{ old('technical_notes') }}</textarea></div>
            <div><label><input type="checkbox" name="disqualified" value="1" @checked(old('disqualified'))> Diskualifikasi resmi</label>
                <input name="disqualification_reason" maxlength="500" placeholder="Alasan diskualifikasi" value="{{ old('disqualification_reason') }}">
            </div>
            <div><label>Juri (kelas dewasa tepat enam; kelas anak minimal empat dan jumlah genap. Ctrl untuk memilih banyak)</label>
                <select id="judge_ids" name="judge_ids[]" multiple size="8" required>
                    @foreach($judges as $judge)
                        <option value="{{ $judge->id }}" @selected(in_array($judge->id, old('judge_ids', [])))>{{ $judge->name }} ({{ $judge->email }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button class="btn" @disabled($judges->count() < 4)>Simpan Penampilan</button>
    </form>
</section>
<script>
const ageDivision = document.getElementById('age_division');
const categorySelect = document.querySelector('[name="category_type"]');
const durationInput = document.getElementById('duration_seconds');
const youthSettings = document.getElementById('youth-score-settings');
const adultScoringInfo = document.getElementById('adult-scoring-info');
const supervisorDeductions = document.querySelector('[name="supervisor_deductions"]');
const scoreMin = document.querySelector('[name="score_min"]');
const scoreMax = document.querySelector('[name="score_max"]');
const aggregationMethod = document.querySelector('[name="aggregation_method"]');
const judgeSelect = document.getElementById('judge_ids');
const youthDivisions = ['pra_usia_dini', 'usia_dini', 'pra_remaja'];

function updateJurusForm() {
    const youth = youthDivisions.includes(ageDivision.value);
    youthSettings.hidden = !youth;
    adultScoringInfo.hidden = youth;
    scoreMin.required = youth;
    scoreMax.required = youth;
    aggregationMethod.required = youth;
    supervisorDeductions.required = !youth;
    for (const option of categorySelect.options) {
        if (!option.value) continue;
        option.disabled = youth
            ? option.value === 'tunggal_bebas'
            : option.value === 'solo_kreatif';
    }
    const soloCreative = categorySelect.value === 'solo_kreatif';
    durationInput.required = soloCreative;
    durationInput.min = soloCreative ? 60 : 1;
    durationInput.max = soloCreative ? 120 : 600;
}

ageDivision.addEventListener('change', updateJurusForm);
categorySelect.addEventListener('change', updateJurusForm);
updateJurusForm();
</script>
@endsection
