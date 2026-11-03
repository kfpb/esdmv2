<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PermissionRequest;
use App\Models\PermissionType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class PermissionRequestController extends Controller
{
    /**
     * Halaman laporan rekapitulasi data izin tidak hadir pegawai untuk Admin / HRD.
     * Mengadopsi struktur data, filter, statistik, dan lampiran dari esdm-native/absen/data_izin.php.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Personalia HRD.');
        }

        $bulan   = $request->input('bulan', 'all');
        $tahun   = $request->input('tahun', date('Y'));
        $jenis   = $request->input('jenis', '');
        $search  = trim($request->input('q', ''));
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));

        // Base Query Eloquent
        $query = PermissionRequest::with(['employee.division', 'permissionType']);

        if ($bulan !== 'all' && ! empty($bulan)) {
            $query->where(function ($q) use ($bulan) {
                $q->whereMonth('start_date', $bulan)
                    ->orWhereMonth('end_date', $bulan);
            });
        }

        if ($tahun !== 'all' && ! empty($tahun)) {
            $query->where(function ($q) use ($tahun) {
                $q->whereYear('start_date', $tahun)
                    ->orWhereYear('end_date', $tahun);
            });
        }

        if (! empty($jenis)) {
            $query->where('permission_type_id', $jenis);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_nip', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($empQ) use ($search) {
                        $empQ->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Ringkasan hitungan statistik praktis (sesuai filter aktif)
        $statQuery = PermissionRequest::query();
        if ($bulan !== 'all' && ! empty($bulan)) {
            $statQuery->where(function ($q) use ($bulan) {
                $q->whereMonth('start_date', $bulan)
                    ->orWhereMonth('end_date', $bulan);
            });
        }
        if ($tahun !== 'all' && ! empty($tahun)) {
            $statQuery->where(function ($q) use ($tahun) {
                $q->whereYear('start_date', $tahun)
                    ->orWhereYear('end_date', $tahun);
            });
        }

        $totalFiltered = (clone $statQuery)->count();
        $totalSakit    = (clone $statQuery)->where('permission_type_id', 2)->count();
        $totalCuti     = (clone $statQuery)->where('permission_type_id', 3)->count();
        $totalDinas    = (clone $statQuery)->where('permission_type_id', 5)->count();

        // Paginate data utama
        $permissions = $query->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($perm) {
                $tglAwal  = Carbon::parse($perm->start_date);
                $tglAkhir = Carbon::parse($perm->end_date);
                $durasi   = $tglAwal->diffInDays($tglAkhir) + 1;

                return [
                    'id'              => $perm->id,
                    'nip'             => $perm->employee_nip,
                    'nama_pegawai'    => $perm->employee?->full_name ?? '-',
                    'bagian'          => $perm->employee?->division?->name ?? 'Plant Banjaran',
                    'id_ijin'         => $perm->permission_type_id,
                    'nama_ijin'       => $perm->permissionType?->name ?? 'Izin',
                    'tgl_awal'        => $tglAwal->format('d/m/Y'),
                    'tgl_akhir'       => $tglAkhir->format('d/m/Y'),
                    'raw_tgl_awal'    => $perm->start_date->format('Y-m-d'),
                    'raw_tgl_akhir'   => $perm->end_date->format('Y-m-d'),
                    'durasi'          => $durasi,
                    'keterangan'      => $perm->reason ?? '-',
                    'lampiran_url'    => $perm->attachment_url,
                    'approval_status' => $perm->approval_status,
                    'created_at'      => $perm->created_at?->format('d/m/Y H:i'),
                ];
            });

        // Master pilihan jenis izin untuk filter
        $permissionTypes = PermissionType::orderBy('id')->get(['id', 'name']);

        // Daftar tahun untuk filter dropdown
        $currentYear = (int) date('Y');
        $years = range($currentYear, $currentYear - 5);

        return Inertia::render('Admin/PermissionReport', [
            'permissions'     => $permissions,
            'permissionTypes' => $permissionTypes,
            'years'           => $years,
            'filters'         => [
                'bulan'    => (string) $bulan,
                'tahun'    => (string) $tahun,
                'jenis'    => (string) $jenis,
                'q'        => $search,
                'per_page' => $perPage,
            ],
            'statistics'      => [
                'total' => $totalFiltered,
                'sakit' => $totalSakit,
                'cuti'  => $totalCuti,
                'dinas' => $totalDinas,
            ],
        ]);
    }

    /**
     * Tampilkan formulir permohonan lapor tidak masuk kerja (Izin / Sakit / Cuti / Dinas Luar).
     * Mereplikasi fungsionalitas dan form dari sistem lama absen/inputijin.php
     * dengan standar arsitektur modern eSDMv2.
     */
    public function create(Request $request): Response
    {
        $user = Auth::user();
        $authNip = $user->employee_nip;
        $employee = Employee::where('nip', $authNip)->with(['division', 'position'])->first();

        // 1. Ambil / inisialisasi master tipe izin
        $permissionTypes = PermissionType::orderBy('id')->get(['id', 'legacy_id', 'name', 'is_salary_deducted']);
        if ($permissionTypes->isEmpty()) {
            $defaultTypes = [
                ['id' => 1, 'legacy_id' => 1, 'name' => 'Izin Pribadi / Stay at Home', 'is_salary_deducted' => false],
                ['id' => 2, 'legacy_id' => 2, 'name' => 'Sakit (Surat Dokter)', 'is_salary_deducted' => false],
                ['id' => 3, 'legacy_id' => 3, 'name' => 'Cuti Tahunan / Bersama', 'is_salary_deducted' => false],
                ['id' => 4, 'legacy_id' => 4, 'name' => 'Dispensasi Khusus', 'is_salary_deducted' => false],
                ['id' => 5, 'legacy_id' => 5, 'name' => 'Dinas Luar / Penugasan Kantor', 'is_salary_deducted' => false],
            ];
            foreach ($defaultTypes as $dt) {
                PermissionType::updateOrCreate(['id' => $dt['id']], $dt);
            }
            $permissionTypes = PermissionType::orderBy('id')->get(['id', 'legacy_id', 'name', 'is_salary_deducted']);
        }

        // 2. Daftar pegawai jika diakses oleh Administrator (sesuai leveluser == 1 di inputijin.php)
        $isAdmin = $user->hasAdminAccess();
        $employees = [];
        if ($isAdmin) {
            $employees = Employee::where('is_active', true)
                ->orderBy('full_name')
                ->get(['nip', 'full_name'])
                ->map(fn ($emp) => [
                    'nip'       => $emp->nip,
                    'full_name' => $emp->full_name,
                    'label'     => "{$emp->full_name} ({$emp->nip})",
                ]);
        }

        // 3. Riwayat 5 pengajuan izin terakhir untuk pegawai saat ini
        $recentRequests = PermissionRequest::where('employee_nip', $authNip)
            ->with('permissionType')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn ($perm) => [
                'id'              => $perm->id,
                'type_name'       => $perm->permissionType?->name ?? 'Izin',
                'start_date'      => $perm->start_date?->format('d/m/Y'),
                'end_date'        => $perm->end_date?->format('d/m/Y'),
                'raw_start_date'  => $perm->start_date?->format('Y-m-d'),
                'raw_end_date'    => $perm->end_date?->format('Y-m-d'),
                'reason'          => $perm->reason ?? '-',
                'attachment_url'  => $perm->attachment_url,
                'approval_status' => $perm->approval_status,
                'created_at'      => $perm->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Employee/PermissionRequest', [
            'authEmployee' => [
                'nip'       => $authNip,
                'name'      => $employee?->full_name ?? $user->name,
                'division'  => $employee?->division?->name ?? 'PT Kimia Farma Plant Banjaran',
                'position'  => $employee?->position?->name ?? 'Pegawai',
            ],
            'isAdmin'         => $isAdmin,
            'employees'       => $employees,
            'permissionTypes' => $permissionTypes,
            'recentRequests'  => $recentRequests,
        ]);
    }

    /**
     * Simpan pengajuan permohonan izin / lapor tidak masuk kerja.
     * Menggunakan DB Transaction untuk menyimpan header ke permission_requests
     * dan meng-generate entri kehadiran harian ke tabel attendances.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $nip = $request->input('employee_nip');
        $permissionTypeId = (int) $request->input('permission_type_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $reason = $request->input('reason');

        // 1. Upload berkas bukti pendukung (jika disertakan)
        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $ext = $file->getClientOriginalExtension();
            $filename = 'izin_' . $nip . '_' . time() . '.' . $ext;
            $destinationDir = public_path('uploads/izin');

            if (! File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true, true);
            }

            $file->move($destinationDir, $filename);
            $attachmentUrl = '/uploads/izin/' . $filename;
        }

        // 2. Transaksi Database: permission_requests + attendances
        DB::transaction(function () use ($nip, $permissionTypeId, $startDate, $endDate, $reason, $attachmentUrl) {
            $permissionType = PermissionType::find($permissionTypeId);
            $notesText = $reason ?: ($permissionType?->name ?? 'Izin Tidak Hadir');

            // Simpan header permohonan ke permission_requests (modernisasi history_ijin)
            PermissionRequest::create([
                'employee_nip'       => $nip,
                'permission_type_id' => $permissionTypeId,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'reason'             => $reason,
                'attachment_url'     => $attachmentUrl,
                'approval_status'    => 1, // Sesuai aturan inputijin.php: langsung diakui dan tercatat di absensi
            ]);

            // Sinkronkan harian ke tabel attendances (modernisasi absensi)
            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $currentDate = $date->format('Y-m-d');

                Attendance::updateOrCreate(
                    [
                        'employee_nip'    => $nip,
                        'attendance_date' => $currentDate,
                    ],
                    [
                        'work_shift_id'      => null,
                        'check_in_time'      => null,
                        'check_out_time'     => null,
                        'is_present_in'      => false,
                        'is_present_out'     => false,
                        'is_late'            => false,
                        'is_early_leave'     => false,
                        'permission_type_id' => $permissionTypeId,
                        'work_mode'          => ($permissionTypeId === 5 ? 'DINAS' : 'WFO'),
                        'notes'              => $notesText,
                    ]
                );
            }
        });

        return redirect()->route('absensi.dashboard')->with('success', 'Laporan izin tidak masuk kerja berhasil disimpan dan disinkronkan ke rekap presensi.');
    }
}

