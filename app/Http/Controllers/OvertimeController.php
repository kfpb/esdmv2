<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeParticipant;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    /**
     * Halaman utama Pengajuan Lembur (SPL) Pegawai Mandiri.
     * Menampilkan formulir baru + riwayat pengajuan lembur milik NIP yang login.
     */
    public function create(Request $request): Response
    {
        $user    = Auth::user();
        $authNip = $user->employee_nip;
        $employee = Employee::where('nip', $authNip)
            ->with(['division', 'subDivision', 'position'])
            ->first();

        // Daftar seluruh pejabat yang dapat menjadi ACC (jabatan Asman/Manager/Supervisor)
        // Mereplikasi logika native: WHERE id_jab IN ('2','3','4') – Asman, Manager, GM
        $supervisorCandidates = Employee::where('is_active', true)
            ->whereNotNull('position_id')
            ->orderBy('full_name')
            ->get(['nip', 'full_name', 'position_id'])
            ->map(fn ($emp) => [
                'nip'       => $emp->nip,
                'full_name' => $emp->full_name,
                'label'     => "{$emp->full_name} ({$emp->nip})",
            ]);

        // Daftar semua pegawai aktif untuk dipilih sebagai peserta lembur
        $allEmployees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['nip', 'full_name'])
            ->map(fn ($emp) => [
                'nip'       => $emp->nip,
                'full_name' => $emp->full_name,
                'label'     => "{$emp->full_name} ({$emp->nip})",
            ]);

        // Riwayat 15 pengajuan lembur terakhir milik pegawai yang login
        $recentOvertime = OvertimeRequest::where('applicant_nip', $authNip)
            ->orderByDesc('request_date')
            ->take(15)
            ->get()
            ->map(fn ($ot) => $this->formatOvertimeForView($ot));

        // Jumlah SPL menunggu verifikasi (sudah ACC tapi belum diverifikasi)
        $pendingVerification = OvertimeRequest::where('applicant_nip', $authNip)
            ->where('approval_status', 1)
            ->whereNull('end_date')
            ->count();

        return Inertia::render('Employee/OvertimeRequest', [
            'authEmployee' => [
                'nip'          => $authNip,
                'name'         => $employee?->full_name ?? $user->name,
                'division'     => $employee?->division?->name ?? 'PT Kimia Farma Plant Banjaran',
                'sub_division' => $employee?->subDivision?->name ?? '-',
                'position'     => $employee?->position?->name ?? 'Pegawai',
            ],
            'overtimeTypes'           => OvertimeRequest::OVERTIME_TYPES,
            'supervisorCandidates'    => $supervisorCandidates,
            'allEmployees'            => $allEmployees,
            'recentOvertime'          => $recentOvertime,
            'pendingVerificationCount' => $pendingVerification,
        ]);
    }

    /**
     * Simpan pengajuan SPL baru ke database.
     * Menggunakan DB Transaction untuk menyimpan master (overtime_requests) + detail peserta (overtime_participants).
     */
    public function store(StoreOvertimeRequest $request): RedirectResponse
    {
        $nip         = $request->input('applicant_nip');
        $overtimeType = (int) $request->input('overtime_type');
        $startDate   = $request->input('start_date');
        $startTime   = $request->input('start_time'); // HH:MM jam mulai lembur
        $endTime     = $request->input('end_time');   // HH:MM jam selesai lembur
        $supervisors = array_values(array_filter((array) $request->input('supervisors', [])));
        $participants = $request->input('participants', []);

        DB::transaction(function () use ($nip, $overtimeType, $startDate, $startTime, $endTime, $supervisors, $participants) {
            // Simpan SPL Master ke overtime_requests
            $overtimeRequest = OvertimeRequest::create([
                'applicant_nip'    => $nip,
                'overtime_type'    => $overtimeType,
                'request_date'     => now()->toDateString(),
                'start_date'       => $startDate,
                'end_date'         => null, // Diisi saat verifikasi realisasi
                'duration'         => $startTime . ':00', // Jam mulai lembur (mapping: lama_lembur)
                'reason'           => $endTime . ':00',   // Jam selesai lembur (mapping: alasan)
                'approval_status'  => 3, // Menunggu ACC 1 (kd_approve=3 di sistem lama)
                'supervisor_title_1' => $supervisors[0] ?? null,
                'supervisor_title_2' => $supervisors[1] ?? null,
                'supervisor_title_3' => $supervisors[2] ?? null,
                'supervisor_status_1' => 0,
                'supervisor_status_2' => 0,
                'supervisor_status_3' => 0,
                'approval_notes'   => '',
            ]);

            // Simpan Daftar Peserta ke overtime_participants (normalisasi npp1..30, tgs1..30)
            $participantRows = [];
            foreach ($participants as $participant) {
                if (empty($participant['nip'])) {
                    continue;
                }
                $participantRows[] = [
                    'overtime_request_id' => $overtimeRequest->id,
                    'employee_nip'        => $participant['nip'],
                    'task_description'    => $participant['task'] ?? '',
                    'duration'            => null,       // Diisi saat verifikasi realisasi
                    'overtime_amount'     => 0.00,
                    'is_approved'         => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            if (! empty($participantRows)) {
                OvertimeParticipant::insert($participantRows);
            }
        });

        return redirect()
            ->route('lembur.create')
            ->with('success', 'Surat Perintah Lembur (SPL) berhasil diajukan. Mohon informasikan kepada Atasan Anda untuk segera melakukan persetujuan.');
    }

    /**
     * API endpoint: Ambil detail satu pengajuan lembur beserta pesertanya.
     * Digunakan oleh frontend Vue untuk modal detail riwayat.
     */
    public function show(Request $request, int $id)
    {
        $user    = Auth::user();
        $authNip = $user->employee_nip;

        $overtime = OvertimeRequest::with(['participants.employee'])
            ->where('applicant_nip', $authNip) // Hanya boleh lihat miliknya sendiri
            ->findOrFail($id);

        $participants = $overtime->participants->map(fn ($p) => [
            'nip'       => $p->employee_nip,
            'name'      => $p->employee?->full_name ?? '-',
            'task'      => $p->task_description,
            'duration'  => $p->duration,
        ]);

        return response()->json([
            'id'              => $overtime->id,
            'overtime_type'   => $overtime->overtime_type,
            'type_label'      => OvertimeRequest::OVERTIME_TYPES[$overtime->overtime_type] ?? 'Lembur',
            'start_date'      => $overtime->start_date?->format('d/m/Y'),
            'raw_start_date'  => $overtime->start_date?->format('Y-m-d'),
            'start_time'      => $overtime->duration ? substr($overtime->duration, 0, 5) : '-',
            'end_time'        => $overtime->reason && strlen($overtime->reason) >= 5 ? substr($overtime->reason, 0, 5) : '-',
            'approval_status' => $overtime->approval_status,
            'status_label'    => OvertimeRequest::APPROVAL_STATUS_LABELS[$overtime->approval_status] ?? 'Menunggu',
            'supervisor_1'    => $overtime->supervisor_title_1,
            'supervisor_2'    => $overtime->supervisor_title_2,
            'supervisor_3'    => $overtime->supervisor_title_3,
            'approval_notes'  => $overtime->approval_notes,
            'request_date'    => $overtime->request_date?->format('d/m/Y'),
            'participants'    => $participants,
        ]);
    }

    /**
     * Format baris OvertimeRequest untuk ditampilkan di frontend (riwayat).
     */
    private function formatOvertimeForView(OvertimeRequest $ot): array
    {
        $statusLabel = OvertimeRequest::APPROVAL_STATUS_LABELS[$ot->approval_status] ?? 'Menunggu';

        // Tentukan badge warna berdasarkan status
        $badge = match ($ot->approval_status) {
            1       => 'approved',
            2       => 'rejected',
            default => 'pending',
        };

        $isVerified = $ot->approval_status === 1 && ! is_null($ot->end_date);
        $canVerify  = $ot->approval_status === 1 && is_null($ot->end_date);

        if ($isVerified) {
            $statusLabel = 'Sudah Verifikasi';
            $badge       = 'verified';
        } elseif ($canVerify) {
            $statusLabel = 'Menunggu Verifikasi';
            $badge       = 'need_verification';
        }

        // Hitung jumlah peserta dari participants yang sudah ter-load atau via count query
        $participantCount = OvertimeParticipant::where('overtime_request_id', $ot->id)->count();

        return [
            'id'               => $ot->id,
            'overtime_type'    => $ot->overtime_type,
            'type_label'       => OvertimeRequest::OVERTIME_TYPES[$ot->overtime_type] ?? 'Lembur',
            'request_date'     => $ot->request_date?->format('d/m/Y'),
            'start_date'       => $ot->start_date?->format('d/m/Y'),
            'raw_start_date'   => $ot->start_date?->format('Y-m-d'),
            'start_time'       => $ot->duration ? substr($ot->duration, 0, 5) : '-',
            'end_time'         => $ot->reason && strlen($ot->reason) >= 5 ? substr($ot->reason, 0, 5) : '-',
            'approval_status'  => $ot->approval_status,
            'status_label'     => $statusLabel,
            'badge'            => $badge,
            'participant_count' => $participantCount,
            'supervisor_1'     => $ot->supervisor_title_1,
            'approval_notes'   => $ot->approval_notes,
            'can_edit'         => $ot->approval_status === 3, // Hanya bisa edit saat masih menunggu ACC 1
            'can_verify'       => $canVerify,                 // Muncul tombol Verifikasi
            'is_verified'      => $isVerified,                // Label Sudah Verifikasi
        ];
    }

    /**
     * Tampilkan Form Verifikasi Lembur (modul resmi dari native: ver_lembur.php / id=verlembur).
     * Memungkinkan pembuat SPL mengoreksi jam mulai lembur serta jam pulang aktual masing-masing peserta.
     */
    public function verify(Request $request, int $id): Response|RedirectResponse
    {
        $user    = Auth::user();
        $authNip = $user->employee_nip;

        $overtime = OvertimeRequest::with(['participants.employee.division', 'participants.employee.position'])
            ->findOrFail($id);

        // Hanya pengaju atau admin yang berhak memverifikasi
        if ($overtime->applicant_nip !== $authNip && ! $user->hasAdminAccess()) {
            abort(403, 'Anda tidak berhak mengakses verifikasi pengajuan lembur ini.');
        }

        // Hanya SPL yang sudah disetujui (ACC Penuh) yang dapat diverifikasi
        if ($overtime->approval_status !== 1) {
            return redirect()
                ->route('lembur.create')
                ->with('error', 'Hanya SPL yang telah disetujui (ACC) yang dapat dilakukan verifikasi realisasi.');
        }

        // Data tanggal pelaksanaan lembur
        $overtimeDate = $overtime->start_date ? $overtime->start_date->format('Y-m-d') : null;

        // Siapkan data peserta lembur beserta realisasi jam tap scan pulang dari tabel attendances
        $participantsData = $overtime->participants->map(function ($p) use ($overtimeDate, $overtime) {
            $att = null;
            if ($overtimeDate) {
                $att = Attendance::where('employee_nip', $p->employee_nip)
                    ->where('attendance_date', $overtimeDate)
                    ->first();
            }

            // Jam pulang riil dari scan tap mesin presensi
            $realCheckOut = $att?->check_out_time ? substr($att->check_out_time, 0, 5) : null;

            // Jam selesai default: gunakan yang sudah tersimpan jika sudah pernah diverifikasi,
            // atau gunakan jam pulang real scan, atau fallback ke jam rencana selesai
            $defaultEndTime = $p->duration
                ? substr($p->duration, 0, 5)
                : ($realCheckOut ?: ($overtime->reason ? substr($overtime->reason, 0, 5) : ''));

            return [
                'id'                  => $p->id,
                'nip'                 => $p->employee_nip,
                'name'                => $p->employee?->full_name ?? '-',
                'division'            => $p->employee?->division?->name ?? 'KFPB',
                'position'            => $p->employee?->position?->name ?? '-',
                'task_description'    => $p->task_description,
                'real_check_out_time' => $realCheckOut ?: 'Tidak ada scan',
                'corrected_end_time'  => $defaultEndTime,
                'is_approved'         => (bool) $p->is_approved,
                'notes'               => $att?->notes ?? '',
            ];
        });

        return Inertia::render('Employee/OvertimeVerification', [
            'overtime' => [
                'id'                   => $overtime->id,
                'applicant_nip'        => $overtime->applicant_nip,
                'type_label'           => OvertimeRequest::OVERTIME_TYPES[$overtime->overtime_type] ?? 'Lembur',
                'start_date'           => $overtime->start_date?->format('d/m/Y'),
                'raw_start_date'       => $overtimeDate,
                'planned_start_time'   => $overtime->duration ? substr($overtime->duration, 0, 5) : '00:00',
                'planned_end_time'     => $overtime->reason ? substr($overtime->reason, 0, 5) : '00:00',
                'corrected_start_time' => $overtime->duration ? substr($overtime->duration, 0, 5) : '00:00',
                'is_verified'          => ! is_null($overtime->end_date),
                'verified_date'        => $overtime->end_date?->format('d/m/Y'),
                'supervisor_1'         => $overtime->supervisor_title_1,
                'supervisor_2'         => $overtime->supervisor_title_2,
                'supervisor_3'         => $overtime->supervisor_title_3,
                'approval_notes'       => $overtime->approval_notes,
                'participants'         => $participantsData,
            ],
        ]);
    }

    /**
     * Simpan hasil Form Verifikasi Lembur ke database.
     * Mengupdate master overtime_requests, peserta overtime_participants, dan tabel attendances.
     */
    public function saveVerification(Request $request, int $id): RedirectResponse
    {
        $user    = Auth::user();
        $authNip = $user->employee_nip;

        $overtime = OvertimeRequest::with('participants')->findOrFail($id);

        if ($overtime->applicant_nip !== $authNip && ! $user->hasAdminAccess()) {
            abort(403, 'Anda tidak berhak memverifikasi SPL ini.');
        }

        if ($overtime->approval_status !== 1) {
            return redirect()
                ->route('lembur.create')
                ->with('error', 'Hanya SPL yang telah disetujui yang dapat diverifikasi.');
        }

        $validated = $request->validate([
            'corrected_start_time'              => ['required', 'date_format:H:i'],
            'participants'                      => ['required', 'array', 'min:1'],
            'participants.*.id'                 => ['required', 'integer', 'exists:overtime_participants,id'],
            'participants.*.is_approved'        => ['required', 'boolean'],
            'participants.*.corrected_end_time' => ['nullable', 'date_format:H:i'],
            'participants.*.notes'              => ['nullable', 'string', 'max:255'],
        ], [
            'corrected_start_time.required'    => 'Koreksi jam mulai lembur wajib diisi.',
            'corrected_start_time.date_format' => 'Format jam mulai harus HH:MM.',
            'participants.*.corrected_end_time.date_format' => 'Format jam pulang harus HH:MM.',
        ]);

        $correctedStartTime = $validated['corrected_start_time'] . ':00';
        $overtimeDate       = $overtime->start_date?->format('Y-m-d');

        DB::transaction(function () use ($overtime, $correctedStartTime, $validated, $overtimeDate) {
            // Update master SPL: catat end_date verifikasi dan jam mulai aktual
            $overtime->update([
                'duration' => $correctedStartTime,
                'end_date' => now()->toDateString(),
            ]);

            // Map request participants by id
            $participantInputs = collect($validated['participants'])->keyBy('id');

            foreach ($overtime->participants as $participant) {
                $input = $participantInputs->get($participant->id);
                if (! $input) {
                    continue;
                }

                $isApproved       = (bool) ($input['is_approved'] ?? true);
                $correctedEndTime = (! empty($input['corrected_end_time']) && $isApproved)
                    ? ($input['corrected_end_time'] . ':00')
                    : null;
                $notes            = $input['notes'] ?? '';

                // Update detail peserta
                $participant->update([
                    'is_approved' => $isApproved,
                    'duration'    => $correctedEndTime,
                ]);

                // Sinkronisasi ke tabel presensi (attendances)
                if ($overtimeDate) {
                    $att = Attendance::where('employee_nip', $participant->employee_nip)
                        ->where('attendance_date', $overtimeDate)
                        ->first();

                    if ($att) {
                        if ($isApproved) {
                            $att->update([
                                'overtime_request_id'  => $overtime->id,
                                'is_overtime'          => true,
                                'is_overtime_verified' => true,
                                'overtime_duration'    => $input['corrected_end_time'] ?? null,
                                'notes'                => ! empty($notes) ? $notes : $att->notes,
                            ]);
                        } else {
                            // Pegawai ditandai "Tidak Jadi" lembur
                            $att->update([
                                'is_overtime_verified' => false,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()
            ->route('lembur.create')
            ->with('success', 'Form Verifikasi Lembur berhasil disimpan. Realisasi lembur telah dicatat ke sistem presensi.');
    }
}
