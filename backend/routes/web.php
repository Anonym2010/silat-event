<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JurusController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [RegistrationController::class, 'dashboard'])->name('dashboard');
    Route::get('/registrations/create', [RegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/registrations', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::patch('/registrations/{registration}/payment', [RegistrationController::class, 'uploadPayment'])->name('registrations.payment');
    Route::get('/registrations/{registration}/documents/{document}', [RegistrationController::class, 'document'])->name('registrations.document');
    Route::patch('/registrations/{registration}/verify', [RegistrationController::class, 'verify'])->name('registrations.verify');
    Route::patch('/registrations/{registration}/payment/verify', [RegistrationController::class, 'verifyPayment'])->name('registrations.payment.verify');
    Route::patch('/registrations/{registration}/payment/reject', [RegistrationController::class, 'rejectPayment'])->name('registrations.payment.reject');
    Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
    Route::get('/matches/create', [MatchController::class, 'create'])->name('matches.create');
    Route::post('/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::post('/matches/judges', [MatchController::class, 'createJudge'])->name('matches.judges.store');
    Route::patch('/matches/{match}/score', [MatchController::class, 'score'])->name('matches.score');
    Route::patch('/matches/{match}/confirm-result', [MatchController::class, 'confirmResult'])->name('matches.confirm-result');
    Route::get('/jurus', [JurusController::class, 'index'])->name('jurus.index');
    Route::get('/jurus/create', [JurusController::class, 'create'])->name('jurus.create');
    Route::post('/jurus', [JurusController::class, 'store'])->name('jurus.store');
    Route::patch('/jurus/{performance}/score', [JurusController::class, 'score'])->name('jurus.score');
    Route::patch('/jurus/{performance}/outcome', [JurusController::class, 'updateOutcome'])->name('jurus.outcome');
});
