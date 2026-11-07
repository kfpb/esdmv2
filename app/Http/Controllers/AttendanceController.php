<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OfficeLocation;
use App\Models\OrganizationStructure;
use App\Models\WorkShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Dashboard presensi mandiri pegawai - menampilkan status harian,
     * formulir absen masuk / pulang, sinkronisasi face-id, kartu ulang tahun,
     * daftar keterlambatan hari ini, dan peta geofence poligon KFPB.
     */
    public function employeeDashboard(): Response
    {
        $user  = Auth::user();
        $nip   = $user->employee_nip;
        $today = now('Asia/Jakarta')->toDateString();

        // 1. Ambil data pegawai relasi
        $employee = Employee::where('nip', $nip)->with('division', 'subDivision', 'position')->first();

        // 2. Status presensi (eSDM): prioritaskan presensi aktif yang belum checkout
        $todayAttendance = Attendance::where('employee_nip', $nip)
            ->whereNotNull('check_in_time')
            ->where(function ($query) {
                $query->whereNull('check_out_time')
                    ->orWhere('check_out_time', '00:00:00')
                    ->orWhere('check_out_time', '00:00:01');
            })
            ->with('workShift')
            ->latest('attendance_date')
            ->first();

        if (! $todayAttendance) {
            $todayAttendance = Attendance::where('employee_nip', $nip)
                ->where('attendance_date', $today)
                ->with('workShift')
                ->first();
        }

        // 3. Sinkronisasi Biometrik Wajah hari ini (Face-ID machine)
        $faceRecords = DB::table('face_biometrics')
            ->where('employee_nip', $nip)
            ->where('recorded_date', $today)
            ->orderBy('recorded_time')
            ->get();

        $faceCheckIn  = $faceRecords->first()?->recorded_time ? substr($faceRecords->first()->recorded_time, 0, 5) : '-';
        $faceCheckOut = ($faceRecords->count() > 1 && $faceRecords->last()?->recorded_time)
            ? substr($faceRecords->last()->recorded_time, 0, 5)
            : '-';

        // 4. Deteksi Pengajuan Izin / Cuti aktif hari ini
        $activeLeave = DB::table('leave_requests')
            ->leftJoin('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->where('leave_requests.employee_nip', $nip)
            ->where('leave_requests.start_date', '<=', $today)
            ->where('leave_requests.end_date', '>=', $today)
            ->where(function ($q) {
                $q->where('leave_requests.approval_status', 1)
                    ->orWhere('leave_requests.approval_status', 'Approved');
            })
            ->select('leave_types.name as leave_name', 'leave_requests.reason')
            ->first();

        if (! $activeLeave) {
            $activePermission = DB::table('permission_requests')
                ->leftJoin('permission_types', 'permission_requests.permission_type_id', '=', 'permission_types.id')
                ->where('permission_requests.employee_nip', $nip)
                ->where('permission_requests.start_date', '<=', $today)
                ->where('permission_requests.end_date', '>=', $today)
                ->where('permission_requests.approval_status', 1)
                ->select('permission_types.name as leave_name', 'permission_requests.reason')
                ->first();

            if ($activePermission) {
                $activeLeave = $activePermission;
            }
        }

        // 5. Cek Ulang Tahun Pegawai Hari Ini
        $isBirthday = false;
        if ($employee && $employee->birth_date) {
            $birthDate  = Carbon::parse($employee->birth_date);
            $isBirthday = ($birthDate->format('m-d') === now('Asia/Jakarta')->format('m-d'));
        }

        $birthdayData = [
            'isBirthday'   => $isBirthday,
            'employeeName' => $employee?->full_name ?? $user->name,
            'gender'       => $employee?->gender ?? 'Male',
        ];

        // 6. Daftar shift aktif untuk dropdown presensi
        $workShifts = WorkShift::orderByRaw("
            CASE
                WHEN name LIKE '%Lembur%' OR name LIKE '%lembur%' THEN 1
                WHEN name LIKE '%Ramadhan%' OR name LIKE '%Rmdn%' THEN 0
                ELSE -1
            END
        ")->orderBy('check_in_time')->get(['id', 'name', 'check_in_time', 'check_out_time', 'is_night_shift']);

        // Shift paling sering dipakai 1 bulan terakhir (auto-selected)
        $frequentShiftId = Attendance::where('employee_nip', $nip)
            ->where('attendance_date', '>=', now('Asia/Jakarta')->subMonth()->toDateString())
            ->whereNotNull('work_shift_id')
            ->select('work_shift_id', DB::raw('COUNT(*) as freq'))
            ->groupBy('work_shift_id')
            ->orderByDesc('freq')
            ->value('work_shift_id');

        // 7. Ringkasan kehadiran bulan berjalan
        $monthSummary = $this->getMonthSummary($nip, $today);

        // 8. Riwayat presensi pegawai (dibatasi 3 data dengan pagination)
        $recentAttendances = Attendance::where('employee_nip', $nip)
            ->with('workShift')
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_time')
            ->paginate(3, ['*'], 'halaman')
            ->withQueryString()
            ->through(function ($att) {
                $statusLabel = 'Hadir Tepat Waktu';
                $statusSeverity = 'success';

                if ($att->is_late) {
                    $statusLabel = 'Terlambat';
                    $statusSeverity = 'warn';
                } elseif ($att->is_early_leave) {
                    $statusLabel = 'Pulang Cepat';
                    $statusSeverity = 'warn';
                } elseif (! $att->check_out_time) {
                    $statusLabel = 'Belum Pulang';
                    $statusSeverity = 'info';
                }

                return [
                    'id'               => $att->id,
                    'date_formatted'   => $this->formatDateIndo($att->attendance_date->toDateString()),
                    'raw_date'         => $att->attendance_date->toDateString(),
                    'shift_name'       => $att->workShift?->name ?? 'Reguler',
                    'check_in'         => $att->check_in_time ? substr($att->check_in_time, 0, 5) : '-',
                    'check_out'        => ($att->check_out_time && $att->check_out_time !== '00:00:01') ? substr($att->check_out_time, 0, 5) : '-',
                    'work_mode'        => $att->work_mode ?? 'WFO',
                    'is_late'          => (bool) $att->is_late,
                    'is_early_leave'   => (bool) $att->is_early_leave,
                    'status_label'     => $statusLabel,
                    'status_severity'  => $statusSeverity,
                    'deduction_amount' => (int) $att->deduction_amount,
                    'notes'            => $att->notes ?? '-',
                ];
            });

        // 9. Data poligon ke-6 gedung Plant Banjaran dari tabel office_locations (legacy tbl_absen2)
        $geofenceZones = OfficeLocation::where('is_active', true)
            ->get()
            ->map(fn ($loc) => [
                'id'        => $loc->id,
                'name'      => $loc->name,
                'label'     => $loc->label ?? ucfirst($loc->name),
                'radius'    => (int) $loc->radius,
                'latitude'  => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'koordinat' => $loc->polygon_coordinates ?? [],
            ]);

        // 9. Top 5 Paling Terlambat Hari Ini (eSDM)
        $topLateToday = Attendance::where('attendance_date', $today)
            ->where('is_late', true)
            ->whereNotNull('check_in_time')
            ->with(['employee.division', 'workShift'])
            ->orderByDesc('check_in_time')
            ->take(5)
            ->get()
            ->map(fn ($att) => [
                'name'     => $att->employee?->full_name ?? $att->employee_nip,
                'division' => $att->employee?->division?->name ?? 'Plant Banjaran',
                'time'     => substr($att->check_in_time, 0, 5),
                'shift'    => $att->workShift?->name ?? 'Reguler',
            ]);

        // 10. Top 5 Keterlambatan Absen Wajah Hari Ini (Biometrik Face-ID)
        $topLateBiometric = DB::table('face_biometrics')
            ->join('employees', 'face_biometrics.employee_nip', '=', 'employees.nip')
            ->leftJoin('divisions', 'employees.division_id', '=', 'divisions.id')
            ->where('face_biometrics.recorded_date', $today)
            ->where('face_biometrics.recorded_time', '>', '08:00:00')
            ->select(
                'employees.full_name as name',
                'divisions.name as division',
                'face_biometrics.recorded_time as time'
            )
            ->orderByDesc('face_biometrics.recorded_time')
            ->take(5)
            ->get()
            ->map(fn ($row) => [
                'name'     => $row->name,
                'division' => $row->division ?? 'Plant Banjaran',
                'time'     => substr($row->time, 0, 5),
                'shift'    => 'Biometrik Face-ID',
            ]);

        return Inertia::render('Employee/Dashboard', [
            'todayAttendance'    => $todayAttendance ? $this->formatAttendance($todayAttendance) : null,
            'faceCheckIn'        => $faceCheckIn,
            'faceCheckOut'       => $faceCheckOut,
            'activeLeave'        => $activeLeave ? [
                'name'   => $activeLeave->leave_name ?? 'Izin / Cuti',
                'reason' => $activeLeave->reason ?? '-',
            ] : null,
            'birthdayData'       => $birthdayData,
            'topLateToday'      => $topLateToday,
            'topLateBiometric'  => $topLateBiometric,
            'recentAttendances' => $recentAttendances,
            'workShifts'         => $workShifts->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'check_in'  => substr($s->check_in_time, 0, 5),
                'check_out' => substr($s->check_out_time, 0, 5),
                'is_night'  => $s->is_night_shift,
            ]),
            'frequentShiftId'  => $frequentShiftId,
            'monthSummary'     => $monthSummary,
            'geofenceZones'    => $geofenceZones,
            'todayLabel'       => $this->formatDateIndo($today),
            'employee'         => $employee ? [
                'nip'       => $employee->nip,
                'name'      => $employee->full_name,
                'position'  => $employee->position?->name ?? 'Staf Pegawai',
                'division'  => $employee->division?->name ?? 'PT Kimia Farma Plant Banjaran',
            ] : null,
        ]);
    }

    /**
     * Proses absen masuk (check-in) dengan validasi geofence sisi server.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $request->validate([
            'work_shift_id' => ['required', 'exists:work_shifts,id'],
            'work_mode'     => ['required', 'in:WFO,FLEXI,PKL'],
            'latitude'      => ['required', 'numeric'],
            'longitude'     => ['required', 'numeric'],
        ], [
            'work_shift_id.required' => 'Shift kerja wajib dipilih.',
            'work_mode.required'     => 'Mode kerja wajib dipilih.',
            'latitude.required'      => 'Lokasi GPS tidak terdeteksi.',
            'longitude.required'     => 'Lokasi GPS tidak terdeteksi.',
        ]);

        $user  = Auth::user();
        $nip   = $user->employee_nip;
        $today = now('Asia/Jakarta')->toDateString();

        // Cek duplikasi absen hari ini
        $existing = Attendance::where('employee_nip', $nip)
            ->where('attendance_date', $today)
            ->exists();

        if ($existing) {
            return back()->with('error', 'Anda sudah melakukan absen masuk hari ini.');
        }

        $shift    = WorkShift::findOrFail($request->work_shift_id);
        $workMode = $request->work_mode;
        $lat      = (float) $request->latitude;
        $lon      = (float) $request->longitude;
        $now      = now('Asia/Jakarta');

        // Validasi geofence — wajib untuk mode WFO
        if ($workMode === 'WFO') {
            $isInsideZone = $this->isInsideGeofence($lat, $lon);
            if (! $isInsideZone) {
                return back()->with('error', 'Absen masuk GAGAL: Anda berada di luar area kantor KFPB!');
            }
        }

        // Hitung keterlambatan & potongan
        $checkInTime     = $shift->check_in_time;
        $currentTime     = $now->format('H:i:s');
        $isLate          = false;
        $deductionAmount = 0;

        if ($workMode === 'WFO' && $currentTime > $checkInTime && ! $shift->is_night_shift) {
            $isLate = true;
            $diffMinutes = (strtotime($currentTime) - strtotime($checkInTime)) / 60;

            if ($diffMinutes >= 5 && $diffMinutes < 31) {
                $deductionAmount = 5000;
            } elseif ($diffMinutes >= 31 && $diffMinutes < 61) {
                $deductionAmount = 10000;
            } elseif ($diffMinutes >= 61 && $diffMinutes < 121) {
                $deductionAmount = 15000;
            } elseif ($diffMinutes >= 121) {
                $deductionAmount = 20000;
            }
        }

        Attendance::create([
            'employee_nip'       => $nip,
            'attendance_date'    => $today,
            'work_shift_id'      => $shift->id,
            'check_in_time'      => $currentTime,
            'is_present_in'      => true,
            'is_late'            => $isLate,
            'check_in_latitude'  => (string) $lat,
            'check_in_longitude' => (string) $lon,
            'work_mode'          => $workMode,
            'deduction_amount'   => $deductionAmount,
        ]);

        $message = $isLate
            ? "Absen masuk tercatat: {$currentTime} WIB (Terlambat)."
            : "Absen masuk berhasil! Jam masuk: {$currentTime} WIB — Tepat Waktu.";

        return back()->with('success', $message);
    }

    /**
     * Proses absen pulang (check-out) dengan validasi geofence sisi server.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        $request->validate([
            'latitude'  => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'notes'     => ['nullable', 'string', 'max:255'],
        ], [
            'latitude.required'  => 'Lokasi GPS tidak terdeteksi.',
            'longitude.required' => 'Lokasi GPS tidak terdeteksi.',
        ]);

        $user  = Auth::user();
        $nip   = $user->employee_nip;

        $attendance = Attendance::where('employee_nip', $nip)
            ->whereNotNull('check_in_time')
            ->where(function ($query) {
                $query->whereNull('check_out_time')
                    ->orWhere('check_out_time', '00:00:00')
                    ->orWhere('check_out_time', '00:00:01');
            })
            ->latest('attendance_date')
            ->first();

        if (! $attendance) {
            return back()->with('error', 'Tidak ditemukan data absen masuk yang aktif.');
        }

        $lat = (float) $request->latitude;
        $lon = (float) $request->longitude;
        $now = now('Asia/Jakarta');

        // Validasi geofence — wajib untuk mode WFO
        if ($attendance->work_mode === 'WFO') {
            $isInsideZone = $this->isInsideGeofence($lat, $lon);
            if (! $isInsideZone) {
                return back()->with('error', 'Absen pulang GAGAL: Anda berada di luar area kantor KFPB!');
            }
        }

        $currentTime  = $now->format('H:i:s');
        $isEarlyLeave = false;

        if ($attendance->workShift && ! $attendance->workShift->is_night_shift) {
            $checkOutTime = $attendance->workShift->check_out_time;
            $isEarlyLeave = $currentTime < $checkOutTime;
        }

        $attendance->update([
            'check_out_time'      => $currentTime,
            'is_present_out'      => true,
            'is_early_leave'      => $isEarlyLeave,
            'check_out_latitude'  => (string) $lat,
            'check_out_longitude' => (string) $lon,
            'notes'               => $request->notes,
        ]);

        return back()->with('success', "Absen pulang berhasil! Jam pulang: {$currentTime} WIB.");
    }

    // =========================================================
    // Private helpers
    // =========================================================

    /** Format data attendance untuk dikirim ke Vue. */
    private function formatAttendance(Attendance $a): array
    {
        return [
            'id'               => $a->id,
            'attendance_date'  => $a->attendance_date->toDateString(),
            'shift_name'       => $a->workShift?->name ?? '-',
            'check_in_time'    => $a->check_in_time,
            'check_out_time'   => $a->check_out_time,
            'is_present_in'    => $a->is_present_in,
            'is_present_out'   => $a->is_present_out,
            'is_late'          => $a->is_late,
            'is_early_leave'   => $a->is_early_leave,
            'work_mode'        => $a->work_mode,
            'deduction_amount' => $a->deduction_amount,
            'notes'            => $a->notes,
        ];
    }

    /** Ringkasan kehadiran bulan berjalan. */
    private function getMonthSummary(string $nip, string $today): array
    {
        $startOfMonth = now('Asia/Jakarta')->startOfMonth()->toDateString();

        $records = Attendance::where('employee_nip', $nip)
            ->whereBetween('attendance_date', [$startOfMonth, $today])
            ->get();

        $hadir       = $records->where('is_present_in', true)->count();
        $terlambat   = $records->where('is_late', true)->count();
        $pulangCepat = $records->where('is_early_leave', true)->count();
        $potongan    = (int) $records->sum('deduction_amount');

        return [
            'hadir'           => $hadir,
            'total_hadir'     => $hadir,
            'terlambat'       => $terlambat,
            'total_terlambat' => $terlambat,
            'pulangCepat'     => $pulangCepat,
            'totalPotongan'   => $potongan,
            'total_potongan'  => $potongan,
        ];
    }

    /**
     * Validasi apakah titik GPS berada dalam poligon salah satu gedung aktif di KFPB.
     * Menggunakan algoritma Ray-Casting dan toleransi radius terdekat.
     */
    private function isInsideGeofence(float $lat, float $lon): bool
    {
        $locations = OfficeLocation::where('is_active', true)->get();

        if ($locations->isEmpty()) {
            return $this->haversineMeters($lat, $lon, -7.0390570, 107.5960505) <= 300;
        }

        foreach ($locations as $loc) {
            $polygon = $loc->polygon_coordinates ?? [];
            if (empty($polygon) || count($polygon) < 3) {
                continue;
            }

            // 1. Ray-Casting point-in-polygon
            if ($this->pointInPolygon($lat, $lon, $polygon)) {
                return true;
            }

            // 2. Buffer toleransi radius (default 50 meter) terhadap titik sudut poligon & centroid
            $toleranceMeters = max((int) $loc->radius, 60);

            if ($loc->latitude && $loc->longitude) {
                if ($this->haversineMeters($lat, $lon, (float) $loc->latitude, (float) $loc->longitude) <= $toleranceMeters) {
                    return true;
                }
            }

            foreach ($polygon as $pt) {
                if ($this->haversineMeters($lat, $lon, (float) $pt[0], (float) $pt[1]) <= $toleranceMeters) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Algoritma Ray-Casting Point-in-Polygon */
    private function pointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        $inside = false;
        $count  = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $lat_i = (float) $polygon[$i][0];
            $lon_i = (float) $polygon[$i][1];
            $lat_j = (float) $polygon[$j][0];
            $lon_j = (float) $polygon[$j][1];

            $intersect = (($lon_i > $lon) !== ($lon_j > $lon))
                && ($lat < ($lat_j - $lat_i) * ($lon - $lon_i) / ($lon_j - $lon_i) + $lat_i);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Jarak Haversine dalam satuan meter */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Format tanggal ke format Indonesia (mis. "Sabtu, 31 Oktober 2026"). */
    private function formatDateIndo(string $dateString): string
    {
        $days   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1  => 'Januari', 2  => 'Februari', 3  => 'Maret',
            4  => 'April',   5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',    8  => 'Agustus',   9  => 'September',
            10 => 'Oktober', 11 => 'November',  12 => 'Desember',
        ];

        $ts    = strtotime($dateString);
        $day   = $days[(int) date('w', $ts)];
        $d     = date('j', $ts);
        $month = $months[(int) date('n', $ts)];
        $year  = date('Y', $ts);

        return "{$day}, {$d} {$month} {$year}";
    }

    /**
     * Halaman Laporan Absensi Pegawai untuk Role User (Setara /user.php?id=lapabsensi)
     * Menampilkan data table presensi, filter rentang tanggal, filter pegawai/bawahan,
     * filter sumber laporan (eSDM / Absen Wajah), dan cetak/export PDF standar korporat.
     */
    public function employeeReport(Request $request): Response
    {
        $user       = Auth::user();
        $isAdmin    = $user->hasAdminAccess();
        $currentNip = $user->employee_nip ?? $user->nip;
        $employee   = $currentNip ? Employee::where('nip', $currentNip)->with(['division', 'position'])->first() : null;

        // 1. Ambil daftar bawahan jika user adalah atasan (supervisor / admin)
        $subordinateNips = [];
        if ($currentNip) {
            try {
                $node = OrganizationStructure::findByNip($currentNip);
                $subordinateNips = $node ? $node->getSubordinateNips() : [];
            } catch (\Throwable $e) {
                $subordinateNips = [];
            }
        }

        $isSupervisor = $user->isSupervisor() || count($subordinateNips) > 0 || $isAdmin;

        // 2. Pilihan daftar pegawai untuk dropdown filter
        $employeeOptions = [];
        if ($isAdmin) {
            // Admin dapat melihat seluruh pegawai aktif di KFPB
            $allEmployees = Employee::orderBy('full_name')->get(['nip', 'full_name']);
            foreach ($allEmployees as $emp) {
                $employeeOptions[] = [
                    'nip'  => $emp->nip,
                    'name' => "{$emp->nip} - {$emp->full_name}",
                ];
            }
        } else {
            if ($currentNip) {
                $employeeOptions[] = [
                    'nip'  => $currentNip,
                    'name' => ($employee?->full_name ?? $currentNip) . ' (Diri Sendiri)',
                ];
            }
            if ($isSupervisor && count($subordinateNips) > 0) {
                $subs = Employee::whereIn('nip', $subordinateNips)->orderBy('full_name')->get(['nip', 'full_name']);
                foreach ($subs as $sub) {
                    $employeeOptions[] = [
                        'nip'  => $sub->nip,
                        'name' => "{$sub->nip} - {$sub->full_name}",
                    ];
                }
            }
        }

        // 3. Tentukan target NIP yang sedang dilihat
        $requestedNip = trim((string) $request->input('nip', ''));
        if (! empty($requestedNip)) {
            // Validasi otorisasi: hanya izinkan jika diri sendiri, atau admin, atau bawahan sah
            if ($isAdmin || $requestedNip === $currentNip || in_array($requestedNip, $subordinateNips)) {
                $targetNip = $requestedNip;
            } else {
                $targetNip = $currentNip;
            }
        } else {
            // Jika requestedNip kosong: prioritaskan currentNip (jika valid), atau pegawai pertama jika akun admin tanpa NIP
            if ($currentNip && Employee::where('nip', $currentNip)->exists()) {
                $targetNip = $currentNip;
            } elseif ($isAdmin && ! empty($employeeOptions)) {
                $targetNip = $employeeOptions[0]['nip'];
            } else {
                $targetNip = $currentNip;
            }
        }

        $targetEmployee = ($targetNip === $currentNip && $employee)
            ? $employee
            : Employee::where('nip', $targetNip)->with(['division', 'position'])->first();

        // 4. Filter rentang tanggal (default awal bulan berjalan s/d akhir bulan berjalan agar mencakup 1 bulan full)
        $startDate = $request->input('awal', $request->input('start_date', now('Asia/Jakarta')->startOfMonth()->toDateString()));
        $endDate   = $request->input('akhir', $request->input('end_date', now('Asia/Jakarta')->endOfMonth()->toDateString()));
        $source    = $request->input('sumber', 'all'); // 'all', 'esdm', 'wajah'
        $today     = now('Asia/Jakarta')->toDateString();

        // 5. Query data absensi eSDM yang sudah ada
        $attendancesRaw = Attendance::where('employee_nip', $targetNip)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->with(['workShift', 'permissionType'])
            ->get()
            ->keyBy(fn ($a) => $a->attendance_date->format('Y-m-d'));

        // 6. Ambil data biometrik wajah (Face-ID) pada rentang tanggal yang sama
        $faceBiometricsMap = DB::table('face_biometrics')
            ->where('employee_nip', $targetNip)
            ->whereBetween('recorded_date', [$startDate, $endDate])
            ->orderBy('recorded_time')
            ->get()
            ->groupBy('recorded_date');

        // 7. Ambil data izin/cuti yang disetujui
        $approvedPermissions = DB::table('permission_requests')
            ->leftJoin('permission_types', 'permission_requests.permission_type_id', '=', 'permission_types.id')
            ->where('permission_requests.employee_nip', $targetNip)
            ->where('permission_requests.start_date', '<=', $endDate)
            ->where('permission_requests.end_date', '>=', $startDate)
            ->where('permission_requests.approval_status', 1)
            ->select(
                'permission_requests.*',
                'permission_types.name as perm_name'
            )
            ->get();

        $permissionDateMap = [];
        foreach ($approvedPermissions as $perm) {
            $pStart = Carbon::parse($perm->start_date)->max(Carbon::parse($startDate));
            $pEnd   = Carbon::parse($perm->end_date)->min(Carbon::parse($endDate));
            $pPeriod = CarbonPeriod::create($pStart, $pEnd);
            foreach ($pPeriod as $pDate) {
                $permissionDateMap[$pDate->format('Y-m-d')] = [
                    'id'                 => $perm->id,
                    'perm_name'          => $perm->perm_name,
                    'permission_type_id' => $perm->permission_type_id,
                    'reason'             => $perm->reason,
                ];
            }
        }

        // 8. Ambil data Hari Libur Nasional dari tabel holidays
        $holidaysMap = DB::table('holidays')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($h) => Carbon::parse($h->date)->format('Y-m-d'));

        // 9. Susun seluruh tanggal kalender dalam rentang $startDate s/d $endDate
        $period   = CarbonPeriod::create($startDate, $endDate);
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $records  = [];

        foreach ($period as $dt) {
            $dateStr   = $dt->format('Y-m-d');
            $dayOfWeek = (int) $dt->format('w'); // 0: Minggu, 6: Sabtu
            $dayName   = $dayNames[$dayOfWeek];
            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
            $holiday   = $holidaysMap->get($dateStr);
            $isHoliday = ! empty($holiday);
            $isFuture  = ($dateStr > $today);

            $att       = $attendancesRaw->get($dateStr);
            $faceGroup = $faceBiometricsMap->get($dateStr);
            $faceIn    = $faceGroup?->first()?->recorded_time ? substr($faceGroup->first()->recorded_time, 0, 5) : null;
            $faceOut   = ($faceGroup && $faceGroup->count() > 1 && $faceGroup->last()?->recorded_time)
                ? substr($faceGroup->last()->recorded_time, 0, 5)
                : null;

            $checkIn = $att?->check_in_time ? substr((string) $att->check_in_time, 0, 5) : ($faceIn ?? '-');
            $checkOut = ($att?->check_out_time && $att->check_out_time !== '00:00:00' && $att->check_out_time !== '00:00:01')
                ? substr((string) $att->check_out_time, 0, 5)
                : ($faceOut ?? '-');

            $hasAttended = ($checkIn !== '-' || $checkOut !== '-' || ($att && $att->is_present_in));
            $overtimeDuration = $att?->overtime_duration ?: ($att?->is_overtime ? 'Lembur' : '-');
            $hasOvertime = ($overtimeDuration !== '-' && ! empty($overtimeDuration));

            // Filter sumber data presensi jika dipilih
            if ($source === 'wajah' && empty($faceIn) && empty($faceOut)) {
                continue;
            }
            if ($source === 'esdm' && (! $att || (empty($att->check_in_time) && empty($att->check_out_time)))) {
                continue;
            }

            $perm = $permissionDateMap[$dateStr] ?? null;

            // Klasifikasi jenis baris sesuai legenda cetak absensi KFPB
            if ($isWeekend || $isHoliday) {
                if ($hasAttended || $hasOvertime) {
                    $badgeType = 'lembur_libur';
                    $status    = 'Lembur / Masuk Libur';
                    $severity  = 'success';
                    $notes     = $att?->notes ?: ($isHoliday ? ('Lembur Libur: ' . $holiday->name) : 'Lembur Hari Libur');
                } elseif ($isHoliday) {
                    $badgeType = 'libur_nasional';
                    $status    = 'Libur Nasional';
                    $severity  = 'warn';
                    $notes     = $holiday->name ?: 'Libur Nasional';
                } else {
                    $badgeType = 'weekend';
                    $status    = 'Libur Mingguan';
                    $severity  = 'secondary';
                    $notes     = $isFuture ? 'Libur Akhir Pekan (Belum Berjalan)' : ($att?->notes ?: 'Libur Akhir Pekan');
                }
            } elseif ($isFuture && ! $hasAttended) {
                $badgeType = 'belum_berjalan';
                $status    = 'Belum Berjalan';
                $severity  = 'secondary';
                $notes     = 'Belum Berjalan';
            } elseif ($perm && ! $hasAttended) {
                $badgeType = 'izin';
                $status    = $perm['perm_name'] ?? 'Izin';
                $severity  = ($perm['permission_type_id'] == 2) ? 'danger' : 'info';
                $notes     = $perm['reason'] ?: 'Izin Disetujui';
            } elseif ($hasAttended) {
                if ($att?->is_late) {
                    $badgeType = 'terlambat';
                    $status    = 'Terlambat';
                    $severity  = 'warn';
                } else {
                    $badgeType = 'hadir';
                    $status    = 'Hadir Tepat Waktu';
                    $severity  = 'success';
                }
                $notes = $att?->notes ?: '-';
            } else {
                $badgeType = 'alpha';
                $status    = 'Tidak Hadir';
                $severity  = 'danger';
                $notes     = 'Tidak Ada Presensi (Alpha)';
            }

            $records[] = [
                'id'                => $att?->id ? (string) $att->id : ('row-' . $dateStr),
                'date'              => $dateStr,
                'formatted_date'    => $dt->format('d/m/Y'),
                'day_name'          => $dayName,
                'shift_name'        => $att?->workShift?->name ?? 'Non-Shift',
                'check_in'          => $checkIn,
                'check_out'         => $checkOut,
                'is_late'           => (bool) ($att?->is_late ?? false),
                'is_early_leave'    => (bool) ($att?->is_early_leave ?? false),
                'overtime_duration' => $overtimeDuration,
                'status'            => $status,
                'badge_type'        => $badgeType,
                'severity'          => $severity,
                'notes'             => $notes,
                'work_mode'         => $att?->work_mode ?? '-',
                'face_in'           => $faceIn,
                'face_out'          => $faceOut,
            ];
        }

        // Urutkan tanggal terbaru di atas untuk tampilan tabel
        usort($records, fn ($a, $b) => strcmp($b['date'], $a['date']));

        $totalRecord = count($records);
        $totalHadir  = count(array_filter($records, fn ($r) => in_array($r['status'], ['Hadir Tepat Waktu', 'Terlambat']) || $r['badge_type'] === 'lembur_libur'));
        $totalLate   = count(array_filter($records, fn ($r) => $r['is_late'] === true || $r['status'] === 'Terlambat'));
        $totalIzin   = count(array_filter($records, fn ($r) => $r['badge_type'] === 'izin'));
        $totalLembur = count(array_filter($records, fn ($r) => $r['overtime_duration'] !== '-' || $r['badge_type'] === 'lembur_libur'));
        $totalAlpha  = count(array_filter($records, fn ($r) => $r['badge_type'] === 'alpha'));
        $totalLibur  = count(array_filter($records, fn ($r) => in_array($r['badge_type'], ['weekend', 'libur_nasional'])));

        return Inertia::render('Employee/AttendanceReport', [
            'attendances'      => $records,
            'targetEmployee'   => [
                'nip'       => $targetEmployee?->nip ?? $targetNip,
                'name'      => $targetEmployee?->full_name ?? '-',
                'division'  => $targetEmployee?->division?->name ?? 'Plant Banjaran',
                'position'  => $targetEmployee?->position?->name ?? '-',
            ],
            'employeeOptions'  => $employeeOptions,
            'isSupervisor'     => $isSupervisor,
            'isAdmin'          => $isAdmin,
            'filters'          => [
                'nip'        => $targetNip,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'sumber'     => $source,
            ],
            'statistics'       => [
                'total'     => $totalRecord,
                'hadir'     => $totalHadir,
                'terlambat' => $totalLate,
                'izin'      => $totalIzin,
                'lembur'    => $totalLembur,
                'alpha'     => $totalAlpha,
                'libur'     => $totalLibur,
            ],
        ]);
    }
}
