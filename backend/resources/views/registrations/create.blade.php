@extends('layouts.app')
@section('content')
<div class="card"><h2>Daftar Pesilat</h2><p class="muted">Lengkapi data dan dokumen agar panitia dapat memverifikasi pendaftaran.</p>
<form method="post" action="{{ route('registrations.store') }}" enctype="multipart/form-data">@csrf
<div class="grid">
<div><label>Nama kontingen</label><input name="contingent_name" required></div>
<div><label>Nama pesilat</label><input name="athlete_name" required></div>
<div><label>NIK / nomor identitas</label><input name="nik" inputmode="numeric" required></div>
<div><label>Nomor WhatsApp</label><input name="phone" inputmode="tel" required></div>
<div><label>Jenis kelamin</label><select name="gender"><option value="male">Putra</option><option value="female">Putri</option></select></div>
<div><label>Tanggal lahir</label><input name="birth_date" type="date" required></div>
<div><label>Berat badan (kg)</label><input name="weight" type="number" min="20" max="150" step="0.1" required></div>
<div><label>Kategori</label><select name="category_id" required><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }} ({{ $category->weight_min }}-{{ $category->weight_max }} kg)</option>@endforeach</select></div>
<div><label>Foto pesilat (JPG/PNG)</label><input name="photo" type="file" accept=".jpg,.jpeg,.png" required></div>
<div><label>Kartu identitas (PDF/JPG/PNG)</label><input name="identity_document" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
<div><label>Surat keterangan sehat (PDF/JPG/PNG)</label><input name="health_document" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
</div><button class="btn">Kirim Pendaftaran</button></form></div>
@endsection
