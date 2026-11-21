<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OrganizationStructure;
use App\Models\OvertimeParticipant;
use App\Models\OvertimeRequest;
use App\Services\OvertimeCalculationService;
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

        // Riwayat 15 pengajuan lembur terakhir
        // Jika SPV: SPL yang diajukan oleh pemohon ($authNip)
        // Jika Pelaksana: SPL di mana pegawai terdaftar sebagai peserta atau pemohon
        $canCreateOvertime = $user->canCreateOvertime();
        $isSupervisor      = $user->isSupervisorOrAbove();

        $recentOvertimeQuery = OvertimeRequest::query();
        if ($canCreateOvertime) {
            $recentOvertimeQuery->where('applicant_nip', $authNip);
        } else {
            $recentOvertimeQuery->where(function ($q) use ($authNip) {
                $q->where('applicant_nip', $authNip)
                    ->orWhereHas('participants', fn ($sub) => $sub->where('employee_nip', $authNip));
            });
        }

        $recentOvertime = $recentOvertimeQuery
            ->orderByDesc('request_date')
            ->take(15)
            ->get()
            ->map(fn ($ot) => $this->formatOvertimeForView($ot));

        // Jumlah SPL menunggu verifikasi (sudah ACC tapi belum diverifikasi)
        $pendingVerification = OvertimeRequest::where('applicant_nip', $authNip)
            ->where('approval_status', 1)
            ->whereNull('end_date')
            ->count();

        $requestedTab = $request->query('tab');
        if ($requestedTab === 'history' || $requestedTab === 'riwayat') {
            $initialTab = 'history';
        } elseif ($requestedTab === 'form') {
            $initialTab = 'form';
        } else {
            $initialTab = ($canCreateOvertime && $pendingVerification === 0) ? 'form' : 'history';
        }

        return Inertia::render('Employee/Overtime/OvertimeRequest', [
            'pageTitle' => 'Pengajuan Lembur (SPL)',
            'authEmployee' => [
                'nip'          => $authNip,
                'name'         => $employee?->full_name ?? $user->name,
                'division'     => $employee?->division?->name ?? 'PT Kimia Farma Plant Banjaran',
                'sub_division' => $employee?->subDivision?->name ?? '-',
                'position'     => $employee?->position?->name ?? 'Pegawai',
            ],
            'canCreateOvertime'        => $canCreateOvertime,
            'isSupervisor'             => $isSupervisor,
            'userLevel'                => $canCreateOvertime ? 'Supervisor' : 'Pelaksana',
            'overtimeTypes'           => OvertimeRequest::OVERTIME_TYPES,
            'supervisorCandidates'    => $supervisorCandidates,
            'allEmployees'            => $allEmployees,
            'recentOvertime'          => $recentOvertime,
            'pendingVerificationCount' => $pendingVerification,
            'initialTab'               => $initialTab,
        ]);
    }

    /**
     * Simpan pengajuan SPL baru ke database.
     * Menggunakan DB Transaction untuk menyimpan master (overtime_requests) + detail peserta (overtime_participants).
     */
    public function store(StoreOvertimeRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->canCreateOvertime()) {
            return redirect()
                ->route('lembur.create')
                ->with('error', 'Pembuatan Surat Perintah Lembur (SPL) hanya dapat dilakukan oleh pejabat tingkat Supervisor (SPV) ke atas. Pelaksana ditugaskan langsung oleh atasan.');
        }

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
            ->route('lembur.create', ['tab' => 'history'])
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
        $isAdmin = $user->hasAdminAccess();

        $query = OvertimeRequest::with([
            'applicant.division',
            'applicant.position',
            'participants.employee',
            'supervisor1.position',
            'supervisor2.position',
            'supervisor3.position',
        ]);

        // Proteksi Otorisasi: Pemohon, Pejabat Penyetuju (ACC 1-3), Peserta, atau Admin
        if (! $isAdmin) {
            $query->where(function ($q) use ($authNip) {
                $q->where('applicant_nip', $authNip)
                    ->orWhere('supervisor_title_1', $authNip)
                    ->orWhere('supervisor_title_2', $authNip)
                    ->orWhere('supervisor_title_3', $authNip)
                    ->orWhereHas('participants', function ($sub) use ($authNip) {
                        $sub->where('employee_nip', $authNip);
                    });
            });
        }

        $overtime = $query->findOrFail($id);

        $participants = $overtime->participants->map(fn ($p) => [
            'nip'       => $p->employee_nip,
            'name'      => $p->employee?->full_name ?? '-',
            'task'      => $p->task_description,
            'duration'  => $p->duration,
        ]);

        $supervisors = [];
        $appStatus   = (int) $overtime->approval_status;

        foreach ([1, 2, 3] as $level) {
            $nip = $overtime->{"supervisor_title_{$level}"};
            if (! $nip) {
                continue;
            }

            $emp        = $overtime->{"supervisor{$level}"};
            $approvedAt = $overtime->{"supervisor_approved_at_{$level}"};

            // Tentukan status approval per level sesuai alur berjenjang hierarki KFPB:
            // 1: Disetujui (semua tingkat ACC telah selesai)
            // 2: Ditolak
            // 3: Sedang menunggu ACC 1
            // 4: ACC 1 selesai, sedang menunggu ACC 2
            // 5: ACC 1 & 2 selesai, sedang menunggu ACC 3
            if ($appStatus === 1) {
                $status      = 1;
                $statusLabel = 'Disetujui';
            } elseif ($appStatus === 2) {
                $rawStatus = (int) $overtime->{"supervisor_status_{$level}"};
                if ($rawStatus === 2) {
                    $status      = 2;
                    $statusLabel = 'Ditolak';
                } elseif (! empty($approvedAt)) {
                    $status      = 1;
                    $statusLabel = 'Disetujui';
                } else {
                    $status      = 0;
                    $statusLabel = 'Dibatalkan';
                }
            } elseif ($appStatus === 3) {
                $status      = 0;
                $statusLabel = ($level === 1) ? 'Menunggu' : 'Menunggu Antrean';
            } elseif ($appStatus === 4) {
                if ($level === 1) {
                    $status      = 1;
                    $statusLabel = 'Disetujui';
                } elseif ($level === 2) {
                    $status      = 0;
                    $statusLabel = 'Menunggu';
                } else {
                    $status      = 0;
                    $statusLabel = 'Menunggu Antrean';
                }
            } elseif ($appStatus === 5) {
                if ($level < 3) {
                    $status      = 1;
                    $statusLabel = 'Disetujui';
                } else {
                    $status      = 0;
                    $statusLabel = 'Menunggu';
                }
            } else {
                if (! empty($approvedAt)) {
                    $status      = 1;
                    $statusLabel = 'Disetujui';
                } else {
                    $status      = 0;
                    $statusLabel = 'Menunggu';
                }
            }

            $supervisors[] = [
                'level'        => $level,
                'level_label'  => "ACC {$level}",
                'nip'          => $nip,
                'name'         => $emp?->full_name ?? "Pegawai ({$nip})",
                'position'     => $emp?->position?->name ?? 'Pejabat Penyetuju',
                'status'       => $status,
                'status_label' => $statusLabel,
                'approved_at'  => $approvedAt?->format('d/m/Y H:i'),
            ];
        }

        return response()->json([
            'id'                 => $overtime->id,
            'code'               => sprintf('SPL-%05d', $overtime->id),
            'legacy_id'          => $overtime->legacy_id,
            'applicant_nip'      => $overtime->applicant_nip,
            'applicant_name'     => $overtime->applicant?->full_name ?? $overtime->applicant_nip,
            'applicant_division' => $overtime->applicant?->division?->name ?? 'Plant Banjaran',
            'applicant_position' => $overtime->applicant?->position?->name ?? '-',
            'overtime_type'      => $overtime->overtime_type,
            'type_label'         => OvertimeRequest::OVERTIME_TYPES[$overtime->overtime_type] ?? 'Lembur',
            'start_date'         => $overtime->start_date?->format('d/m/Y'),
            'raw_start_date'     => $overtime->start_date?->format('Y-m-d'),
            'start_time'         => $overtime->duration ? substr($overtime->duration, 0, 5) : '-',
            'end_time'           => $overtime->reason && strlen($overtime->reason) >= 5 ? substr($overtime->reason, 0, 5) : '-',
            'approval_status'    => $overtime->approval_status,
            'status_label'       => OvertimeRequest::APPROVAL_STATUS_LABELS[$overtime->approval_status] ?? 'Menunggu',
            'supervisor_1'       => $overtime->supervisor_title_1,
            'supervisor_2'       => $overtime->supervisor_title_2,
            'supervisor_3'       => $overtime->supervisor_title_3,
            'supervisors'        => $supervisors,
            'approval_notes'     => $overtime->approval_notes,
            'request_date'       => $overtime->request_date?->format('d/m/Y'),
            'participants'       => $participants,
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
            'code'             => sprintf('SPL-%05d', $ot->id),
            'legacy_id'        => $ot->legacy_id,
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

        return Inertia::render('Employee/Overtime/OvertimeVerification', [
            'overtime' => [
                'id'                   => $overtime->id,
                'code'                 => sprintf('SPL-%05d', $overtime->id),
                'legacy_id'            => $overtime->legacy_id,
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

    /**
     * Halaman Pusat Persetujuan Lembur Atasan (replikasi resmi modul native: absen/app_lembur.php).
     * Menampilkan daftar SPL bawahan yang menunggu giliran persetujuan ACC 1, 2, atau 3.
     */
    public function approvalIndex(Request $request): Response
    {
        $user    = Auth::user();
        $authNip = $user->employee_nip;
        $isAdmin = $user->hasAdminAccess();

        // 1. Ambil SPL yang sedang MENUNGGU persetujuan user yang login
        $pendingQuery = OvertimeRequest::with([
            'applicant.division',
            'applicant.position',
            'participants.employee',
            'supervisor1.position',
            'supervisor2.position',
            'supervisor3.position',
        ]);

        if ($isAdmin) {
            $pendingQuery->whereIn('approval_status', [3, 4, 5]);
        } else {
            $pendingQuery->where(function ($q) use ($authNip) {
                $q->where(function ($sub) use ($authNip) {
                    $sub->where('supervisor_title_1', $authNip)->where('approval_status', 3);
                })->orWhere(function ($sub) use ($authNip) {
                    $sub->where('supervisor_title_2', $authNip)->where('approval_status', 4);
                })->orWhere(function ($sub) use ($authNip) {
                    $sub->where('supervisor_title_3', $authNip)->where('approval_status', 5);
                });
            });
        }

        $pendingApprovals = $pendingQuery
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($ot) => $this->formatApprovalRow($ot, $authNip, $isAdmin));

        // 2. Ambil Riwayat SPL yang pernah diproses oleh atasan ini (atau riwayat global untuk admin)
        $historyQuery = OvertimeRequest::with([
            'applicant.division',
            'applicant.position',
            'participants.employee',
            'supervisor1.position',
            'supervisor2.position',
            'supervisor3.position',
        ]);

        if ($isAdmin) {
            $historyQuery->whereIn('approval_status', [1, 2]);
        } else {
            $historyQuery->where(function ($q) use ($authNip) {
                $q->where('supervisor_title_1', $authNip)
                    ->orWhere('supervisor_title_2', $authNip)
                    ->orWhere('supervisor_title_3', $authNip);
            })->whereIn('approval_status', [1, 2]);
        }

        $historyApprovals = $historyQuery
            ->orderByDesc('updated_at')
            ->take(30)
            ->get()
            ->map(fn ($ot) => $this->formatApprovalRow($ot, $authNip, $isAdmin));

        $canApproveOvertime = $user->canApproveOvertime();
        $isSupervisor       = $user->isSupervisorOrAbove();

        $requestedTab = $request->query('tab');
        $initialTab   = in_array($requestedTab, ['history', 'riwayat'], true) ? 'history' : 'pending';

        return Inertia::render('Employee/Overtime/OvertimeApproval', [
            'pageTitle'          => 'Persetujuan Lembur',
            'pendingApprovals'   => $pendingApprovals,
            'historyApprovals'   => $historyApprovals,
            'isAdmin'            => $isAdmin,
            'canApproveOvertime' => $canApproveOvertime,
            'isSupervisor'       => $isSupervisor,
            'userLevel'          => $canApproveOvertime ? 'Supervisor' : 'Pelaksana',
            'initialTab'         => $initialTab,
        ]);
    }

    /**
     * Proses keputusan persetujuan Atasan (Setuju / Tolak).
     * Mengikuti 100% transisi status hierarki KFPB di absen/data_lembur.php.
     */
    public function processApproval(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes'    => ['nullable', 'string', 'max:255'],
        ], [
            'decision.required' => 'Pilihan persetujuan wajib ditentukan.',
            'decision.in'       => 'Pilihan persetujuan tidak valid.',
        ]);

        $user    = Auth::user();
        $authNip = $user->employee_nip;
        $isAdmin = $user->hasAdminAccess();

        $overtime = OvertimeRequest::findOrFail($id);

        // Tentukan level otorisasi
        $level = null;
        if ($overtime->supervisor_title_1 === $authNip && $overtime->approval_status === 3) {
            $level = 1;
        } elseif ($overtime->supervisor_title_2 === $authNip && $overtime->approval_status === 4) {
            $level = 2;
        } elseif ($overtime->supervisor_title_3 === $authNip && $overtime->approval_status === 5) {
            $level = 3;
        } elseif ($isAdmin && in_array($overtime->approval_status, [3, 4, 5])) {
            $level = match ($overtime->approval_status) {
                3 => 1,
                4 => 2,
                5 => 3,
                default => 1,
            };
        }

        if (! $level) {
            abort(403, 'Anda tidak memiliki hak akses atau bukan giliran Anda untuk memproses pengajuan lembur ini.');
        }

        $decision = $validated['decision'];
        $notes    = trim($validated['notes'] ?? '');

        DB::transaction(function () use ($overtime, $level, $decision, $notes, $isAdmin) {
            if ($decision === 'reject') {
                // Sesuai sistem lama: kd_approve = 2 (Ditolak)
                $overtime->update([
                    'approval_status'            => 2,
                    "supervisor_status_{$level}" => 2,
                    'approval_notes'             => ! empty($notes) ? $notes : ($overtime->approval_notes ?: 'Ditolak oleh Atasan'),
                ]);
            } else {
                // Sesuai sistem lama: Setuju berjenjang
                if ($isAdmin) {
                    // Admin dapat menyetujui langsung penuh
                    $overtime->update([
                        'approval_status'            => 1,
                        'supervisor_status_1'        => 1,
                        'supervisor_status_2'        => ! empty($overtime->supervisor_title_2) ? 1 : 0,
                        'supervisor_status_3'        => ! empty($overtime->supervisor_title_3) ? 1 : 0,
                        "supervisor_approved_at_{$level}" => now(),
                        'approval_notes'             => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 1) {
                    $hasAcc2 = ! empty($overtime->supervisor_title_2);
                    $overtime->update([
                        'supervisor_status_1'      => 1,
                        'supervisor_approved_at_1' => now(),
                        'approval_status'          => $hasAcc2 ? 4 : 1, // Jika ada ACC 2 -> status 4 (Menunggu ACC 2), jika tidak -> Final (1)
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 2) {
                    $hasAcc3 = ! empty($overtime->supervisor_title_3);
                    $overtime->update([
                        'supervisor_status_2'      => 1,
                        'supervisor_approved_at_2' => now(),
                        'approval_status'          => $hasAcc3 ? 5 : 1, // Jika ada ACC 3 -> status 5 (Menunggu ACC 3), jika tidak -> Final (1)
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 3) {
                    $overtime->update([
                        'supervisor_status_3'      => 1,
                        'supervisor_approved_at_3' => now(),
                        'approval_status'          => 1, // Final Approved (1)
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                }
            }
        });

        $msg = $decision === 'approve'
            ? "Persetujuan SPL {$overtime->id} berhasil disimpan."
            : "Pengajuan SPL {$overtime->id} telah ditolak.";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Proses persetujuan SPL massal (batch approval) untuk Manager / Asman / Admin.
     * Mengikuti 100% transisi hierarki status KFPB untuk setiap berkas yang dipilih.
     */
    public function processBatchApproval(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:overtime_requests,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'ids.required' => 'Pilih minimal satu Surat Perintah Lembur (SPL).',
            'ids.min'      => 'Pilih minimal satu Surat Perintah Lembur (SPL).',
        ]);

        $user    = Auth::user();
        $authNip = $user->employee_nip;
        $isAdmin = $user->hasAdminAccess();
        $notes   = trim($validated['notes'] ?? '');

        $successCount = 0;

        DB::transaction(function () use ($validated, $authNip, $isAdmin, $notes, &$successCount) {
            $overtimes = OvertimeRequest::whereIn('id', $validated['ids'])
                ->whereIn('approval_status', [3, 4, 5])
                ->get();

            foreach ($overtimes as $overtime) {
                $level = null;
                if ($overtime->supervisor_title_1 === $authNip && $overtime->approval_status === 3) {
                    $level = 1;
                } elseif ($overtime->supervisor_title_2 === $authNip && $overtime->approval_status === 4) {
                    $level = 2;
                } elseif ($overtime->supervisor_title_3 === $authNip && $overtime->approval_status === 5) {
                    $level = 3;
                } elseif ($isAdmin && in_array($overtime->approval_status, [3, 4, 5])) {
                    $level = match ($overtime->approval_status) {
                        3 => 1,
                        4 => 2,
                        5 => 3,
                        default => 1,
                    };
                }

                // Hanya proses jika memang giliran persetujuan user yang login
                if (! $level) {
                    continue;
                }

                if ($isAdmin) {
                    $overtime->update([
                        'approval_status'                 => 1,
                        'supervisor_status_1'             => 1,
                        'supervisor_status_2'             => ! empty($overtime->supervisor_title_2) ? 1 : 0,
                        'supervisor_status_3'             => ! empty($overtime->supervisor_title_3) ? 1 : 0,
                        "supervisor_approved_at_{$level}" => now(),
                        'approval_notes'                  => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 1) {
                    $hasAcc2 = ! empty($overtime->supervisor_title_2);
                    $overtime->update([
                        'supervisor_status_1'      => 1,
                        'supervisor_approved_at_1' => now(),
                        'approval_status'          => $hasAcc2 ? 4 : 1,
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 2) {
                    $hasAcc3 = ! empty($overtime->supervisor_title_3);
                    $overtime->update([
                        'supervisor_status_2'      => 1,
                        'supervisor_approved_at_2' => now(),
                        'approval_status'          => $hasAcc3 ? 5 : 1,
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                } elseif ($level === 3) {
                    $overtime->update([
                        'supervisor_status_3'      => 1,
                        'supervisor_approved_at_3' => now(),
                        'approval_status'          => 1,
                        'approval_notes'           => ! empty($notes) ? $notes : $overtime->approval_notes,
                    ]);
                }

                $successCount++;
            }
        });

        if ($successCount === 0) {
            return redirect()->back()->with('error', 'Tidak ada SPL terpilih yang memenuhi giliran persetujuan Anda.');
        }

        return redirect()->back()->with('success', "Sebanyak {$successCount} Surat Perintah Lembur (SPL) berhasil disetujui secara massal.");
    }

    /**
     * Helper formatting baris untuk tampilan OvertimeApproval.vue.
     */
    private function formatApprovalRow(OvertimeRequest $ot, ?string $authNip, bool $isAdmin): array
    {
        $currentLevelLabel = match ($ot->approval_status) {
            3       => 'ACC 1',
            4       => 'ACC 2',
            5       => 'ACC 3',
            1       => 'Disetujui',
            2       => 'Ditolak',
            default => 'Menunggu',
        };

        // Cek giliran ACC user yang login
        $canApprove = false;
        $myLevel = null;
        if ($ot->supervisor_title_1 === $authNip && $ot->approval_status === 3) {
            $canApprove = true;
            $myLevel = 'ACC 1';
        } elseif ($ot->supervisor_title_2 === $authNip && $ot->approval_status === 4) {
            $canApprove = true;
            $myLevel = 'ACC 2';
        } elseif ($ot->supervisor_title_3 === $authNip && $ot->approval_status === 5) {
            $canApprove = true;
            $myLevel = 'ACC 3';
        } elseif ($isAdmin && in_array($ot->approval_status, [3, 4, 5])) {
            $canApprove = true;
            $myLevel = $currentLevelLabel;
        }

        $participants = $ot->participants->map(fn ($p) => [
            'nip'  => $p->employee_nip,
            'name' => $p->employee?->full_name ?? '-',
            'task' => $p->task_description,
        ]);

        return [
            'id'                 => $ot->id,
            'code'               => sprintf('SPL-%05d', $ot->id),
            'legacy_id'          => $ot->legacy_id,
            'overtime_type'      => $ot->overtime_type,
            'type_label'         => OvertimeRequest::OVERTIME_TYPES[$ot->overtime_type] ?? 'Lembur',
            'request_date'       => $ot->request_date?->format('d/m/Y'),
            'start_date'         => $ot->start_date?->format('d/m/Y'),
            'raw_start_date'     => $ot->start_date?->format('Y-m-d'),
            'start_time'         => $ot->duration ? substr($ot->duration, 0, 5) : '-',
            'end_time'           => $ot->reason && strlen($ot->reason) >= 5 ? substr($ot->reason, 0, 5) : '-',
            'approval_status'    => $ot->approval_status,
            'status_label'       => OvertimeRequest::APPROVAL_STATUS_LABELS[$ot->approval_status] ?? 'Menunggu',
            'current_level'      => $currentLevelLabel,
            'can_approve'        => $canApprove,
            'my_level'           => $myLevel,
            'applicant_nip'      => $ot->applicant_nip,
            'applicant_name'     => $ot->applicant?->full_name ?? $ot->applicant_nip,
            'applicant_division' => $ot->applicant?->division?->name ?? 'Plant Banjaran',
            'applicant_position' => $ot->applicant?->position?->name ?? '-',
            'participant_count'  => $participants->count(),
            'participants'       => $participants,
            'approval_notes'     => $ot->approval_notes,
            'supervisor_1'       => $ot->supervisor_title_1,
            'supervisor_2'       => $ot->supervisor_title_2,
            'supervisor_3'       => $ot->supervisor_title_3,
        ];
    }

    /**
     * Halaman Laporan Lengkap Lembur Pegawai Mandiri
     * (replikasi fitur & rumus hitung eSDM native: adminlembur_detail + lap_lemburrekapbulancsv).
     */
    public function employeeReport(Request $request, OvertimeCalculationService $calculator): Response
    {
        $user       = Auth::user();
        $isAdmin    = $user->hasAdminAccess();
        $currentNip = $user->employee_nip;
        $employee   = $currentNip
            ? Employee::where('nip', $currentNip)->with(['division', 'position'])->first()
            : null;

        // Ambil daftar NIP bawahan langsung jika user adalah atasan
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

        // Daftar pilihan pegawai sesuai otorisasi (diri sendiri, bawahan, atau seluruh pegawai untuk Admin)
        $employeeOptions = [];
        if ($isAdmin) {
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
                $subs = Employee::whereIn('nip', $subordinateNips)
                    ->orderBy('full_name')
                    ->get(['nip', 'full_name']);
                foreach ($subs as $sub) {
                    $employeeOptions[] = [
                        'nip'  => $sub->nip,
                        'name' => "{$sub->nip} - {$sub->full_name}",
                    ];
                }
            }
        }

        // Validasi otorisasi target NIP (anti-IDOR)
        $requestedNip = trim((string) $request->input('nip', ''));
        if ($requestedNip !== '') {
            if ($isAdmin || $requestedNip === $currentNip || in_array($requestedNip, $subordinateNips, true)) {
                $targetNip = $requestedNip;
            } else {
                $targetNip = $currentNip;
            }
        } else {
            if ($currentNip && Employee::where('nip', $currentNip)->exists()) {
                $targetNip = $currentNip;
            } elseif ($isAdmin && ! empty($employeeOptions)) {
                $targetNip = $employeeOptions[0]['nip'];
            } else {
                $targetNip = $currentNip;
            }
        }

        $targetEmployee = $targetNip
            ? Employee::where('nip', $targetNip)
                ->with(['division', 'subDivision', 'position', 'salaryStructure'])
                ->first()
            : null;

        // Filter tanggal & tab mode
        $defaultStart = now('Asia/Jakarta')->startOfMonth()->toDateString();
        $defaultEnd   = now('Asia/Jakarta')->endOfMonth()->toDateString();
        $startDate    = $this->parseDateFilter($request->input('start_date', $request->input('awal')), $defaultStart);
        $endDate      = $this->parseDateFilter($request->input('end_date', $request->input('akhir')), $defaultEnd);

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $requestedMode = (string) $request->input('mode', 'all');
        $mode = in_array($requestedMode, ['all', 'verified', 'wait_verif', 'wait_real'], true)
            ? $requestedMode
            : 'all';

        $rates            = $calculator->resolveEmployeeOvertimeRates($targetEmployee?->salaryStructure);
        $positionLegacyId = $targetEmployee?->position?->legacy_id ?? $targetEmployee?->position_id;
        $isAtasan         = $calculator->isAtasanPosition($positionLegacyId ? (int) $positionLegacyId : null);

        // Query SPL yang diikuti target NIP beserta data absensi pada rentang yang sama (Zero N+1)
        $participants = $targetNip
            ? OvertimeParticipant::where('employee_nip', $targetNip)
                ->whereHas('overtimeRequest', fn ($q) => $q->whereBetween('start_date', [$startDate, $endDate]))
                ->with(['overtimeRequest.applicant'])
                ->get()
            : collect();

        $attendancesByDate = $targetNip
            ? Attendance::where('employee_nip', $targetNip)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->with('workShift')
                ->get()
                ->keyBy(fn ($a) => $a->attendance_date->format('Y-m-d'))
            : collect();

        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $allRows  = [];
        $matchedAttendanceDates = [];

        foreach ($participants as $participant) {
            $ot = $participant->overtimeRequest;
            if (! $ot || ! $ot->start_date) {
                continue;
            }

            $dateStr   = $ot->start_date->format('Y-m-d');
            $dayOfWeek = (int) $ot->start_date->format('w');
            $att       = $attendancesByDate->get($dateStr);
            $matchedAttendanceDates[$dateStr] = true;

            $isRejected             = ((int) $ot->approval_status === 2);
            $isCancelledParticipant = ((int) $ot->approval_status === 1 && ! is_null($ot->end_date) && ! $participant->is_approved);
            $attMatchesSpl          = $att && $att->is_overtime_verified && (! $att->overtime_request_id || (int) $att->overtime_request_id === (int) $ot->id);
            $isVerified             = ! $isRejected && ! $isCancelledParticipant && (
                ((int) $ot->approval_status === 1 && ! is_null($ot->end_date) && $participant->is_approved)
                || ($attMatchesSpl && $participant->is_approved)
            );

            $jamIn      = $att?->check_in_time;
            $jamOut     = $att?->check_out_time;
            $jamIn2     = $ot->duration;
            $jamOut2Raw = $participant->duration;
            $jamOut2ForCalc = $jamOut2Raw ?: ($isVerified ? $jamOut : null);

            $plannedHours = $calculator->calculatePlannedHours($dateStr, $ot->duration, $ot->reason);
            $realSeconds  = $calculator->calculateRealizationSeconds($dateStr, $jamIn, $jamOut, $jamIn2, $jamOut2Raw);
            $realMinutes  = (int) round($realSeconds / 60);
            $realHours    = round($realMinutes / 60, 2);
            $afterSeconds = $calculator->calculateAfterBreakSeconds($realSeconds);

            $effectiveIn  = $calculator->normalizeTime($jamIn2) ?? $calculator->normalizeTime($jamIn);
            $effectiveOut = $calculator->normalizeTime($jamOut2Raw) ?? $calculator->normalizeTime($jamOut);

            $verifiedCalc = $calculator->calculateVerifiedOvertime([
                'is_verified'          => $isVerified,
                'overtime_type'        => (int) ($ot->overtime_type ?? 1),
                'work_shift_legacy_id' => $att?->workShift?->legacy_id ?? $att?->work_shift_id,
                'attendance_date'      => $dateStr,
                'jam_in'               => $jamIn,
                'jam_in2'              => $jamIn2,
                'jam_out2'             => $jamOut2ForCalc,
                'position_legacy_id'   => $positionLegacyId ? (int) $positionLegacyId : null,
                'hourly_rate'          => $rates['hourly_rate'],
                'minute_rate'          => $rates['minute_rate'],
            ]);

            if ($isVerified) {
                $statusKey      = 'verified';
                $statusLabel    = 'Terverifikasi';
                $statusSeverity = 'success';
            } elseif ($isRejected) {
                $statusKey      = 'rejected';
                $statusLabel    = 'SPL Ditolak';
                $statusSeverity = 'danger';
            } elseif ($isCancelledParticipant) {
                $statusKey      = 'cancelled';
                $statusLabel    = 'Tidak Jadi';
                $statusSeverity = 'secondary';
            } elseif ($effectiveIn !== null && $effectiveOut !== null && $realMinutes > 0) {
                $statusKey      = 'wait_verif';
                $statusLabel    = 'Menunggu Verifikasi';
                $statusSeverity = 'info';
            } else {
                $statusKey      = 'wait_real';
                $statusLabel    = 'Menunggu Realisasi';
                $statusSeverity = 'warn';
            }

            $realStartShort = $ot->start_date->format('d/m');
            $realEndShort   = ($effectiveIn !== null && $effectiveOut !== null && strtotime("2000-01-01 {$effectiveOut}") < strtotime("2000-01-01 {$effectiveIn}"))
                ? $ot->start_date->copy()->addDay()->format('d/m')
                : $realStartShort;

            $deltaHours = ($plannedHours > 0 && $realHours > 0)
                ? round($realHours - $plannedHours, 2)
                : null;

            $allRows[] = [
                'id'                    => "spl-{$ot->id}-{$participant->id}",
                'overtime_request_id'   => $ot->id,
                'spl_code'              => sprintf('SPL-%05d', $ot->id),
                'legacy_id'             => $ot->legacy_id,
                'has_spl'               => true,
                'date'                  => $dateStr,
                'formatted_date'        => $ot->start_date->format('d/m/Y'),
                'day_name'              => $dayNames[$dayOfWeek] ?? '-',
                'request_date'          => $ot->request_date?->format('d/m/Y') ?? '-',
                'verified_date'         => $ot->end_date?->format('d/m/Y') ?? '-',
                'shift_name'            => $att?->workShift?->name ?? 'Non-Shift',
                'overtime_type'         => (int) ($ot->overtime_type ?? 1),
                'type_label'            => OvertimeRequest::OVERTIME_TYPES[$ot->overtime_type] ?? 'Lembur',
                'task_description'      => $participant->task_description ?: '-',
                'planned_start'         => $this->formatTimeShort($ot->duration),
                'planned_end'           => $this->formatTimeShort($ot->reason),
                'planned_hours'         => $plannedHours,
                'jam_in'                => $this->formatTimeShort($jamIn),
                'jam_out'               => $this->formatTimeShort($jamOut),
                'jam_in2'               => $this->formatTimeShort($jamIn2),
                'jam_out2'              => $this->formatTimeShort($jamOut2Raw),
                'effective_in'          => $this->formatTimeShort($effectiveIn),
                'effective_out'         => $this->formatTimeShort($effectiveOut),
                'real_date_start_short' => $realStartShort,
                'real_date_end_short'   => $realEndShort,
                'real_seconds'          => $realSeconds,
                'real_hours'            => $realHours,
                'after_break_seconds'   => $afterSeconds,
                'after_break_hours'     => $isVerified
                    ? $verifiedCalc['after_break_hours']
                    : $calculator->calculateAfterBreakHours($realHours, $isAtasan),
                'delta_hours'           => $deltaHours,
                'counted_hours'         => $verifiedCalc['counted_hours'],
                'overtime_amount'       => $verifiedCalc['amount'],
                'is_verified'           => $isVerified,
                'status_key'            => $statusKey,
                'status_label'          => $statusLabel,
                'status_severity'       => $statusSeverity,
                'approval_status_label' => OvertimeRequest::APPROVAL_STATUS_LABELS[$ot->approval_status] ?? 'Menunggu',
            ];
        }

        // Tangkap rekaman lembur terverifikasi pada attendances yang belum terikat SPL (kompatibel dengan scope native)
        foreach ($attendancesByDate as $dateStr => $att) {
            if (! $att->is_overtime_verified || isset($matchedAttendanceDates[$dateStr])) {
                continue;
            }

            $dayOfWeek   = (int) $att->attendance_date->format('w');
            $jamIn       = $att->check_in_time;
            $jamOut      = $att->check_out_time;
            $realSeconds = $calculator->calculateRealizationSeconds($dateStr, $jamIn, $jamOut);
            $realMinutes = (int) round($realSeconds / 60);
            $realHours   = round($realMinutes / 60, 2);
            $afterSeconds = $calculator->calculateAfterBreakSeconds($realSeconds);

            $effectiveIn  = $calculator->normalizeTime($jamIn);
            $effectiveOut = $calculator->normalizeTime($jamOut);

            $verifiedCalc = $calculator->calculateVerifiedOvertime([
                'is_verified'          => true,
                'overtime_type'        => 1,
                'work_shift_legacy_id' => $att->workShift?->legacy_id ?? $att->work_shift_id,
                'attendance_date'      => $dateStr,
                'jam_in'               => $jamIn,
                'jam_in2'              => $jamIn,
                'jam_out2'             => $jamOut,
                'position_legacy_id'   => $positionLegacyId ? (int) $positionLegacyId : null,
                'hourly_rate'          => $rates['hourly_rate'],
                'minute_rate'          => $rates['minute_rate'],
            ]);

            $realStartShort = $att->attendance_date->format('d/m');
            $realEndShort   = ($effectiveIn !== null && $effectiveOut !== null && strtotime("2000-01-01 {$effectiveOut}") < strtotime("2000-01-01 {$effectiveIn}"))
                ? $att->attendance_date->copy()->addDay()->format('d/m')
                : $realStartShort;

            $allRows[] = [
                'id'                    => "att-{$att->id}",
                'overtime_request_id'   => $att->overtime_request_id,
                'spl_code'              => 'Tanpa SPL',
                'legacy_id'             => null,
                'has_spl'               => false,
                'date'                  => $dateStr,
                'formatted_date'        => $att->attendance_date->format('d/m/Y'),
                'day_name'              => $dayNames[$dayOfWeek] ?? '-',
                'request_date'          => '-',
                'verified_date'         => $att->attendance_date->format('d/m/Y'),
                'shift_name'            => $att->workShift?->name ?? 'Non-Shift',
                'overtime_type'         => 1,
                'type_label'            => OvertimeRequest::OVERTIME_TYPES[1] ?? 'Lembur Hari Kerja',
                'task_description'      => $att->notes ?: '-',
                'planned_start'         => '-',
                'planned_end'           => '-',
                'planned_hours'         => 0.0,
                'jam_in'                => $this->formatTimeShort($jamIn),
                'jam_out'               => $this->formatTimeShort($jamOut),
                'jam_in2'               => $this->formatTimeShort($jamIn),
                'jam_out2'              => $this->formatTimeShort($jamOut),
                'effective_in'          => $this->formatTimeShort($effectiveIn),
                'effective_out'         => $this->formatTimeShort($effectiveOut),
                'real_date_start_short' => $realStartShort,
                'real_date_end_short'   => $realEndShort,
                'real_seconds'          => $realSeconds,
                'real_hours'            => $realHours,
                'after_break_seconds'   => $afterSeconds,
                'after_break_hours'     => $verifiedCalc['after_break_hours'],
                'delta_hours'           => null,
                'counted_hours'         => $verifiedCalc['counted_hours'],
                'overtime_amount'       => $verifiedCalc['amount'],
                'is_verified'           => true,
                'status_key'            => 'verified',
                'status_label'          => 'Terverifikasi',
                'status_severity'       => 'success',
                'approval_status_label' => 'Disetujui',
            ];
        }

        // Urutkan terbaru terlebih dahulu sesuai adminlembur_detail
        usort($allRows, function ($a, $b) {
            $dateCmp = strcmp($b['date'], $a['date']);

            return $dateCmp !== 0 ? $dateCmp : strcmp($b['id'], $a['id']);
        });

        $tabCounts = [
            'all'        => count($allRows),
            'verified'   => count(array_filter($allRows, fn ($r) => $r['status_key'] === 'verified')),
            'wait_verif' => count(array_filter($allRows, fn ($r) => $r['status_key'] === 'wait_verif')),
            'wait_real'  => count(array_filter($allRows, fn ($r) => $r['status_key'] === 'wait_real')),
        ];

        $filteredRows = $mode === 'all'
            ? $allRows
            : array_values(array_filter($allRows, fn ($r) => $r['status_key'] === $mode));

        $plannedRows = array_filter($allRows, fn ($r) => $r['has_spl'] && $r['planned_hours'] > 0);
        $totalPlannedHours = round(array_sum(array_column($plannedRows, 'planned_hours')), 2);
        $totalRealSeconds  = array_sum(array_column($filteredRows, 'real_seconds'));
        $totalAfterSeconds = array_sum(array_column($filteredRows, 'after_break_seconds'));
        $gt4Count          = count(array_filter($filteredRows, fn ($r) => $r['real_seconds'] > 14400));
        $verifiedSubset    = array_filter($filteredRows, fn ($r) => $r['is_verified']);

        $summary = [
            'total_planned_hours'   => $totalPlannedHours,
            'planned_count'         => count($plannedRows),
            'total_real_hours'      => round($totalRealSeconds / 3600, 2),
            'real_count'            => count($filteredRows),
            'total_after_hours'     => round($totalAfterSeconds / 3600, 2),
            'after_gt4_count'       => $gt4Count,
            'total_counted_hours'   => round(array_sum(array_column($verifiedSubset, 'counted_hours')), 2),
            'verified_count'        => count($verifiedSubset),
            'total_overtime_amount' => round(array_sum(array_column($verifiedSubset, 'overtime_amount')), 0),
            'has_salary_rate'       => $rates['has_rate'],
        ];

        return Inertia::render('Employee/Overtime/OvertimeReport', [
            'pageTitle'       => 'Laporan Lengkap Lembur',
            'records'         => array_values($filteredRows),
            'targetEmployee'  => [
                'nip'          => $targetEmployee?->nip ?? $targetNip ?? '-',
                'name'         => $targetEmployee?->full_name ?? '-',
                'division'     => $targetEmployee?->division?->name ?? 'Plant Banjaran',
                'sub_division' => $targetEmployee?->subDivision?->name ?? '-',
                'position'     => $targetEmployee?->position?->name ?? '-',
            ],
            'employeeOptions' => $employeeOptions,
            'isSupervisor'    => $isSupervisor,
            'isAdmin'         => $isAdmin,
            'isAtasan'        => $isAtasan,
            'filters'         => [
                'nip'        => $targetNip,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'mode'       => $mode,
            ],
            'tabCounts'       => $tabCounts,
            'summary'         => $summary,
            'overtimeTypes'   => OvertimeRequest::OVERTIME_TYPES,
        ]);
    }

    private function parseDateFilter(?string $value, string $fallback): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) !== 1) {
            return $fallback;
        }

        try {
            $dt = Carbon::createFromFormat('Y-m-d', $trimmed);

            return ($dt && $dt->format('Y-m-d') === $trimmed) ? $trimmed : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private function formatTimeShort(?string $time): string
    {
        if ($time === null) {
            return '-';
        }

        $trimmed = trim($time);
        if ($trimmed === '' || in_array($trimmed, ['00:00', '00:00:00', '00:00:01'], true) || strlen($trimmed) < 5) {
            return '-';
        }

        return substr($trimmed, 0, 5);
    }
}
