<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ExitPermitController;
use App\Http\Controllers\PermissionRequestController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Application Protected Pages
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        if (! Auth::user()->hasAdminAccess()) {
            return redirect()->route('absensi.dashboard');
        }

        return Inertia::render('Home', [
            'appName' => config('app.name'),
        ]);
    })->name('home');

    Route::get('/components', function () {
        return Inertia::render('Components');
    })->name('components');

    Route::get('/datatable', function () {
        return Inertia::render('Datatable');
    })->name('datatable');

    Route::get('/forms', function () {
        return Inertia::render('Forms');
    })->name('forms');

    Route::get('/charts', function () {
        return Inertia::render('Charts');
    })->name('charts');

    // ===== Portal Pegawai — Presensi Mandiri =====
    Route::prefix('absensi')->name('absensi.')->group(function () {
        Route::get('/', [AttendanceController::class, 'employeeDashboard'])->name('dashboard');
        Route::get('/laporan', [AttendanceController::class, 'employeeReport'])->name('laporan');
        Route::post('/masuk',  [AttendanceController::class, 'checkIn'])->name('masuk');
        Route::post('/pulang', [AttendanceController::class, 'checkOut'])->name('pulang');
    });

    // ===== Permohonan Izin / Lapor Tidak Masuk Kerja =====
    Route::prefix('izin')->name('izin.')->group(function () {
        Route::get('/buat', [PermissionRequestController::class, 'create'])->name('create');
        Route::post('/simpan', [PermissionRequestController::class, 'store'])->name('store');
    });

    //  Laporan & Rekapitulasi Izin Pegawai admin 
    Route::get('/admin/izin', [PermissionRequestController::class, 'index'])->name('admin.izin.index');

    //  Laporan Rekapitulasi Absensi Pegawai Admin (Seluruh Karyawan KFPB)
    Route::get('/admin/absensi/laporan', [AttendanceController::class, 'employeeReport'])->name('admin.absensi.laporan');

    //  Modul Izin Keluar Kantor (Layanan Pegawai, Approval Atasan & Scanner Satpam)
    Route::prefix('izin-keluar')->name('izin-keluar.')->group(function () {
        Route::get('/', [ExitPermitController::class, 'employeeIndex'])->name('index');
        Route::post('/', [ExitPermitController::class, 'store'])->name('store');
        Route::post('/{exitPermit}/approval', [ExitPermitController::class, 'supervisorApproval'])->name('approval');
        Route::get('/status/{exitPermit}', [ExitPermitController::class, 'checkTicketStatus'])->name('ticket-status');
        Route::get('/scan', [ExitPermitController::class, 'securityIndex'])->name('scanner');
        Route::post('/scan/process', [ExitPermitController::class, 'securityProcessScan'])->name('scan-process');
    });

    //  Pusat Verifikasi & Rekap Izin Keluar Kantor Admin
    Route::prefix('admin/izin-keluar')->name('admin.izin-keluar.')->group(function () {
        Route::get('/', [ExitPermitController::class, 'adminIndex'])->name('index');
        Route::post('/{exitPermit}/status', [ExitPermitController::class, 'updateStatus'])->name('status');
    });
});
