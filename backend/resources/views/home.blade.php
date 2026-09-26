@extends('layouts.app')
@section('content')
<style>
.hero-card{display:grid;grid-template-columns:1.3fr .7fr;gap:45px;align-items:center;padding:42px 35px;color:#fff;background:linear-gradient(135deg,#063e33,#0b6956);border-radius:10px;box-shadow:0 12px 28px #123d2c22}.hero-copy{grid-column:1}.hero-side{display:flex;flex-direction:column;justify-content:center;min-height:180px;padding:25px;border:1px solid #8fc1b255;border-radius:10px;text-align:center}.hero-side strong{display:block;margin:8px 0;color:#e2a83d;font-size:52px}.hero-side span{color:#c5d8d0;font-size:11px;letter-spacing:1.5px}.hero-card .eyebrow,.eyebrow{color:#e2a83d;font-size:12px;font-weight:bold;letter-spacing:1.5px}.hero-card h1{max-width:650px;margin:15px 0;font-size:clamp(36px,6vw,64px);line-height:1.05}.hero-card h1 span{color:#8dd2bd}.hero-card p{max-width:590px;color:#d5e8e0}.event-grid,.value-grid,.figure-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.event-grid{margin:20px 0 45px}.event-grid .card{margin:0}.section{margin:45px 0}.section h2{margin:8px 0 22px}.category-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.category{padding:18px;background:#fff;border:1px solid #dbe4df;border-radius:8px}.category span{display:block;color:#63756d;font-size:13px;margin-top:5px}.figure{padding:22px;background:#075442;color:#fff;border-radius:8px}.figure b{display:grid;place-items:center;width:36px;height:36px;margin-bottom:22px;border:1px solid #e2a83d;border-radius:50%;color:#e2a83d}.figure p{color:#c5d8d0;font-size:14px}@media(max-width:700px){.event-grid,.value-grid,.figure-grid,.category-grid,.hero-card{grid-template-columns:1fr}.hero-card{padding:30px 22px}}
</style>
<section class="hero-card">
  <div><p class="eyebrow">KEJUARAAN ANTAR CABANG TRI SUKMA INDONESIA 2026</p>
    <h1>Berani bertanding,<br><span>junjung sportivitas.</span></h1>
    <p>Portal kejuaraan untuk pendaftaran, pengelolaan kontingen, serta skoring Tanding dan Jurus dalam satu aplikasi.</p>
    <a class="btn" href="/register">Mulai Pendaftaran →</a>
  </div>
  <div class="hero-side"><span>TRI SUKMA EVENT</span><strong>2026</strong><span>Sportivitas · Tradisi · Prestasi</span></div>
</section>
<section class="section">
  <p class="eyebrow">PENDAFTARAN & PENILAIAN</p><h2>Satu aplikasi untuk seluruh alur kejuaraan</h2>
  <div class="category-grid">
    <div class="category"><b>Peserta & Official</b><span>Daftar pesilat, lengkapi dokumen, dan pantau verifikasi serta pembayaran.</span></div>
    <div class="category"><b>Skoring Tanding</b><span>Juri mencatat poin teknik dan sanksi tiap ronde; admin melihat rekap juri.</span></div>
    <div class="category"><b>Penilaian Jurus</b><span>Juri mengirim nilai penampilan yang ditugaskan; admin mengelola rekap.</span></div>
    <div class="category"><b>Dashboard sesuai peran</b><span>Masuk untuk membuka fitur peserta, official, juri, atau panitia.</span></div>
  </div>
</section>
<section class="event-grid">
  <div class="card"><b>12–14 Desember 2026</b><p class="muted">Waktu pelaksanaan</p></div>
  <div class="card"><b>Lapangan Sekte Pinang Merah</b><p class="muted">Lokasi kejuaraan</p></div>
  <div class="card"><b>30 November 2026</b><p class="muted">Batas pendaftaran</p></div>
</section>
<section class="section">
  <p class="eyebrow">KATEGORI PERTANDINGAN</p><h2>Pilih kelas yang sesuai</h2>
  <div class="category-grid">
    <div class="category"><b>Pra Remaja Putra</b><span>Kelas A · 25–30 kg</span></div>
    <div class="category"><b>Remaja Putri</b><span>Kelas B · 40–45 kg</span></div>
    <div class="category"><b>Dewasa Putra</b><span>Kelas C · 55–60 kg</span></div>
    <div class="category"><b>Dewasa Putri</b><span>Kelas D · 50–55 kg</span></div>
  </div>
</section>
<section class="card section">
  <p class="eyebrow">NILAI PERGURUAN</p><h2>Tangguh dalam gerak, luhur dalam sikap.</h2>
  <p class="muted">Pencak silat bukan hanya tentang menang di gelanggang. Setiap latihan membentuk disiplin, keberanian, rasa hormat, dan tanggung jawab.</p>
  <div class="value-grid">
    <div><b>Tangguh</b><p class="muted">Melatih ketahanan fisik dan mental.</p></div>
    <div><b>Berbudaya</b><p class="muted">Menjaga tradisi dan menghormati sesama.</p></div>
    <div><b>Berprestasi</b><p class="muted">Berjuang dengan sportif dan bertanggung jawab.</p></div>
  </div>
</section>
<section class="section">
  <p class="eyebrow">TOKOH INSPIRATIF</p><h2>Teladan di dalam dan di luar gelanggang</h2>
  <div class="figure-grid">
    <div class="figure"><b>A</b><strong>Guru Utama Perguruan</strong><p>Penjaga nilai, tradisi, dan arah pembinaan generasi.</p></div>
    <div class="figure"><b>B</b><strong>Pelatih Berprestasi</strong><p>Membentuk atlet dengan disiplin dan keteladanan.</p></div>
    <div class="figure"><b>C</b><strong>Atlet Inspiratif</strong><p>Membuktikan bahwa kerja keras melahirkan prestasi.</p></div>
  </div>
</section>
@endsection
