<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ExitPermitController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PermissionRequestController;
use App\Http\Controllers\MasterData\DivisionController;
use App\Http\Controllers\MasterData\SubDivisionController;
use App\Http\Controllers\MasterData\ReligionController;
use App\Http\Controllers\MasterData\WorkShiftController;
use App\Http\Controllers\MasterData\DocumentController;
use App\Http\Controllers\MasterData\EmployeeStatusController;
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

    // ===== Pengajuan Lembur (SPL – Surat Perintah Lembur), Persetujuan Atasan & Form Verifikasi =====
    Route::prefix('lembur')->name('lembur.')->group(function () {
        Route::get('/ajukan', [OvertimeController::class, 'create'])->name('create');
        Route::post('/simpan', [OvertimeController::class, 'store'])->name('store');
        Route::get('/persetujuan', [OvertimeController::class, 'approvalIndex'])->name('approvals');
        Route::post('/persetujuan-massal', [OvertimeController::class, 'processBatchApproval'])->name('approval.batch');
        Route::post('/{id}/approval', [OvertimeController::class, 'processApproval'])->name('approval.process')->where('id', '[0-9]+');
        Route::get('/verifikasi/{id}', [OvertimeController::class, 'verify'])->name('verify')->where('id', '[0-9]+');
        Route::post('/verifikasi/{id}', [OvertimeController::class, 'saveVerification'])->name('verify.store')->where('id', '[0-9]+');
        Route::get('/{id}', [OvertimeController::class, 'show'])->name('show')->where('id', '[0-9]+');
    });

    // ===== Master Data (Admin / HRD) =====
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::prefix('master_data')->name('master_data.')->group(function () {
            Route::resource('division', DivisionController::class)->except(['create', 'edit', 'show']);
            Route::resource('sub_division', SubDivisionController::class)->except(['create', 'edit', 'show']);
            Route::resource('religion', ReligionController::class)->except(['create', 'edit', 'show']);

            Route::resource('work_shift', WorkShiftController::class)->except(['create', 'edit', 'show']);

            Route::resource('document', DocumentController::class)->except(['create', 'edit', 'show']);

            Route::resource('employee_status', EmployeeStatusController::class)->except(['create', 'edit', 'show']);
        });
    });
});
