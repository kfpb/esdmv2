<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OfficeLocation;
use App\Models\WorkShift;
use Carbon\Carbon;
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
            ->where('leave_requests.approval_status', 'Approved')
            ->select('leave_types.name as leave_name', 'leave_requests.reason')
            ->first();

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

        return [
            'hadir'         => $records->where('is_present_in', true)->count(),
            'terlambat'     => $records->where('is_late', true)->count(),
            'pulangCepat'   => $records->where('is_early_leave', true)->count(),
            'totalPotongan' => (int) $records->sum('deduction_amount'),
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
}
