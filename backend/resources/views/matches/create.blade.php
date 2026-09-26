@extends('layouts.app')
@section('content')
<h1>Pengaturan Pertandingan</h1>
<p class="muted">Buat tiga akun juri lalu tugaskan tiga juri untuk setiap pertandingan. Juri mengisi catatan skor untuk tiga ronde di akun masing-masing.</p>

<div class="card"><h2>Buat akun juri</h2>
    <form method="post" action="{{ route('matches.judges.store') }}">@csrf
        <div class="grid">
            <div><label>Nama juri</label><input name="name" required></div>
            <div><label>Email juri</label><input name="email" type="email" required></div>
            <div><label>Password sementara (minimal 8 karakter)</label><input name="password" type="password" minlength="8" required></div>
            <div><label>Ulangi password</label><input name="password_confirmation" type="password" minlength="8" required></div>
        </div>
        <button class="btn">Buat Akun Juri</button>
    </form>
</div>

<div class="card"><h2>Buat jadwal pertandingan</h2>
    @if($judges->count() < 3)<p class="muted">Buat minimal tiga akun juri terlebih dahulu.</p>@endif
    @if($athletes->count() < 2)<p class="muted">Belum cukup pesilat yang pendaftarannya disetujui dan pembayarannya diverifikasi.</p>@endif
    <form method="post" action="{{ route('matches.store') }}">@csrf
        <div class="grid">
            <div><label>Nomor pertandingan</label><input name="match_number" placeholder="Contoh: A-01" value="{{ old('match_number') }}" required></div>
            <div><label>Kategori</label><select name="category_id" required><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div><label>Gelanggang / tatami</label><input name="tatami" placeholder="Contoh: Gelanggang 1" value="{{ old('tatami') }}" required></div>
            <div><label>Tanggal dan waktu</label><input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" required></div>
            <div><label>Pesilat pertama</label><select name="athlete_a_id" required><option value="">Pilih pesilat</option>@foreach($athletes as $athlete)<option value="{{ $athlete->id }}" @selected(old('athlete_a_id') == $athlete->id)>{{ $athlete->name }} — {{ $athlete->contingent_name }}</option>@endforeach</select></div>
            <div><label>Pesilat kedua</label><select name="athlete_b_id" required><option value="">Pilih pesilat</option>@foreach($athletes as $athlete)<option value="{{ $athlete->id }}" @selected(old('athlete_b_id') == $athlete->id)>{{ $athlete->name }} — {{ $athlete->contingent_name }}</option>@endforeach</select></div>
            <div><label>Tiga juri (pilih tepat tiga; Ctrl untuk memilih lebih dari satu)</label><select name="judge_ids[]" multiple size="6" required>@foreach($judges as $judge)<option value="{{ $judge->id }}" @selected(in_array($judge->id, old('judge_ids', [])))>{{ $judge->name }} ({{ $judge->email }})</option>@endforeach</select></div>
        </div>
        <button class="btn" @disabled($athletes->count() < 2 || $judges->count() < 3)>Simpan Jadwal</button>
    </form>
</div>
@endsection
