<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExitPermit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExitPermitController extends Controller
{
    /**
     * Helper deteksi atasan langsung pengaju dari struktur organisasi.
     */
    private function getDirectSupervisor(?string $nip): ?array
    {
        if (empty($nip)) {
            return null;
        }

        // 1. Coba ambil dari tabel relasi struktur legacy (sql_sdm.tbl_struktur / tbl_struktur)
        try {
            $row = DB::table('sql_sdm.tbl_struktur as ts')
                ->join('esdmv2.employees as p', 'ts.id_jab_b', '=', 'p.nip')
                ->where('ts.id_jab_a', $nip)
                ->select('p.nip', 'p.full_name as name')
                ->first();

            if ($row) {
                return [
                    'nip'  => $row->nip,
                    'name' => $row->name,
                ];
            }
        } catch (\Throwable $e) {
            // Fallback jika database sql_sdm tidak tersedia
        }

        // 2. Fallback: Cari Manager / Supervisor pada divisi yang sama
        $emp = Employee::where('nip', $nip)->first();
        if ($emp && $emp->division_id) {
            $lead = Employee::where('division_id', $emp->division_id)
                ->where('nip', '!=', $nip)
                ->whereHas('position', function ($q) {
                    $q->where('name', 'like', '%Manager%')
                        ->orWhere('name', 'like', '%Supervisor%')
                        ->orWhere('name', 'like', '%Kepala%')
                        ->orWhere('name', 'like', '%Koordinator%');
                })
                ->first();

            if ($lead) {
                return [
                    'nip'  => $lead->nip,
                    'name' => $lead->full_name,
                ];
            }
        }

        // 3. Fallback umum: Atasan SDM / Default KFPB
        return [
            'nip'  => '10000063',
            'name' => 'apt. Drs. ASEP PERMANA',
        ];
    }

    /**
     * Helper daftar NIP bawahan dari atasan tertentu.
     */
    private function getSubordinateNips(?string $supervisorNip): array
    {
        if (empty($supervisorNip)) {
            return [];
        }

        try {
            return DB::table('sql_sdm.tbl_struktur')
                ->where('id_jab_b', $supervisorNip)
                ->pluck('id_jab_a')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Halaman portal pegawai: formulir pengajuan izin keluar kantor,
     * riwayat tiket izin keluar mandiri, serta Tab Approval untuk Atasan.
     */
    public function employeeIndex(Request $request): Response
    {
        $user = Auth::user();
        $nip  = $user->employee_nip;
        $employee = Employee::where('nip', $nip)->with(['division', 'position'])->first();

        // 1. Ambil info atasan langsung untuk pengajuan mandiri
        $supervisor = $this->getDirectSupervisor($nip);

        // 2. Deteksi apakah user adalah Atasan (memiliki bawahan atau role supervisor/admin)
        $subordinateNips = $this->getSubordinateNips($nip);
        $isSupervisor = $user->isSupervisor() || count($subordinateNips) > 0 || $user->hasAdminAccess();

        // 3. Riwayat izin keluar mandiri pegawai (15 data terbaru)
        $myExitPermits = ExitPermit::where('employee_nip', $nip)
            ->latest('date')
            ->latest('id')
            ->take(15)
            ->get()
            ->map(function ($permit) {
                return [
                    'id'              => $permit->id,
                    'kode_izin'       => $permit->kode_izin,
                    'date'            => $permit->date->format('d/m/Y'),
                    'raw_date'        => $permit->date->format('Y-m-d'),
                    'exit_time'       => substr((string) $permit->exit_time, 0, 5),
                    'return_time'     => $permit->return_time ? substr((string) $permit->return_time, 0, 5) : null,
                    'reason'          => $permit->reason,
                    'approval_status' => $permit->approval_status,
                    'approver_nip'    => $permit->approver_nip,
                    'approver_name'   => $permit->approver_name,
                    'created_at'      => $permit->created_at?->format('d/m/Y H:i'),
                ];
            });

        // 4. Data Izin Bawahan (Khusus jika user adalah Atasan)
        $subordinatePermits = [];
        $subordinatePendingCount = 0;

        if ($isSupervisor) {
            $subQuery = ExitPermit::with(['employee.division', 'employee.position']);

            if (! $user->hasAdminAccess() && count($subordinateNips) > 0) {
                $subQuery->where(function ($q) use ($subordinateNips, $nip) {
                    $q->whereIn('employee_nip', $subordinateNips)
                        ->orWhere('reason', 'like', "%[Atasan: {$nip}%");
                });
            }

            $rawSubs = $subQuery->latest('date')
                ->latest('id')
                ->take(30)
                ->get();

            $subordinatePendingCount = $rawSubs->where('approval_status', ExitPermit::STATUS_PENDING)->count();

            $subordinatePermits = $rawSubs->map(function ($permit) {
                return [
                    'id'              => $permit->id,
                    'kode_izin'       => $permit->kode_izin,
                    'nip'             => $permit->employee_nip,
                    'nama_pegawai'    => $permit->employee?->full_name ?? '-',
                    'bagian'          => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'jabatan'         => $permit->employee?->position?->name ?? '-',
                    'date'            => $permit->date->format('d/m/Y'),
                    'raw_date'        => $permit->date->format('Y-m-d'),
                    'exit_time'       => substr((string) $permit->exit_time, 0, 5),
                    'return_time'     => $permit->return_time ? substr((string) $permit->return_time, 0, 5) : null,
                    'reason'          => $permit->reason,
                    'approval_status' => $permit->approval_status,
                    'created_at'      => $permit->created_at?->format('d/m/Y H:i'),
                ];
            })->values()->all();
        }

        return Inertia::render('Employee/ExitPermit', [
            'authEmployee' => [
                'nip'      => $nip,
                'name'     => $employee?->full_name ?? $user->name,
                'division' => $employee?->division?->name ?? 'Plant Banjaran',
                'position' => $employee?->position?->name ?? 'Staf Pegawai',
            ],
            'supervisor'              => $supervisor,
            'isSupervisor'            => $isSupervisor,
            'isSecurity'              => $user->isSecurity(),
            'myExitPermits'           => $myExitPermits,
            'subordinatePermits'      => $subordinatePermits,
            'subordinatePendingCount' => $subordinatePendingCount,
            'todayDate'               => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    /**
     * Simpan formulir pengajuan izin keluar kantor baru oleh pegawai.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $nip  = $user->employee_nip;
        $today = now('Asia/Jakarta')->toDateString();

        $request->validate([
            'date'         => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today],
            'type'         => ['required', 'in:keluar_tengah,datang_terlambat'],
            'exit_time'    => ['nullable', 'date_format:H:i'],
            'return_time'  => ['nullable', 'date_format:H:i'],
            'is_returning' => ['nullable', 'boolean'],
            'atasan_nip'   => ['nullable', 'string', 'max:50'],
            'atasan_nama'  => ['nullable', 'string', 'max:150'],
            'reason'       => ['required', 'string', 'max:1000'],
        ], [
            'date.required'          => 'Tanggal izin keluar wajib diisi.',
            'date.after_or_equal'    => 'Anda tidak dapat mengajukan izin keluar untuk tanggal yang sudah lewat.',
            'type.required'          => 'Tipe izin keluar wajib dipilih.',
            'reason.required'        => 'Alasan atau keperluan izin keluar wajib dituliskan.',
        ]);

        $type = $request->input('type');
        $isReturning = (bool) $request->input('is_returning', true);

        // Validasi waktu sesuai tipe izin
        if ($type === 'keluar_tengah') {
            if (empty($request->exit_time)) {
                return back()->withErrors(['exit_time' => 'Jam rencana keluar wajib diisi.']);
            }
            if ($isReturning) {
                if (empty($request->return_time)) {
                    return back()->withErrors(['return_time' => 'Jam rencana kembali wajib diisi jika Anda akan kembali ke kantor.']);
                }
                if ($request->return_time <= $request->exit_time) {
                    return back()->withErrors(['return_time' => 'Jam rencana kembali harus lebih besar dari jam rencana keluar.']);
                }
            }
        } elseif ($type === 'datang_terlambat') {
            if (empty($request->return_time)) {
                return back()->withErrors(['return_time' => 'Jam rencana masuk kerja (perkiraan tiba) wajib diisi.']);
            }
        }

        // Cek apakah pegawai sudah memiliki izin keluar aktif pada tanggal yang sama
        $hasActivePermit = ExitPermit::where('employee_nip', $nip)
            ->where('date', $request->date)
            ->whereIn('approval_status', [ExitPermit::STATUS_PENDING, ExitPermit::STATUS_APPROVED, ExitPermit::STATUS_OUT])
            ->exists();

        if ($hasActivePermit) {
            return back()->withErrors([
                'date' => 'Anda sudah memiliki pengajuan izin keluar yang aktif pada tanggal tersebut.',
            ]);
        }

        // Ambil info atasan
        $atasanNip  = $request->input('atasan_nip') ?: '10000063';
        $atasanNama = $request->input('atasan_nama') ?: 'Atasan Langsung';

        // Susun data penyimpanan
        if ($type === 'datang_terlambat') {
            $exitTime     = '00:00:00';
            $returnTime   = $request->return_time . ':00';
            $prefixReason = "[Izin Datang Terlambat] [Atasan: {$atasanNip} - {$atasanNama}] ";
        } else {
            $exitTime     = $request->exit_time . ':00';
            $returnTime   = $isReturning && ! empty($request->return_time) ? $request->return_time . ':00' : null;
            $modeLabel    = $isReturning ? '[Izin Keluar Sementara]' : '[Izin Keluar Langsung Pulang]';
            $prefixReason = "{$modeLabel} [Atasan: {$atasanNip} - {$atasanNama}] ";
        }

        ExitPermit::create([
            'employee_nip'    => $nip,
            'date'            => $request->date,
            'exit_time'       => $exitTime,
            'return_time'     => $returnTime,
            'reason'          => $prefixReason . $request->reason,
            'approval_status' => ExitPermit::STATUS_PENDING,
        ]);

        return redirect()->route('izin-keluar.index')->with('success', 'Pengajuan izin keluar kantor berhasil dikirim. Menunggu persetujuan dari atasan Anda (' . $atasanNama . ').');
    }

    /**
     * Persetujuan / Penolakan izin keluar oleh Atasan Langsung dari halaman izin keluar.
     */
    public function supervisorApproval(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $user = Auth::user();
        $nip  = $user->employee_nip;

        $request->validate([
            'status' => ['required', 'in:1,2'], // 1: Approve, 2: Reject
            'notes'  => ['nullable', 'string', 'max:255'],
        ]);

        $subordinateNips = $this->getSubordinateNips($nip);
        $isAuthorized = $user->hasAdminAccess()
            || in_array($exitPermit->employee_nip, $subordinateNips)
            || str_contains($exitPermit->reason, "[Atasan: {$nip}");

        if (! $isAuthorized) {
            abort(403, 'Akses ditolak. Anda bukan atasan langsung dari pegawai ini.');
        }

        $newStatus = (int) $request->input('status');
        $notes     = trim((string) $request->input('notes'));

        $updatedReason = $exitPermit->reason;
        if (! empty($notes)) {
            $updatedReason .= " [Catatan Atasan ({$user->name}): {$notes}]";
        }

        $exitPermit->update([
            'approval_status' => $newStatus,
            'reason'          => $updatedReason,
        ]);

        $message = $newStatus === ExitPermit::STATUS_APPROVED
            ? 'Izin keluar berhasil disetujui. Tiket gate pass QR satpam telah aktif.'
            : 'Izin keluar telah ditolak.';

        return back()->with('success', $message);
    }

    /**
     * Halaman Scanner QR Izin Keluar untuk Petugas Satpam / Keamanan.
     */
    public function securityIndex(Request $request): Response
    {
        $user = Auth::user();
        if (! $user->isSecurity() && ! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini khusus untuk Petugas Keamanan (Satpam).');
        }

        $today = now('Asia/Jakarta')->toDateString();

        // Riwayat scan hari ini oleh satpam
        $todayScans = ExitPermit::with(['employee.division'])
            ->where('date', $today)
            ->whereIn('approval_status', [ExitPermit::STATUS_OUT, ExitPermit::STATUS_COMPLETED])
            ->latest('updated_at')
            ->take(30)
            ->get()
            ->map(function ($permit) {
                return [
                    'id'                 => $permit->id,
                    'kode_izin'          => $permit->kode_izin,
                    'nip'                => $permit->employee_nip,
                    'nama_pegawai'       => $permit->employee?->full_name ?? '-',
                    'bagian'             => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'exit_time'          => substr((string) $permit->exit_time, 0, 5),
                    'return_time'        => $permit->return_time ? substr((string) $permit->return_time, 0, 5) : null,
                    'actual_exit_time'   => $permit->actual_exit_time,
                    'actual_return_time' => $permit->actual_return_time,
                    'reason'             => $permit->reason,
                    'approval_status'    => $permit->approval_status,
                    'updated_at'         => $permit->updated_at?->format('H:i:s'),
                ];
            });

        return Inertia::render('Employee/ExitPermitScanner', [
            'todayScans' => $todayScans,
            'guardInfo'  => [
                'username' => $user->username,
                'name'     => $user->name,
            ],
            'todayDate'  => $today,
        ]);
    }

    /**
     * Pemrosesan Scan QR Code Izin Keluar oleh Petugas Satpam via AJAX / Form.
     */
    public function securityProcessScan(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user->isSecurity() && ! $user->hasAdminAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya untuk petugas keamanan.',
            ], 403);
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $rawCode = trim($request->input('code'));
        $permit  = null;

        // Cari berdasarkan kode tiket (contoh: IK-20261104-001) atau ID numerik
        if (preg_match('/IK-\d{8}-(\d+)/i', $rawCode, $matches)) {
            $id = (int) $matches[1];
            $permit = ExitPermit::with(['employee.division'])->find($id);
        } elseif (is_numeric($rawCode)) {
            $permit = ExitPermit::with(['employee.division'])->find((int) $rawCode);
        } else {
            // Coba cari substring kode izin
            $permit = ExitPermit::with(['employee.division'])
                ->where('id', $rawCode)
                ->first();
        }

        if (! $permit) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket Izin Keluar tidak ditemukan di sistem.',
            ], 404);
        }

        $today = now('Asia/Jakarta')->toDateString();
        $currentTime = now('Asia/Jakarta')->format('H:i:s');
        $permitDate = $permit->date->format('Y-m-d');

        // Validasi tanggal
        if ($permitDate !== $today) {
            return response()->json([
                'success' => false,
                'message' => "QR Code ini hanya berlaku pada tanggal {$permit->date->format('d/m/Y')}. Hari ini adalah " . now('Asia/Jakarta')->format('d/m/Y') . '.',
            ], 422);
        }

        // Validasi status persetujuan atasan
        if ($permit->approval_status === ExitPermit::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Izin keluar belum disetujui (Approved) oleh atasan pengaju.',
            ], 422);
        }

        if ($permit->approval_status === ExitPermit::STATUS_REJECTED) {
            return response()->json([
                'success' => false,
                'message' => 'Izin keluar ini telah DITOLAK oleh atasan pengaju.',
            ], 422);
        }

        // Tipe Izin
        $isTerlambat = str_contains($permit->reason, '[Izin Datang Terlambat]') || $permit->exit_time === '00:00:00';
        $isLangsungPulang = str_contains($permit->reason, '[Izin Keluar Langsung Pulang]') || empty($permit->return_time);

        // Kasus 1: Izin Datang Terlambat -> Scan Masuk Sekali Langsung Selesai
        if ($isTerlambat) {
            if ($permit->approval_status === ExitPermit::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Izin Datang Terlambat pegawai ini sudah selesai discan masuk sebelumnya pada ' . ($permit->actual_return_time ?: '-') . '.',
                ], 422);
            }

            $updatedReason = $permit->reason . " [ScanMasuk: {$currentTime} oleh {$user->username}]";
            $permit->update([
                'approval_status' => ExitPermit::STATUS_COMPLETED,
                'reason'          => $updatedReason,
            ]);

            return response()->json([
                'success' => true,
                'action'  => 'masuk',
                'message' => "Jam MASUK kedatangan pegawai berhasil dicatat ({$currentTime}). Izin Datang Terlambat SELESAI.",
                'data'    => [
                    'kode_izin'          => $permit->kode_izin,
                    'nama_pegawai'       => $permit->employee?->full_name ?? '-',
                    'nip'                => $permit->employee_nip,
                    'bagian'             => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'actual_time'        => $currentTime,
                    'status_label'       => 'Selesai (Sudah Masuk)',
                ],
            ]);
        }

        // Kasus 2: Izin Keluar Normal (Scan Pertama: Keluar Gerbang)
        if ($permit->approval_status === ExitPermit::STATUS_APPROVED) {
            $nextStatus = $isLangsungPulang ? ExitPermit::STATUS_COMPLETED : ExitPermit::STATUS_OUT;
            $noteText = $isLangsungPulang
                ? " [ScanKeluar: {$currentTime} oleh {$user->username} (Langsung Pulang)]"
                : " [ScanKeluar: {$currentTime} oleh {$user->username}]";

            $permit->update([
                'approval_status' => $nextStatus,
                'reason'          => $permit->reason . $noteText,
            ]);

            $message = $isLangsungPulang
                ? "Jam KELUAR berhasil dicatat ({$currentTime}). Pegawai izin langsung pulang, status SELESAI."
                : "Jam KELUAR berhasil dicatat ({$currentTime}). Pegawai diizinkan keluar pabrik.";

            return response()->json([
                'success' => true,
                'action'  => 'keluar',
                'message' => $message,
                'data'    => [
                    'kode_izin'          => $permit->kode_izin,
                    'nama_pegawai'       => $permit->employee?->full_name ?? '-',
                    'nip'                => $permit->employee_nip,
                    'bagian'             => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'actual_time'        => $currentTime,
                    'status_label'       => $isLangsungPulang ? 'Selesai Pulang' : 'Sudah Keluar',
                ],
            ]);
        }

        // Kasus 3: Izin Keluar Normal (Scan Kedua: Kembali Masuk Pabrik)
        if ($permit->approval_status === ExitPermit::STATUS_OUT) {
            $permit->update([
                'approval_status' => ExitPermit::STATUS_COMPLETED,
                'reason'          => $permit->reason . " [ScanKembali: {$currentTime} oleh {$user->username}]",
            ]);

            return response()->json([
                'success' => true,
                'action'  => 'kembali',
                'message' => "Jam KEMBALI pegawai berhasil dicatat ({$currentTime}). Pegawai telah kembali ke lingkungan pabrik. Izin SELESAI.",
                'data'    => [
                    'kode_izin'          => $permit->kode_izin,
                    'nama_pegawai'       => $permit->employee?->full_name ?? '-',
                    'nip'                => $permit->employee_nip,
                    'bagian'             => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'actual_time'        => $currentTime,
                    'status_label'       => 'Selesai (Sudah Kembali)',
                ],
            ]);
        }

        // Kasus 4: Sudah Selesai
        return response()->json([
            'success' => false,
            'message' => 'Tiket izin keluar ini sudah SELESAI digunakan (jam keluar dan kembali telah tercatat lengkap).',
        ], 422);
    }

    /**
     * Halaman laporan rekapitulasi & pusat persetujuan izin keluar kantor untuk Admin / HRD Pusat.
     */
    public function adminIndex(Request $request): Response
    {
        $user = Auth::user();
        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Atasan.');
        }

        $bulan   = $request->input('bulan', 'all');
        $tahun   = $request->input('tahun', date('Y'));
        $status  = $request->input('status', 'all');
        $search  = trim($request->input('q', ''));
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));

        // Base Query
        $query = ExitPermit::with(['employee.division', 'employee.position']);

        if ($bulan !== 'all' && ! empty($bulan)) {
            $query->whereMonth('date', $bulan);
        }

        if ($tahun !== 'all' && ! empty($tahun)) {
            $query->whereYear('date', $tahun);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('approval_status', (int) $status);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_nip', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($empQ) use ($search) {
                        $empQ->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        // Hitung statistik ringkas (KPI non-grafik)
        $statQuery = ExitPermit::query();
        if ($bulan !== 'all' && ! empty($bulan)) {
            $statQuery->whereMonth('date', $bulan);
        }
        if ($tahun !== 'all' && ! empty($tahun)) {
            $statQuery->whereYear('date', $tahun);
        }

        $totalFiltered  = (clone $statQuery)->count();
        $totalPending   = (clone $statQuery)->where('approval_status', ExitPermit::STATUS_PENDING)->count();
        $totalApproved  = (clone $statQuery)->where('approval_status', ExitPermit::STATUS_APPROVED)->count();
        $totalRejected  = (clone $statQuery)->where('approval_status', ExitPermit::STATUS_REJECTED)->count();

        // Paginated items
        $permits = $query->latest('date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($permit) {
                return [
                    'id'              => $permit->id,
                    'kode_izin'       => $permit->kode_izin,
                    'nip'             => $permit->employee_nip,
                    'nama_pegawai'    => $permit->employee?->full_name ?? '-',
                    'bagian'          => $permit->employee?->division?->name ?? 'Plant Banjaran',
                    'jabatan'         => $permit->employee?->position?->name ?? '-',
                    'date'            => $permit->date->format('d/m/Y'),
                    'raw_date'        => $permit->date->format('Y-m-d'),
                    'exit_time'       => substr((string) $permit->exit_time, 0, 5),
                    'return_time'     => $permit->return_time ? substr((string) $permit->return_time, 0, 5) : null,
                    'reason'          => $permit->reason,
                    'approval_status' => $permit->approval_status,
                    'created_at'      => $permit->created_at?->format('d/m/Y H:i'),
                ];
            });

        // Daftar tahun untuk filter dropdown
        $currentYear = (int) date('Y');
        $years = range($currentYear, $currentYear - 5);

        return Inertia::render('Admin/ExitPermitReport', [
            'permits'    => $permits,
            'years'      => $years,
            'filters'    => [
                'bulan'    => (string) $bulan,
                'tahun'    => (string) $tahun,
                'status'   => (string) $status,
                'q'        => $search,
                'per_page' => $perPage,
            ],
            'statistics' => [
                'total'    => $totalFiltered,
                'pending'  => $totalPending,
                'approved' => $totalApproved,
                'rejected' => $totalRejected,
            ],
        ]);
    }

    /**
     * Proses persetujuan (Approve) atau penolakan (Reject) izin keluar oleh Admin / Atasan pusat.
     */
    public function updateStatus(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'status' => ['required', 'in:1,2'], // 1: Approved, 2: Rejected
        ]);

        $newStatus = (int) $request->input('status');
        $exitPermit->update([
            'approval_status' => $newStatus,
        ]);

        $message = $newStatus === ExitPermit::STATUS_APPROVED
            ? 'Izin keluar berhasil disetujui. Tiket gate pass digital pegawai telah aktif.'
            : 'Izin keluar berhasil ditolak.';

        return back()->with('success', $message);
    }
}
