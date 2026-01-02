<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TugasController;
use App\Http\Controllers\BotController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// routes publik
Route::prefix('auth')->group(function () {
    Route::get('/register-options', [AuthController::class, 'registerOptions']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// routes bot (untuk webhook dari bot external)
Route::prefix('bot')->group(function () {
    // endpoint dengan proteksi api key (untuk bot node.js)
    Route::middleware(['bot.key'])->group(function () {
        // ambil semua siswa yang perlu reminder (optimized untuk bot)
        Route::get('/siswa-pending', [BotController::class, 'ambilSiswaPerluReminder']);
        
        // ambil siswa pending untuk tugas tertentu
        Route::get('/siswa-pending/{idTugas}', [BotController::class, 'ambilSiswaPendingByTugas']);
        
        // catat reminder setelah bot berhasil kirim
        Route::post('/reminder', [BotController::class, 'catatReminder']);
        
        // webhook untuk update status pengiriman (opsional)
        Route::post('/webhook/status', [BotController::class, 'webhookStatus']);
    });
});

// routes terproteksi
Route::middleware(['jwt.auth'])->group(function () {
    
    // routes autentikasi
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // routes siswa & kelas
    Route::prefix('siswa')->group(function () {
        Route::get('/', [TugasController::class, 'listSiswa']);
        Route::get('/kelas', [TugasController::class, 'listKelas']);
        Route::post('/by-kelas', [TugasController::class, 'getSiswaByKelas']);
    });

    // routes tugas
    Route::prefix('tugas')->group(function () {
        // hanya guru
        Route::middleware(['role:guru'])->group(function () {
            Route::post('/', [TugasController::class, 'buatTugas']);
            Route::post('/{id}', [TugasController::class, 'updateTugas']);
            Route::delete('/{id}', [TugasController::class, 'hapusTugas']);
            Route::get('/{id}/pending', [TugasController::class, 'ambilPenugasanPending']);
            Route::put('/penugasan/{id}/status', [TugasController::class, 'updateStatusPenugasan']);
            Route::post('/{id}/reminder', [BotController::class, 'kirimReminder']);
            Route::get('/{id}/export', [TugasController::class, 'exportTugas']);
        });

        // hanya siswa
        Route::middleware(['role:siswa'])->group(function () {
            Route::post('/{id}/submit', [TugasController::class, 'ajukanPenugasan']);
        });

        // guru dan siswa
        Route::get('/', [TugasController::class, 'ambilTugas']);
        Route::get('/{id}/detail', [TugasController::class, 'ambilDetailTugas']);
    });

    // riwayat bot reminder (hanya guru)
    Route::prefix('bot')->middleware(['role:guru'])->group(function () {
        Route::get('/reminder/{idTugas}', [BotController::class, 'ambilReminder']);
    });
});
