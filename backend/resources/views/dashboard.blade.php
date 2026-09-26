@extends('layouts.app')
@section('content')
<style>
.dashboard-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.dashboard-heading h1{margin-bottom:4px}.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:22px 0}.summary-card{padding:20px;background:#fff;border:1px solid #dbe4df;border-radius:8px}.summary-card strong{display:block;color:#075442;font-size:28px}.summary-card span{color:#63756d;font-size:13px}.dashboard-actions{display:flex;gap:10px;flex-wrap:wrap}.dashboard-actions .btn{margin-top:0}.dashboard-table{overflow-x:auto}.dashboard-table table{min-width:680px}.empty-state{padding:25px;text-align:center;color:#63756d}
</style>

<div class="dashboard-heading">
    <div>
        @if($role === 'admin')
            <h1>Dashboard Panitia</h1>
            <p class="muted">Kelola pendaftaran, pembayaran, jadwal, dan akun juri.</p>
        @elseif($role === 'judge')
            <h1>Dashboard Juri</h1>
            <p class="muted">Masukkan skor Tanding dan nilai Jurus yang ditugaskan kepada Anda.</p>
        @else
            <h1>Dashboard {{ $role === 'official' ? 'Official' : 'Peserta' }}</h1>
            <p class="muted">Selamat datang, {{ auth()->user()->name }}. Pantau pendaftaran dan pembayaran Anda.</p>
        @endif
    </div>
    @if(in_array($role, ['participant', 'official'], true))
        <a class="btn" href="{{ route('registrations.create') }}">+ Daftarkan Pesilat</a>
    @elseif($role === 'admin')
        <div class="dashboard-actions">
            <a class="btn" href="{{ route('matches.create') }}">+ Atur Jadwal</a>
            <a class="btn" href="{{ route('matches.index') }}">Skoring Tanding</a>
            <a class="btn" href="{{ route('jurus.index') }}">Penilaian Jurus</a>
        </div>
    @else
        <div class="dashboard-actions">
            <a class="btn" href="{{ route('matches.index') }}">Buka Skoring Tanding</a>
            <a class="btn" href="{{ route('jurus.index') }}">Buka Penilaian Jurus</a>
        </div>
    @endif
</div>

@if($role === 'judge')
    <div class="summary-grid">
        <div class="summary-card"><strong>{{ $assignedMatches->count() }}</strong><span>Pertandingan Tanding ditugaskan</span></div>
        <div class="summary-card"><strong>{{ $assignedMatches->whereNull('judge_submitted_at')->count() }}</strong><span>Skor Tanding menunggu Anda</span></div>
        <div class="summary-card"><strong>{{ $assignedPerformances->count() }}</strong><span>Penampilan ditugaskan</span></div>
        <div class="summary-card"><strong>{{ $assignedPerformances->whereNull('judge_submitted_at')->count() }}</strong><span>Menunggu nilai Anda</span></div>
        <div class="summary-card"><strong>{{ $assignedPerformances->whereNotNull('judge_submitted_at')->count() }}</strong><span>Nilai Anda tersimpan</span></div>
    </div>
    <section class="card">
        <h2>Pertandingan Tanding Saya</h2>
        @if($assignedMatches->isEmpty())
            <div class="empty-state">Belum ada pertandingan Tanding yang ditugaskan kepada Anda.</div>
        @else
            <div class="dashboard-table"><table>
                <thead><tr><th>No.</th><th>Waktu / Gelanggang</th><th>Kategori</th><th>Pesilat</th><th>Status skoring</th></tr></thead>
                <tbody>@foreach($assignedMatches as $match)
                    <tr>
                        <td>{{ $match->match_number }}</td>
                        <td>{{ $match->scheduled_at ? \Carbon\Carbon::parse($match->scheduled_at)->format('d M Y H:i') : 'Belum dijadwalkan' }}<br><span class="muted">{{ $match->tatami }}</span></td>
                        <td>{{ $match->category_name }}</td>
                        <td>Merah: {{ $match->athlete_a_name }}<br>Biru: {{ $match->athlete_b_name }}</td>
                        <td>{{ $match->judge_submitted_at ? 'Tiga ronde terkirim' : 'Menunggu skor Anda' }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
    <section class="card">
        <h2>Penampilan Jurus Saya</h2>
        @if($assignedPerformances->isEmpty())
            <div class="empty-state">Belum ada penampilan yang ditugaskan kepada Anda.</div>
        @else
            <div class="dashboard-table"><table>
                <thead><tr><th>Penampilan</th><th>Jadwal</th><th>Kategori</th><th>Kontingen / Pesilat</th><th>Nilai saya</th><th>Status</th></tr></thead>
                <tbody>@foreach($assignedPerformances as $performance)
                    <tr>
                        <td>{{ $performance->entry_name }}</td>
                        <td>{{ $performance->scheduled_at ? \Carbon\Carbon::parse($performance->scheduled_at)->format('d M Y H:i') : 'Belum dijadwalkan' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $performance->category_type)) }} / {{ ucfirst($performance->phase) }}</td>
                        <td>{{ $performance->contingent_name }}<br>{{ $performance->performers }}</td>
                        <td>{{ $performance->judge_submitted_at ? number_format($performance->judge_score - $performance->judge_deductions * 0.01, 2) : '—' }}</td>
                        <td>
                            @if($performance->judge_submitted_at)
                                Nilai terkirim
                            @else
                                Menunggu nilai Anda
                            @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@else
    <div class="summary-grid">
        <div class="summary-card"><strong>{{ $summary['registrations'] }}</strong><span>{{ $role === 'admin' ? 'Total pendaftaran' : 'Pesilat didaftarkan' }}</span></div>
        @if($role === 'admin')
            <div class="summary-card"><strong>{{ $summary['pending_registrations'] }}</strong><span>Menunggu verifikasi data</span></div>
            <div class="summary-card"><strong>{{ $summary['pending_payments'] }}</strong><span>Menunggu verifikasi pembayaran</span></div>
            <div class="summary-card"><strong>{{ $summary['judges'] }}</strong><span>Akun juri</span></div>
        @else
            <div class="summary-card"><strong>{{ $summary['pending_registrations'] }}</strong><span>Pendaftaran menunggu keputusan</span></div>
            <div class="summary-card"><strong>{{ $summary['pending_payments'] }}</strong><span>Pembayaran perlu ditindaklanjuti</span></div>
        @endif
    </div>
    <section class="card">
        <h2>{{ $role === 'admin' ? 'Semua Pendaftaran' : 'Pendaftaran Pesilat Saya' }}</h2>
        @if($registrations->isEmpty())
            <div class="empty-state">Belum ada pendaftaran pesilat.</div>
        @else
            <div class="dashboard-table"><table>
                <thead><tr><th>Pesilat</th><th>Kontingen</th><th>Kategori</th><th>Status data</th><th>Status pembayaran</th><th>Dokumen</th>
                    @if($role === 'admin')
                        <th>Aksi</th>
                    @else
                        <th>Bukti pembayaran</th>
                    @endif
                </tr></thead>
                <tbody>@foreach($registrations as $registration)
                    <tr>
                        <td>{{ $registration->athlete_name }}</td>
                        <td>{{ $registration->contingent_name }}</td>
                        <td>{{ $registration->category_name }}</td>
                        <td>{{ ucfirst($registration->status) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($registration->payment_status)) }}</td>
                        <td>
                            <a href="{{ route('registrations.document', [$registration->id, 'photo']) }}" target="_blank" rel="noopener">Foto</a> ·
                            <a href="{{ route('registrations.document', [$registration->id, 'identity']) }}" target="_blank" rel="noopener">Identitas</a> ·
                            <a href="{{ route('registrations.document', [$registration->id, 'health']) }}" target="_blank" rel="noopener">Surat sehat</a>
                            @if($registration->payment_proof)
                                · <a href="{{ route('registrations.document', [$registration->id, 'payment']) }}" target="_blank" rel="noopener">Bukti bayar</a>
                            @endif
                        </td>
                        @if($role === 'admin')
                            <td><div class="dashboard-actions">
                                @if($registration->status === 'pending')
                                    <form method="post" action="{{ route('registrations.verify', $registration->id) }}">@csrf @method('PATCH')<button class="btn">Setujui data</button></form>
                                @endif
                                @if($registration->payment_status === 'waiting_verification')
                                    <form method="post" action="{{ route('registrations.payment.verify', $registration->id) }}">@csrf @method('PATCH')<button class="btn">Terima bayar</button></form>
                                    <form method="post" action="{{ route('registrations.payment.reject', $registration->id) }}">@csrf @method('PATCH')<button class="btn" style="background:#a33">Tolak bayar</button></form>
                                @endif
                            </div></td>
                        @else
                            <td>
                                @if($registration->payment_status !== 'verified')
                                    <form method="post" action="{{ route('registrations.payment', $registration->id) }}" enctype="multipart/form-data">@csrf @method('PATCH')
                                        <input name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <button class="btn">Kirim bukti</button>
                                    </form>
                                @else
                                    <span>Pembayaran terverifikasi</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
@endif
@endsection
