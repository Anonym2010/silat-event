<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function dashboard()
    {
        $role = auth()->user()->role;

        if ($role === 'judge') {
            $assignedMatches = DB::table('matches')
                ->join('categories', 'categories.id', '=', 'matches.category_id')
                ->join('match_judges', 'match_judges.match_id', '=', 'matches.id')
                ->leftJoin('athletes as athlete_a', 'athlete_a.id', '=', 'matches.athlete_a_id')
                ->leftJoin('athletes as athlete_b', 'athlete_b.id', '=', 'matches.athlete_b_id')
                ->where('match_judges.judge_id', auth()->id())
                ->select(
                    'matches.*',
                    'categories.name as category_name',
                    'athlete_a.name as athlete_a_name',
                    'athlete_b.name as athlete_b_name',
                    'match_judges.submitted_at as judge_submitted_at'
                )
                ->orderBy('matches.scheduled_at')
                ->limit(5)
                ->get();

            $assignedPerformances = DB::table('jurus_performances')
                ->join('jurus_scores', 'jurus_scores.performance_id', '=', 'jurus_performances.id')
                ->where('jurus_scores.judge_id', auth()->id())
                ->select(
                    'jurus_performances.*',
                    'jurus_scores.submitted_at as judge_submitted_at',
                    'jurus_scores.base_score as judge_score',
                    'jurus_scores.judge_deductions'
                )
                ->orderBy('jurus_performances.scheduled_at')
                ->limit(5)
                ->get();

            return view('dashboard', [
                'role' => $role,
                'assignedMatches' => $assignedMatches,
                'assignedPerformances' => $assignedPerformances,
            ]);
        }

        $query = DB::table('registrations')->join('users', 'users.id', '=', 'registrations.user_id')
            ->join('athletes', 'athletes.id', '=', 'registrations.athlete_id')
            ->join('categories', 'categories.id', '=', 'registrations.category_id')
            ->select('registrations.*', 'users.name as account_name', 'athletes.name as athlete_name', 'athletes.contingent_name', 'athletes.photo_path', 'categories.name as category_name');
        $registrations = $role === 'admin'
            ? $query->latest('registrations.created_at')->get()
            : $query->where('registrations.user_id', auth()->id())->latest('registrations.created_at')->get();

        $summary = $role === 'admin' ? [
            'registrations' => DB::table('registrations')->count(),
            'pending_registrations' => DB::table('registrations')->where('status', 'pending')->count(),
            'pending_payments' => DB::table('registrations')->where('payment_status', 'waiting_verification')->count(),
            'judges' => DB::table('users')->where('role', 'judge')->count(),
        ] : [
            'registrations' => $registrations->count(),
            'pending_registrations' => $registrations->where('status', 'pending')->count(),
            'pending_payments' => $registrations->whereIn('payment_status', ['unpaid', 'waiting_verification', 'rejected'])->count(),
        ];

        return view('dashboard', compact('registrations', 'role', 'summary'));
    }

    public function create()
    {
        return view('registrations.create', ['categories' => DB::table('categories')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contingent_name' => ['required', 'string', 'max:150'],
            'athlete_name' => ['required', 'string', 'max:150'],
            'nik' => ['required', 'digits_between:8,20'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date'],
            'weight' => ['required', 'numeric', 'min:20', 'max:150'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('gender', $request->input('gender'))],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'identity_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'health_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $category = DB::table('categories')->where('id', $data['category_id'])->first();
        $event = DB::table('events')->orderBy('id')->first();
        abort_unless($category && $event, 500, 'Kategori atau data event belum tersedia.');
        $birthDate = \Carbon\Carbon::parse($data['birth_date']);
        $eventDate = \Carbon\Carbon::parse($event->start_date);
        $age = $eventDate->year - $birthDate->year;
        if ($eventDate->copy()->subYears($age)->lt($birthDate)) {
            $age--;
        }
        if ($age < $category->age_min || $age > $category->age_max) {
            return back()->withErrors(['category_id' => "Usia pesilat pada tanggal event harus {$category->age_min}–{$category->age_max} tahun untuk kategori ini."])->withInput();
        }
        if ((float) $data['weight'] < (float) $category->weight_min || (float) $data['weight'] > (float) $category->weight_max) {
            return back()->withErrors(['category_id' => "Berat badan pesilat harus {$category->weight_min}–{$category->weight_max} kg untuk kategori ini."])->withInput();
        }
        DB::transaction(function () use ($data, $request) {
            $photoPath = $request->file('photo')->store('athletes/photos', 'local');
            $identityPath = $request->file('identity_document')->store('registrations/documents', 'local');
            $healthPath = $request->file('health_document')->store('registrations/documents', 'local');
            $athlete = DB::table('athletes')->insertGetId([
                'user_id' => auth()->id(), 'contingent_name' => $data['contingent_name'], 'name' => $data['athlete_name'],
                'nik' => $data['nik'], 'phone' => $data['phone'], 'gender' => $data['gender'], 'birth_date' => $data['birth_date'],
                'weight' => $data['weight'], 'photo_path' => $photoPath, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('registrations')->insert([
                'user_id' => auth()->id(), 'athlete_id' => $athlete, 'category_id' => $data['category_id'],
                'status' => 'pending', 'payment_status' => 'unpaid', 'identity_document_path' => $identityPath,
                'health_document_path' => $healthPath, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect('/dashboard')->with('success', 'Pendaftaran berhasil dikirim dan menunggu verifikasi panitia.');
    }

    public function uploadPayment(Request $request, int $registration)
    {
        $registrationRow = DB::table('registrations')->where('id', $registration)->first();
        abort_unless($registrationRow && ($registrationRow->user_id === auth()->id() || auth()->user()->role === 'admin'), 403);
        $data = $request->validate(['payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096']]);
        $path = $request->file('payment_proof')->store('registrations/payments', 'local');
        DB::table('registrations')->where('id', $registration)->update(['payment_proof' => $path, 'payment_status' => 'waiting_verification', 'updated_at' => now()]);
        return back()->with('success', 'Bukti pembayaran berhasil diunggah dan menunggu verifikasi panitia.');
    }

    public function document(int $registration, string $document)
    {
        $record = DB::table('registrations')
            ->join('athletes', 'athletes.id', '=', 'registrations.athlete_id')
            ->where('registrations.id', $registration)
            ->select('registrations.*', 'athletes.photo_path')
            ->first();

        abort_unless($record, 404);
        abort_unless(
            auth()->user()->role === 'admin' || (int) $record->user_id === (int) auth()->id(),
            403
        );

        $path = match ($document) {
            'photo' => $record->photo_path,
            'identity' => $record->identity_document_path,
            'health' => $record->health_document_path,
            'payment' => $record->payment_proof,
            default => null,
        };
        abort_unless($path, 404);

        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return response()->file(Storage::disk($disk)->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verify(int $registration)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        DB::table('registrations')->where('id', $registration)->update(['status' => 'approved', 'updated_at' => now()]);
        return back()->with('success', 'Pendaftaran disetujui.');
    }

    public function verifyPayment(int $registration)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        DB::table('registrations')->where('id', $registration)->update(['payment_status' => 'verified', 'updated_at' => now()]);
        return back()->with('success', 'Pembayaran berhasil diverifikasi.');
    }

    public function rejectPayment(int $registration)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        DB::table('registrations')->where('id', $registration)->update(['payment_status' => 'rejected', 'updated_at' => now()]);
        return back()->with('success', 'Bukti pembayaran ditolak. Peserta dapat mengunggah ulang.');
    }
}
